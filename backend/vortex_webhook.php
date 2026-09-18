<?php
// backend/vortex_webhook.php
// Receives asynchronous webhooks from the Vortex Gateway

require_once __DIR__ . '/db.php';
$env = parse_ini_file(__DIR__ . '/../.env');

header('Content-Type: application/json');

// 1. Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

// 2. Read the raw body
$webhookBody = file_get_contents('php://input');
if (empty($webhookBody)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Empty payload.']);
    exit;
}

// 3. Read the signature header
$signatureHeader = $_SERVER['HTTP_X_VORTEX_SIGNATURE'] ?? '';
if (empty($signatureHeader)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing signature.']);
    exit;
}

// 4. Verify the HMAC SHA256 Signature
$webhookSecret = $env['VORTEX_WEBHOOK_SECRET'] ?? '';
if (empty($webhookSecret)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Webhook secret not configured.']);
    exit;
}

$expectedSignature = hash_hmac('sha256', $webhookBody, $webhookSecret);

if (!hash_equals($expectedSignature, $signatureHeader)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid signature.']);
    exit;
}

// 5. Process the payload
$payload = json_decode($webhookBody, true);
if (!is_array($payload) || empty($payload['vortex_transaction_id']) || empty($payload['status'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload format.']);
    exit;
}

$vortexTxnId = $payload['vortex_transaction_id'];
$status = $payload['status'];

// Map Vortex status to Main Project status
$mainProjectStatus = '';
if ($status === 'SUCCESS') {
    $mainProjectStatus = 'Completed';
} elseif ($status === 'FAILED') {
    $mainProjectStatus = 'Failed';
} else {
    // Ignore PENDING or others
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Ignored status.']);
    exit;
}

try {
    // 6. Update the user_registrations table
    $stmt = $pdo->prepare("UPDATE user_registrations SET payment_status = :status WHERE transaction_id = :txn_id AND payment_status != 'Completed'");
    $stmt->execute([
        ':status' => $mainProjectStatus,
        ':txn_id' => $vortexTxnId
    ]);

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Webhook processed successfully.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
}
