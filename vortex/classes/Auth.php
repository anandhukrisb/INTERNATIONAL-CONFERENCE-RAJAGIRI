<?php

/**
 * ------------------------------------------------------------
 * Auth.php
 * ------------------------------------------------------------
 * Handles authentication of Vortex API clients.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/ApiClient.php';
require_once __DIR__ . '/Logger.php';


class Auth
{
    private $apiClient;


    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->apiClient = new ApiClient();
    }


    /**
     * --------------------------------------------------------
     * Authenticate API Client
     * --------------------------------------------------------
     * Checks whether the API key and API secret belong
     * to an active Vortex API client.
     * --------------------------------------------------------
     */
    public function authenticate($apiKey, $apiSecret)
    {
        try {

            // Ask ApiClient to verify the credentials.
            $client = $this->apiClient->verifyClient(
                $apiKey,
                $apiSecret
            );


            // Authentication failed.
            if ($client === false) {

                // ApiClient already logs the failed
                // authentication attempt.
                return false;
            }


            // Authentication successful.
            // ApiClient already logs the successful
            // authentication.
            return $client;


        } catch (\Throwable $e) {

            /**
             * Log unexpected authentication errors.
             *
             * IMPORTANT:
             * Never log the API key or API secret.
             */
            Logger::error(
                'Authentication error: '
                . $e->getMessage()
            );

            return false;
        }
    }
}

?>