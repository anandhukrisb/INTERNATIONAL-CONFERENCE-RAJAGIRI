<?php

/**
 * ------------------------------------------------------------
 * health_check.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 *
 * Purpose:
 * Provides a lightweight health status check for the Vortex API.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../classes/Response.php';

header('Content-Type: application/json');

http_response_code(200);
Response::success('Vortex API is healthy', [
    'status' => 'UP',
    'timestamp' => date('c')
]);

?>
