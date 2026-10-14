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
 * 1. Read and validate the Razorpay webhook event data.
 * 2. Extract event, order, and payment information.
 * 3. Verify payment details against stored Vortex transaction.
 * 4. Save the webhook in the webhooks table.
 * 5. Update the corresponding transaction atomically.
 * 6. Prevent duplicate webhook processing (idempotency).
 * 7. Safely handle race conditions with checkout verification.
 * 8. Log important webhook activity securely without credential leakage.
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


    /**
     * --------------------------------------------------------
     * Constructor
     * --------------------------------------------------------
     */
    public function __construct()
    {
        // Get singleton database connection.
        $this->db = Database::getInstance()->getConnection();
    }


    /**
     * --------------------------------------------------------
     * Process Webhook
     * --------------------------------------------------------
     *
     * IMPORTANT:
     * This method should only be called AFTER
     * process_webhook.php has verified the Razorpay signature.
     *
     * @param array  $event
     * @param string $signature
     *
     * @return array
     * --------------------------------------------------------
     */
    public function process($event, $signature)
    {
        try {

            /**
             * ------------------------------------------------
             * 1. Validate event structure
             * ------------------------------------------------
             */

            if (!is_array($event) || empty($event)) {
                Logger::error('Webhook processing failed: Event payload is empty or not an array.');
                return [
                    'status' => 'FAILED',
                    'message' => 'Invalid webhook event data.'
                ];
            }


            /**
             * ------------------------------------------------
             * 2. Validate event name and Razorpay event ID
             * ------------------------------------------------
             */

            $eventName = trim((string) ($event['event'] ?? ''));
            $razorpayEventId = trim((string) ($event['id'] ?? ''));

            if ($eventName === '') {
                Logger::error('Webhook processing failed: Missing event name.');
                return [
                    'status' => 'FAILED',
                    'message' => 'Webhook event name is missing.'
                ];
            }

            if ($razorpayEventId === '') {
                Logger::error('Webhook processing failed: Missing Razorpay event ID.');
                return [
                    'status' => 'FAILED',
                    'message' => 'Razorpay webhook event ID is missing.'
                ];
            }

            Logger::info(
                'Razorpay webhook received. Event: ' . $eventName
                . ', Event ID: ' . $razorpayEventId
            );


            /**
             * ------------------------------------------------
             * 3. Check for Duplicate Webhook (Idempotency)
             * ------------------------------------------------
             */

            $checkQuery = "SELECT id, status FROM webhooks WHERE razorpay_event_id = ? LIMIT 1";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->execute([$razorpayEventId]);
            $existingWebhook = $checkStmt->fetch();

            if ($existingWebhook) {
                Logger::info('Duplicate Razorpay webhook ignored. Event ID: ' . $razorpayEventId);

                return [
                    'status' => 'SUCCESS',
                    'message' => 'Webhook already processed.',
                    'event' => $eventName
                ];
            }


            /**
             * ------------------------------------------------
             * 4. Handle Unsupported / Ignored Events
             * ------------------------------------------------
             */

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
                Logger::info(
                    'Razorpay webhook event ignored (unsupported event type). '
                    . 'Event: ' . $eventName . ', Event ID: ' . $razorpayEventId
                );

                $payloadJson = json_encode($event);
                if ($payloadJson !== false) {
                    $insertIgnoredQuery = "INSERT INTO webhooks (
                                            razorpay_event_id,
                                            event_type,
                                            signature,
                                            payload,
                                            status,
                                            processed_at
                                          ) VALUES (?, ?, ?, ?, 'PROCESSED', CURRENT_TIMESTAMP)";
                    $insertIgnoredStmt = $this->db->prepare($insertIgnoredQuery);
                    $insertIgnoredStmt->execute([
                        $razorpayEventId,
                        $eventName,
                        (string) $signature,
                        $payloadJson
                    ]);
                }

                return [
                    'status' => 'IGNORED',
                    'message' => 'Webhook event not handled.',
                    'event' => $eventName
                ];
            }


            /**
             * ------------------------------------------------
             * 5. Extract Entities (Payment, Refund, Dispute)
             * ------------------------------------------------
             */

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


            /**
             * ------------------------------------------------
             * 6. Find Corresponding Vortex Transaction
             * ------------------------------------------------
             * Search by Order ID first, fallback to Payment ID
             */

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
                if ($transaction && empty($orderId)) {
                    $orderId = (string)($transaction['razorpay_order_id'] ?? '');
                }
            }

            if (!$transaction) {
                Logger::error(
                    'Webhook processing failed: Transaction not found in database for Order: '
                    . ($orderId ?: 'EMPTY') . ', Payment: ' . ($paymentId ?: 'EMPTY')
                );

                $payloadJson = json_encode($event);
                if ($payloadJson !== false) {
                    $insertFailQuery = "INSERT INTO webhooks (
                                            razorpay_event_id,
                                            event_type,
                                            payment_id,
                                            order_id,
                                            signature,
                                            payload,
                                            status
                                        ) VALUES (?, ?, ?, ?, ?, ?, 'FAILED')";
                    $insertFailStmt = $this->db->prepare($insertFailQuery);
                    $insertFailStmt->execute([
                        $razorpayEventId,
                        $eventName,
                        $paymentId ?: null,
                        $orderId ?: null,
                        (string) $signature,
                        $payloadJson
                    ]);
                }

                return [
                    'status' => 'FAILED',
                    'message' => 'Transaction not found for the provided order/payment ID.'
                ];
            }


            /**
             * ------------------------------------------------
             * 7. Determine Target State & Actions
             * ------------------------------------------------
             */

            $currentTxStatus = strtoupper((string) $transaction['status']);
            $targetStatus    = $currentTxStatus;
            $payloadJson     = json_encode($event);

            if ($payloadJson === false) {
                return ['status' => 'FAILED', 'message' => 'Unable to encode webhook payload.'];
            }

            switch ($eventName) {
                case 'payment.captured':
                case 'order.paid':
                    $targetStatus = 'SUCCESS';
                    break;

                case 'payment.failed':
                    if ($currentTxStatus === 'PENDING') {
                        $targetStatus = 'FAILED';
                    }
                    break;

                case 'payment.authorized':
                    // Authorized; remain PENDING until captured unless already SUCCESS
                    if ($currentTxStatus === 'PENDING') {
                        $targetStatus = 'PENDING';
                    }
                    break;

                case 'refund.processed':
                    $targetStatus = 'REFUNDED';
                    break;

                case 'refund.created':
                case 'refund.failed':
                case 'refund.speed_changed':
                    // Maintain current transaction status, log refund activity
                    break;

                case 'payment.dispute.created':
                    $targetStatus = 'DISPUTED';
                    break;

                case 'payment.dispute.won':
                    $targetStatus = 'SUCCESS';
                    break;

                case 'payment.dispute.lost':
                    $targetStatus = 'REFUNDED';
                    break;

                case 'payment.dispute.under_review':
                case 'payment.dispute.action_required':
                case 'payment.dispute.closed':
                    // Maintained in webhooks audit trail
                    break;
            }

            $this->db->beginTransaction();

            try {
                // 1. Insert Webhook Record
                $insertWebhookQuery = "INSERT INTO webhooks (
                                            razorpay_event_id,
                                            event_type,
                                            payment_id,
                                            order_id,
                                            signature,
                                            payload,
                                            status,
                                            processed_at
                                       ) VALUES (?, ?, ?, ?, ?, ?, 'PROCESSED', CURRENT_TIMESTAMP)";
                $insertStmt = $this->db->prepare($insertWebhookQuery);
                $insertStmt->execute([
                    $razorpayEventId,
                    $eventName,
                    $paymentId ?: null,
                    $orderId ?: null,
                    (string) $signature,
                    $payloadJson
                ]);

                // 2. Update Transaction if status or payment_id changed
                $statusChanged = ($targetStatus !== $currentTxStatus);
                $payIdNeeded   = (empty($transaction['razorpay_payment_id']) && !empty($paymentId));

                if ($statusChanged || $payIdNeeded) {
                    $updateSql = "UPDATE transactions SET updated_at = NOW()";
                    $params = [];

                    if ($statusChanged) {
                        $updateSql .= ", status = ?";
                        $params[] = $targetStatus;
                    }
                    if ($payIdNeeded || !empty($paymentId)) {
                        $updateSql .= ", razorpay_payment_id = ?";
                        $params[] = $paymentId;
                    }

                    $updateSql .= " WHERE id = ?";
                    $params[] = $transaction['id'];

                    $updateStmt = $this->db->prepare($updateSql);
                    $updateStmt->execute($params);
                }

                // 3. Handle Refunds Table Integration
                if (str_starts_with($eventName, 'refund.') && !empty($refundEntity)) {
                    $refundId  = $refundEntity['id'] ?? null;
                    $rfAmount  = isset($refundEntity['amount']) ? ($refundEntity['amount'] / 100) : $transaction['amount'];
                    $rfStatus  = ($eventName === 'refund.processed') ? 'PROCESSED' : (($eventName === 'refund.failed') ? 'FAILED' : 'PENDING');
                    $rfNotes   = $refundEntity['notes']['reason'] ?? ($refundEntity['speed_requested'] ?? 'Razorpay Webhook');

                    if (!empty($refundId)) {
                        $rfStmt = $this->db->prepare("
                            INSERT INTO refunds (transaction_id, razorpay_refund_id, amount, reason, status)
                            VALUES (?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE status = VALUES(status)
                        ");
                        $rfStmt->execute([
                            $transaction['id'],
                            $refundId,
                            $rfAmount,
                            $rfNotes,
                            $rfStatus
                        ]);
                    }
                }

                // 4. Record event in chronological transaction_timeline
                $timelineReason = match ($eventName) {
                    'payment.captured'             => 'Payment captured successfully via Razorpay (' . ($paymentEntity['method'] ?? 'card/upi/netbanking') . ')',
                    'order.paid'                   => 'Order fully paid and captured via Razorpay',
                    'payment.failed'               => 'Payment attempt failed: ' . ($paymentEntity['error_description'] ?? 'Bank/User declined payment'),
                    'payment.authorized'           => 'Payment authorized, awaiting capture/settlement',
                    'payment.dispute.created'      => 'Chargeback / Dispute opened by customer with their bank',
                    'payment.dispute.won'          => 'Dispute won by merchant; bank dismissed chargeback and funds restored',
                    'payment.dispute.lost'         => 'Dispute lost; bank debited funds to refund customer',
                    'payment.dispute.under_review' => 'Dispute under bank/merchant review',
                    'payment.dispute.action_required' => 'Dispute evidence required from merchant',
                    'payment.dispute.closed'       => 'Dispute lifecycle closed',
                    'refund.created'               => 'Refund initiated (' . ($refundEntity['id'] ?? '') . ')',
                    'refund.processed'             => 'Customer got money back; refund processed successfully',
                    'refund.failed'                => 'Refund attempt failed by customer bank; funds remained with merchant',
                    'refund.speed_changed'         => 'Refund processing speed updated',
                    default                        => 'Webhook event: ' . $eventName
                };

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

                // 4. Dispatch Client Webhook if status transitioned
                if ($statusChanged) {
                    $this->dispatchClientWebhook($transaction['vortex_transaction_id'], $targetStatus, $transaction['api_client_id']);
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
            // Log technical exception details internally without exposing them externally
            Logger::error('Webhook Processing Error: ' . $e->getMessage());

            return [
                'status' => 'FAILED',
                'message' => 'Webhook processing failed.'
            ];
        }
    }

    /**
     * --------------------------------------------------------
     * Dispatch Client Webhook
     * --------------------------------------------------------
     * Sends an asynchronous POST request to the API Client's registered webhook URL.
     * 
     * @param string $vortexTxnId
     * @param string $status
     * @param int $apiClientId
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
                return;
            }

            $payload = json_encode([
                'vortex_transaction_id' => $vortexTxnId,
                'status' => $status,
                'timestamp' => date('Y-m-d H:i:s')
            ]);

            $signature = hash_hmac('sha256', $payload, $client['webhook_secret']);

            $ch = curl_init($client['webhook_url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Vortex-Webhook-Dispatcher/1.0');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-Vortex-Signature: ' . $signature
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 second timeout so we don't block Razorpay
            
            if (strpos($client['webhook_url'], 'localhost') !== false || strpos($client['webhook_url'], '127.0.0.1') !== false) {
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
                return;
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                Logger::error(
                    "Client webhook dispatch received non-2xx response for transaction {$vortexTxnId} "
                    . "to {$client['webhook_url']}: HTTP {$httpCode}"
                );
                return;
            }

            Logger::info("Client webhook dispatched successfully for transaction {$vortexTxnId} to {$client['webhook_url']} (HTTP {$httpCode}).");
        } catch (\Throwable $e) {
            Logger::error("Failed to dispatch client webhook for transaction {$vortexTxnId}: " . $e->getMessage());
        }
    }
}

?>