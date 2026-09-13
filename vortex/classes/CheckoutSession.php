<?php

/**
 * ------------------------------------------------------------
 * CheckoutSession.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 *
 * Purpose:
 * Manages 5-minute temporary checkout sessions for customer payment.
 * Decouples developer backend requests from customer browser flows
 * without storing redirect URL permanently in transactions.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Logger.php';

class CheckoutSession
{
    private PDO $db;

    public function __construct()
    {
        date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Kolkata');
        $this->db = Database::getInstance()->getConnection();
        $this->ensureTableExists();
    }

    /**
     * Ensure the checkout_sessions table exists
     */
    private function ensureTableExists(): void
    {
        $query = "CREATE TABLE IF NOT EXISTS checkout_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_token VARCHAR(64) NOT NULL UNIQUE,
            transaction_id INT NOT NULL,
            redirect_url TEXT NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (transaction_id)
                REFERENCES transactions(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        try {
            $this->db->exec($query);
        } catch (\Throwable $e) {
            // Ignore if already created or permission restriction
        }
    }

    /**
     * --------------------------------------------------------
     * Create a 5-minute Checkout Session
     * --------------------------------------------------------
     *
     * @param int    $transactionId   Database transaction ID
     * @param string $redirectUrl    Developer's single redirect URL
     * @param int    $durationMinutes Session validity (default: 5)
     * @return array
     * --------------------------------------------------------
     */
    public function createSession(
        int $transactionId,
        string $redirectUrl,
        int $durationMinutes = 5
    ): array {
        try {
            date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Kolkata');
            $sessionToken = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + ($durationMinutes * 60));

            $query = "INSERT INTO checkout_sessions 
                        (session_token, transaction_id, redirect_url, expires_at)
                      VALUES (?, ?, ?, ?)";

            $statement = $this->db->prepare($query);
            $statement->execute([
                $sessionToken,
                $transactionId,
                $redirectUrl,
                $expiresAt
            ]);

            Logger::info(
                'Checkout session created. Token: ' . substr($sessionToken, 0, 10) . '... '
                . 'Expires at: ' . $expiresAt
            );

            return [
                'success' => true,
                'session_token' => $sessionToken,
                'expires_at' => $expiresAt
            ];

        } catch (\PDOException $e) {
            Logger::error('Checkout session database error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Unable to create checkout session.'
            ];
        } catch (\Throwable $e) {
            Logger::error('Checkout session error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Unexpected error creating checkout session.'
            ];
        }
    }

    /**
     * --------------------------------------------------------
     * Validate & Retrieve Checkout Session
     * --------------------------------------------------------
     *
     * @param string $sessionToken
     * @return array
     * --------------------------------------------------------
     */
    public function getValidSession(string $sessionToken): array
    {
        try {
            date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Kolkata');
            $sessionToken = trim($sessionToken);

            if ($sessionToken === '' || strlen($sessionToken) !== 64) {
                return [
                    'valid' => false,
                    'error_code' => 400,
                    'message' => 'Invalid checkout session token format.'
                ];
            }

            $query = "SELECT cs.id AS session_id,
                             cs.session_token,
                             cs.transaction_id,
                             cs.redirect_url,
                             cs.expires_at,
                             t.vortex_transaction_id,
                             t.event_id,
                             e.event_name,
                             t.amount,
                             t.currency,
                             t.customer_email,
                             t.customer_mobile,
                             t.razorpay_order_id,
                             t.status AS transaction_status,
                             t.created_at AS transaction_created_at
                      FROM checkout_sessions cs
                      INNER JOIN transactions t ON cs.transaction_id = t.id
                      LEFT JOIN events e ON t.event_id = e.event_id
                      WHERE cs.session_token = ?
                      LIMIT 1";

            $statement = $this->db->prepare($query);
            $statement->execute([$sessionToken]);
            $session = $statement->fetch();

            if (!$session) {
                Logger::error('Checkout session not found for token: ' . substr($sessionToken, 0, 10) . '...');
                return [
                    'valid' => false,
                    'error_code' => 404,
                    'message' => 'Checkout session not found.'
                ];
            }

            // Check expiration
            $now = time();
            $expiresTime = strtotime($session['expires_at']);

            if ($now > $expiresTime) {
                Logger::error('Checkout session expired: ' . substr($sessionToken, 0, 10) . '...');
                return [
                    'valid' => false,
                    'error_code' => 410,
                    'message' => 'Checkout session has expired.'
                ];
            }

            return [
                'valid' => true,
                'data' => $session
            ];

        } catch (\PDOException $e) {
            Logger::error('Checkout session lookup database error: ' . $e->getMessage());
            return [
                'valid' => false,
                'error_code' => 500,
                'message' => 'Database error verifying checkout session.'
            ];
        } catch (\Throwable $e) {
            Logger::error('Checkout session lookup error: ' . $e->getMessage());
            return [
                'valid' => false,
                'error_code' => 500,
                'message' => 'Unexpected error verifying checkout session.'
            ];
        }
    }

    /**
     * --------------------------------------------------------
     * Retrieve Payment Status for Checkout Session Recovery
     * --------------------------------------------------------
     *
     * Enables checkout.php to poll and recover transaction status
     * following customer browser network dropouts without requiring
     * merchant API credentials.
     *
     * @param string $sessionToken 64-character hexadecimal token
     * @return array
     * --------------------------------------------------------
     */
    public function getCheckoutPaymentStatus(string $sessionToken): array
    {
        try {
            date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Kolkata');
            $sessionToken = trim($sessionToken);

            if ($sessionToken === '') {
                return [
                    'success' => false,
                    'error_code' => 400,
                    'message' => 'Session token is required.'
                ];
            }

            if (strlen($sessionToken) !== 64 || !ctype_xdigit($sessionToken)) {
                return [
                    'success' => false,
                    'error_code' => 400,
                    'message' => 'Invalid checkout session token format.'
                ];
            }

            $query = "SELECT cs.session_token,
                             cs.redirect_url,
                             cs.expires_at,
                             t.vortex_transaction_id,
                             t.status AS payment_status,
                             t.amount,
                             t.currency
                      FROM checkout_sessions cs
                      INNER JOIN transactions t ON cs.transaction_id = t.id
                      WHERE cs.session_token = ?
                      LIMIT 1";

            $statement = $this->db->prepare($query);
            $statement->execute([$sessionToken]);
            $session = $statement->fetch();

            if (!$session) {
                Logger::error('Checkout payment status lookup failed: Session not found for token: ' . substr($sessionToken, 0, 10) . '...');
                return [
                    'success' => false,
                    'error_code' => 404,
                    'message' => 'Checkout session not found.'
                ];
            }

            // Check session expiration
            $now = time();
            $expiresTime = strtotime($session['expires_at']);

            if ($now > $expiresTime) {
                Logger::error('Checkout payment status lookup failed: Session expired for token: ' . substr($sessionToken, 0, 10) . '...');
                return [
                    'success' => false,
                    'error_code' => 410,
                    'message' => 'Checkout session has expired.'
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'vortex_transaction_id' => $session['vortex_transaction_id'],
                    'payment_status'        => $session['payment_status'],
                    'redirect_url'          => $session['redirect_url'],
                    'amount'                => (float) $session['amount'],
                    'currency'              => $session['currency']
                ]
            ];

        } catch (\PDOException $e) {
            Logger::error('Checkout payment status database error: ' . $e->getMessage());
            return [
                'success' => false,
                'error_code' => 500,
                'message' => 'Database error retrieving checkout payment status.'
            ];
        } catch (\Throwable $e) {
            Logger::error('Checkout payment status unexpected error: ' . $e->getMessage());
            return [
                'success' => false,
                'error_code' => 500,
                'message' => 'Unexpected error retrieving checkout payment status.'
            ];
        }
    }

    /**
     * --------------------------------------------------------
     * Build Payment URL
     * --------------------------------------------------------
     *
     * @param string $sessionToken
     * @return string
     * --------------------------------------------------------
     */
    public static function buildPaymentUrl(string $sessionToken): string
    {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? null) == 443;
        $protocol = $isHttps ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        // Detect project subfolder if applicable
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        $baseDir = rtrim(str_replace('/api', '', $scriptDir), '/\\');

        return $protocol . $host . $baseDir . '/checkout.php?token=' . urlencode($sessionToken);
    }
}
