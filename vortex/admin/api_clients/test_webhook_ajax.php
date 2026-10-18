<?php
/**
 * ------------------------------------------------------------
 * test_webhook_ajax.php – AJAX Live Webhook Diagnostic Endpoint
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway - Admin Panel
 * Purpose : Authenticated endpoint for initiating real-time diagnostic
 *           cURL pings to merchant webhook endpoints with HMAC-SHA256
 *           signatures and reporting latency, status code, and response.
 * ------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// Ensure error output doesn't corrupt JSON response
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/ApiClient.php';
require_once __DIR__ . '/../../classes/RBAC.php';
require_once __DIR__ . '/../../classes/Logger.php';

// Auth Guard
if (empty($_SESSION['vortex_admin_id'])) {
    http_response_code(401);
    echo json_encode([
        'success'   => false,
        'http_code' => 401,
        'message'   => 'Unauthorized: Admin authentication session required.'
    ]);
    exit;
}

// RBAC Guard (clients.create or clients.edit)
if (!RBAC::can('clients.create') && !RBAC::can('clients.edit')) {
    http_response_code(403);
    echo json_encode([
        'success'   => false,
        'http_code' => 403,
        'message'   => 'Forbidden: Insufficient privileges to test webhook endpoints.'
    ]);
    exit;
}

// Accept both application/json and POST form-encoded
$input = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?: [];
} else {
    $input = $_POST;
}

$webhookUrl = trim((string)($input['webhook_url'] ?? ''));
$webhookSecret = trim((string)($input['webhook_secret'] ?? ''));
$event = trim((string)($input['event'] ?? 'payment.captured'));

if (empty($webhookUrl)) {
    http_response_code(422);
    echo json_encode([
        'success'   => false,
        'http_code' => 422,
        'message'   => 'Webhook URL is required.'
    ]);
    exit;
}

if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
    http_response_code(422);
    echo json_encode([
        'success'   => false,
        'http_code' => 422,
        'message'   => 'Invalid webhook URL format. Must start with http:// or https://'
    ]);
    exit;
}

// If secret is omitted, generate an ephemeral test secret
if (empty($webhookSecret)) {
    $webhookSecret = bin2hex(random_bytes(24));
}

// Perform live diagnostic ping
try {
    $result = ApiClient::testWebhookEndpoint($webhookUrl, $webhookSecret, $event);

    echo json_encode(array_merge([
        'target_url'     => $webhookUrl,
        'webhook_secret' => $webhookSecret,
        'tested_at'      => date('Y-m-d H:i:s'),
    ], $result));
} catch (\Throwable $e) {
    Logger::error("Webhook test endpoint exception: " . $e->getMessage());
    echo json_encode([
        'success'          => false,
        'http_code'        => 0,
        'response_time_ms' => 0,
        'message'          => 'Diagnostic ping execution failed: ' . $e->getMessage()
    ]);
}
