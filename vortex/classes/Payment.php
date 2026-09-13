<?php

/**
 * ------------------------------------------------------------
 * Payment.php
 * ------------------------------------------------------------
 * Handles:
 * - Payment request validation
 * - Vortex transaction creation
 * - Razorpay order creation
 * - Transaction updates
 *
 * Authentication is handled by the API endpoint.
 * This class receives the authenticated API client ID.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/Event.php';
require_once __DIR__ . '/Logger.php';


class Payment
{
    /**
     * Database connection.
     */
    private $db;


    /**
     * --------------------------------------------------------
     * Constructor
     * --------------------------------------------------------
     */
    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }


    /**
     * --------------------------------------------------------
     * Validate Payment Request
     * --------------------------------------------------------
     *
     * Authentication is NOT handled here.
     *
     * API authentication is already completed by:
     *
     * create_payment_order.php
     *
     * This method only validates payment information.
     * --------------------------------------------------------
     */
    public function validatePaymentRequest(
        $eventId,
        $email,
        $mobile,
        $amount,
        $currency
    ) {
        try {

            // ------------------------------------------------
            // Validate Event ID
            // ------------------------------------------------

            if (!Validator::eventId($eventId)) {

                Logger::error(
                    'Payment validation failed: Event ID is required.'
                );

                return [
                    'success' => false,
                    'message' => 'Event ID is required.'
                ];
            }


            // ------------------------------------------------
            // Check active event
            // ------------------------------------------------

            $event = new Event();

            $activeEvent = $event->getActiveEvent($eventId);

            if ($activeEvent === false) {

                Logger::error(
                    'Payment validation failed: '
                    . 'Event does not exist or is not active. '
                    . 'Event ID: ' . $eventId
                );

                return [
                    'success' => false,
                    'message' =>
                        'The event does not exist or is not active.'
                ];
            }


            // ------------------------------------------------
            // Validate email
            // ------------------------------------------------

            if (!Validator::email($email)) {

                Logger::error(
                    'Payment validation failed: Invalid email address.'
                );

                return [
                    'success' => false,
                    'message' => 'Invalid email address.'
                ];
            }


            // ------------------------------------------------
            // Validate mobile
            // ------------------------------------------------

            if (!Validator::mobile($mobile)) {

                Logger::error(
                    'Payment validation failed: Invalid mobile number.'
                );

                return [
                    'success' => false,
                    'message' => 'Invalid mobile number.'
                ];
            }


            // ------------------------------------------------
            // Validate amount
            // ------------------------------------------------

            if (!Validator::amount($amount)) {

                Logger::error(
                    'Payment validation failed: Invalid payment amount.'
                );

                return [
                    'success' => false,
                    'message' => 'Invalid payment amount.'
                ];
            }


            // ------------------------------------------------
            // Validate currency
            // ------------------------------------------------

            if (!Validator::currency($currency)) {

                Logger::error(
                    'Payment validation failed: Unsupported currency.'
                );

                return [
                    'success' => false,
                    'message' => 'Unsupported currency.'
                ];
            }


            // ------------------------------------------------
            // All validation passed
            // ------------------------------------------------

            return [
                'success' => true,
                'message' => 'Payment request is valid.'
            ];


        } catch (\PDOException $e) {

            Logger::error(
                'Payment validation database error: '
                . $e->getMessage()
            );

            return [
                'success' => false,
                'message' =>
                    'A database error occurred while validating the payment request.'
            ];


        } catch (\Throwable $e) {

            Logger::error(
                'Payment validation unexpected error: '
                . $e->getMessage()
            );

            return [
                'success' => false,
                'message' =>
                    'An unexpected error occurred while validating the payment request.'
            ];
        }
    }


    /**
     * --------------------------------------------------------
     * Generate Vortex Transaction ID
     * --------------------------------------------------------
     */
    private function generateTransactionId()
    {
        try {

            $milliseconds = round(
                microtime(true) * 1000
            );

            return 'VTX'
                . $milliseconds
                . strtoupper(
                    bin2hex(random_bytes(4))
                );

        } catch (\Throwable $e) {

            Logger::error(
                'Vortex transaction ID generation failed: '
                . $e->getMessage()
            );

            throw $e;
        }
    }


    /**
     * --------------------------------------------------------
     * Create Pending Transaction
     * --------------------------------------------------------
     */
    private function createTransaction(
        $vortexTransactionId,
        $eventId,
        $apiClientId,
        $email,
        $mobile,
        $amount,
        $currency
    ) {
        $query = "INSERT INTO transactions (
                    vortex_transaction_id,
                    event_id,
                    api_client_id,
                    customer_email,
                    customer_mobile,
                    amount,
                    currency,
                    status
                  )
                  VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING')";


        $statement = $this->db->prepare($query);


        $statement->execute([
            $vortexTransactionId,
            $eventId,
            $apiClientId,
            $email,
            $mobile,
            $amount,
            $currency
        ]);


        return $this->db->lastInsertId();
    }


    /**
     * --------------------------------------------------------
     * Update Razorpay Order ID
     * --------------------------------------------------------
     */
    private function updateRazorpayOrderId(
        $transactionId,
        $razorpayOrderId
    ) {
        $query = "UPDATE transactions
                  SET razorpay_order_id = ?
                  WHERE id = ?";


        $statement = $this->db->prepare($query);


        $statement->execute([
            $razorpayOrderId,
            $transactionId
        ]);


        if ($statement->rowCount() === 0) {
            return false;
        }


        return true;
    }


    /**
     * --------------------------------------------------------
     * Create Razorpay Order
     * --------------------------------------------------------
     */
    private function createRazorpayOrder(
        $amount,
        $currency,
        $vortexTransactionId
    ) {
        try {

            /**
             * Create Razorpay SDK object.
             */
            $razorpay = new \Razorpay\Api\Api(
                $_ENV['RAZORPAY_KEY_ID'],
                $_ENV['RAZORPAY_KEY_SECRET']
            );


            /**
             * Razorpay expects amount in paise.
             *
             * Example:
             *
             * ₹500
             *
             * 500 × 100 = 50000 paise
             */
            $razorpayAmount = (int) round(
                $amount * 100
            );


            /**
             * Create Razorpay order.
             */
            $order = $razorpay->order->create([
                'receipt' => $vortexTransactionId,
                'amount' => $razorpayAmount,
                'currency' => strtoupper(trim($currency))
            ]);


            /**
             * Log successful Razorpay order creation.
             */
            Logger::info(
                'Razorpay order created successfully. '
                . 'Vortex Transaction ID: '
                . $vortexTransactionId
                . ', Razorpay Order ID: '
                . $order['id']
            );


            return $order;


        } catch (\Throwable $e) {

            /**
             * Do NOT expose the Razorpay technical error
             * to the external developer.
             */
            Logger::error(
                'Razorpay order creation failed. '
                . 'Vortex Transaction ID: '
                . $vortexTransactionId
                . '. Error: '
                . $e->getMessage()
            );


            return false;
        }
    }


    /**
     * --------------------------------------------------------
     * Create Payment
     * --------------------------------------------------------
     *
     * Authentication has already happened in the API layer.
     *
     * Parameters:
     *
     * $apiClientId
     * $eventId
     * $email
     * $mobile
     * $amount
     * $currency
     *
     * Flow:
     *
     * API client
     *      ↓
     * Authentication
     *      ↓
     * apiClientId
     *      ↓
     * Payment.php
     *      ↓
     * Validate request
     *      ↓
     * Create PENDING transaction
     *      ↓
     * Create Razorpay order
     *      ↓
     * Save Razorpay order ID
     *      ↓
     * Return payment details
     * --------------------------------------------------------
     */
    public function createPayment(
        $apiClientId,
        $eventId,
        $email,
        $mobile,
        $amount,
        $currency
    ) {
        try {

            // ------------------------------------------------
            // 1. Make sure authenticated client ID exists
            // ------------------------------------------------

            if (empty($apiClientId)) {

                Logger::error(
                    'Payment creation failed: '
                    . 'Authenticated API client ID is missing.'
                );

                return [
                    'success' => false,
                    'message' => 'Invalid API client.'
                ];
            }


            // ------------------------------------------------
            // 2. Validate payment request
            // ------------------------------------------------

            $validation = $this->validatePaymentRequest(
                $eventId,
                $email,
                $mobile,
                $amount,
                $currency
            );


            if (!$validation['success']) {
                return $validation;
            }


            // ------------------------------------------------
            // 3. Generate Vortex Transaction ID
            // ------------------------------------------------

            $vortexTransactionId =
                $this->generateTransactionId();


            // ------------------------------------------------
            // 4. Create PENDING transaction
            // ------------------------------------------------

            $transactionId = $this->createTransaction(
                $vortexTransactionId,
                $eventId,
                $apiClientId,
                $email,
                $mobile,
                $amount,
                strtoupper(trim($currency))
            );


            // ------------------------------------------------
            // 5. Create Razorpay order
            // ------------------------------------------------

            $razorpayOrder = $this->createRazorpayOrder(
                $amount,
                $currency,
                $vortexTransactionId
            );


            // ------------------------------------------------
            // Razorpay order creation failed
            // ------------------------------------------------

            if (!$razorpayOrder) {

                $query = "UPDATE transactions
                          SET status = 'FAILED'
                          WHERE id = ?";


                $statement = $this->db->prepare($query);


                $statement->execute([
                    $transactionId
                ]);


                Logger::error(
                    'Payment creation failed: '
                    . 'Unable to create Razorpay order. '
                    . 'Transaction ID: '
                    . $transactionId
                );


                return [
                    'success' => false,
                    'message' =>
                        'Unable to create Razorpay order.'
                ];
            }


            // ------------------------------------------------
            // 6. Get Razorpay Order ID
            // ------------------------------------------------

            $razorpayOrderId = $razorpayOrder['id'];


            // ------------------------------------------------
            // 7. Save Razorpay Order ID
            // ------------------------------------------------

            $updated = $this->updateRazorpayOrderId(
                $transactionId,
                $razorpayOrderId
            );


            if (!$updated) {

                $query = "UPDATE transactions
                          SET status = 'FAILED'
                          WHERE id = ?";


                $statement = $this->db->prepare($query);


                $statement->execute([
                    $transactionId
                ]);


                Logger::error(
                    'Payment creation failed: '
                    . 'Unable to save Razorpay order information. '
                    . 'Transaction ID: '
                    . $transactionId
                );


                return [
                    'success' => false,
                    'message' =>
                        'Unable to save Razorpay order information.'
                ];
            }


            // ------------------------------------------------
            // 8. Log successful payment creation
            // ------------------------------------------------

            Logger::info(
                'Payment created successfully. '
                . 'Database Transaction ID: '
                . $transactionId
                . ', Vortex Transaction ID: '
                . $vortexTransactionId
                . ', Razorpay Order ID: '
                . $razorpayOrderId
            );


            // ------------------------------------------------
            // 9. Return successful result
            // ------------------------------------------------

            return [
                'success' => true,
                'message' => 'Payment created successfully.',
                'data' => [
                    'database_transaction_id' =>
                        $transactionId,

                    'vortex_transaction_id' =>
                        $vortexTransactionId,

                    'razorpay_order_id' =>
                        $razorpayOrderId,

                    'status' => 'PENDING'
                ]
            ];


        } catch (\PDOException $e) {

            Logger::error(
                'Payment creation database error: '
                . $e->getMessage()
            );


            return [
                'success' => false,
                'message' =>
                    'A database error occurred while creating the payment.'
            ];


        } catch (\Throwable $e) {

            Logger::error(
                'Payment creation unexpected error: '
                . $e->getMessage()
            );


            return [
                'success' => false,
                'message' =>
                    'An unexpected error occurred while creating the payment.'
            ];
        }
    }
}

?>