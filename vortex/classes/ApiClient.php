<?php

/**
 * ------------------------------------------------------------
 * ApiClient.php
 * ------------------------------------------------------------
 * Handles API client authentication and client information.
 * ------------------------------------------------------------
 */

// Load the Database class.
require_once __DIR__ . '/Database.php';

// Load the Logger class.
require_once __DIR__ . '/Logger.php';


class ApiClient
{
    // Database connection
    private $db;


    /**
     * Constructor.
     *
     * Gets the database connection from Database.php.
     */
    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }


    /**
     * --------------------------------------------------------
     * Verify API Client
     * --------------------------------------------------------
     * Checks whether the API key and API secret are valid
     * and whether the client is active.
     * --------------------------------------------------------
     */
    public function verifyClient($apiKey, $apiSecret)
    {
        // SQL query to find the client.
        $query = "SELECT id, client_name, api_key, is_active
                  FROM api_clients
                  WHERE api_key = ?
                  AND api_secret = ?
                  AND is_active = 1";

        // Prepare the SQL query.
        $statement = $this->db->prepare($query);

        // Execute the query with the API credentials.
        $statement->execute([$apiKey, $apiSecret]);

        // Get the matching client.
        $client = $statement->fetch();

        // Check whether a client was found.
        if ($client) {

            // Log successful authentication.
            Logger::info(
                'API client authenticated successfully. '
                . 'Client ID: ' . $client['id']
            );

            return $client;
        }

        // Log failed authentication.
        // Do NOT log the API key or API secret.
        Logger::error(
            'API client authentication failed.'
        );

        // No valid client was found.
        return false;
    }


    /**
     * --------------------------------------------------------
     * Get Client By ID
     * --------------------------------------------------------
     * Gets client details using the client's ID.
     * --------------------------------------------------------
     */
    public function getClientById($id)
    {
        // SQL query to find the client.
        $query = "SELECT id, client_name, api_key, is_active
                  FROM api_clients
                  WHERE id = ?";

        // Prepare the SQL query.
        $statement = $this->db->prepare($query);

        // Execute the query using the client ID.
        $statement->execute([$id]);

        // Get the matching client.
        $client = $statement->fetch();

        // Check whether a client was found.
        if ($client) {
            return $client;
        }

        // Client was not found.
        return false;
    }


    /**
     * --------------------------------------------------------
     * Create API Client
     * --------------------------------------------------------
     * Generates a new API key and API secret for a developer.
     * This method should be called by an admin.
     * --------------------------------------------------------
     */
    public function createClient($clientName)
    {
        try {

            // Validate client name.
            $clientName = trim($clientName);

            if ($clientName === '') {

                Logger::error(
                    'API client creation failed: Client name is required.'
                );

                return [
                    'success' => false,
                    'message' => 'Client name is required.'
                ];
            }


            // Generate secure API credentials.
            $apiKey = bin2hex(random_bytes(16));
            $apiSecret = bin2hex(random_bytes(16));


            // Insert the new client.
            $query = "INSERT INTO api_clients
                      (client_name, api_key, api_secret, is_active)
                      VALUES (?, ?, ?, 1)";

            $statement = $this->db->prepare($query);

            $statement->execute([
                $clientName,
                $apiKey,
                $apiSecret
            ]);


            // Get the newly created client ID.
            $clientId = $this->db->lastInsertId();


            // Log successful creation.
            //
            // IMPORTANT:
            // Never write the API key or API secret to the log.
            Logger::info(
                'API client created successfully. '
                . 'Client ID: ' . $clientId
                . ', Client Name: ' . $clientName
            );


            // Return credentials to the admin.
            return [
                'success' => true,
                'message' => 'API client created successfully.',
                'data' => [
                    'id' => $clientId,
                    'client_name' => $clientName,
                    'api_key' => $apiKey,
                    'api_secret' => $apiSecret
                ]
            ];


        } catch (\PDOException $e) {

            // Log database error.
            Logger::error(
                'API client database error: '
                . $e->getMessage()
            );

            return [
                'success' => false,
                'message' => 'Unable to create API client.'
            ];


        } catch (\Exception $e) {

            // Log unexpected error.
            Logger::error(
                'API client unexpected error: '
                . $e->getMessage()
            );

            return [
                'success' => false,
                'message' =>
                    'An unexpected error occurred while creating the API client.'
            ];
        }
    }


    /**
     * --------------------------------------------------------
     * Activate API Client
     * --------------------------------------------------------
     * Sets is_active = 1 for the specified API client.
     *
     * @param int $clientId
     * @return array
     * --------------------------------------------------------
     */
    public function activateClient($clientId)
    {
        try {
            $clientId = (int) $clientId;

            if ($clientId <= 0) {
                return [
                    'success' => false,
                    'message' => 'Invalid client ID.',
                    'error_code' => 400
                ];
            }

            // Check if the API client exists.
            $checkQuery = "SELECT id, is_active FROM api_clients WHERE id = ? LIMIT 1";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->execute([$clientId]);
            $client = $checkStmt->fetch();

            if (!$client) {
                Logger::error('API client activation failed: Client not found. ID: ' . $clientId);
                return [
                    'success' => false,
                    'message' => 'API client not found.',
                    'error_code' => 404
                ];
            }

            // If already active, return success idempotently.
            if ((int) $client['is_active'] === 1) {
                Logger::info('API client already active. ID: ' . $clientId);
                return [
                    'success' => true,
                    'message' => 'API client activated successfully',
                    'data' => [
                        'client_id' => $clientId
                    ]
                ];
            }

            // Update is_active = 1.
            $query = "UPDATE api_clients SET is_active = 1 WHERE id = ?";
            $statement = $this->db->prepare($query);
            $statement->execute([$clientId]);

            Logger::info('API client activated successfully. ID: ' . $clientId);

            return [
                'success' => true,
                'message' => 'API client activated successfully',
                'data' => [
                    'client_id' => $clientId
                ]
            ];

        } catch (\PDOException $e) {
            Logger::error('API client activation database error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Unable to activate API client.',
                'error_code' => 500
            ];
        } catch (\Throwable $e) {
            Logger::error('API client activation unexpected error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An unexpected error occurred while activating the API client.',
                'error_code' => 500
            ];
        }
    }


    /**
     * --------------------------------------------------------
     * Deactivate API Client
     * --------------------------------------------------------
     * Sets is_active = 0 for the specified API client.
     *
     * @param int $clientId
     * @return array
     * --------------------------------------------------------
     */
    public function deactivateClient($clientId)
    {
        try {
            $clientId = (int) $clientId;

            if ($clientId <= 0) {
                return [
                    'success' => false,
                    'message' => 'Invalid client ID.',
                    'error_code' => 400
                ];
            }

            // Check if the API client exists.
            $checkQuery = "SELECT id, is_active FROM api_clients WHERE id = ? LIMIT 1";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->execute([$clientId]);
            $client = $checkStmt->fetch();

            if (!$client) {
                Logger::error('API client deactivation failed: Client not found. ID: ' . $clientId);
                return [
                    'success' => false,
                    'message' => 'API client not found.',
                    'error_code' => 404
                ];
            }

            // If already inactive, return success idempotently.
            if ((int) $client['is_active'] === 0) {
                Logger::info('API client already inactive. ID: ' . $clientId);
                return [
                    'success' => true,
                    'message' => 'API client deactivated successfully',
                    'data' => [
                        'client_id' => $clientId
                    ]
                ];
            }

            // Update is_active = 0.
            $query = "UPDATE api_clients SET is_active = 0 WHERE id = ?";
            $statement = $this->db->prepare($query);
            $statement->execute([$clientId]);

            Logger::info('API client deactivated successfully. ID: ' . $clientId);

            return [
                'success' => true,
                'message' => 'API client deactivated successfully',
                'data' => [
                    'client_id' => $clientId
                ]
            ];

        } catch (\PDOException $e) {
            Logger::error('API client deactivation database error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Unable to deactivate API client.',
                'error_code' => 500
            ];
        } catch (\Throwable $e) {
            Logger::error('API client deactivation unexpected error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An unexpected error occurred while deactivating the API client.',
                'error_code' => 500
            ];
        }
    }


    /**
     * --------------------------------------------------------
     * Get All API Clients
     * --------------------------------------------------------
     * Retrieves all API clients safely (excluding api_secret)
     * ordered by creation date descending.
     *
     * @param int $limit
     * @return array
     * --------------------------------------------------------
     */
    public function getAllClients($limit = 50)
    {
        try {
            $limit = max(1, min((int) $limit, 200));
            $query = "SELECT id, client_name, api_key, is_active, created_at
                      FROM api_clients
                      ORDER BY id DESC
                      LIMIT " . $limit;

            $statement = $this->db->prepare($query);
            $statement->execute();

            return $statement->fetchAll();

        } catch (\PDOException $e) {
            Logger::error('Failed to fetch API clients: ' . $e->getMessage());
            return [];
        } catch (\Throwable $e) {
            Logger::error('Unexpected error fetching API clients: ' . $e->getMessage());
            return [];
        }
    }


    /**
     * --------------------------------------------------------
     * Get Total API Clients Count
     * --------------------------------------------------------
     * Returns total count of API clients in database.
     *
     * @return int
     * --------------------------------------------------------
     */
    public function getTotalCount()
    {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM api_clients");
            return (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            Logger::error('Failed to get total api_clients count: ' . $e->getMessage());
            return 0;
        } catch (\Throwable $e) {
            Logger::error('Unexpected error getting total api_clients count: ' . $e->getMessage());
            return 0;
        }
    }
}

?>