<?php
// Admin endpoint/script to register a new external client and generate an API key

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/client_helper.php';
require_once __DIR__ . '/../helpers/response.php';

// Accept JSON request or CLI arguments
$input = json_decode(file_get_contents('php://input'), true);
$clientName = $input['client_name'] ?? ($argv[1] ?? null);

if (empty($clientName)) {
    sendJsonResponse('error', 'Client name is required. Example payload: {"client_name": "Dyuti Web App"}', [], 400);
}

try {
    $pdo = getDbConnection();
    $keys = generateApiClientKeys();

    $stmt = $pdo->prepare("INSERT INTO api_clients (client_name, api_key, api_secret, is_active) VALUES (:name, :key, :secret, 1)");
    $stmt->execute([
        'name'   => $clientName,
        'key'    => $keys['api_key'],
        'secret' => $keys['api_secret']
    ]);

    sendJsonResponse('success', 'API Client created successfully!', [
        'client_id'   => $pdo->lastInsertId(),
        'client_name' => $clientName,
        'api_key'     => $keys['api_key'],
        'api_secret'  => $keys['api_secret']
    ], 201);
} catch (Exception $e) {
    sendJsonResponse('error', 'Failed to create client: ' . $e->getMessage(), [], 500);
}
