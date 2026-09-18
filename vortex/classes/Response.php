<?php

/**
 * ------------------------------------------------------------
 * Response.php
 * ------------------------------------------------------------
 * Purpose:
 * Handles all JSON responses returned by the API.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/Logger.php';


class Response
{
    /**
     * --------------------------------------------------------
     * Send Success Response
     * --------------------------------------------------------
     *
     * Example:
     *
     * Response::success(
     *     'Payment created successfully.',
     *     $data
     * );
     *
     * Response:
     *
     * {
     *     "status": "success",
     *     "message": "...",
     *     "data": {}
     * }
     * --------------------------------------------------------
     */
    public static function success(
        $message,
        $data = [],
        $httpCode = 200
    ) {
        // Set HTTP status code.
        http_response_code($httpCode);

        // Tell the client that the response is JSON.
        header('Content-Type: application/json');

        // Create the response.
        $response = [
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ];

        // Convert the PHP array into JSON.
        echo json_encode($response);

        // Stop execution.
        exit;
    }


    /**
     * --------------------------------------------------------
     * Send Error Response
     * --------------------------------------------------------
     *
     * Example:
     *
     * Response::error(
     *     'Invalid API credentials.',
     *     [],
     *     401
     * );
     * --------------------------------------------------------
     */
    public static function error(
        $message,
        $data = [],
        $httpCode = null
    ) {
        // Set HTTP status code: use explicit $httpCode if provided,
        // otherwise retain existing http_response_code() if set, or default to 400.
        $currentCode = http_response_code();
        $code = $httpCode ?? ($currentCode && $currentCode !== 200 ? $currentCode : 400);
        http_response_code($code);

        // Tell the client that the response is JSON.
        header('Content-Type: application/json');

        // Create the response.
        $response = [
            'status' => 'error',
            'message' => $message,
            'data' => $data
        ];

        // Convert the PHP array into JSON.
        echo json_encode($response);

        // Stop execution.
        exit;
    }
}

?>