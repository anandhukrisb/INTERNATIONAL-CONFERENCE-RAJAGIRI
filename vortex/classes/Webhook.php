<?php

/**
 * ------------------------------------------------------------
 * Webhook.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 *
 * Purpose:
 * Handles verified webhook events received from Razorpay.
 *
 * Responsibilities:
 * 1. Validate the Razorpay webhook event data.
 * 2. Extract event, order, and payment information.
 * 3. Verify payment amount/currency against the stored transaction.
 * 4. Save the webhook in the webhooks table.
 * 5. Update the corresponding transaction atomically (row lock).
 * 6. Prevent duplicate processing (idempotency) while letting
 *    previously FAILED events be retried.
 * 7. Guard against out-of-order events, partial refunds, disputes.
 * 8. Notify the client app (Layer 2 webhook) on status changes.
 *
 * Requires PHP 8.0+ (match, str_starts_with).
 * Assumes UNIQUE indexes on webhooks.razorpay_event_id and
 * refunds.razorpay_refund_id.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/TransactionTimeline.php';


class Webhook
{
    /**
     * PDO database connection.
     */
    private $db;


    public function __construct()
    {
        // Get singleton database connection.
        $this->db = Database::getInstance()->getConnection();
    }


    /**
     * --------------------------------------------------------
     * Process Webhook
     * --------------------------------------------------------
     * IMPORTANT: call only AFTER process_webhook.php has verified
     * the Razorpay signature.
     *
     * @param array  $event            Decoded webhook JSON
     * @param string $signature        X-Razorpay-Signature header
     * @param string $razorpayEventId  X-Razorpay-Event-Id header
     *
     * @return array
     * --------------------------------------------------------
     */
    public function process($event, $signature, $razorpayEventId = '')
    {
        try {

            // 1. Validate structure
            if (!is_array($event) || empty($event)) {
                Logger::error('Webhook processing failed: Event payload is empty or not an array.');
                return ['status' => 'FAILED', 'message' => 'Invalid webhook event data.'];
            }

            // 2. Event name + event ID (ID comes from the header)
            $eventName = trim((string) ($event['event'] ?? ''));
            $razorpayEventId = trim((string) $razorpayEventId);
            if ($razorpayEventId === '') {
                $razorpayEventId = trim((string) ($event['id'] ?? ''));
            }
            if ($razorpayEventId === '') {
                $razorpayEventId = 'body_' . sha1((string) json_encode($event));
            }

            if ($eventName === '') {
                Logger::error('Webhook processing failed: Missing event name.');
                return ['status' => 'FAILED', 'message' => 'Webhook event name is missing.'];
            }

            Logger::info('Razorpay webhook received. Event: ' . $eventName . ', Event ID: ' . $razorpayEventId);

            $payloadJson = json_encode($event);
            if ($payloadJson === false) {
                return ['status' => 'FAILED', 'message' => 'Unable to encode webhook payload.'];
            }

            // 3. Idempotency: ONLY a PROCESSED webhook counts as a duplicate.
            //    A previously FAILED one (e.g. transaction not found yet) must be retryable.
            $checkStmt = $this->db->prepare("SELECT id, status FROM webhooks WHERE razorpay_event_id = ? LIMIT 1");
            $checkStmt->execute([$razorpayEventId]);
            $existingWebhook = $checkStmt->fetch();

            if ($existingWebhook && strtoupper((string) $existingWebhook['status']) === 'PROCESSED') {
                Logger::info('Duplicate Razorpay webhook ignored. Event ID: ' . $razorpayEventId);
                return ['status' => 'SUCCESS', 'message' => 'Webhook already processed.', 'event' => $eventName];
            }

            // 4. Unsupported events
            $supportedEvents = [
                // Payment events
                'payment.authorized',
                'payment.captured',
                'payment.failed',
                // Order events
                'order.paid',
                // Dispute events
                'payment.dispute.created',
                'payment.dispute.won',
                'payment.dispute.lost',
                'payment.dispute.closed',
                'payment.dispute.under_review',
                'payment.dispute.action_required',
                // Refund events
                'refund.created',
                'refund.processed',
                'refund.failed',
                'refund.speed_changed'
            ];

            if (!in_array($eventName, $supportedEvents, true)) {
                Logger::info('Razorpay webhook event ignored (unsupported). Event: ' . $eventName . ', Event ID: ' . $razorpayEventId);

                $stmt = $this->db->prepare("INSERT INTO webhooks
                    (razorpay_event_id, event_type, signature, payload, status, processed_at)
                    VALUES (?, ?, ?, ?, 'PROCESSED', CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE status = 'PROCESSED', processed_at = CURRENT_TIMESTAMP");
                $stmt->execute([$razorpayEventId, $eventName, (string) $signature, $payloadJson]);

                return ['status' => 'IGNORED', 'message' => 'Webhook event not handled.', 'event' => $eventName];
            }

            // 5. Extract entities
            $paymentEntity = $event['payload']['payment']['entity'] ?? null;
            $refundEntity  = $event['payload']['refund']['entity'] ?? null;
            $disputeEntity = $event['payload']['dispute']['entity'] ?? null;
            $orderEntity   = $event['payload']['order']['entity'] ?? null;

            $paymentId = trim((string) (
                $paymentEntity['id'] ??
                $refundEntity['payment_id'] ??
                $disputeEntity['payment_id'] ??
                ''
            ));

            $orderId = trim((string) (
                $paymentEntity['order_id'] ??
                $orderEntity['id'] ??
                ''
            ));

            // 6. Find the Vortex transaction (order ID first, then payment ID)
            $transaction = null;

            if ($orderId !== '') {
                $txStmt = $this->db->prepare("SELECT * FROM transactions WHERE razorpay_order_id = ? LIMIT 1");
                $txStmt->execute([$orderId]);
                $transaction = $txStmt->fetch();
            }

            if (!$transaction && $paymentId !== '') {
                $txStmt = $this->db->prepare("SELECT * FROM transactions WHERE razorpay_payment_id = ? LIMIT 1");
                $txStmt->execute([$paymentId]);
                $transaction = $txStmt->fetch();
                if ($transaction && $orderId === '') {
                    $orderId = (string) ($transaction['razorpay_order_id'] ?? '');
                }
            }

            if (!$transaction) {
                Logger::error(
                    'Webhook processing failed: Transaction not found. Order: '
                    . ($orderId ?: 'EMPTY') . ', Payment: ' . ($paymentId ?: 'EMPTY')
                );

                $stmt = $this->db->prepare("INSERT INTO webhooks
                    (razorpay_event_id, event_type, payment_id, order_id, signature, payload, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'FAILED')
                    ON DUPLICATE KEY UPDATE status = 'FAILED'");
                $stmt->execute([
                    $razorpayEventId,
                    $eventName,
                    $paymentId ?: null,
                    $orderId ?: null,
                    (string) $signature,
                    $payloadJson
                ]);

                // Returning FAILED => HTTP 500 => Razorpay retries later
                return [
                    'status' => 'FAILED',
                    'message' => 'Transaction not found for the provided order/payment ID.'
                ];
            }

            // 7. Atomic processing under a row lock
            $this->db->beginTransaction();

            try {
                // Lock the transaction row so checkout verification / parallel webhooks can't race us
                $lockStmt = $this->db->prepare("SELECT * FROM transactions WHERE id = ? FOR UPDATE");
                $lockStmt->execute([$transaction['id']]);
                $transaction = $lockStmt->fetch();

                // Re-check idempotency now that we hold the lock
                $dupStmt = $this->db->prepare("SELECT status FROM webhooks WHERE razorpay_event_id = ? LIMIT 1");
                $dupStmt->execute([$razorpayEventId]);
                $dup = $dupStmt->fetch();
                if ($dup && strtoupper((string) $dup['status']) === 'PROCESSED') {
                    $this->db->rollBack();
                    return ['status' => 'SUCCESS', 'message' => 'Webhook already processed.', 'event' => $eventName];
                }

                $currentTxStatus = strtoupper((string) $transaction['status']);
                $mismatchNote = null;
                $targetStatus = $this->resolveTargetStatus(
                    $eventName,
                    $currentTxStatus,
                    $transaction,
                    $paymentEntity,
                    $orderEntity,
                    $refundEntity,
                    $mismatchNote
                );

                // 7a. Save webhook record (insert, or upgrade an earlier FAILED row)
                $stmt = $this->db->prepare("INSERT INTO webhooks
                    (razorpay_event_id, event_type, payment_id, order_id, signature, payload, status, processed_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'PROCESSED', CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE
                        status = 'PROCESSED',
                        processed_at = CURRENT_TIMESTAMP,
                        payment_id = VALUES(payment_id),
                        order_id = VALUES(order_id)");
                $stmt->execute([
                    $razorpayEventId,
                    $eventName,
                    $paymentId ?: null,
                    $orderId ?: null,
                    (string) $signature,
                    $payloadJson
                ]);

                // 7b. Update transaction
                $statusChanged  = ($targetStatus !== $currentTxStatus);
                $isSuccessEvent = in_array($eventName, ['payment.captured', 'order.paid'], true);

                // Set payment_id only when empty, or when the successful payment is confirmed.
                // Never let a late failed attempt overwrite it.
                $newPaymentId = null;
                if ($paymentId !== '') {
                    $storedPaymentId = (string) ($transaction['razorpay_payment_id'] ?? '');
                    if ($storedPaymentId === '') {
                        $newPaymentId = $paymentId;
                    } elseif ($isSuccessEvent && $targetStatus === 'SUCCESS' && $storedPaymentId !== $paymentId) {
                        $newPaymentId = $paymentId;
                    }
                }

                if ($statusChanged || $newPaymentId !== null) {
                    $updateSql = "UPDATE transactions SET updated_at = NOW()";
                    $params = [];

                    if ($statusChanged) {
                        $updateSql .= ", status = ?";
                        $params[] = $targetStatus;
                    }
                    if ($newPaymentId !== null) {
                        $updateSql .= ", razorpay_payment_id = ?";
                        $params[] = $newPaymentId;
                    }

                    $updateSql .= " WHERE id = ?";
                    $params[] = $transaction['id'];

                    $this->db->prepare($updateSql)->execute($params);
                }

                // 7c. Refunds table
                if (str_starts_with($eventName, 'refund.') && !empty($refundEntity)) {
                    $refundId = $refundEntity['id'] ?? null;
                    $rfAmount = isset($refundEntity['amount']) ? ($refundEntity['amount'] / 100) : $transaction['amount'];
                    $rfStatus = ($eventName === 'refund.processed') ? 'PROCESSED'
                              : (($eventName === 'refund.failed') ? 'FAILED' : 'PENDING');
                    $rfNotes  = $refundEntity['notes']['reason'] ?? ($refundEntity['speed_requested'] ?? 'Razorpay Webhook');

                    if (!empty($refundId)) {
                        // A late refund.created must not downgrade an already PROCESSED refund
                        $rfStmt = $this->db->prepare("
                            INSERT INTO refunds (transaction_id, razorpay_refund_id, amount, reason, status)
                            VALUES (?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE status = IF(status = 'PROCESSED', status, VALUES(status))
                        ");
                        $rfStmt->execute([$transaction['id'], $refundId, $rfAmount, $rfNotes, $rfStatus]);
                    }
                }

                // 7d. Timeline
                $timelineReason = match ($eventName) {
                    'payment.captured'                => 'Payment captured successfully via Razorpay (' . ($paymentEntity['method'] ?? 'card/upi/netbanking') . ')',
                    'order.paid'                      => 'Order fully paid and captured via Razorpay',
                    'payment.failed'                  => 'Payment attempt failed: ' . ($paymentEntity['error_description'] ?? 'Bank/User declined payment'),
                    'payment.authorized'              => 'Payment authorized, awaiting capture/settlement',
                    'payment.dispute.created'         => 'Chargeback / Dispute opened by customer with their bank',
                    'payment.dispute.won'             => 'Dispute won by merchant; bank dismissed chargeback and funds restored',
                    'payment.dispute.lost'            => 'Dispute lost; bank debited funds to refund customer',
                    'payment.dispute.under_review'    => 'Dispute under bank/merchant review',
                    'payment.dispute.action_required' => 'Dispute evidence required from merchant',
                    'payment.dispute.closed'          => 'Dispute lifecycle closed',
                    'refund.created'                  => 'Refund initiated (' . ($refundEntity['id'] ?? '') . ')',
                    'refund.processed'                => 'Refund processed successfully (' . ($refundEntity['id'] ?? '') . ')',
                    'refund.failed'                   => 'Refund attempt failed; funds remained with merchant',
                    'refund.speed_changed'            => 'Refund processing speed updated',
                    default                           => 'Webhook event: ' . $eventName
                };
                if ($mismatchNote !== null) {
                    $timelineReason .= ' | WARNING: ' . $mismatchNote;
                }

                TransactionTimeline::record(
                    $transaction['vortex_transaction_id'],
                    strtoupper(str_replace('.', '_', $eventName)),
                    $targetStatus,
                    [
                        'transaction_id' => $transaction['id'],
                        'order_id'       => $orderId,
                        'payment_id'     => $paymentId,
                        'amount'         => $transaction['amount'],
                        'currency'       => $transaction['currency'] ?? 'INR',
                        'reason'         => $timelineReason,
                        'source'         => 'RAZORPAY_WEBHOOK',
                        'metadata'       => [
                            'razorpay_event_id' => $razorpayEventId,
                            'event'             => $eventName,
                            'method'            => $paymentEntity['method'] ?? null,
                            'error_code'        => $paymentEntity['error_code'] ?? null,
                            'error_description' => $paymentEntity['error_description'] ?? null
                        ]
                    ]
                );

                $this->db->commit();

                // 8. Notify the client app (Layer 2).
                //    Notify on a real status change, OR when the client was never told
                //    this status (e.g. checkout verification already set SUCCESS first,
                //    so $statusChanged is false but the client still has not been told).
                $notifiedStatus = strtoupper((string) ($transaction['client_notified_status'] ?? ''));
                $shouldNotify = $statusChanged
                    || ($targetStatus !== 'PENDING' && $notifiedStatus !== $targetStatus);

                if ($shouldNotify) {
                    try {
                        $delivered = $this->dispatchClientWebhook(
                            $transaction['vortex_transaction_id'],
                            $targetStatus,
                            $transaction['api_client_id']
                        );

                        if ($delivered) {
                            $this->db->prepare(
                                "UPDATE transactions SET client_notified_status = ?, client_notified_at = NOW() WHERE id = ?"
                            )->execute([$targetStatus, $transaction['id']]);
                        }
                    } catch (\Throwable $notifyError) {
                        // Never fail the Razorpay webhook because the client notification failed
                        Logger::error('Client notification bookkeeping failed: ' . $notifyError->getMessage());
                    }
                }

                Logger::info(
                    "Webhook processed successfully. Event: {$eventName}, "
                    . "Vortex Tx: {$transaction['vortex_transaction_id']}, "
                    . "Status: {$currentTxStatus} -> {$targetStatus}, Payment ID: {$paymentId}"
                );

                return [
                    'status' => 'SUCCESS',
                    'message' => 'Webhook processed successfully.',
                    'event' => $eventName,
                    'payment_id' => $paymentId,
                    'order_id' => $orderId,
                    'payment_status' => $targetStatus
                ];

            } catch (\Throwable $transError) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $transError;
            }

        } catch (\Throwable $e) {
            // Log technical details internally without exposing them externally
            Logger::error('Webhook Processing Error: ' . $e->getMessage());

            return [
                'status' => 'FAILED',
                'message' => 'Webhook processing failed.'
            ];
        }
    }


    /**
     * --------------------------------------------------------
     * Resolve Target Status
     * --------------------------------------------------------
     * Decides the new transaction status for an event. Guards
     * against out-of-order delivery (Razorpay doesn't guarantee
     * order), partial refunds, and amount/currency mismatches.
     * --------------------------------------------------------
     */
    private function resolveTargetStatus(
        string $eventName,
        string $current,
        array $transaction,
        ?array $paymentEntity,
        ?array $orderEntity,
        ?array $refundEntity,
        ?string &$mismatchNote
    ): string {
        $target = $current;
        $txAmountPaise = (int) round(((float) $transaction['amount']) * 100);
        $txCurrency = strtoupper((string) ($transaction['currency'] ?? 'INR'));
        $lockedStates = ['REFUNDED', 'DISPUTED']; // late success events must not undo these

        switch ($eventName) {
            case 'payment.captured':
            case 'order.paid':
                if (in_array($current, $lockedStates, true)) {
                    break; // out-of-order event, keep final state
                }

                $paidPaise = isset($paymentEntity['amount'])
                    ? (int) $paymentEntity['amount']
                    : (isset($orderEntity['amount_paid']) ? (int) $orderEntity['amount_paid'] : null);
                $payCurrency = strtoupper((string) ($paymentEntity['currency'] ?? $orderEntity['currency'] ?? $txCurrency));

                if (($paidPaise !== null && $paidPaise !== $txAmountPaise) || $payCurrency !== $txCurrency) {
                    $mismatchNote = "Amount/currency mismatch. Expected {$txAmountPaise} {$txCurrency}, got "
                        . ($paidPaise ?? 'n/a') . " {$payCurrency}";
                    Logger::error('Webhook ' . $mismatchNote . ' for Vortex Tx: ' . $transaction['vortex_transaction_id']);
                    break; // do NOT mark SUCCESS
                }

                $target = 'SUCCESS'; // PENDING/FAILED -> SUCCESS (a retry on the same order can succeed)
                break;

            case 'payment.failed':
                // One failed attempt must never undo SUCCESS/REFUNDED/DISPUTED
                if ($current === 'PENDING') {
                    $target = 'FAILED';
                }
                break;

            case 'payment.authorized':
                // Stay PENDING until captured
                break;

            case 'refund.processed':
                $paymentPaise  = (int) ($paymentEntity['amount'] ?? $txAmountPaise);
                $refundedPaise = (int) ($paymentEntity['amount_refunded'] ?? ($refundEntity['amount'] ?? 0));
                $isFull = (($paymentEntity['refund_status'] ?? null) === 'full') || ($refundedPaise >= $paymentPaise);

                if ($isFull) {
                    $target = 'REFUNDED';
                }
                // Partial refund: keep status; the refunds table records the amount.
                break;

            case 'refund.created':
            case 'refund.failed':
            case 'refund.speed_changed':
                break;

            case 'payment.dispute.created':
                if ($current === 'SUCCESS') {
                    $target = 'DISPUTED';
                }
                break;

            case 'payment.dispute.won':
                if ($current === 'DISPUTED') {
                    $target = 'SUCCESS';
                }
                break;

            case 'payment.dispute.lost':
                if (in_array($current, ['DISPUTED', 'SUCCESS'], true)) {
                    $target = 'REFUNDED';
                }
                break;

            case 'payment.dispute.under_review':
            case 'payment.dispute.action_required':
            case 'payment.dispute.closed':
                break; // audit trail only
        }

        return $target;
    }


    /**
     * --------------------------------------------------------
     * Dispatch Client Webhook (Layer 2)
     * --------------------------------------------------------
     * Sends a signed POST to the API client's registered
     * webhook URL (e.g. https://icswhmh.com/webhook.php).
     *
     * @param string $vortexTxnId
     * @param string $status
     * @param int    $apiClientId
     * @return bool true if the client answered with HTTP 2xx
     * --------------------------------------------------------
     */
    public function dispatchClientWebhook($vortexTxnId, $status, $apiClientId)
    {
        try {
            $stmt = $this->db->prepare("SELECT webhook_url, webhook_secret FROM api_clients WHERE id = ?");
            $stmt->execute([$apiClientId]);
            $client = $stmt->fetch();

            if (!$client || empty($client['webhook_url']) || empty($client['webhook_secret'])) {
                Logger::info("Client webhook not dispatched for transaction {$vortexTxnId}: no webhook_url or webhook_secret configured for client ID {$apiClientId}.");
                return false;
            }

            $payload = json_encode([
                'vortex_transaction_id' => $vortexTxnId,
                'status' => $status,
                'timestamp' => date('Y-m-d H:i:s')
            ]);

            // trim() so a stray space/newline in the DB value can't break the signature
            $signature = hash_hmac('sha256', $payload, trim((string) $client['webhook_secret']));

            $ch = curl_init($client['webhook_url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Vortex-Webhook-Dispatcher/1.0');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-Vortex-Signature: ' . $signature
            ]);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3); // stay well inside Razorpay's ~5s window

            // Disable SSL verification only for an exact local host, never a substring match
            $host = strtolower((string) parse_url($client['webhook_url'], PHP_URL_HOST));
            if (in_array($host, ['localhost', '127.0.0.1'], true)) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            }

            $response  = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($curlError !== '') {
                Logger::error(
                    "Client webhook dispatch failed for transaction {$vortexTxnId} to {$client['webhook_url']}: "
                    . "cURL error: {$curlError}"
                );
                return false;
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                Logger::error(
                    "Client webhook dispatch received non-2xx response for transaction {$vortexTxnId} "
                    . "to {$client['webhook_url']}: HTTP {$httpCode}. Response: "
                    . substr((string) $response, 0, 300)
                );
                return false;
            }

            Logger::info("Client webhook dispatched successfully for transaction {$vortexTxnId} to {$client['webhook_url']} (HTTP {$httpCode}).");
            return true;
        } catch (\Throwable $e) {
            Logger::error("Failed to dispatch client webhook for transaction {$vortexTxnId}: " . $e->getMessage());
            return false;
        }
    }
}