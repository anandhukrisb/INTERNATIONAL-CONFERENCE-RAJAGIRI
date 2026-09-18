<?php

/**
 * ------------------------------------------------------------
 * verify_payment.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 *
 * Purpose:
 * Verifies Razorpay payment signature after customer payment
 * and updates transaction status to SUCCESS.
 *
 * Webhook Isolation:
 * This verification is performed via direct Razorpay signature
 * verification and does NOT depend on webhooks.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Explicitly load .env from project root for this request
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Response.php';
require_once __DIR__ . '/../classes/Logger.php';
require_once __DIR__ . '/../classes/CheckoutSession.php';

header('Content-Type: application/json');

// ------------------------------------------------------------
// 1. Allow only POST requests
// ------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed.', [], 405);
}

try {
    // --------------------------------------------------------
    // 2. Read and decode JSON request
    // --------------------------------------------------------

    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody, true);

    if (!is_array($data)) {
        Logger::error('Payment verification failed: Invalid JSON request.');
        Response::error('Invalid JSON request.', [], 400);
    }

    // --------------------------------------------------------
    // 3. Extract & validate required parameters
    // --------------------------------------------------------

    $vortexTransactionId = $data['vortex_transaction_id'] ?? null;
    $razorpayOrderId     = $data['razorpay_order_id'] ?? null;
    $razorpayPaymentId   = $data['razorpay_payment_id'] ?? null;
    $razorpaySignature   = $data['razorpay_signature'] ?? null;
    $sessionToken        = $data['session_token'] ?? null;

    if (
        empty($vortexTransactionId) ||
        empty($razorpayOrderId) ||
        empty($razorpayPaymentId) ||
        empty($razorpaySignature) ||
        empty($sessionToken)
    ) {
        Logger::error('Payment verification failed: Missing required parameters.');
        Response::error('Required payment verification parameters are missing.', [], 400);
    }

    // --------------------------------------------------------
    // 4. Validate Checkout Session
    // --------------------------------------------------------

    $checkoutSession = new CheckoutSession();
    $sessionResult = $checkoutSession->getValidSession((string) $sessionToken);

    if (!$sessionResult['valid']) {
        Logger::error('Payment verification failed: Session invalid or expired. Token: ' . substr((string)$sessionToken, 0, 10));
        $httpCode = $sessionResult['error_code'] ?? 400;
        Response::error('Checkout session is invalid or expired.', [], $httpCode);
    }

    $sessionData = $sessionResult['data'];

    // Validate relationships between session, transaction, and order ID
    if ($sessionData['vortex_transaction_id'] !== $vortexTransactionId) {
        Logger::error('Payment verification failed: Session does not match Vortex transaction ID.');
        Response::error('Payment verification failed.', [], 400);
    }

    if ($sessionData['razorpay_order_id'] !== $razorpayOrderId) {
        Logger::error('Payment verification failed: Session does not match Razorpay order ID.');
        Response::error('Payment verification failed.', [], 400);
    }

    // --------------------------------------------------------
    // 5. Retrieve Transaction from Database
    // --------------------------------------------------------

    $db = Database::getInstance()->getConnection();

    $query = "SELECT id, vortex_transaction_id, razorpay_order_id, razorpay_payment_id, amount, currency, status
              FROM transactions
              WHERE vortex_transaction_id = ?
              LIMIT 1";

    $statement = $db->prepare($query);
    $statement->execute([$vortexTransactionId]);
    $transaction = $statement->fetch();

    if (!$transaction) {
        Logger::error('Payment verification failed: Transaction not found in database: ' . $vortexTransactionId);
        Response::error('Transaction not found.', [], 404);
    }

    if ($transaction['razorpay_order_id'] !== $razorpayOrderId) {
        Logger::error('Payment verification failed: Stored order ID mismatch.');
        Response::error('Payment verification failed.', [], 400);
    }

    // --------------------------------------------------------
    // 6. Handle Idempotency / Duplicate Verification
    // --------------------------------------------------------

    if ($transaction['status'] === 'SUCCESS') {
        if ($transaction['razorpay_payment_id'] === $razorpayPaymentId) {
            Logger::info('Idempotent payment verification acknowledged for transaction: ' . $vortexTransactionId);
            Response::success(
                'Payment already verified.',
                [
                    'vortex_transaction_id' => $transaction['vortex_transaction_id'],
                    'payment_status'        => 'SUCCESS',
                    'status_code'           => 200,
                    'amount'                => (float) $transaction['amount'],
                    'currency'              => $transaction['currency'],
                    'redirect_url'          => $sessionData['redirect_url']
                ]
            );
        } else {
            Logger::error('Duplicate verification rejected: Mismatched payment ID for already successful transaction: ' . $vortexTransactionId);
            Response::error('Payment verification failed.', [], 400);
        }
    }

    // --------------------------------------------------------
    // 7. Verify Signature using Razorpay SDK
    // --------------------------------------------------------

    $keyId = trim($_ENV['RAZORPAY_KEY_ID'] ?? '');
    $keySecret = trim($_ENV['RAZORPAY_KEY_SECRET'] ?? '');

    if (empty($keyId) || empty($keySecret)) {
        Logger::error('Payment verification failed: Razorpay credentials not configured in environment.');
        Response::error('Unable to verify payment.', [], 500);
    }

    $razorpay = new \Razorpay\Api\Api($keyId, $keySecret);

    $attributes = [
        'razorpay_order_id'   => $razorpayOrderId,
        'razorpay_payment_id' => $razorpayPaymentId,
        'razorpay_signature'  => $razorpaySignature
    ];

    try {
        $razorpay->utility->verifyPaymentSignature($attributes);
    } catch (\Throwable $e) {
        Logger::error(
            'Payment verification failed: Invalid Razorpay signature for Order ID '
            . $razorpayOrderId . '. Error: ' . $e->getMessage()
        );
        Response::error('Payment verification failed.', [
            'status_code'  => 400,
            'redirect_url' => $sessionData['redirect_url']
        ], 400);
    }

    // --------------------------------------------------------
    // 8. Update Transaction to SUCCESS
    // --------------------------------------------------------

    $updateQuery = "UPDATE transactions
                    SET razorpay_payment_id = ?,
                        razorpay_signature = ?,
                        status = 'SUCCESS'
                    WHERE id = ?";

    $updateStatement = $db->prepare($updateQuery);
    $updateStatement->execute([
        $razorpayPaymentId,
        $razorpaySignature,
        $transaction['id']
    ]);

    Logger::info(
        'Payment signature verified successfully. '
        . 'Vortex Transaction ID: ' . $transaction['vortex_transaction_id']
        . ', Razorpay Payment ID: ' . $razorpayPaymentId
        . ', Status: SUCCESS'
    );

    // --------------------------------------------------------
    // 9. Return Successful Response
    // --------------------------------------------------------

    Response::success(
        'Payment verified successfully.',
        [
            'vortex_transaction_id' => $transaction['vortex_transaction_id'],
            'payment_status'        => 'SUCCESS',
            'status_code'           => 200,
            'amount'                => (float) $transaction['amount'],
            'currency'              => $transaction['currency'],
            'redirect_url'          => $sessionData['redirect_url']
        ]
    );

} catch (\PDOException $e) {
    Logger::error('Payment verification database error: ' . $e->getMessage());
    Response::error('Unable to verify payment.', [], 500);
} catch (\Throwable $e) {
    Logger::error('Payment verification unexpected error: ' . $e->getMessage());
    Response::error('Unable to verify payment.', [], 500);
}

?>