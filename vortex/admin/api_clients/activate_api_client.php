<?php
/**
 * ------------------------------------------------------------
 * activate_api_client.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 * Purpose : Admin action to activate an API client.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../../classes/ApiClient.php';
require_once __DIR__ . '/../../classes/Logger.php';

require_once __DIR__ . '/../../classes/RBAC.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['vortex_admin_id'])) {
    header('Location: ../login.php');
    exit;
}
RBAC::require('clients.edit', null, '../dashboard.php#clients-section');

$clientId = $_POST['client_id'] ?? $_GET['client_id'] ?? null;

if (!empty($clientId) && is_numeric($clientId) && (int)$clientId > 0) {
    $clientModel = new ApiClient();
    $result = $clientModel->activateClient((int) $clientId);

    if ($result['success']) {
        $_SESSION['flash_success'] = "API Client ID " . (int) $clientId . " activated successfully.";
        Logger::info("Admin activated API Client: " . (int)$clientId);
    } else {
        $_SESSION['flash_error'] = "Failed to activate API client: " . htmlspecialchars($result['message']);
        Logger::error("Admin failed to activate API Client " . (int)$clientId . ": " . $result['message']);
    }
} else {
    $_SESSION['flash_error'] = "Unable to activate API client: Invalid Client ID.";
}

$returnUrl = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
header('Location: ' . $returnUrl);
exit;
