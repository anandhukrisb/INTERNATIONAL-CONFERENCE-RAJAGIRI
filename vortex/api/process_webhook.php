<?php

/**
 * ------------------------------------------------------------
 * process_webhook.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 * Purpose : Receives and verifies Razorpay webhook requests.
 *
 * Responsibilities:
 * 1. Accept only POST requests.
 * 2. Read the raw Razorpay webhook body.
 * 3. Read the Razorpay webhook signature header.
 * 4. Verify the signature using Razorpay SDK.
 * 5. Decode the webhook JSON.
 * 6. Pass the verified event to Webhook.php.
 * 7. Return safe, standardized HTTP responses.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Explicitly load .env from project root
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

require_once __DIR__ . '/../classes/Webhook.php';
require_once __DIR__ . '/../classes/Logger.php';

header('Content-Type: application/json');

// ------------------------------------------------------------
// 1. Allow only POST requests
// ------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed.'
    ]);
    exit;
}

// ------------------------------------------------------------
// 2. Read the raw webhook payload body
// ------------------------------------------------------------

$webhookBody = file_get_contents('php://input');

if ($webhookBody === false || trim($webhookBody) === '') {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Webhook payload is empty.'
    ]);
    exit;
}

// ------------------------------------------------------------
// 3. Get Razorpay webhook signature header
// ------------------------------------------------------------

$webhookSignature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

if (trim($webhookSignature) === '') {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing Razorpay webhook signature.'
    ]);
    exit;
}

// ------------------------------------------------------------
// 4. Get and validate webhook secret from environment
// ------------------------------------------------------------

$webhookSecret = trim($_ENV['RAZORPAY_WEBHOOK_SECRET'] ?? '');

if ($webhookSecret === '' || $webhookSecret === 'the_secret_configured_by_your_system_analyst') {
    Logger::error('Razorpay Webhook Error: Webhook secret is not configured.');
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Webhook configuration error.'
    ]);
    exit;
}

// ------------------------------------------------------------
// 5. Verify Razorpay webhook signature using Razorpay SDK
// ------------------------------------------------------------

try {
    $keyId = trim($_ENV['RAZORPAY_KEY_ID'] ?? '');
    $keySecret = trim($_ENV['RAZORPAY_KEY_SECRET'] ?? '');

    $api = new Razorpay\Api\Api($keyId, $keySecret);
    $api->utility->verifyWebhookSignature(
        $webhookBody,
        $webhookSignature,
        $webhookSecret
    );
} catch (Throwable $e) {
    Logger::error('Razorpay Webhook Error: Invalid signature. ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid webhook signature.'
    ]);
    exit;
}

// ------------------------------------------------------------
// 6. Decode the verified webhook JSON payload
// ------------------------------------------------------------

$event = json_decode($webhookBody, true);

if (!is_array($event)) {
    Logger::error('Razorpay Webhook Error: Invalid JSON payload.');
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid webhook JSON payload.'
    ]);
    exit;
}

// ------------------------------------------------------------
// 7. Process the verified webhook event via Webhook.php
// ------------------------------------------------------------

try {
    $webhook = new Webhook();
    $result = $webhook->process($event, $webhookSignature);

    if (!is_array($result) || ($result['status'] ?? null) === 'FAILED') {
        Logger::error('Razorpay Webhook Error: Webhook processing returned FAILED.');
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Webhook processing failed.'
        ]);
        exit;
    }

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Webhook processed successfully.'
    ]);
    exit;

} catch (Throwable $e) {
    Logger::error('Razorpay Webhook Processing Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Webhook processing failed.'
    ]);
    exit;
}