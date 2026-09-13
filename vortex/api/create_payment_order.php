<?php

/**
 * ------------------------------------------------------------
 * create_payment_order.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 *
 * Flow:
 * Developer Backend Request
 *        ↓
 * Authentication (Auth.php / ApiClient.php)
 *        ↓
 * Request Validation (Validator.php)
 *        ↓
 * Payment Creation (Payment.php)
 *        ↓
 * 5-Minute Checkout Session (CheckoutSession.php)
 *        ↓
 * Return payment_url & vortex_transaction_id
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Payment.php';
require_once __DIR__ . '/../classes/Event.php';
require_once __DIR__ . '/../classes/CheckoutSession.php';
require_once __DIR__ . '/../classes/Response.php';
require_once __DIR__ . '/../classes/Logger.php';
require_once __DIR__ . '/../classes/Validator.php';

header('Content-Type: application/json');

// ------------------------------------------------------------
// 1. Allow only POST requests.
// ------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    Response::error('Method not allowed. Only POST requests are supported.');
}

// ------------------------------------------------------------
// 2. Read JSON request.
// ------------------------------------------------------------

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

// ------------------------------------------------------------
// 3. Validate JSON payload.
// ------------------------------------------------------------

if (!is_array($data)) {
    Logger::error('Payment API request failed: Invalid JSON request.');
    http_response_code(400);
    Response::error('Invalid JSON request.');
}

// ------------------------------------------------------------
// 4. Get API credentials & Authenticate.
// ------------------------------------------------------------

$apiKey = $data['api_key'] ?? null;
$apiSecret = $data['api_secret'] ?? null;

if (empty($apiKey) || empty($apiSecret)) {
    Logger::error('Payment API request failed: Missing API credentials.');
    http_response_code(401);
    Response::error('Invalid API credentials.', [], 401);
}

$auth = new Auth();
$client = $auth->authenticate($apiKey, $apiSecret);

if ($client === false) {
    Logger::error('Payment API authentication failed for provided credentials.');
    http_response_code(401);
    Response::error('Invalid API credentials.', [], 401);
}

// ------------------------------------------------------------
// 5. Validate Required Fields
// ------------------------------------------------------------

$eventId    = $data['event_id'] ?? null;
$email      = $data['email'] ?? null;
$mobile     = $data['mobile'] ?? null;
$amount     = $data['amount'] ?? null;
$currency   = $data['currency'] ?? null;
$redirectUrl = $data['redirect_url'] ?? null;

// Validate amount
if ($amount === null || !is_numeric($amount) || (float)$amount <= 0) {
    Logger::error('Payment API request failed: Invalid or missing amount.');
    http_response_code(400);
    Response::error('Valid numeric amount greater than 0 is required.', [], 400);
}

// Currency is strictly required — NO DEFAULT VALUE
if ($currency === null || trim($currency) === '') {
    Logger::error('Payment API request failed: Currency was not provided.');
    http_response_code(400);
    Response::error('Currency is required. Allowed currencies are INR and USD.', [], 400);
}

$normalizedCurrency = strtoupper(trim($currency));

if (!Validator::currency($normalizedCurrency)) {
    Logger::error('Payment API request failed: Unsupported currency ' . $currency);
    http_response_code(400);
    Response::error('Unsupported currency. Allowed currencies are INR and USD.', [], 400);
}

// Validate event_id presence
if (empty($eventId) || trim($eventId) === '') {
    Logger::error('Payment API request failed: Event ID is required.');
    http_response_code(400);
    Response::error('Event ID is required.', [], 400);
}

// Check active event exists (404 for nonexistent event)
$eventObj = new Event();
$activeEvent = $eventObj->getActiveEvent($eventId);
if ($activeEvent === false) {
    Logger::error('Payment API request failed: Event does not exist or is not active. Event ID: ' . $eventId);
    http_response_code(404);
    Response::error('The event does not exist or is not active.', [], 404);
}

// Validate customer email
if (empty($email) || !Validator::email($email)) {
    Logger::error('Payment API request failed: Invalid email address.');
    http_response_code(400);
    Response::error('Invalid email address.', [], 400);
}

// Validate redirect_url
if (empty($redirectUrl) || !Validator::url($redirectUrl)) {
    Logger::error('Payment API request failed: Invalid or missing redirect_url.');
    http_response_code(400);
    Response::error('Valid redirect_url is required.', [], 400);
}

// ------------------------------------------------------------
// 6. Create Payment via Payment.php
// ------------------------------------------------------------

$payment = new Payment();
$result = $payment->createPayment(
    $client['id'],
    $eventId,
    $email,
    $mobile,
    $amount,
    $normalizedCurrency
);

if (!is_array($result) || ($result['success'] ?? false) === false) {
    http_response_code(400);
    Response::error($result['message'] ?? 'Unable to create payment.');
}

$paymentData = $result['data'];
$transactionDbId = $paymentData['database_transaction_id'];
$vortexTransactionId = $paymentData['vortex_transaction_id'];

// ------------------------------------------------------------
// 7. Create 5-Minute Checkout Session
// ------------------------------------------------------------

$checkoutSession = new CheckoutSession();
$sessionResult = $checkoutSession->createSession(
    $transactionDbId,
    $redirectUrl,
    5 // 5-minute expiry
);

if (!$sessionResult['success']) {
    Logger::error('Failed to create checkout session for transaction ID ' . $transactionDbId);
    http_response_code(500);
    Response::error('Unable to create checkout session.');
}

$sessionToken = $sessionResult['session_token'];
$paymentUrl = CheckoutSession::buildPaymentUrl($sessionToken);

// ------------------------------------------------------------
// 8. Return Standardized Developer Response
// ------------------------------------------------------------

http_response_code(200);
Response::success(
    'Payment created successfully.',
    [
        'vortex_transaction_id' => $vortexTransactionId,
        'payment_url'           => $paymentUrl,
        'date_time'             => date('Y-m-d H:i:s'),
        'payment_status'        => 'PENDING'
    ]
);

?>