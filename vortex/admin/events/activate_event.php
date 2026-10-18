<?php
/**
 * ------------------------------------------------------------
 * activate_event.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 * Purpose : Admin action to activate an event.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../../classes/Event.php';
require_once __DIR__ . '/../../classes/Validator.php';
require_once __DIR__ . '/../../classes/Logger.php';

require_once __DIR__ . '/../../classes/RBAC.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['vortex_admin_id'])) {
    header('Location: ../login.php');
    exit;
}
RBAC::require('events.edit', null, '../dashboard.php#events-section');

$eventId = trim((string)($_POST['event_id'] ?? $_GET['event_id'] ?? ''));

if (Validator::eventId($eventId)) {
    $eventModel = new Event();
    $result = $eventModel->activateEvent($eventId);

    if ($result['success']) {
        $_SESSION['flash_success'] = "Event '" . htmlspecialchars($eventId) . "' activated successfully.";
        Logger::info("Admin activated event: " . $eventId);
        AuditLogger::log('event', $eventId, AuditLogger::EVENT_ACTIVATED, ['is_active' => 0], ['is_active' => 1], "Event '{$eventId}' activated");
    } else {
        $_SESSION['flash_error'] = "Failed to activate event: " . htmlspecialchars($result['message']);
        Logger::error("Admin failed to activate event " . $eventId . ": " . $result['message']);
    }
} else {
    $_SESSION['flash_error'] = "Unable to activate event: Valid Event ID is required.";
}

$returnUrl = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
header('Location: ' . $returnUrl);
exit;
