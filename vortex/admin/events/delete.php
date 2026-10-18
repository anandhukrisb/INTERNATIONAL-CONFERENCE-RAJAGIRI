<?php
/**
 * ------------------------------------------------------------
 * delete.php – Delete Event Endpoint
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway - Admin Panel
 * Purpose : Secure POST-only endpoint to safely delete events
 *           with zero transactions, enforcing Super Admin RBAC,
 *           CSRF protection, and audit logging.
 * ------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Event.php';
require_once __DIR__ . '/../../classes/RBAC.php';
require_once __DIR__ . '/../../classes/AuditLogger.php';
require_once __DIR__ . '/../../classes/Logger.php';
require_once __DIR__ . '/../../classes/Validator.php';

// 1. Method Check - POST Only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Only POST requests are permitted.'
    ]);
    exit;
}

// 2. Authentication Guard - Must be logged into Vortex Admin
if (empty($_SESSION['vortex_admin_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Please sign in to the admin panel.'
    ]);
    exit;
}

// 3. RBAC Guard - Super Admin Role ONLY
if (!RBAC::hasRole(RBAC::ROLE_SUPER_ADMIN)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access Denied: Only Super Administrators can delete events.'
    ]);
    exit;
}

// 4. Parse Request Data (supports JSON payload or application/x-www-form-urlencoded POST)
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

// 5. CSRF Token Validation
$sessionCsrf = $_SESSION['vortex_csrf_token'] ?? '';
$requestCsrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($data['csrf_token'] ?? '');

if (empty($sessionCsrf) || empty($requestCsrf) || !hash_equals($sessionCsrf, $requestCsrf)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or missing CSRF token. Please refresh the page and try again.'
    ]);
    exit;
}

// 6. Validate Event ID
$eventId = trim((string)($data['event_id'] ?? ''));
if (empty($eventId) || !Validator::eventId($eventId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'A valid Event ID is required.'
    ]);
    exit;
}

// 7. Execute Deletion via Event Model (handles transaction check, lock, and DB transaction)
$eventModel = new Event();
$result = $eventModel->deleteEvent($eventId);

if ($result['success']) {
    $eventData = $result['data'] ?? [];
    $eventName = $eventData['event_name'] ?? $eventId;

    // 8. Write Audit Trail Entry
    AuditLogger::log(
        'event',
        $eventId,
        AuditLogger::EVENT_DELETED,
        $eventData,
        null,
        "Super Admin deleted event '{$eventName}' (#{$eventId})",
        [
            'deleted_by_admin_id' => $_SESSION['vortex_admin_id'],
            'deleted_by_username' => $_SESSION['vortex_admin_username'] ?? 'admin'
        ]
    );

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $result['message'],
        'data'    => $eventData
    ]);
    exit;
} else {
    $statusCode = $result['error_code'] ?? 400;
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'message' => $result['message']
    ]);
    exit;
}
