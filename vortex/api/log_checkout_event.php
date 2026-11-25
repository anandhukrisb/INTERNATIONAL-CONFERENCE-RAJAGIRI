<?php

/**
 * ------------------------------------------------------------
 * log_checkout_event.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 *
 * Purpose:
 * Captures browser-side Razorpay checkout modal events and errors
 * (SDK blocked, modal opened, modal dismissed, payment.failed JS)
 * into `payment_logs` and `transaction_timeline`.
 *
 * Authentication:
 * Authorized via the 64-character checkout session token.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

require_once __DIR__ . '/../classes/CheckoutSession.php';
require_once __DIR__ . '/../classes/PaymentLogger.php';
require_once __DIR__ . '/../classes/TransactionTimeline.php';
require_once __DIR__ . '/../classes/Response.php';
require_once __DIR__ . '/../classes/Logger.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Response::error('Method not allowed. Use POST.', [], 405);
}

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

if (!is_array($data)) {
    Response::error('Invalid JSON payload.', [], 400);
}

$sessionToken = trim((string) ($data['session_token'] ?? ''));
$eventType    = trim((string) ($data['event_type'] ?? ''));

$allowedEvents = [
    'checkout.opened',
    'checkout.dismissed',
    'checkout.sdk_error',
    PaymentLogger::EVENT_PAYMENT_FAILED_JS,
];

if ($sessionToken === '' || !in_array($eventType, $allowedEvents, true)) {
    Response::error('Invalid session_token or event_type.', [], 400);
}

try {
    $checkoutSession = new CheckoutSession();
    $sessionResult = $checkoutSession->getValidSession($sessionToken);

    if (!($sessionResult['valid'] ?? false) || empty($sessionResult['data'])) {
        Response::error($sessionResult['message'] ?? 'Invalid or expired checkout session.', [], $sessionResult['error_code'] ?? 400);
    }

    $session = $sessionResult['data'];
    $err     = is_array($data['error'] ?? null) ? $data['error'] : [];
    $meta    = is_array($err['metadata'] ?? null) ? $err['metadata'] : [];
    $client  = is_array($data['client_info'] ?? null) ? $data['client_info'] : [];

    $razorpayPaymentId = trim((string) ($meta['payment_id'] ?? $err['payment_id'] ?? '')) ?: null;
    $razorpayOrderId   = trim((string) ($meta['order_id'] ?? $session['razorpay_order_id'] ?? '')) ?: null;

    $paymentStatusMap = [
        'checkout.opened'                      => 'opened',
        'checkout.dismissed'                   => 'dismissed',
        'checkout.sdk_error'                   => 'sdk_error',
        PaymentLogger::EVENT_PAYMENT_FAILED_JS => 'failed',
    ];

    PaymentLogger::log([
        'event_type'            => $eventType,
        'transaction_id'        => $session['transaction_id'] ?? null,
        'vortex_transaction_id' => $session['vortex_transaction_id'] ?? null,
        'razorpay_order_id'     => $razorpayOrderId,
        'razorpay_payment_id'   => $razorpayPaymentId,
        'currency'              => $session['currency'] ?? null,
        'amount'                => $session['amount'] ?? null,
        'payment_status'        => $paymentStatusMap[$eventType] ?? 'unknown',
        'error_code'            => $err['code'] ?? null,
        'error_description'     => $err['description'] ?? ($client['message'] ?? null),
        'error_reason'          => $err['reason'] ?? null,
        'error_step'            => $err['step'] ?? null,
        'error_source'          => $err['source'] ?? 'browser',
        'customer_email'        => $session['customer_email'] ?? null,
        'customer_mobile'       => $session['customer_mobile'] ?? null,
        'request_payload'       => $client ?: null,
        'response_payload'      => $err ?: null,
    ]);

    // Record errors and dismissals in transaction_timeline while preserving current DB status
    if ($eventType !== 'checkout.opened' && !empty($session['vortex_transaction_id'])) {
        $timelineEventMap = [
            PaymentLogger::EVENT_PAYMENT_FAILED_JS => 'PAYMENT_FAILED_JS',
            'checkout.sdk_error'                   => 'CHECKOUT_SDK_ERROR',
            'checkout.dismissed'                   => 'CHECKOUT_DISMISSED',
        ];
        $reasonText = $err['description']
            ?? $client['message']
            ?? ($eventType === 'checkout.dismissed' ? 'Customer closed or dismissed checkout modal' : 'Browser checkout error');

        TransactionTimeline::record(
            $session['vortex_transaction_id'],
            $timelineEventMap[$eventType] ?? strtoupper(str_replace('.', '_', $eventType)),
            strtoupper((string) ($session['transaction_status'] ?? 'PENDING')),
            [
                'transaction_id' => $session['transaction_id'] ?? null,
                'order_id'       => $razorpayOrderId,
                'payment_id'     => $razorpayPaymentId,
                'amount'         => $session['amount'] ?? null,
                'currency'       => $session['currency'] ?? 'INR',
                'reason'         => $reasonText,
                'source'         => 'CHECKOUT_JS',
                'metadata'       => [
                    'event_type'   => $eventType,
                    'error_code'   => $err['code'] ?? null,
                    'error_reason' => $err['reason'] ?? null,
                    'error_step'   => $err['step'] ?? null,
                    'error_source' => $err['source'] ?? null,
                ],
            ]
        );
    }

    if ($eventType === PaymentLogger::EVENT_PAYMENT_FAILED_JS || $eventType === 'checkout.sdk_error') {
        Logger::warning(sprintf(
            'Checkout modal %s for %s (%s): code=%s reason=%s step=%s desc=%s',
            $eventType,
            $session['vortex_transaction_id'] ?? 'unknown',
            $razorpayOrderId ?? 'no_order',
            $err['code'] ?? 'none',
            $err['reason'] ?? 'none',
            $err['step'] ?? 'none',
            $err['description'] ?? ($client['message'] ?? 'none')
        ));
    }

    Response::success('Checkout event logged.', ['event_type' => $eventType], 200);

} catch (\Throwable $e) {
    Logger::error('log_checkout_event.php error: ' . $e->getMessage());
    Response::error('Unable to log checkout event.', [], 500);
}
