<?php
/**
 * ------------------------------------------------------------
 * deactivate_api_client.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 * Purpose : Admin action to deactivate an API client.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../../classes/ApiClient.php';
require_once __DIR__ . '/../../classes/Logger.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$clientId = $_POST['client_id'] ?? $_GET['client_id'] ?? null;

if (!empty($clientId) && is_numeric($clientId) && (int)$clientId > 0) {
    $clientModel = new ApiClient();
    $result = $clientModel->deactivateClient((int) $clientId);

    if ($result['success']) {
        $_SESSION['flash_success'] = "API Client ID " . (int) $clientId . " deactivated successfully.";
        Logger::info("Admin deactivated API Client: " . (int)$clientId);
    } else {
        $_SESSION['flash_error'] = "Failed to deactivate API client: " . htmlspecialchars($result['message']);
        Logger::error("Admin failed to deactivate API Client " . (int)$clientId . ": " . $result['message']);
    }
} else {
    $_SESSION['flash_error'] = "Unable to deactivate API client: Invalid Client ID.";
}

header('Location: ../dashboard.php#clients-section');
exit;
