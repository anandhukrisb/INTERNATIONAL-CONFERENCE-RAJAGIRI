<?php

/**
 * ------------------------------------------------------------
 * authenticate_user.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 *
 * Purpose:
 * Public API endpoint used by an external developer to verify
 * their Vortex API credentials (api_key and api_secret).
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Response.php';
require_once __DIR__ . '/../classes/Logger.php';

header('Content-Type: application/json');

// ------------------------------------------------------------
// 1. Allow ONLY POST requests.
// ------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error(
        'Method not allowed.',
        [],
        405
    );
}

// ------------------------------------------------------------
// 2. Read raw request body.
// ------------------------------------------------------------

$rawBody = file_get_contents('php://input');

// ------------------------------------------------------------
// 3. Decode JSON request.
// ------------------------------------------------------------

$data = json_decode($rawBody, true);

// ------------------------------------------------------------
// 4. Validate JSON format.
// ------------------------------------------------------------

if (!is_array($data)) {
    Logger::error('Authentication request failed: Invalid JSON request.');

    Response::error(
        'Invalid JSON request.',
        [],
        400
    );
}

// ------------------------------------------------------------
// 5. Read API credentials from JSON.
// ------------------------------------------------------------

$apiKey = $data['api_key'] ?? null;
$apiSecret = $data['api_secret'] ?? null;

// ------------------------------------------------------------
// 6. Validate required fields.
// ------------------------------------------------------------

if ($apiKey === null || trim((string) $apiKey) === '') {
    Response::error(
        'API key is required.',
        [],
        400
    );
}

if ($apiSecret === null || trim((string) $apiSecret) === '') {
    Response::error(
        'API secret is required.',
        [],
        400
    );
}

// ------------------------------------------------------------
// 7. Authenticate API client with error handling.
// ------------------------------------------------------------

try {
    $auth = new Auth();

    $client = $auth->authenticate(
        $apiKey,
        $apiSecret
    );

    // If authentication fails
    if ($client === false) {
        Response::error(
            'Invalid API credentials.',
            [],
            401
        );
    }

    // Authentication succeeded
    Logger::info(
        'Authentication endpoint verified client successfully. Client ID: ' . $client['id']
    );

    Response::success(
        'Authentication successful.',
        [
            'client_id'   => (int) $client['id'],
            'client_name' => $client['client_name']
        ]
    );

} catch (\Throwable $e) {
    // Log internal error without exposing details to external client
    Logger::error(
        'Authentication endpoint error: ' . $e->getMessage()
    );

    Response::error(
        'Unable to authenticate API client.',
        [],
        500
    );
}

?>
