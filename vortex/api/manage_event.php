<?php

/**
 * ------------------------------------------------------------
 * manage_event.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 *
 * Purpose:
 * API endpoint to manage events (create, get, deactivate).
 *
 * Authentication:
 * Requires valid API Client credentials (api_key, api_secret).
 *
 * Supported Actions:
 * - create     : Creates a new event
 * - get        : Retrieves active event details by event_id
 * - deactivate : Deactivates an event by event_id
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Event.php';
require_once __DIR__ . '/../classes/Validator.php';
require_once __DIR__ . '/../classes/Response.php';
require_once __DIR__ . '/../classes/Logger.php';

header('Content-Type: application/json');

// ------------------------------------------------------------
// 1. Allow only POST requests
// ------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    Response::error('Method not allowed. Only POST requests are supported.', [], 405);
}

// ------------------------------------------------------------
// 2. Read & parse JSON payload
// ------------------------------------------------------------

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

if (!is_array($data)) {
    Logger::error('Event management request failed: Invalid JSON request.');
    http_response_code(400);
    Response::error('Invalid JSON request.', [], 400);
}

// ------------------------------------------------------------
// 3. Authenticate API client
// ------------------------------------------------------------

$apiKey    = $data['api_key'] ?? null;
$apiSecret = $data['api_secret'] ?? null;

if (empty($apiKey) || empty($apiSecret)) {
    Logger::error('Event management authentication failed: Missing API credentials.');
    http_response_code(401);
    Response::error('Invalid API credentials.', [], 401);
}

$auth = new Auth();
$client = $auth->authenticate($apiKey, $apiSecret);

if ($client === false) {
    Logger::error('Event management authentication failed for provided credentials.');
    http_response_code(401);
    Response::error('Invalid API credentials.', [], 401);
}

// ------------------------------------------------------------
// 4. Validate Action
// ------------------------------------------------------------

$action = strtolower(trim((string) ($data['action'] ?? '')));

if ($action === '') {
    Logger::error('Event management request failed: Missing action parameter.');
    http_response_code(400);
    Response::error('Action is required.', [], 400);
}

$event = new Event();

// ------------------------------------------------------------
// 5. Execute Action
// ------------------------------------------------------------

switch ($action) {

    // --------------------------------------------------------
    // Action: CREATE EVENT
    // --------------------------------------------------------
    case 'create':
        $eventName  = $data['event_name'] ?? null;
        $department = $data['department'] ?? null;
        $startDate  = $data['start_date'] ?? null;
        $endDate    = $data['end_date'] ?? null;

        if (!Validator::required($eventName)) {
            Response::error('Event name is required.', [], 400);
        }

        if (!Validator::required($department)) {
            Response::error('Department is required.', [], 400);
        }

        if (!Validator::required($startDate)) {
            Response::error('Start date is required.', [], 400);
        }

        if (!Validator::required($endDate)) {
            Response::error('End date is required.', [], 400);
        }

        $result = $event->createEvent(
            $eventName,
            $department,
            $startDate,
            $endDate
        );

        if (!$result['success']) {
            http_response_code(400);
            Response::error($result['message'], [], 400);
        }

        http_response_code(200);
        Response::success(
            'Event created successfully.',
            [
                'event_id'   => $result['data']['event_id'],
                'event_name' => trim($eventName),
                'department' => trim($department),
                'start_date' => trim($startDate),
                'end_date'   => trim($endDate)
            ]
        );
        break;

    // --------------------------------------------------------
    // Action: GET EVENT
    // --------------------------------------------------------
    case 'get':
        $eventId = $data['event_id'] ?? null;

        if (!Validator::eventId($eventId)) {
            Response::error('Event ID is required.', [], 400);
        }

        $eventData = $event->getActiveEvent($eventId);

        if ($eventData === false) {
            Logger::error('Event lookup failed: Event not found or inactive. Event ID: ' . $eventId);
            http_response_code(404);
            Response::error('Event not found or is inactive.', [], 404);
        }

        http_response_code(200);
        Response::success(
            'Event retrieved successfully.',
            [
                'event_id'   => $eventData['event_id'],
                'event_name' => $eventData['event_name'],
                'department' => $eventData['department'],
                'start_date' => $eventData['start_date'],
                'end_date'   => $eventData['end_date'],
                'is_active'  => (int) $eventData['is_active']
            ]
        );
        break;

    // --------------------------------------------------------
    // Action: DEACTIVATE EVENT
    // --------------------------------------------------------
    case 'deactivate':
        $eventId = $data['event_id'] ?? null;

        if (!Validator::eventId($eventId)) {
            Response::error('Event ID is required.', [], 400);
        }

        $deactivateResult = $event->deactivateEvent($eventId);

        if (!$deactivateResult['success']) {
            $errorCode = $deactivateResult['error_code'] ?? 400;
            http_response_code($errorCode);
            Response::error($deactivateResult['message'], [], $errorCode);
        }

        http_response_code(200);
        Response::success(
            'Event deactivated successfully.',
            [
                'event_id' => $deactivateResult['data']['event_id']
            ]
        );
        break;

    // --------------------------------------------------------
    // Default: Unsupported Action
    // --------------------------------------------------------
    default:
        Logger::error('Event management request failed: Unsupported action: ' . $action);
        http_response_code(400);
        Response::error('Invalid action. Supported actions are create, get, and deactivate.', [], 400);
        break;
}

?>
