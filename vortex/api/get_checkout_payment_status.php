<?php

/**
 * ------------------------------------------------------------
 * get_checkout_payment_status.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 *
 * Purpose:
 * Customer-checkout-specific endpoint to retrieve payment status
 * using the secure checkout session token. Enables checkout recovery
 * in event of customer browser network loss without requiring
 * merchant API credentials.
 *
 * Authentication:
 * Authorized exclusively via 64-character checkout session token.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Explicitly load .env from project root
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

require_once __DIR__ . '/../classes/CheckoutSession.php';
require_once __DIR__ . '/../classes/Response.php';
require_once __DIR__ . '/../classes/Logger.php';

header('Content-Type: application/json');

// ------------------------------------------------------------
// 1. Allow POST or GET requests
// ------------------------------------------------------------

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod !== 'POST' && $requestMethod !== 'GET') {
    Response::error('Method not allowed. Use POST.', [], 405);
}

// ------------------------------------------------------------
// 2. Extract session token
// ------------------------------------------------------------

$sessionToken = null;

if ($requestMethod === 'POST') {
    $rawBody = file_get_contents('php://input');
    $jsonData = json_decode($rawBody, true);

    if (is_array($jsonData) && !empty($jsonData['session_token'])) {
        $sessionToken = trim((string) $jsonData['session_token']);
    } elseif (!empty($_POST['session_token'])) {
        $sessionToken = trim((string) $_POST['session_token']);
    }
} else {
    if (!empty($_GET['session_token'])) {
        $sessionToken = trim((string) $_GET['session_token']);
    }
}

if (empty($sessionToken)) {
    Logger::error('Checkout payment status request failed: Missing session_token.');
    Response::error('session_token is required.', [], 400);
}

// ------------------------------------------------------------
// 3. Query payment status via CheckoutSession
// ------------------------------------------------------------

try {
    $checkoutSession = new CheckoutSession();
    $statusResult = $checkoutSession->getCheckoutPaymentStatus($sessionToken);

    if (!$statusResult['success']) {
        $errorCode = $statusResult['error_code'] ?? 400;
        Response::error($statusResult['message'] ?? 'Unable to retrieve payment status.', [], $errorCode);
    }

    $data = $statusResult['data'];
    $paymentStatus = strtoupper((string) $data['payment_status']);

    Logger::info(
        'Checkout payment status retrieved. Vortex Tx: ' . $data['vortex_transaction_id']
        . ', Status: ' . $paymentStatus
    );

    // --------------------------------------------------------
    // 4. Return standardized response based on payment state
    // --------------------------------------------------------

    if ($paymentStatus === 'SUCCESS') {
        Response::success(
            'Payment status retrieved successfully.',
            [
                'vortex_transaction_id' => $data['vortex_transaction_id'],
                'payment_status'        => 'SUCCESS',
                'redirect_url'          => $data['redirect_url'],
                'amount'                => (float) $data['amount'],
                'currency'              => $data['currency']
            ],
            200
        );
    } elseif ($paymentStatus === 'FAILED') {
        Response::success(
            'Payment failed.',
            [
                'vortex_transaction_id' => $data['vortex_transaction_id'],
                'payment_status'        => 'FAILED',
                'redirect_url'          => $data['redirect_url'],
                'amount'                => (float) $data['amount'],
                'currency'              => $data['currency']
            ],
            200
        );
    } else {
        // PENDING / PROCESSING
        Response::success(
            'Payment is still being processed.',
            [
                'vortex_transaction_id' => $data['vortex_transaction_id'],
                'payment_status'        => 'PENDING'
            ],
            200
        );
    }

} catch (\Throwable $e) {
    Logger::error('Checkout payment status unexpected error: ' . $e->getMessage());
    Response::error('Unable to retrieve payment status.', [], 500);
}
