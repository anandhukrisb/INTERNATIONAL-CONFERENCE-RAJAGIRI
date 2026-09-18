<?php

/**
 * ------------------------------------------------------------
 * create_refund.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 *
 * Purpose:
 * Processes refunds for successful transactions.
 * Scoped strictly to the authenticated API client.
 *
 * Authentication:
 * Requires valid API Client credentials (api_key, api_secret).
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Refund.php';
require_once __DIR__ . '/../classes/Response.php';
require_once __DIR__ . '/../classes/Logger.php';
require_once __DIR__ . '/../classes/Validator.php';

header('Content-Type: application/json');

// ------------------------------------------------------------
// 1. Allow only POST requests
// ------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    Response::error('Method not allowed. Only POST is allowed.');
}

// ------------------------------------------------------------
// 2. Read and decode JSON request
// ------------------------------------------------------------

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

if (!is_array($data)) {
    Logger::error('Refund API request failed: Invalid JSON request.');
    http_response_code(400);
    Response::error('Invalid JSON request.');
}

// ------------------------------------------------------------
// 3. Extract and authenticate API client
// ------------------------------------------------------------

$apiKey = $data['api_key'] ?? null;
$apiSecret = $data['api_secret'] ?? null;

if (empty($apiKey) || empty($apiSecret)) {
    Logger::error('Refund API request failed: Missing API credentials.');
    http_response_code(401);
    Response::error('Invalid API credentials.', [], 401);
}

$auth = new Auth();
$client = $auth->authenticate($apiKey, $apiSecret);

if ($client === false) {
    Logger::error('Refund API authentication failed.');
    http_response_code(401);
    Response::error('Invalid API credentials.', [], 401);
}

// ------------------------------------------------------------
// 4. Validate Refund Inputs
// ------------------------------------------------------------

$vortexTransactionId = $data['vortex_transaction_id'] ?? null;
$amount = $data['amount'] ?? null;
$reason = $data['reason'] ?? 'Requested by API Client';

if (empty($vortexTransactionId) || trim($vortexTransactionId) === '') {
    Logger::error('Refund API request failed: Missing vortex_transaction_id.');
    http_response_code(400);
    Response::error('vortex_transaction_id is required.', [], 400);
}

if (!Validator::amount($amount)) {
    Logger::error('Refund API request failed: Invalid refund amount.');
    http_response_code(400);
    Response::error('Valid numeric refund amount greater than 0 is required.', [], 400);
}

// ------------------------------------------------------------
// 5. Verify Transaction Ownership & Status
// ------------------------------------------------------------

try {
    $db = Database::getInstance()->getConnection();

    $query = "SELECT id, status, amount, api_client_id 
              FROM transactions 
              WHERE vortex_transaction_id = ?
              LIMIT 1";

    $statement = $db->prepare($query);
    $statement->execute([trim($vortexTransactionId)]);
    $transaction = $statement->fetch();

    if (!$transaction || $transaction['api_client_id'] != $client['id']) {
        Logger::error(
            'Refund failed: Transaction not found or unauthorized for client ID ' . $client['id']
        );
        http_response_code(404);
        Response::error('Transaction not found.', [], 404);
    }

    if ($transaction['status'] !== 'SUCCESS') {
        Logger::error(
            'Refund rejected: Transaction status is ' . $transaction['status'] . ' (Only SUCCESS allowed)'
        );
        http_response_code(400);
        Response::error('Only successful transactions can be refunded.');
    }

} catch (\PDOException $e) {
    Logger::error('Refund pre-check database error: ' . $e->getMessage());
    http_response_code(500);
    Response::error('A database error occurred while processing refund.');
}

// ------------------------------------------------------------
// 6. Process Refund via Refund.php
// ------------------------------------------------------------

$refund = new Refund();
$result = $refund->createRefund(
    $vortexTransactionId,
    (float) $amount,
    $reason,
    null
);

if (!$result['success']) {
    http_response_code(400);
    Response::error($result['message']);
}

// ------------------------------------------------------------
// 7. Return Successful Response
// ------------------------------------------------------------

http_response_code(200);
Response::success(
    $result['message'],
    $result['data']
);

?>
