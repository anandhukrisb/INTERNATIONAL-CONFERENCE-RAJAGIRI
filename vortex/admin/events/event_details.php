<?php
/**
 * ------------------------------------------------------------
 * event_details.php – Event Overview & Audit Timeline Endpoint
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway - Admin Panel
 * Purpose : Authenticated JSON endpoint returning full event details,
 *           revenue metrics, and lazy-loaded audit timeline entries.
 *           Masks webhook secrets; reveals them only upon explicit
 *           authenticated secondary request.
 * ------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
}

error_reporting(E_ALL);
ini_set('display_errors', '0');

// 1. Authentication Guard
if (empty($_SESSION['vortex_admin_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Admin session required.'
    ]);
    exit;
}

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Event.php';
require_once __DIR__ . '/../../classes/RBAC.php';
require_once __DIR__ . '/../../classes/AuditLogger.php';
require_once __DIR__ . '/../../classes/Logger.php';
require_once __DIR__ . '/../../classes/Validator.php';

// 2. RBAC Guard - Events viewing permission
if (!RBAC::can('events.view')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access Denied: You do not have permission to view events.'
    ]);
    exit;
}

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'get_details'));
$eventId = trim((string)($_GET['id'] ?? $_GET['event_id'] ?? $_POST['event_id'] ?? ''));

if ($eventId === '' || !Validator::eventId($eventId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'A valid Event ID is required.'
    ]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // --------------------------------------------------------
    // Secondary Action: Reveal Webhook Secret (Authenticated)
    // --------------------------------------------------------
    if ($action === 'reveal_secret') {
        if (!RBAC::can('events.view') && !RBAC::can('events.edit')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Access Denied: Permission required to reveal webhook secret.'
            ]);
            exit;
        }

        $stmt = $db->prepare("SELECT event_id, event_name, webhook_secret FROM events WHERE event_id = ? LIMIT 1");
        $stmt->execute([$eventId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Event not found.']);
            exit;
        }

        if (empty($row['webhook_secret'])) {
            echo json_encode([
                'success' => false,
                'message' => 'No webhook secret configured for this event.'
            ]);
            exit;
        }

        AuditLogger::log(
            'event',
            $eventId,
            AuditLogger::EVENT_EDITED,
            null,
            null,
            "Admin revealed webhook secret for event '{$row['event_name']}' (#{$eventId})"
        );

        echo json_encode([
            'success'        => true,
            'event_id'       => $eventId,
            'webhook_secret' => $row['webhook_secret']
        ]);
        exit;
    }

    // --------------------------------------------------------
    // Primary Action: Fetch Event Details & Audit Entries
    // --------------------------------------------------------
    $stmt = $db->prepare("
        SELECT id, event_id, event_name, department, start_date, end_date,
               webhook_url, webhook_secret, is_active, created_at, updated_at
        FROM events
        WHERE event_id = ?
        LIMIT 1
    ");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Event not found.'
        ]);
        exit;
    }

    // Revenue & Transactions Metrics
    $revStmt = $db->prepare("
        SELECT 
            COUNT(CASE WHEN status = 'SUCCESS' THEN 1 END) as tx_count,
            COALESCE(SUM(CASE WHEN status = 'SUCCESS' THEN amount ELSE 0 END), 0) as total_rev,
            COUNT(*) as all_tx_count
        FROM transactions
        WHERE event_id = ?
    ");
    $revStmt->execute([$eventId]);
    $rev = $revStmt->fetch(PDO::FETCH_ASSOC) ?: ['tx_count' => 0, 'total_rev' => 0, 'all_tx_count' => 0];

    // Audit Trail Entries for this Event
    $auditStmt = $db->prepare("
        SELECT id, entity_type, entity_id, action, actor_type, actor_username, actor_role,
               ip_address, description, created_at
        FROM audit_logs
        WHERE (entity_type = 'event' AND entity_id = ?)
           OR description LIKE ?
        ORDER BY id DESC
        LIMIT 50
    ");
    $auditStmt->execute([$eventId, '%' . $eventId . '%']);
    $auditLogs = $auditStmt->fetchAll(PDO::FETCH_ASSOC);

    // Compute Status
    $now = time();
    $endTs = strtotime($event['end_date']);
    $isActive = (int)($event['is_active'] ?? 0) === 1;

    if ($isActive && $endTs !== false && $endTs < $now) {
        $statusKey = 'CONCLUDED';
        $statusLabel = 'Concluded';
        $statusBadgeClass = 'badge-warning';
    } elseif ($isActive) {
        $statusKey = 'ACTIVE';
        $statusLabel = 'Active';
        $statusBadgeClass = 'badge-success';
    } else {
        $statusKey = 'DISABLED';
        $statusLabel = 'Disabled';
        $statusBadgeClass = 'badge-read-only';
    }

    // Mask secret: NEVER return unmasked webhook secret in this endpoint
    $hasSecret = !empty($event['webhook_secret']);
    $maskedSecret = $hasSecret ? (substr($event['webhook_secret'], 0, 4) . '••••••••••••') : null;
    unset($event['webhook_secret']);

    $event['has_webhook_secret'] = $hasSecret;
    $event['masked_secret']      = $maskedSecret;
    $event['status_key']         = $statusKey;
    $event['status_label']       = $statusLabel;
    $event['status_badge_class'] = $statusBadgeClass;
    $event['is_concluded']       = ($statusKey === 'CONCLUDED');

    echo json_encode([
        'success'      => true,
        'event'        => $event,
        'revenue'      => [
            'successful_count' => (int)$rev['tx_count'],
            'total_revenue'    => (float)$rev['total_rev'],
            'total_tx_count'   => (int)$rev['all_tx_count'],
            'formatted_rev'    => '₹' . number_format((float)$rev['total_rev'], 2)
        ],
        'audit_logs'   => $auditLogs,
        'audit_count'  => count($auditLogs)
    ], JSON_UNESCAPED_SLASHES);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve event details: ' . $e->getMessage()
    ]);
}
