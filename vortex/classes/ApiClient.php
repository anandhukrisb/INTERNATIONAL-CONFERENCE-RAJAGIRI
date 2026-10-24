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
require_once __DIR__ . '/AuditLogger.php';


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
        // 1. Check Live keys
        $queryLive = "SELECT id, client_name, api_key, is_active
                  FROM api_clients
                  WHERE api_key = ?
                  AND api_secret = ?
                  AND is_active = 1";
        $stmtLive = $this->db->prepare($queryLive);
        $stmtLive->execute([$apiKey, $apiSecret]);
        $client = $stmtLive->fetch();

        if ($client) {
            $client['is_test_mode'] = 0;
            Logger::info('API client authenticated successfully (LIVE mode). Client ID: ' . $client['id']);
            return $client;
        }

        // 2. Check Test keys
        $queryTest = "SELECT id, client_name, api_key, is_active
                  FROM api_clients
                  WHERE test_api_key = ?
                  AND test_api_secret = ?
                  AND is_active = 1";
        $stmtTest = $this->db->prepare($queryTest);
        $stmtTest->execute([$apiKey, $apiSecret]);
        $clientTest = $stmtTest->fetch();

        if ($clientTest) {
            $clientTest['is_test_mode'] = 1;
            Logger::info('API client authenticated successfully (TEST mode). Client ID: ' . $clientTest['id']);
            return $clientTest;
        }

        Logger::error('API client authentication failed.');
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
        $query = "SELECT * FROM api_clients WHERE id = ?";

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

            $testApiKey = 'test_' . bin2hex(random_bytes(16));
            $testApiSecret = bin2hex(random_bytes(16));


            // Insert the new client.
            $query = "INSERT INTO api_clients
                      (client_name, api_key, api_secret, test_api_key, test_api_secret, is_active)
                      VALUES (?, ?, ?, ?, ?, 1)";

            $statement = $this->db->prepare($query);

            $statement->execute([
                $clientName,
                $apiKey,
                $apiSecret,
                $testApiKey,
                $testApiSecret
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

            // Record enterprise audit log
            AuditLogger::logClientAction(
                (int)$clientId,
                AuditLogger::CLIENT_CREATED,
                null,
                ['client_name' => $clientName, 'is_active' => 1],
                "API Client #{$clientId} ({$clientName}) created"
            );


            // Return credentials to the admin.
            return [
                'success' => true,
                'message' => 'API client created successfully.',
                'data' => [
                    'id' => $clientId,
                    'client_name' => $clientName,
                    'api_key' => $apiKey,
                    'api_secret' => $apiSecret,
                    'test_api_key' => $testApiKey,
                    'test_api_secret' => $testApiSecret
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

            AuditLogger::logClientAction(
                $clientId,
                AuditLogger::CLIENT_ACTIVATED,
                ['is_active' => 0],
                ['is_active' => 1],
                "API Client #{$clientId} activated"
            );

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

            AuditLogger::logClientAction(
                $clientId,
                AuditLogger::CLIENT_DEACTIVATED,
                ['is_active' => 1],
                ['is_active' => 0],
                "API Client #{$clientId} deactivated / suspended"
            );

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
            $query = "SELECT id, client_name, api_key, api_secret, test_api_key, test_api_secret, is_active, created_at
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
     * Update Webhook Configuration
     * --------------------------------------------------------
     * Sets the webhook_url for a client.
     * If a URL is provided, a new webhook_secret is auto-generated.
     * If URL is empty, both URL and secret are cleared.
     *
     * @param int    $clientId
     * @param string $webhookUrl  (empty string to clear)
     * @return array
     * --------------------------------------------------------
     */
    public function updateWebhook(int $clientId, string $webhookUrl, ?string $customSecret = null): array
    {
        try {
            if ($clientId <= 0) {
                return ['success' => false, 'message' => 'Invalid client ID.'];
            }

            // Webhooks are now per-event. Delegate to the primary event associated with this client.
            $stmt = $this->db->prepare("
                SELECT event_id FROM events
                WHERE event_id IN (SELECT DISTINCT event_id FROM transactions WHERE api_client_id = ?)
                   OR LOWER(event_name) = (SELECT LOWER(client_name) FROM api_clients WHERE id = ?)
                ORDER BY id ASC LIMIT 1
            ");
            $stmt->execute([$clientId, $clientId]);
            $row = $stmt->fetch();
            if ($row && !empty($row['event_id'])) {
                require_once __DIR__ . '/Event.php';
                $eventModel = new Event();
                return $eventModel->updateWebhook($row['event_id'], $webhookUrl, $customSecret);
            }

            return [
                'success' => false,
                'message' => 'Webhooks are managed per-event. No associated event found for client #' . $clientId
            ];
        } catch (\PDOException $e) {
            Logger::error('Webhook update database error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Database error while updating webhook.'];
        } catch (\Throwable $e) {
            Logger::error('Webhook update unexpected error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unexpected error while updating webhook.'];
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


    /**
     * --------------------------------------------------------
     * Authenticate Merchant Portal User
     * --------------------------------------------------------
     * Supports:
     * 1. API Key + API Secret (Live or Test)
     * 2. Email + Merchant Password
     * --------------------------------------------------------
     */
    public function authenticatePortalUser(string $credential, string $secretOrPassword): ?array
    {
        $credential = trim($credential);
        $secretOrPassword = trim($secretOrPassword);

        if (empty($credential) || empty($secretOrPassword)) {
            return null;
        }

        try {
            // A. Check Live API Key + Live Secret
            $stmt = $this->db->prepare("SELECT * FROM api_clients WHERE api_key = ? AND api_secret = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$credential, $secretOrPassword]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($client) {
                $client['login_mode'] = 'live_key';
                Logger::info("Merchant Portal login via Live API Key: Client #{$client['id']} ({$client['client_name']})");
                return $client;
            }

            // B. Check Test API Key + Test Secret
            $stmt = $this->db->prepare("SELECT * FROM api_clients WHERE test_api_key = ? AND test_api_secret = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$credential, $secretOrPassword]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($client) {
                $client['login_mode'] = 'test_key';
                Logger::info("Merchant Portal login via Test API Key: Client #{$client['id']} ({$client['client_name']})");
                return $client;
            }

            // C. Check Email + Password (or Client Name + Password)
            $stmt = $this->db->prepare("SELECT * FROM api_clients WHERE (email = ? OR client_name = ?) AND is_active = 1 LIMIT 1");
            $stmt->execute([$credential, $credential]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($client && !empty($client['password_hash']) && password_verify($secretOrPassword, $client['password_hash'])) {
                $client['login_mode'] = 'password';
                Logger::info("Merchant Portal login via Password: Client #{$client['id']} ({$client['client_name']})");
                return $client;
            }

            Logger::error("Merchant Portal login failed for credential: " . substr($credential, 0, 10) . "...");
            return null;

        } catch (\Throwable $e) {
            Logger::error("Merchant Portal authentication error: " . $e->getMessage());
            return null;
        }
    }


    /**
     * --------------------------------------------------------
     * Get Client Transactions (Strict Tenant Isolation)
     * --------------------------------------------------------
     */
    public function getClientTransactions(int $clientId, array $filters = []): array
    {
        try {
            $params = [':cid' => $clientId];
            $where = ["t.api_client_id = :cid"];

            // Filter by Status
            if (!empty($filters['status']) && $filters['status'] !== 'ALL') {
                $where[] = "t.status = :status";
                $params[':status'] = strtoupper(trim($filters['status']));
            }

            // Filter by Currency
            if (!empty($filters['currency']) && $filters['currency'] !== 'ALL') {
                $where[] = "t.currency = :currency";
                $params[':currency'] = strtoupper(trim($filters['currency']));
            }

            // Filter by Date Range
            if (!empty($filters['from_date'])) {
                $where[] = "t.created_at >= :from_date";
                $params[':from_date'] = $filters['from_date'] . ' 00:00:00';
            }
            if (!empty($filters['to_date'])) {
                $where[] = "t.created_at <= :to_date";
                $params[':to_date'] = $filters['to_date'] . ' 23:59:59';
            }

            // Filter by Mode (Live vs Test)
            if (isset($filters['is_test_mode']) && $filters['is_test_mode'] !== '') {
                $where[] = "t.is_test_mode = :mode";
                $params[':mode'] = (int)$filters['is_test_mode'];
            }

            // Search query
            if (!empty($filters['search'])) {
                $search = '%' . trim($filters['search']) . '%';
                $where[] = "(t.vortex_transaction_id LIKE :s1 OR t.razorpay_order_id LIKE :s2 OR t.razorpay_payment_id LIKE :s3 OR t.customer_email LIKE :s4 OR t.customer_mobile LIKE :s5)";
                $params[':s1'] = $search;
                $params[':s2'] = $search;
                $params[':s3'] = $search;
                $params[':s4'] = $search;
                $params[':s5'] = $search;
            }

            $whereSql = implode(' AND ', $where);

            // Total matching count
            $countStmt = $this->db->prepare("SELECT COUNT(*) FROM transactions t WHERE {$whereSql}");
            $countStmt->execute($params);
            $totalCount = (int)$countStmt->fetchColumn();

            // Paginated rows
            $limit = max(1, min((int)($filters['limit'] ?? 20), 500));
            $offset = max(0, (int)($filters['offset'] ?? 0));

            $query = "SELECT t.*, e.event_name, e.department 
                      FROM transactions t 
                      LEFT JOIN events e ON t.event_id = e.event_id 
                      WHERE {$whereSql} 
                      ORDER BY t.created_at DESC 
                      LIMIT {$limit} OFFSET {$offset}";

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'total' => $totalCount,
                'limit' => $limit,
                'offset' => $offset,
                'rows' => $rows
            ];

        } catch (\Throwable $e) {
            Logger::error("Failed to fetch client transactions: " . $e->getMessage());
            return ['total' => 0, 'limit' => 20, 'offset' => 0, 'rows' => []];
        }
    }


    /**
     * --------------------------------------------------------
     * Get Single Client Transaction Details (Tenant Isolated)
     * --------------------------------------------------------
     */
    public function getClientTransactionDetails(int $clientId, string $txId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT t.*, e.event_name, e.department, e.start_date AS event_start_date, e.end_date AS event_end_date 
                FROM transactions t 
                LEFT JOIN events e ON t.event_id = e.event_id 
                WHERE t.api_client_id = :cid AND (t.vortex_transaction_id = :tx1 OR t.id = :tx2) 
                LIMIT 1
            ");
            $stmt->execute([':cid' => $clientId, ':tx1' => $txId, ':tx2' => $txId]);
            $txn = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$txn) {
                return null;
            }

            // Fetch Refunds
            $refStmt = $this->db->prepare("SELECT * FROM refunds WHERE transaction_id = :tid ORDER BY id DESC");
            $refStmt->execute([':tid' => $txn['id']]);
            $txn['refunds'] = $refStmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch Timeline
            $timeStmt = $this->db->prepare("SELECT * FROM transaction_timeline WHERE vortex_transaction_id = :vtx ORDER BY created_at ASC");
            $timeStmt->execute([':vtx' => $txn['vortex_transaction_id']]);
            $txn['timeline'] = $timeStmt->fetchAll(PDO::FETCH_ASSOC);

            return $txn;

        } catch (\Throwable $e) {
            Logger::error("Failed to fetch client transaction details: " . $e->getMessage());
            return null;
        }
    }


    /**
     * --------------------------------------------------------
     * Get Client Overview KPI Metrics
     * --------------------------------------------------------
     */
    public function getClientMetrics(int $clientId): array
    {
        try {
            $params = [':cid' => $clientId];

            // Summary aggregates
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) AS total_transactions,
                    SUM(CASE WHEN status = 'SUCCESS' THEN 1 ELSE 0 END) AS success_count,
                    SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) AS failed_count,
                    SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) AS pending_count,
                    SUM(CASE WHEN status = 'REFUNDED' THEN 1 ELSE 0 END) AS refunded_count,
                    COALESCE(SUM(CASE WHEN status = 'SUCCESS' THEN amount ELSE 0 END), 0) AS gross_volume,
                    COALESCE(SUM(CASE WHEN status = 'REFUNDED' THEN amount ELSE 0 END), 0) AS refunded_volume,
                    COALESCE(AVG(CASE WHEN status = 'SUCCESS' THEN amount ELSE NULL END), 0) AS avg_ticket_size
                FROM transactions 
                WHERE api_client_id = :cid
            ");
            $stmt->execute($params);
            $metrics = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $total = (int)($metrics['total_transactions'] ?? 0);
            $success = (int)($metrics['success_count'] ?? 0);
            $metrics['success_rate'] = ($total > 0) ? round(($success / $total) * 100, 1) : 0.0;

            // Last 30 days volume
            $stmt30 = $this->db->prepare("
                SELECT COALESCE(SUM(amount), 0) 
                FROM transactions 
                WHERE api_client_id = :cid AND status = 'SUCCESS' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt30->execute($params);
            $metrics['volume_30d'] = (float)$stmt30->fetchColumn();

            return $metrics;

        } catch (\Throwable $e) {
            Logger::error("Failed to get client metrics: " . $e->getMessage());
            return [
                'total_transactions' => 0,
                'success_count' => 0,
                'failed_count' => 0,
                'pending_count' => 0,
                'refunded_count' => 0,
                'gross_volume' => 0,
                'refunded_volume' => 0,
                'avg_ticket_size' => 0,
                'success_rate' => 0,
                'volume_30d' => 0,
            ];
        }
    }


    /**
     * --------------------------------------------------------
     * Update Client Account Password
     * --------------------------------------------------------
     */
    public function updateClientPassword(int $clientId, string $newPassword): array
    {
        if (strlen($newPassword) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters.'];
        }

        try {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $this->db->prepare("UPDATE api_clients SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $clientId]);

            Logger::info("Merchant #{$clientId} password updated.");

            AuditLogger::logClientAction(
                $clientId,
                AuditLogger::CLIENT_PASSWORD_UPDATED,
                null,
                null,
                "API Client #{$clientId} portal password changed"
            );

            return ['success' => true, 'message' => 'Password updated successfully.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }


    /**
     * --------------------------------------------------------
     * Update Client Profile Details
     * --------------------------------------------------------
     */
    public function updateClientProfile(int $clientId, string $clientName, ?string $email): array
    {
        $clientName = trim($clientName);
        $email = trim((string)$email);

        if (empty($clientName)) {
            return ['success' => false, 'message' => 'Client name is required.'];
        }

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address format.'];
        }

        try {
            $oldClient = $this->getClientById($clientId);

            $stmt = $this->db->prepare("UPDATE api_clients SET client_name = ?, email = ? WHERE id = ?");
            $stmt->execute([$clientName, $email ?: null, $clientId]);

            Logger::info("Merchant #{$clientId} profile updated.");

            AuditLogger::logClientAction(
                $clientId,
                AuditLogger::CLIENT_PROFILE_UPDATED,
                ['client_name' => $oldClient['client_name'] ?? null, 'email' => $oldClient['email'] ?? null],
                ['client_name' => $clientName, 'email' => $email ?: null],
                "API Client #{$clientId} profile updated"
            );

            return ['success' => true, 'message' => 'Profile updated successfully.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * --------------------------------------------------------
     * Rename Client
     * --------------------------------------------------------
     * Quickly renames an API client/merchant while maintaining
     * existing email, keys, and logging audit history.
     * --------------------------------------------------------
     */
    public function renameClient(int $clientId, string $newClientName): array
    {
        $newClientName = trim($newClientName);
        if ($newClientName === '') {
            return ['success' => false, 'message' => 'Merchant / Client name cannot be empty.'];
        }
        if (mb_strlen($newClientName) > 150) {
            return ['success' => false, 'message' => 'Client name is too long (max 150 characters).'];
        }

        $client = $this->getClientById($clientId);
        if (!$client) {
            return ['success' => false, 'message' => 'API Client not found.'];
        }

        return $this->updateClientProfile($clientId, $newClientName, $client['email'] ?? null);
    }

    /**
     * --------------------------------------------------------
     * Onboard Client (Enterprise Wizard)
     * --------------------------------------------------------
     * Comprehensive onboarding creating client profile,
     * live/test API keys, webhook endpoint, and notification flags.
     * --------------------------------------------------------
     */
    public function onboardClient(array $data): array
    {
        try {
            $clientName = trim((string)($data['client_name'] ?? ''));
            if ($clientName === '') {
                return ['success' => false, 'message' => 'Client / Merchant name is required.'];
            }

            $email = trim((string)($data['email'] ?? ''));
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid contact email address format.'];
            }

            $phone = trim((string)($data['phone'] ?? ''));
            if ($phone !== '' && strlen(preg_replace('/[^0-9]/', '', $phone)) < 8) {
                return ['success' => false, 'message' => 'Invalid phone number format.'];
            }

            // Generate Cryptographic Credentials
            $apiKey        = bin2hex(random_bytes(16));
            $apiSecret     = bin2hex(random_bytes(16));
            $testApiKey    = 'test_' . bin2hex(random_bytes(16));
            $testApiSecret = bin2hex(random_bytes(16));
            $isActive      = isset($data['is_active']) ? (int)$data['is_active'] : 1;

            // Notification preferences
            $nep = isset($data['notify_email_payment']) ? (int)$data['notify_email_payment'] : 1;
            $ner = isset($data['notify_email_refund'])  ? (int)$data['notify_email_refund']  : 1;
            $ned = isset($data['notify_email_dispute']) ? (int)$data['notify_email_dispute'] : 1;
            $nsp = isset($data['notify_sms_payment'])   ? (int)$data['notify_sms_payment']   : 0;
            $nsr = isset($data['notify_sms_refund'])    ? (int)$data['notify_sms_refund']    : 1;
            $nsd = isset($data['notify_sms_dispute'])   ? (int)$data['notify_sms_dispute']   : 1;

            $stmt = $this->db->prepare("
                INSERT INTO api_clients (
                    client_name, email, phone,
                    api_key, api_secret, test_api_key, test_api_secret,
                    is_active,
                    notify_email_payment, notify_email_refund, notify_email_dispute,
                    notify_sms_payment, notify_sms_refund, notify_sms_dispute
                ) VALUES (
                    ?, ?, ?,
                    ?, ?, ?, ?,
                    ?,
                    ?, ?, ?,
                    ?, ?, ?
                )
            ");

            $stmt->execute([
                $clientName,
                $email ?: null,
                $phone ?: null,
                $apiKey,
                $apiSecret,
                $testApiKey,
                $testApiSecret,
                $isActive,
                $nep, $ner, $ned,
                $nsp, $nsr, $nsd
            ]);

            $clientId = (int)$this->db->lastInsertId();

            AuditLogger::logClientAction(
                $clientId,
                AuditLogger::CLIENT_CREATED,
                null,
                [
                    'client_name' => $clientName,
                    'email'       => $email,
                    'phone'       => $phone,
                    'is_active'   => $isActive
                ],
                "Merchant '{$clientName}' onboarded via Enterprise Wizard (#{$clientId})"
            );

            return [
                'success' => true,
                'message' => "Merchant '{$clientName}' onboarded successfully.",
                'data'    => [
                    'id'              => $clientId,
                    'client_id'       => $clientId,
                    'client_name'     => $clientName,
                    'email'           => $email,
                    'phone'           => $phone,
                    'api_key'         => $apiKey,
                    'api_secret'      => $apiSecret,
                    'test_api_key'    => $testApiKey,
                    'test_api_secret' => $testApiSecret,
                    'is_active'       => $isActive
                ]
            ];
        } catch (\Throwable $e) {
            Logger::error("Onboarding failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Onboarding failed: ' . $e->getMessage()];
        }
    }

    /**
     * --------------------------------------------------------
     * Test Webhook Endpoint (Live Diagnostic Ping)
     * --------------------------------------------------------
     * Sends a signed test payload to verify endpoint reachability,
     * TLS certificate, signature format, and latency.
     * --------------------------------------------------------
     */
    public static function testWebhookEndpoint(string $url, string $secret, string $event = 'payment.captured'): array
    {
        $url = trim($url);
        $secret = trim($secret);

        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
            return [
                'success'   => false,
                'http_code' => 0,
                'message'   => 'Invalid target webhook URL format. Must start with http:// or https://'
            ];
        }

        if (empty($secret)) {
            return [
                'success'   => false,
                'http_code' => 0,
                'message'   => 'Webhook secret is required for cryptographic HMAC signing.'
            ];
        }

        $timestamp = date('Y-m-d H:i:s');
        $payload = json_encode([
            'event'                 => $event,
            'vortex_transaction_id' => 'VTX_PING_' . strtoupper(bin2hex(random_bytes(4))),
            'status'                => 'SUCCESS',
            'amount'                => 100.00,
            'currency'              => 'INR',
            'is_test'               => true,
            'timestamp'             => $timestamp,
            'message'               => 'PayVortex Webhook Endpoint Diagnostic Ping'
        ], JSON_UNESCAPED_SLASHES);

        $signature = hash_hmac('sha256', $payload, $secret);

        $startTime = microtime(true);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => 'PayVortex-Webhook-Tester/2.0',
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Vortex-Event: ' . $event,
                'X-Vortex-Signature: ' . $signature,
                'X-Vortex-Timestamp: ' . $timestamp,
            ],
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3
        ]);

        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if (in_array($host, ['localhost', '127.0.0.1'], true)) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        $response = curl_exec($ch);
        $endTime = microtime(true);
        $latencyMs = round(($endTime - $startTime) * 1000, 1);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $primaryIp = curl_getinfo($ch, CURLINFO_PRIMARY_IP) ?: 'Unknown';
        curl_close($ch);

        $isSuccess = ($httpCode >= 200 && $httpCode < 300);
        $snippet = is_string($response) ? substr(trim($response), 0, 400) : '';

        return [
            'success'          => $isSuccess,
            'http_code'        => $httpCode,
            'response_time_ms' => $latencyMs,
            'response_body'    => $snippet,
            'primary_ip'       => $primaryIp,
            'curl_error'       => $curlError ?: null,
            'signature_sample' => substr($signature, 0, 12) . '...',
            'message'          => $isSuccess
                ? "Endpoint answered successfully with HTTP {$httpCode} in {$latencyMs}ms."
                : ($httpCode > 0
                    ? "Endpoint returned HTTP {$httpCode} in {$latencyMs}ms."
                    : "Connection failed: " . ($curlError ?: 'Destination unreachable'))
        ];
    }
}

?>