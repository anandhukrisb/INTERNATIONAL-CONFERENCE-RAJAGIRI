<?php

/**
 * ------------------------------------------------------------
 * manage_api_client.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 *
 * Purpose:
 * Administrative API endpoint to manage API clients
 * (create, get, activate, deactivate).
 *
 * Architecture:
 * manage_api_client.php -> ApiClient.php -> Database.php -> MySQL
 *
 * Supported Actions:
 * - create     : Registers a new developer client and generates initial API key/secret
 * - get        : Retrieves safe client details (never exposes api_secret)
 * - activate   : Sets client is_active = 1
 * - deactivate : Sets client is_active = 0
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../classes/ApiClient.php';
require_once __DIR__ . '/../classes/Validator.php';
require_once __DIR__ . '/../classes/Response.php';
require_once __DIR__ . '/../classes/Logger.php';

header('Content-Type: application/json');

// ------------------------------------------------------------
// 1. Allow ONLY POST requests
// ------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    Response::error('Method not allowed.', [], 405);
}

// ------------------------------------------------------------
// 2. Read & parse JSON payload
// ------------------------------------------------------------

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

if (!is_array($data)) {
    Logger::error('API client management failed: Invalid JSON request.');
    http_response_code(400);
    Response::error('Invalid JSON request.', [], 400);
}

// ------------------------------------------------------------
// 3. Validate Action
// ------------------------------------------------------------

$action = strtolower(trim((string) ($data['action'] ?? '')));

if ($action === '') {
    Logger::error('API client management failed: Missing action.');
    http_response_code(400);
    Response::error('Action is required.', [], 400);
}

$apiClient = new ApiClient();

// ------------------------------------------------------------
// 4. Handle Actions
// ------------------------------------------------------------

switch ($action) {

    // --------------------------------------------------------
    // Action: CREATE API CLIENT
    // --------------------------------------------------------
    case 'create':
        $clientName = $data['client_name'] ?? null;

        if (!Validator::required($clientName)) {
            Response::error('Client name is required.', [], 400);
        }

        $result = $apiClient->createClient($clientName);

        if (!$result['success']) {
            http_response_code(400);
            Response::error($result['message'], [], 400);
        }

        http_response_code(200);
        Response::success(
            'API client created successfully',
            [
                'client_id'   => (int) $result['data']['id'],
                'client_name' => $result['data']['client_name'],
                'api_key'     => $result['data']['api_key'],
                'api_secret'  => $result['data']['api_secret']
            ]
        );
        break;

    // --------------------------------------------------------
    // Action: GET API CLIENT
    // --------------------------------------------------------
    case 'get':
        $clientId = $data['client_id'] ?? null;

        if ($clientId === null || !is_numeric($clientId) || (int) $clientId <= 0) {
            Response::error('Valid client_id is required.', [], 400);
        }

        $client = $apiClient->getClientById((int) $clientId);

        if ($client === false) {
            Logger::error('API client lookup failed: Client not found. ID: ' . $clientId);
            http_response_code(404);
            Response::error('API client not found.', [], 404);
        }

        http_response_code(200);
        Response::success(
            'API client retrieved successfully',
            [
                'client_id'   => (int) $client['id'],
                'client_name' => $client['client_name'],
                'api_key'     => $client['api_key'],
                'is_active'   => (int) $client['is_active']
            ]
        );
        break;

    // --------------------------------------------------------
    // Action: ACTIVATE API CLIENT
    // --------------------------------------------------------
    case 'activate':
        $clientId = $data['client_id'] ?? null;

        if ($clientId === null || !is_numeric($clientId) || (int) $clientId <= 0) {
            Response::error('Valid client_id is required.', [], 400);
        }

        $result = $apiClient->activateClient((int) $clientId);

        if (!$result['success']) {
            $errorCode = $result['error_code'] ?? 400;
            http_response_code($errorCode);
            Response::error($result['message'], [], $errorCode);
        }

        http_response_code(200);
        Response::success(
            'API client activated successfully',
            [
                'client_id' => (int) $result['data']['client_id']
            ]
        );
        break;

    // --------------------------------------------------------
    // Action: DEACTIVATE API CLIENT
    // --------------------------------------------------------
    case 'deactivate':
        $clientId = $data['client_id'] ?? null;

        if ($clientId === null || !is_numeric($clientId) || (int) $clientId <= 0) {
            Response::error('Valid client_id is required.', [], 400);
        }

        $result = $apiClient->deactivateClient((int) $clientId);

        if (!$result['success']) {
            $errorCode = $result['error_code'] ?? 400;
            http_response_code($errorCode);
            Response::error($result['message'], [], $errorCode);
        }

        http_response_code(200);
        Response::success(
            'API client deactivated successfully',
            [
                'client_id' => (int) $result['data']['client_id']
            ]
        );
        break;

    // --------------------------------------------------------
    // Default: Invalid Action
    // --------------------------------------------------------
    default:
        Logger::error('API client management failed: Invalid action: ' . $action);
        http_response_code(400);
        Response::error('Invalid action. Supported actions are create, get, activate, and deactivate.', [], 400);
        break;
}

?>
