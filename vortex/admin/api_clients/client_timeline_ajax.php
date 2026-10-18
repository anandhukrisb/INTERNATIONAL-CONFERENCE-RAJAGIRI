<?php
/**
 * ------------------------------------------------------------
 * client_timeline_ajax.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway - Admin Panel
 * Purpose : Authenticated JSON endpoint for merchant audit logs,
 *           activity timeline, and transaction stats in the client modal.
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

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/AuditLogger.php';
require_once __DIR__ . '/../../classes/RBAC.php';
require_once __DIR__ . '/../../classes/Logger.php';

$clientId = (int)($_GET['client_id'] ?? $_GET['id'] ?? 0);

if ($clientId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing or invalid client_id.'
    ]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // 1. Fetch Client Details
    $stmt = $db->prepare("SELECT * FROM api_clients WHERE id = ? LIMIT 1");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'API Client not found.'
        ]);
        exit;
    }

    // Sanitize secrets if user lacks permission
    if (!RBAC::can('clients.view_secret')) {
        $client['api_secret'] = '••••••••••••';
        $client['test_api_secret'] = '••••••••••••';
    }

    // 2. Fetch Merchant Audit Logs (Recent on top)
    $auditLogs = AuditLogger::getClientLogs($clientId, 30);

    // 3. Fetch Merchant Transaction Summary Stats
    $statsStmt = $db->prepare("
        SELECT 
            COUNT(*) AS total_count,
            COUNT(CASE WHEN status = 'SUCCESS' THEN 1 END) AS success_count,
            COUNT(CASE WHEN status = 'FAILED' THEN 1 END) AS failed_count,
            COUNT(CASE WHEN status = 'REFUNDED' THEN 1 END) AS refunded_count,
            COALESCE(SUM(CASE WHEN status = 'SUCCESS' THEN amount ELSE 0 END), 0) AS total_revenue
        FROM transactions 
        WHERE api_client_id = ?
    ");
    $statsStmt->execute([$clientId]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // 4. Fetch Payments for this Merchant (up to 100, recent on top)
    $recentStmt = $db->prepare("
        SELECT t.id, t.vortex_transaction_id, t.amount, t.currency, t.status, t.customer_email, t.customer_mobile, t.created_at, e.event_name, t.event_id
        FROM transactions t
        LEFT JOIN events e ON t.event_id = e.event_id
        WHERE t.api_client_id = ?
        ORDER BY t.id DESC LIMIT 100
    ");
    $recentStmt->execute([$clientId]);
    $recentPayments = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

    // Ensure audit logs are strictly sorted descending (most recent milestone on top)
    usort($auditLogs, function($a, $b) {
        $tA = strtotime($a['created_at'] ?? '1970-01-01');
        $tB = strtotime($b['created_at'] ?? '1970-01-01');
        if ($tA === $tB) {
            return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
        }
        return $tB <=> $tA;
    });

    echo json_encode([
        'success'         => true,
        'client'          => $client,
        'audit_logs'      => $auditLogs,
        'stats'           => [
            'total_count'    => (int)($stats['total_count'] ?? 0),
            'success_count'  => (int)($stats['success_count'] ?? 0),
            'failed_count'   => (int)($stats['failed_count'] ?? 0),
            'refunded_count' => (int)($stats['refunded_count'] ?? 0),
            'total_revenue'  => (float)($stats['total_revenue'] ?? 0)
        ],
        'recent_payments' => $recentPayments,
        'total_events'    => count($auditLogs) + count($recentPayments)
    ], JSON_UNESCAPED_SLASHES);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch client timeline: ' . $e->getMessage()
    ]);
}
