<?php
/**
 * ------------------------------------------------------------
 * process_refund.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway - Admin Panel
 * Purpose : Authenticated endpoint to process full or partial
 *           refunds directly via Razorpay and record audit trails.
 * ------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../classes/RBAC.php';

// 1. Authentication Guard - Require Vortex Admin session
if (empty($_SESSION['vortex_admin_id'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Please log in to Vortex Admin.'
    ]);
    exit;
}

// 2. RBAC Guard - Require payments.refund privilege (Super Admin or Finance)
RBAC::require('payments.refund');

// 2. Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Only POST requests are permitted.'
    ]);
    exit;
}

// 3. Parse payload (supports JSON payload or standard POST form data)
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

$vortexTransactionId = trim($data['vortex_transaction_id'] ?? '');
$amount              = isset($data['amount']) ? (float)$data['amount'] : 0.0;
$reason              = trim($data['reason'] ?? 'Customer refund requested via Admin');

if (empty($vortexTransactionId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Vortex Transaction ID is required.'
    ]);
    exit;
}

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Refund amount must be a valid positive number.'
    ]);
    exit;
}

// 4. Execute Refund through Vortex Core
try {
    require_once __DIR__ . '/../../classes/Refund.php';
require_once __DIR__ . '/../../classes/AuditLogger.php';
    
    $adminIdentifier = $_SESSION['vortex_admin_username'] ?? ('Admin #' . $_SESSION['vortex_admin_id']);
    $refundService   = new Refund();
    $result          = $refundService->createRefund(
        $vortexTransactionId,
        $amount,
        $reason,
        'ADMIN_' . $adminIdentifier
    );

    if (!empty($result['success'])) {
        AuditLogger::log(
            'refund',
            $vortexTransactionId,
            AuditLogger::REFUND_PROCESSED,
            ['status' => 'SUCCESS'],
            ['status' => 'REFUNDED', 'refund_amount' => $amount],
            "Refund of ₹{$amount} processed for transaction {$vortexTransactionId}",
            ['reason' => $reason]
        );

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'data'    => $result['data'] ?? []
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to process refund.'
        ]);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
