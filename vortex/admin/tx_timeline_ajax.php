<?php
/**
 * ------------------------------------------------------------
 * tx_timeline_ajax.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 * Purpose : Authenticated JSON endpoint for transaction timeline
 *           and audit log fragments in the admin overview modal.
 * ------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', '0');

// Authentication Guard
if (empty($_SESSION['vortex_admin_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Admin session required.'
    ]);
    exit;
}

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/TransactionTimeline.php';
require_once __DIR__ . '/../classes/Logger.php';

$txParam = trim((string)($_GET['vortex_transaction_id'] ?? $_GET['id'] ?? ''));

if ($txParam === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing vortex_transaction_id parameter.'
    ]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // 1. Fetch Transaction
    $numericId = is_numeric($txParam) ? (int)$txParam : 0;
    $stmt = $db->prepare("
        SELECT t.*, e.event_name, e.department, c.client_name
        FROM transactions t
        LEFT JOIN events e ON t.event_id = e.event_id
        LEFT JOIN api_clients c ON t.api_client_id = c.id
        WHERE t.vortex_transaction_id = :p1
           OR t.id = :num
           OR t.razorpay_order_id = :p2
           OR t.razorpay_payment_id = :p3
        ORDER BY t.id DESC LIMIT 1
    ");
    $stmt->execute([
        ':p1'  => $txParam,
        ':p2'  => $txParam,
        ':p3'  => $txParam,
        ':num' => $numericId,
    ]);
    $tx = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tx) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Transaction not found.'
        ]);
        exit;
    }

    $vortexTxId = $tx['vortex_transaction_id'];
    $txDbId     = (int)$tx['id'];
    $rzpOrder   = $tx['razorpay_order_id'] ?? '';
    $rzpPayment = $tx['razorpay_payment_id'] ?? '';

    // 2. Fetch Timeline Events
    $timeline = TransactionTimeline::getTimeline($vortexTxId, $txDbId);

    // If timeline table has no explicit rows, synthesize the chronological lifecycle
    if (empty($timeline)) {
        $timeline = [];
        
        // Event 1: Order / Intent Created
        $timeline[] = [
            'id'                    => 1,
            'vortex_transaction_id' => $vortexTxId,
            'transaction_id'        => $txDbId,
            'order_id'              => $rzpOrder,
            'payment_id'            => null,
            'event_type'            => 'ORDER_CREATED',
            'status'                => 'PENDING',
            'amount'                => $tx['amount'],
            'currency'              => $tx['currency'] ?? 'INR',
            'reason'                => 'Payment checkout initialized for ' . ($tx['event_name'] ?? 'Event'),
            'source'                => 'CHECKOUT_SESSION',
            'metadata'              => null,
            'created_at'            => $tx['created_at']
        ];

        // Event 2: Resulting Terminal State
        $statusUpper = strtoupper((string)$tx['status']);
        if ($statusUpper === 'SUCCESS') {
            $timeline[] = [
                'id'                    => 2,
                'vortex_transaction_id' => $vortexTxId,
                'transaction_id'        => $txDbId,
                'order_id'              => $rzpOrder,
                'payment_id'            => $rzpPayment,
                'event_type'            => 'PAYMENT_CAPTURED',
                'status'                => 'SUCCESS',
                'amount'                => $tx['amount'],
                'currency'              => $tx['currency'] ?? 'INR',
                'reason'                => 'Payment successfully captured via Razorpay',
                'source'                => 'RAZORPAY_GATEWAY',
                'metadata'              => null,
                'created_at'            => $tx['updated_at'] ?? $tx['created_at']
            ];
        } elseif ($statusUpper === 'FAILED') {
            $timeline[] = [
                'id'                    => 2,
                'vortex_transaction_id' => $vortexTxId,
                'transaction_id'        => $txDbId,
                'order_id'              => $rzpOrder,
                'payment_id'            => $rzpPayment,
                'event_type'            => 'PAYMENT_FAILED',
                'status'                => 'FAILED',
                'amount'                => $tx['amount'],
                'currency'              => $tx['currency'] ?? 'INR',
                'reason'                => 'Payment attempt failed or was interrupted by user',
                'source'                => 'RAZORPAY_GATEWAY',
                'metadata'              => null,
                'created_at'            => $tx['updated_at'] ?? $tx['created_at']
            ];
        } elseif ($statusUpper === 'REFUNDED') {
            $timeline[] = [
                'id'                    => 2,
                'vortex_transaction_id' => $vortexTxId,
                'transaction_id'        => $txDbId,
                'order_id'              => $rzpOrder,
                'payment_id'            => $rzpPayment,
                'event_type'            => 'REFUND_PROCESSED',
                'status'                => 'REFUNDED',
                'amount'                => $tx['amount'],
                'currency'              => $tx['currency'] ?? 'INR',
                'reason'                => 'Payment refunded back to customer',
                'source'                => 'ADMIN_ACTION',
                'metadata'              => null,
                'created_at'            => $tx['updated_at'] ?? $tx['created_at']
            ];
        }
    }

    // Sort timeline descending so the most recent milestone is always on top
    usort($timeline, function($a, $b) {
        $timeA = strtotime($a['created_at'] ?? '1970-01-01');
        $timeB = strtotime($b['created_at'] ?? '1970-01-01');
        if ($timeA === $timeB) {
            return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
        }
        return $timeB <=> $timeA;
    });

    // 3. Fetch Admin Audit Logs associated with this transaction
    $auditLogs = [];
    try {
        $stmtAudit = $db->prepare("
            SELECT id, entity_type, entity_id, action, actor_type, actor_username, actor_role, ip_address, description, created_at
            FROM audit_logs
            WHERE entity_id = :p1
               OR entity_id = :p2
               OR description LIKE :p3
            ORDER BY id DESC LIMIT 15
        ");
        $stmtAudit->execute([
            ':p1' => $vortexTxId,
            ':p2' => (string)$txDbId,
            ':p3' => '%' . $vortexTxId . '%'
        ]);
        $auditLogs = $stmtAudit->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {}

    // 4. Fetch Webhook Callbacks associated with this order/payment
    $webhooks = [];
    try {
        $stmtWh = $db->prepare("
            SELECT id, event_type, payment_id, order_id, status, created_at
            FROM webhooks
            WHERE (order_id IS NOT NULL AND order_id != '' AND order_id = :oid)
               OR (payment_id IS NOT NULL AND payment_id != '' AND payment_id = :pid)
               OR payload LIKE :like_vtx
            ORDER BY id DESC LIMIT 10
        ");
        $stmtWh->execute([
            ':oid'      => $rzpOrder,
            ':pid'      => $rzpPayment,
            ':like_vtx' => '%' . $vortexTxId . '%'
        ]);
        $webhooks = $stmtWh->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {}

    // 5. Fetch Refund Records
    $refunds = [];
    try {
        $stmtRef = $db->prepare("
            SELECT id, razorpay_refund_id, amount, reason, status, created_at
            FROM refunds
            WHERE transaction_id = :vtx_id OR transaction_id = :db_id
            ORDER BY id DESC LIMIT 10
        ");
        $stmtRef->execute([
            ':vtx_id' => $vortexTxId,
            ':db_id'  => (string)$txDbId
        ]);
        $refunds = $stmtRef->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {}

    echo json_encode([
        'success'               => true,
        'vortex_transaction_id' => $vortexTxId,
        'db_id'                 => $txDbId,
        'timeline'              => $timeline,
        'audit_logs'            => $auditLogs,
        'webhooks'              => $webhooks,
        'refunds'               => $refunds,
        'total_events'          => count($timeline) + count($auditLogs) + count($webhooks)
    ], JSON_UNESCAPED_SLASHES);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch timeline: ' . $e->getMessage()
    ]);
}
