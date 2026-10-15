<?php

/**
 * ------------------------------------------------------------
 * Refund.php
 * ------------------------------------------------------------
 * Purpose:
 * Handles refund requests for successful transactions.
 *
 * Flow:
 *
 * Vortex Transaction ID
 *        ↓
 * transactions table
 *        ↓
 * Razorpay Payment ID
 *        ↓
 * Razorpay Refund API
 *        ↓
 * refunds table
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/TransactionTimeline.php';
require_once __DIR__ . '/Webhook.php';


class Refund
{
    /**
     * PDO database connection.
     */
    private $db;


    /**
     * Razorpay API object.
     */
    private $razorpay;


    /**
     * --------------------------------------------------------
     * Constructor
     * --------------------------------------------------------
     */
    public function __construct()
    {
        // Get database connection.
        $this->db = Database::getInstance()->getConnection();

        // Default Razorpay instance (can be overridden per transaction)
        $keyId = trim($_ENV['RAZORPAY_LIVE_KEY_ID'] ?? $_ENV['RAZORPAY_KEY_ID'] ?? getenv('RAZORPAY_LIVE_KEY_ID') ?: (getenv('RAZORPAY_KEY_ID') ?: 'rzp_live_T1nKWvHiz4Mqkq'));
        $keySecret = trim($_ENV['RAZORPAY_LIVE_KEY_SECRET'] ?? $_ENV['RAZORPAY_KEY_SECRET'] ?? getenv('RAZORPAY_LIVE_KEY_SECRET') ?: (getenv('RAZORPAY_KEY_SECRET') ?: 'MSnDGsBR6PJ9mvuUZXJB05VN'));
        $this->razorpay = new \Razorpay\Api\Api($keyId, $keySecret);
    }


    /**
     * --------------------------------------------------------
     * Create Refund
     * --------------------------------------------------------
     */
    public function createRefund(
        $vortexTransactionId,
        $amount,
        $reason = null,
        $refundedBy = null
    ) {
        try {
            /**
             * ------------------------------------------------
             * 1. Find transaction
             * ------------------------------------------------
             */
            $query = "
                SELECT
                    id,
                    vortex_transaction_id,
                    razorpay_payment_id,
                    amount,
                    currency,
                    status,
                    is_test_mode,
                    api_client_id
                FROM transactions
                WHERE vortex_transaction_id = ?
                LIMIT 1
            ";

            $statement = $this->db->prepare($query);
            $statement->execute([$vortexTransactionId]);
            $transaction = $statement->fetch(\PDO::FETCH_ASSOC);

            /**
             * ------------------------------------------------
             * 2. Check transaction exists
             * ------------------------------------------------
             */
            if (!$transaction) {
                throw new \Exception('Transaction not found.');
            }

            /**
             * ------------------------------------------------
             * 3. Refund only successful transactions
             * ------------------------------------------------
             */
            if ($transaction['status'] !== 'SUCCESS') {
                if ($transaction['status'] === 'REFUNDED') {
                    throw new \Exception('Transaction has already been fully refunded.');
                }
                throw new \Exception('Only successful transactions can be refunded (current status: ' . $transaction['status'] . ').');
            }

            /**
             * ------------------------------------------------
             * 4. Check Razorpay payment ID
             * ------------------------------------------------
             */
            if (empty($transaction['razorpay_payment_id'])) {
                throw new \Exception('Razorpay payment ID not found for this transaction.');
            }

            /**
             * ------------------------------------------------
             * 5. Validate refund amount & check existing refunds
             * ------------------------------------------------
             */
            $amount = (float) $amount;
            if ($amount <= 0) {
                throw new \Exception('Refund amount must be greater than zero.');
            }

            // Calculate existing non-failed refunds
            $stmtRefCheck = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) FROM refunds WHERE transaction_id = ? AND status != 'FAILED'");
            $stmtRefCheck->execute([$transaction['id']]);
            $alreadyRefunded = (float) $stmtRefCheck->fetchColumn();

            $refundableRemaining = (float) $transaction['amount'] - $alreadyRefunded;
            if ($refundableRemaining <= 0) {
                throw new \Exception('This transaction has already been fully refunded.');
            }

            if ($amount > $refundableRemaining) {
                throw new \Exception('Refund amount (₹' . number_format($amount, 2) . ') cannot exceed remaining balance (₹' . number_format($refundableRemaining, 2) . ').');
            }

            /**
             * ------------------------------------------------
             * 6. Select appropriate Razorpay keys (Test vs Live)
             * ------------------------------------------------
             */
            $isTest = ((int)($transaction['is_test_mode'] ?? 0)) === 1;
            if ($isTest) {
                $keyId = trim($_ENV['RAZORPAY_TEST_KEY_ID'] ?? $_ENV['RAZORPAY_KEY_ID'] ?? getenv('RAZORPAY_TEST_KEY_ID') ?: (getenv('RAZORPAY_KEY_ID') ?: 'rzp_test_SzpEmCgPhS6q2u'));
                $keySecret = trim($_ENV['RAZORPAY_TEST_KEY_SECRET'] ?? $_ENV['RAZORPAY_KEY_SECRET'] ?? getenv('RAZORPAY_TEST_KEY_SECRET') ?: (getenv('RAZORPAY_KEY_SECRET') ?: 'Aoj0qRP2XI7jXh66MTssn9f3'));
            } else {
                $keyId = trim($_ENV['RAZORPAY_LIVE_KEY_ID'] ?? $_ENV['RAZORPAY_KEY_ID'] ?? getenv('RAZORPAY_LIVE_KEY_ID') ?: (getenv('RAZORPAY_KEY_ID') ?: 'rzp_live_T1nKWvHiz4Mqkq'));
                $keySecret = trim($_ENV['RAZORPAY_LIVE_KEY_SECRET'] ?? $_ENV['RAZORPAY_KEY_SECRET'] ?? getenv('RAZORPAY_LIVE_KEY_SECRET') ?: (getenv('RAZORPAY_KEY_SECRET') ?: 'MSnDGsBR6PJ9mvuUZXJB05VN'));
            }

            if (empty($keyId) || empty($keySecret)) {
                throw new \Exception('Razorpay credentials not configured for this mode.');
            }

            $razorpayClient = new \Razorpay\Api\Api($keyId, $keySecret);

            /**
             * ------------------------------------------------
             * 7. Convert amount to paise & Call Razorpay API
             * ------------------------------------------------
             */
            $refundAmountPaise = (int) round($amount * 100);

            $refundPayload = [
                'amount' => $refundAmountPaise,
                'speed'  => 'normal',
                'notes'  => [
                    'vortex_transaction_id' => $vortexTransactionId,
                    'reason'                => $reason ?: 'Refund initiated by Admin',
                    'refunded_by'           => $refundedBy ?: 'Vortex Admin'
                ]
            ];

            $refund = $razorpayClient
                ->payment
                ->fetch($transaction['razorpay_payment_id'])
                ->refund($refundPayload);

            /**
             * ------------------------------------------------
             * 8. Process Razorpay refund response
             * ------------------------------------------------
             */
            $razorpayRefundId = $refund['id'];
            $razorpayStatus   = $refund['status'] ?? 'pending';

            if ($razorpayStatus === 'processed') {
                $status = 'PROCESSED';
            } elseif ($razorpayStatus === 'failed') {
                $status = 'FAILED';
            } else {
                $status = 'PENDING';
            }

            /**
             * ------------------------------------------------
             * 9. Save refund in refunds table
             * ------------------------------------------------
             */
            $insertQuery = "
                INSERT INTO refunds (
                    transaction_id,
                    razorpay_refund_id,
                    amount,
                    reason,
                    status,
                    refunded_by
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ";

            $insertStatement = $this->db->prepare($insertQuery);
            $insertStatement->execute([
                $transaction['id'],
                $razorpayRefundId,
                $amount,
                $reason,
                $status,
                $refundedBy
            ]);

            /**
             * ------------------------------------------------
             * 10. Update transactions table status if fully refunded
             * ------------------------------------------------
             */
            $newTotalRefunded = $alreadyRefunded + $amount;
            $isFullRefund = ($newTotalRefunded >= (float)$transaction['amount']);

            if ($isFullRefund) {
                $stmtUpTx = $this->db->prepare("UPDATE transactions SET status = 'REFUNDED', updated_at = NOW() WHERE id = ?");
                $stmtUpTx->execute([$transaction['id']]);
            }

            /**
             * ------------------------------------------------
             * 11. Record in Transaction Timeline
             * ------------------------------------------------
             */
            TransactionTimeline::record(
                $vortexTransactionId,
                'REFUND_PROCESSED',
                $isFullRefund ? 'REFUNDED' : 'SUCCESS',
                [
                    'transaction_id' => $transaction['id'],
                    'payment_id'     => $transaction['razorpay_payment_id'],
                    'amount'         => $amount,
                    'currency'       => $transaction['currency'] ?? 'INR',
                    'reason'         => ($isFullRefund ? 'Full refund' : 'Partial refund') . ' of ₹' . number_format($amount, 2) . ' processed (' . ($reason ?: 'Admin initiated') . '). RZP Refund ID: ' . $razorpayRefundId,
                    'source'         => $refundedBy ?: 'VORTEX_ADMIN',
                    'metadata'       => [
                        'razorpay_refund_id' => $razorpayRefundId,
                        'refund_status'      => $status,
                        'refund_amount'      => $amount,
                        'reason'             => $reason,
                        'refunded_by'        => $refundedBy,
                        'is_full_refund'     => $isFullRefund
                    ]
                ]
            );

            /**
             * ------------------------------------------------
             * 12. Dispatch Webhook to API Client (if configured)
             * ------------------------------------------------
             */
            if (!empty($transaction['api_client_id'])) {
                try {
                    $wh = new Webhook();
                    $wh->dispatchClientWebhook(
                        $vortexTransactionId,
                        $isFullRefund ? 'REFUNDED' : 'SUCCESS',
                        (int)$transaction['api_client_id']
                    );
                } catch (\Throwable $whEx) {
                    Logger::error("Failed to dispatch client webhook on refund: " . $whEx->getMessage());
                }
            }

            /**
             * ------------------------------------------------
             * 13. Log successful refund
             * ------------------------------------------------
             */
            Logger::info(
                'Refund created successfully. '
                . 'Vortex Transaction ID: ' . $transaction['vortex_transaction_id']
                . ', Razorpay Payment ID: ' . $transaction['razorpay_payment_id']
                . ', Razorpay Refund ID: ' . $razorpayRefundId
                . ', Amount: ' . $amount
                . ', Status: ' . $status
                . ($isFullRefund ? ' (Full Refund)' : ' (Partial Refund)')
            );

            /**
             * ------------------------------------------------
             * 14. Return successful result
             * ------------------------------------------------
             */
            return [
                'success' => true,
                'message' => 'Refund of ₹' . number_format($amount, 2) . ' processed successfully.',
                'data' => [
                    'refund_id'             => $razorpayRefundId,
                    'vortex_transaction_id' => $transaction['vortex_transaction_id'],
                    'razorpay_payment_id'   => $transaction['razorpay_payment_id'],
                    'amount'                => $amount,
                    'status'                => $status,
                    'is_full_refund'        => $isFullRefund
                ]
            ];

        } catch (\PDOException $e) {
            Logger::error('Refund database error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'A database error occurred while processing the refund.'
            ];
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            // Try to extract clean message if Razorpay returned JSON error
            $decoded = json_decode($msg, true);
            if (isset($decoded['error']['description'])) {
                $msg = $decoded['error']['description'];
            }

            Logger::error('Refund failed. Vortex Transaction ID: ' . $vortexTransactionId . '. Error: ' . $msg);
            return [
                'success' => false,
                'message' => $msg
            ];
        }
    }
}

?>