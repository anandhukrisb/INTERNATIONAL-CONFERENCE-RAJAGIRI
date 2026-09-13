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

            $supportedEvents = ['payment.captured', 'payment.failed'];

            if (!in_array($eventName, $supportedEvents, true)) {
                Logger::info(
                    'Razorpay webhook event ignored (unsupported event type). '
                    . 'Event: ' . $eventName . ', Event ID: ' . $razorpayEventId
                );

                // Save unsupported webhook as PROCESSED so duplicate deliveries are ignored
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
             * 5. Extract & Validate Payment Entity
             * ------------------------------------------------
             */

            $paymentEntity = $event['payload']['payment']['entity'] ?? null;

            if (!is_array($paymentEntity) || empty($paymentEntity)) {
                Logger::error('Webhook processing failed: Payment entity missing in event ' . $eventName);
                return [
                    'status' => 'FAILED',
                    'message' => 'Payment entity is missing from webhook.'
                ];
            }

            $paymentId = trim((string) ($paymentEntity['id'] ?? ''));
            $orderId   = trim((string) ($paymentEntity['order_id'] ?? ''));

            if ($paymentId === '') {
                Logger::error('Webhook processing failed: Payment ID is missing.');
                return [
                    'status' => 'FAILED',
                    'message' => 'Razorpay payment ID is missing.'
                ];
            }

            if ($orderId === '') {
                Logger::error('Webhook processing failed: Order ID is missing for Payment ID: ' . $paymentId);
                return [
                    'status' => 'FAILED',
                    'message' => 'Razorpay order ID is missing.'
                ];
            }


            /**
             * ------------------------------------------------
             * 6. Find Corresponding Vortex Transaction
             * ------------------------------------------------
             */

            $txQuery = "SELECT id, vortex_transaction_id, razorpay_order_id, razorpay_payment_id,
                               amount, currency, status
                        FROM transactions
                        WHERE razorpay_order_id = ?
                        LIMIT 1";

            $txStmt = $this->db->prepare($txQuery);
            $txStmt->execute([$orderId]);
            $transaction = $txStmt->fetch();

            if (!$transaction) {
                Logger::error(
                    'Webhook processing failed: Transaction not found in database for Order ID: '
                    . $orderId . ', Payment ID: ' . $paymentId
                );

                // Safely record failed webhook attempt for tracking
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
                        $paymentId,
                        $orderId,
                        (string) $signature,
                        $payloadJson
                    ]);
                }

                return [
                    'status' => 'FAILED',
                    'message' => 'Transaction not found for the provided order ID.'
                ];
            }


            /**
             * ------------------------------------------------
             * 7. Verify Payment Matches Expected Transaction Data
             * ------------------------------------------------
             */

            // Verify Order ID matches
            if ($transaction['razorpay_order_id'] !== $orderId) {
                Logger::error("Webhook order ID mismatch: Stored {$transaction['razorpay_order_id']} vs Received {$orderId}");
                return [
                    'status' => 'FAILED',
                    'message' => 'Transaction order ID mismatch.'
                ];
            }

            // Verify Amount (Razorpay entity amount is in subunits/paise)
            if (isset($paymentEntity['amount'])) {
                $receivedPaise = (int) $paymentEntity['amount'];
                $expectedPaise = (int) round((float) $transaction['amount'] * 100);

                if ($receivedPaise !== $expectedPaise) {
                    Logger::error(
                        "Webhook payment amount mismatch for Order ID: {$orderId}. "
                        . "Expected paise: {$expectedPaise}, Received paise: {$receivedPaise}"
                    );
                    return [
                        'status' => 'FAILED',
                        'message' => 'Payment amount mismatch.'
                    ];
                }
            }

            // Verify Currency (if provided in payment entity)
            if (!empty($paymentEntity['currency'])) {
                $receivedCurrency = strtoupper(trim((string) $paymentEntity['currency']));
                $expectedCurrency = strtoupper(trim((string) $transaction['currency']));

                if ($receivedCurrency !== $expectedCurrency) {
                    Logger::error(
                        "Webhook payment currency mismatch for Order ID: {$orderId}. "
                        . "Expected: {$expectedCurrency}, Received: {$receivedCurrency}"
                    );
                    return [
                        'status' => 'FAILED',
                        'message' => 'Payment currency mismatch.'
                    ];
                }
            }


            /**
             * ------------------------------------------------
             * 8. Handle Race Conditions & Existing Final States
             * ------------------------------------------------
             * Protect against overwriting final states:
             * - SUCCESS must never become FAILED.
             * - FAILED must never become SUCCESS.
             * - If verify_payment.php already marked SUCCESS, acknowledge idempotently.
             */

            $currentTxStatus = strtoupper((string) $transaction['status']);

            // Case A: Transaction is ALREADY SUCCESS
            if ($currentTxStatus === 'SUCCESS') {
                Logger::info(
                    "Webhook received for already SUCCESS transaction: {$transaction['vortex_transaction_id']} "
                    . "(Order ID: {$orderId}, Incoming Event: {$eventName})"
                );

                // Save webhook as PROCESSED
                $payloadJson = json_encode($event);
                $saveWebhookQuery = "INSERT INTO webhooks (
                                        razorpay_event_id,
                                        event_type,
                                        payment_id,
                                        order_id,
                                        signature,
                                        payload,
                                        status,
                                        processed_at
                                     ) VALUES (?, ?, ?, ?, ?, ?, 'PROCESSED', CURRENT_TIMESTAMP)";
                $saveStmt = $this->db->prepare($saveWebhookQuery);
                $saveStmt->execute([
                    $razorpayEventId,
                    $eventName,
                    $paymentId,
                    $orderId,
                    (string) $signature,
                    $payloadJson
                ]);

                // Ensure razorpay_payment_id is populated if previously blank
                if (empty($transaction['razorpay_payment_id']) && $eventName === 'payment.captured') {
                    $fillPaymentIdQuery = "UPDATE transactions SET razorpay_payment_id = ? WHERE id = ? AND (razorpay_payment_id IS NULL OR razorpay_payment_id = '')";
                    $fillStmt = $this->db->prepare($fillPaymentIdQuery);
                    $fillStmt->execute([$paymentId, $transaction['id']]);
                }

                return [
                    'status' => 'SUCCESS',
                    'message' => 'Webhook acknowledged: transaction already marked as SUCCESS.',
                    'event' => $eventName,
                    'payment_id' => $paymentId,
                    'order_id' => $orderId,
                    'payment_status' => 'SUCCESS'
                ];
            }

            // Case B: Transaction is ALREADY FAILED
            if ($currentTxStatus === 'FAILED') {
                Logger::info(
                    "Webhook received for already FAILED transaction: {$transaction['vortex_transaction_id']} "
                    . "(Order ID: {$orderId}, Incoming Event: {$eventName})"
                );

                $payloadJson = json_encode($event);
                $saveWebhookQuery = "INSERT INTO webhooks (
                                        razorpay_event_id,
                                        event_type,
                                        payment_id,
                                        order_id,
                                        signature,
                                        payload,
                                        status,
                                        processed_at
                                     ) VALUES (?, ?, ?, ?, ?, ?, 'PROCESSED', CURRENT_TIMESTAMP)";
                $saveStmt = $this->db->prepare($saveWebhookQuery);
                $saveStmt->execute([
                    $razorpayEventId,
                    $eventName,
                    $paymentId,
                    $orderId,
                    (string) $signature,
                    $payloadJson
                ]);

                return [
                    'status' => 'SUCCESS',
                    'message' => 'Webhook acknowledged: transaction is in terminal FAILED state.',
                    'event' => $eventName,
                    'payment_id' => $paymentId,
                    'order_id' => $orderId,
                    'payment_status' => 'FAILED'
                ];
            }


            /**
             * ------------------------------------------------
             * 9. Atomic State Transition (PENDING -> SUCCESS / FAILED)
             * ------------------------------------------------
             * Uses database transaction:
             *   BEGIN
             *     INSERT webhook record
             *     UPDATE transaction status (guarded by WHERE status = 'PENDING')
             *     UPDATE webhook to PROCESSED
             *   COMMIT
             * On error:
             *   ROLLBACK
             * ------------------------------------------------
             */

            $targetStatus = ($eventName === 'payment.captured') ? 'SUCCESS' : 'FAILED';
            $payloadJson  = json_encode($event);

            if ($payloadJson === false) {
                Logger::error('Webhook payload could not be JSON encoded.');
                return [
                    'status' => 'FAILED',
                    'message' => 'Unable to encode webhook payload.'
                ];
            }

            $this->db->beginTransaction();

            try {
                // Insert webhook record with initial status 'RECEIVED'
                $insertWebhookQuery = "INSERT INTO webhooks (
                                            razorpay_event_id,
                                            event_type,
                                            payment_id,
                                            order_id,
                                            signature,
                                            payload,
                                            status
                                       ) VALUES (?, ?, ?, ?, ?, ?, 'RECEIVED')";

                $insertStmt = $this->db->prepare($insertWebhookQuery);
                $insertStmt->execute([
                    $razorpayEventId,
                    $eventName,
                    $paymentId,
                    $orderId,
                    (string) $signature,
                    $payloadJson
                ]);

                $webhookDatabaseId = $this->db->lastInsertId();

                // Atomically update transaction status ONLY if currently PENDING
                $updateTxQuery = "UPDATE transactions
                                  SET razorpay_payment_id = ?,
                                      status = ?
                                  WHERE id = ?
                                    AND status = 'PENDING'";

                $updateTxStmt = $this->db->prepare($updateTxQuery);
                $updateTxStmt->execute([
                    $paymentId,
                    $targetStatus,
                    $transaction['id']
                ]);

                if ($updateTxStmt->rowCount() > 0) {
                    // Successfully transitioned transaction; mark webhook as PROCESSED
                    $updateWebhookQuery = "UPDATE webhooks
                                           SET status = 'PROCESSED',
                                               processed_at = CURRENT_TIMESTAMP
                                           WHERE id = ?";
                    $updateWebhookStmt = $this->db->prepare($updateWebhookQuery);
                    $updateWebhookStmt->execute([$webhookDatabaseId]);

                    $this->db->commit();

                    Logger::info(
                        "Webhook processed successfully. Event: {$eventName}, "
                        . "Vortex Tx: {$transaction['vortex_transaction_id']}, "
                        . "Order ID: {$orderId}, Payment ID: {$paymentId}, New Status: {$targetStatus}"
                    );

                    return [
                        'status' => 'SUCCESS',
                        'message' => 'Webhook processed successfully.',
                        'event' => $eventName,
                        'payment_id' => $paymentId,
                        'order_id' => $orderId,
                        'payment_status' => $targetStatus
                    ];
                } else {
                    // Transaction was modified concurrently (e.g. checkout verification completed first)
                    // Mark webhook as PROCESSED to avoid reprocessing
                    $updateWebhookQuery = "UPDATE webhooks
                                           SET status = 'PROCESSED',
                                               processed_at = CURRENT_TIMESTAMP
                                           WHERE id = ?";
                    $updateWebhookStmt = $this->db->prepare($updateWebhookQuery);
                    $updateWebhookStmt->execute([$webhookDatabaseId]);

                    $this->db->commit();

                    Logger::info(
                        "Webhook acknowledged: Transaction {$transaction['vortex_transaction_id']} "
                        . "was resolved concurrently by verification."
                    );

                    return [
                        'status' => 'SUCCESS',
                        'message' => 'Webhook acknowledged: transaction state already resolved.',
                        'event' => $eventName,
                        'payment_id' => $paymentId,
                        'order_id' => $orderId,
                        'payment_status' => $targetStatus
                    ];
                }

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
}

?>