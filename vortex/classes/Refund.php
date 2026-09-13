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
        $this->db =
            Database::getInstance()->getConnection();


        /**
         * Create Razorpay API object.
         *
         * The credentials come from the environment.
         */
        $this->razorpay = new \Razorpay\Api\Api(
            $_ENV['RAZORPAY_KEY_ID'],
            $_ENV['RAZORPAY_KEY_SECRET']
        );
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
             *
             * The admin/developer provides the Vortex
             * transaction ID.
             *
             * Example:
             *
             * VTX1787555539816273C3CBC
             */
            $query = "
                SELECT
                    id,
                    vortex_transaction_id,
                    razorpay_payment_id,
                    amount,
                    status
                FROM transactions
                WHERE vortex_transaction_id = ?
                LIMIT 1
            ";


            $statement = $this->db->prepare($query);


            $statement->execute([
                $vortexTransactionId
            ]);


            $transaction = $statement->fetch();


            /**
             * ------------------------------------------------
             * 2. Check transaction exists
             * ------------------------------------------------
             */
            if (!$transaction) {

                throw new Exception(
                    'Transaction not found.'
                );
            }


            /**
             * ------------------------------------------------
             * 3. Refund only successful transactions
             * ------------------------------------------------
             */
            if ($transaction['status'] !== 'SUCCESS') {

                throw new Exception(
                    'Only successful transactions can be refunded.'
                );
            }


            /**
             * ------------------------------------------------
             * 4. Check Razorpay payment ID
             * ------------------------------------------------
             *
             * This is the important connection between our
             * transaction and Razorpay.
             *
             * transactions.razorpay_payment_id
             */
            if (
                empty(
                    $transaction['razorpay_payment_id']
                )
            ) {

                throw new Exception(
                    'Razorpay payment ID not found.'
                );
            }


            /**
             * ------------------------------------------------
             * 5. Validate refund amount
             * ------------------------------------------------
             */
            if ($amount <= 0) {

                throw new Exception(
                    'Refund amount must be greater than zero.'
                );
            }


            /**
             * Refund cannot be greater than the
             * original transaction amount.
             */
            if ($amount > $transaction['amount']) {

                throw new Exception(
                    'Refund amount cannot exceed transaction amount.'
                );
            }


            /**
             * ------------------------------------------------
             * 6. Convert amount to paise
             * ------------------------------------------------
             *
             * Example:
             *
             * ₹500.00
             *
             * becomes:
             *
             * 50000 paise
             */
            $refundAmount = (int) round(
                $amount * 100
            );


            /**
             * ------------------------------------------------
             * 7. Call Razorpay Refund API
             * ------------------------------------------------
             *
             * We do NOT ask the developer for the Razorpay
             * payment ID.
             *
             * We found it from our transactions table.
             */
            $refund = $this->razorpay
                ->payment
                ->fetch(
                    $transaction['razorpay_payment_id']
                )
                ->refund([
                    'amount' => $refundAmount,
                    'speed' => 'normal'
                ]);


            /**
             * ------------------------------------------------
             * 8. Get Razorpay refund ID
             * ------------------------------------------------
             */
            $razorpayRefundId =
                $refund['id'];


            /**
             * ------------------------------------------------
             * 9. Get Razorpay refund status
             * ------------------------------------------------
             */
            $razorpayStatus =
                $refund['status'] ?? 'pending';


            /**
             * ------------------------------------------------
             * 10. Convert Razorpay status
             * ------------------------------------------------
             *
             * Razorpay:
             *
             * processed
             * failed
             * pending
             *
             * Vortex:
             *
             * PROCESSED
             * FAILED
             * PENDING
             */
            if ($razorpayStatus === 'processed') {

                $status = 'PROCESSED';

            } elseif ($razorpayStatus === 'failed') {

                $status = 'FAILED';

            } else {

                $status = 'PENDING';
            }


            /**
             * ------------------------------------------------
             * 11. Save refund in our database
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


            $insertStatement =
                $this->db->prepare($insertQuery);


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
             * 12. Log successful refund
             * ------------------------------------------------
             *
             * We log IDs useful for troubleshooting.
             *
             * We do NOT log Razorpay credentials.
             */
            Logger::info(
                'Refund created successfully. '
                . 'Vortex Transaction ID: '
                . $transaction['vortex_transaction_id']
                . ', Razorpay Payment ID: '
                . $transaction['razorpay_payment_id']
                . ', Razorpay Refund ID: '
                . $razorpayRefundId
                . ', Amount: '
                . $amount
                . ', Status: '
                . $status
            );


            /**
             * ------------------------------------------------
             * 13. Return successful result
             * ------------------------------------------------
             */
            return [
                'success' => true,
                'message' => 'Refund created successfully.',
                'data' => [

                    'refund_id' =>
                        $razorpayRefundId,

                    'vortex_transaction_id' =>
                        $transaction['vortex_transaction_id'],

                    'razorpay_payment_id' =>
                        $transaction['razorpay_payment_id'],

                    'amount' =>
                        $amount,

                    'status' =>
                        $status
                ]
            ];


        } catch (\PDOException $e) {

            /**
             * Database error.
             *
             * Log technical details but don't expose them
             * to the API caller.
             */
            Logger::error(
                'Refund database error: '
                . $e->getMessage()
            );


            return [
                'success' => false,
                'message' =>
                    'A database error occurred while processing the refund.'
            ];


        } catch (\Throwable $e) {

            /**
             * Razorpay errors and other application errors
             * come here.
             */
            Logger::error(
                'Refund failed. '
                . 'Vortex Transaction ID: '
                . $vortexTransactionId
                . '. Error: '
                . $e->getMessage()
            );


            return [
                'success' => false,
                'message' =>
                    $e->getMessage()
            ];
        }
    }
}

?>