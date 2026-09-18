<?php

/**
 * ------------------------------------------------------------
 * get_payment_status.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 *
 * Purpose:
 * Retrieves payment status for a specific Vortex transaction.
 * Scoped strictly to the authenticated API client.
 *
 * Authentication:
 * Requires valid API Client credentials (api_key, api_secret).
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Response.php';
require_once __DIR__ . '/../classes/Logger.php';

header('Content-Type: application/json');

// ------------------------------------------------------------
// 1. Allow POST or GET requests
// ------------------------------------------------------------

$requestMethod = $_SERVER['REQUEST_METHOD'];

if ($requestMethod !== 'POST' && $requestMethod !== 'GET') {
    http_response_code(405);
    Response::error('Method not allowed. Use POST or GET.');
}

// ------------------------------------------------------------
// 2. Read Request Parameters
// ------------------------------------------------------------

if ($requestMethod === 'POST') {
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody, true);

    if (!is_array($data)) {
        $data = $_POST;
    }
} else {
    $data = $_GET;
}

// ------------------------------------------------------------
// 3. Extract credentials & transaction ID
// ------------------------------------------------------------

$apiKey = $data['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? null;
$apiSecret = $data['api_secret'] ?? $_SERVER['HTTP_X_API_SECRET'] ?? null;
$vortexTransactionId = $data['vortex_transaction_id'] ?? null;

// ------------------------------------------------------------
// 4. Authenticate API client
// ------------------------------------------------------------

if (empty($apiKey) || empty($apiSecret)) {
    Logger::error('Payment status lookup failed: Missing API credentials.');
    http_response_code(401);
    Response::error('Invalid API credentials.', [], 401);
}

$auth = new Auth();
$client = $auth->authenticate($apiKey, $apiSecret);

if ($client === false) {
    Logger::error('Payment status API authentication failed.');
    http_response_code(401);
    Response::error('Invalid API credentials.', [], 401);
}

// ------------------------------------------------------------
// 5. Validate Vortex Transaction ID
// ------------------------------------------------------------

if (empty($vortexTransactionId) || trim($vortexTransactionId) === '') {
    Logger::error('Payment status API request failed: Missing vortex_transaction_id.');
    http_response_code(400);
    Response::error('vortex_transaction_id is required.', [], 400);
}

// ------------------------------------------------------------
// 6. Query Payment Status scoped to api_client_id
// ------------------------------------------------------------

try {
    $db = Database::getInstance()->getConnection();

    $query = "SELECT vortex_transaction_id,
                     status,
                     created_at,
                     updated_at
              FROM transactions
              WHERE vortex_transaction_id = ?
              AND api_client_id = ?
              LIMIT 1";

    $statement = $db->prepare($query);
    $statement->execute([
        trim($vortexTransactionId),
        $client['id']
    ]);

    $transaction = $statement->fetch();

    if (!$transaction) {
        Logger::error(
            'Payment status lookup failed: Transaction not found or unauthorized. '
            . 'Vortex Transaction ID: ' . $vortexTransactionId
            . ', Client ID: ' . $client['id']
        );
        http_response_code(404);
        Response::error('Transaction not found.', [], 404);
    }

    Logger::info(
        'Payment status retrieved successfully. '
        . 'Vortex Transaction ID: ' . $transaction['vortex_transaction_id']
        . ', Status: ' . $transaction['status']
    );

    // --------------------------------------------------------
    // 7. Return Standardized Developer Response
    // --------------------------------------------------------

    http_response_code(200);
    Response::success(
        'Payment status retrieved successfully.',
        [
            'vortex_transaction_id' => $transaction['vortex_transaction_id'],
            'date_time'             => $transaction['created_at'],
            'payment_status'        => $transaction['status'],
            'status'                => $transaction['status']
        ]
    );

} catch (\PDOException $e) {
    Logger::error('Payment status database error: ' . $e->getMessage());
    http_response_code(500);
    Response::error('A database error occurred while retrieving payment status.');
} catch (\Throwable $e) {
    Logger::error('Payment status unexpected error: ' . $e->getMessage());
    http_response_code(500);
    Response::error('Unable to retrieve payment status.');
}

?>
