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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$eventId = trim((string)($_POST['event_id'] ?? $_GET['event_id'] ?? ''));

if (Validator::eventId($eventId)) {
    $eventModel = new Event();
    $result = $eventModel->activateEvent($eventId);

    if ($result['success']) {
        $_SESSION['flash_success'] = "Event '" . htmlspecialchars($eventId) . "' activated successfully.";
        Logger::info("Admin activated event: " . $eventId);
    } else {
        $_SESSION['flash_error'] = "Failed to activate event: " . htmlspecialchars($result['message']);
        Logger::error("Admin failed to activate event " . $eventId . ": " . $result['message']);
    }
} else {
    $_SESSION['flash_error'] = "Unable to activate event: Valid Event ID is required.";
}

header('Location: ../dashboard.php#events-section');
exit;
