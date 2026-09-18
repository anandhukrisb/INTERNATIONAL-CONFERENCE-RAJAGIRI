<?php

/**
 * ------------------------------------------------------------
 * TestHelper.php
 * ------------------------------------------------------------
 * Shared test framework and helper functions for Vortex automated tests.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load .env from project root
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Kolkata');

require_once __DIR__ . '/../classes/Database.php';

class TestHelper
{
    private static $passed = 0;
    private static $failed = 0;
    private static $skipped = 0;
    private static $failures = [];
    private static $curlErrors = [];

    // Tracked resources for safe cleanup
    private static $trackedClientIds = [];
    private static $trackedEventIds = [];
    private static $trackedTransactionIds = [];

    /**
     * Get Base URL for test requests
     */
    public static function getBaseUrl(): string
    {
        return $_ENV['TEST_BASE_URL'] ?? getenv('TEST_BASE_URL') ?: 'http://localhost:8000';
    }

    /**
     * Confirm Razorpay configuration is Test Mode
     */
    public static function isRazorpayTestMode(): bool
    {
        $keyId = $_ENV['RAZORPAY_KEY_ID'] ?? '';
        return str_starts_with($keyId, 'rzp_test_');
    }

    /**
     * Mask sensitive secrets for secure console logging
     */
    public static function maskSecret(?string $secret, int $visible = 4): string
    {
        if (empty($secret)) {
            return '[EMPTY]';
        }
        $len = strlen($secret);
        if ($len <= $visible) {
            return str_repeat('*', $len);
        }
        return substr($secret, 0, $visible) . str_repeat('*', max(4, $len - $visible));
    }

    /**
     * Sanitize sensitive secrets from text (logs, errors, urls)
     */
    public static function sanitizeSecret(string $text): string
    {
        $secrets = [
            $_ENV['DB_PASS'] ?? '',
            $_ENV['RAZORPAY_KEY_SECRET'] ?? '',
            $_ENV['RAZORPAY_WEBHOOK_SECRET'] ?? '',
        ];

        try {
            $client = self::getPrimaryClient();
            if (!empty($client['api_secret'])) {
                $secrets[] = $client['api_secret'];
            }
        } catch (\Throwable $e) {
            // DB may not be ready
        }

        foreach ($secrets as $secret) {
            if (!empty($secret) && strlen($secret) >= 3) {
                $text = str_replace($secret, '[REDACTED]', $text);
            }
        }

        // Mask tokens in query params (e.g. token=...)
        $text = preg_replace('/(token=)[a-zA-Z0-9_-]+/i', '$1[REDACTED_TOKEN]', $text);
        // Mask 64-character hex strings (session tokens)
        $text = preg_replace('/(?<![a-f0-9])[a-f0-9]{64}(?![a-f0-9])/i', '[REDACTED_TOKEN]', $text);

        return $text;
    }

    /**
     * Get PDO Database Connection
     */
    public static function getDb(): PDO
    {
        return Database::getInstance()->getConnection();
    }

    /**
     * Fetch default active API client credentials for testing
     */
    public static function getPrimaryClient(): ?array
    {
        $stmt = self::getDb()->query("SELECT id, client_name, api_key, api_secret, is_active FROM api_clients WHERE is_active = 1 LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Fetch default active Event for testing
     */
    public static function getPrimaryEvent(): ?array
    {
        $stmt = self::getDb()->query("SELECT id, event_id, event_name, department, start_date, end_date FROM events WHERE is_active = 1 LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Send HTTP request to Vortex Gateway using cURL
     */
    public static function request(string $method, string $path, $data = null, array $headers = []): array
    {
        $url = rtrim(self::getBaseUrl(), '/') . '/' . ltrim($path, '/');
        
        $hasContentType = false;
        foreach ($headers as $hdr) {
            if (stripos($hdr, 'Content-Type:') !== false) {
                $hasContentType = true;
                break;
            }
        }

        if (!$hasContentType && $data !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        $content = null;
        if ($data !== null) {
            $content = is_array($data) ? json_encode($data) : (string) $data;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

        if ($content !== null || strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $content ?? '');
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);

        if ($httpCode === 0 || $curlErrno !== 0) {
            $safeMsg = self::sanitizeSecret("cURL error ($curlErrno): $curlError connecting to $url");
            self::$curlErrors[] = $safeMsg;
            echo "  [cURL ERROR] $safeMsg\n";
        }

        $raw = '';
        $responseHeaders = [];

        if ($response !== false) {
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $headerStr = substr($response, 0, $headerSize);
            $raw = substr($response, $headerSize);

            $headerLines = explode("\r\n", trim($headerStr));
            foreach ($headerLines as $line) {
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    $responseHeaders[] = $trimmed;
                }
            }
        }

        // curl_close is a no-op since PHP 8.0 and deprecated in PHP 8.5+

        return [
            'http_code' => $httpCode,
            'headers'   => $responseHeaders,
            'raw'       => $raw,
            'json'      => json_decode($raw, true)
        ];
    }

    /**
     * Resource tracking for isolated cleanup
     */
    public static function trackClientId(int $id): void
    {
        self::$trackedClientIds[] = $id;
    }

    public static function trackEventId(string $id): void
    {
        self::$trackedEventIds[] = $id;
    }

    public static function trackTransactionId(int $id): void
    {
        self::$trackedTransactionIds[] = $id;
    }

    /**
     * Clean up only records created by the current test execution
     */
    public static function cleanup(): void
    {
        $db = self::getDb();

        if (!empty(self::$trackedTransactionIds)) {
            $ids = implode(',', array_map('intval', self::$trackedTransactionIds));
            $db->exec("DELETE FROM transactions WHERE id IN ($ids)");
            self::$trackedTransactionIds = [];
        }

        if (!empty(self::$trackedClientIds)) {
            $ids = implode(',', array_map('intval', self::$trackedClientIds));
            $db->exec("DELETE FROM api_clients WHERE id IN ($ids)");
            self::$trackedClientIds = [];
        }

        if (!empty(self::$trackedEventIds)) {
            $quoted = implode(',', array_map([$db, 'quote'], self::$trackedEventIds));
            $db->exec("DELETE FROM events WHERE event_id IN ($quoted)");
            self::$trackedEventIds = [];
        }
    }

    // --------------------------------------------------------
    // Assertion Methods
    // --------------------------------------------------------

    public static function assertStatus(int $expected, int $actual, string $testName): bool
    {
        if ($expected === $actual) {
            self::pass($testName);
            return true;
        }
        self::fail($testName, "Expected HTTP status $expected, got $actual");
        return false;
    }

    public static function assertJsonStatus(string $expected, ?array $json, string $testName): bool
    {
        $status = $json['status'] ?? null;
        if ($status === $expected) {
            self::pass($testName);
            return true;
        }
        self::fail($testName, "Expected JSON status '$expected', got " . var_export($status, true));
        return false;
    }

    public static function assertTrue(bool $condition, string $testName, string $failureDetails = ''): bool
    {
        if ($condition) {
            self::pass($testName);
            return true;
        }
        self::fail($testName, $failureDetails ?: "Condition asserted as true was false");
        return false;
    }

    public static function assertFalse(bool $condition, string $testName, string $failureDetails = ''): bool
    {
        if (!$condition) {
            self::pass($testName);
            return true;
        }
        self::fail($testName, $failureDetails ?: "Condition asserted as false was true");
        return false;
    }

    public static function assertFieldEquals($expected, $actual, string $fieldName, string $testName): bool
    {
        if ($expected === $actual) {
            self::pass($testName);
            return true;
        }
        self::fail($testName, "Field '$fieldName' expected " . json_encode($expected) . ", got " . json_encode($actual));
        return false;
    }

    public static function assertFieldExists(string $field, ?array $array, string $testName): bool
    {
        if (is_array($array) && array_key_exists($field, $array) && $array[$field] !== null) {
            self::pass($testName);
            return true;
        }
        self::fail($testName, "Field '$field' missing or null in response");
        return false;
    }

    public static function assertFieldNotExists(string $field, ?array $array, string $testName): bool
    {
        if (!is_array($array) || !array_key_exists($field, $array)) {
            self::pass($testName);
            return true;
        }
        self::fail($testName, "Field '$field' should not exist in response, but was found");
        return false;
    }

    public static function pass(string $testName): void
    {
        self::$passed++;
        echo "  [PASS] $testName\n";
    }

    public static function fail(string $testName, string $reason): void
    {
        self::$failed++;
        self::$failures[] = [
            'test'   => $testName,
            'reason' => $reason
        ];
        echo "  [FAIL] $testName: $reason\n";
    }

    public static function skip(string $testName, string $reason): void
    {
        self::$skipped++;
        echo "  [SKIP] $testName: $reason\n";
    }

    public static function printSuiteHeader(string $title): void
    {
        echo "\n----------------------------------------\n";
        echo "SUITE: $title\n";
        echo "----------------------------------------\n";
    }

    public static function getCounts(): array
    {
        return [
            'passed'   => self::$passed,
            'failed'   => self::$failed,
            'skipped'  => self::$skipped,
            'failures' => self::$failures
        ];
    }

    public static function resetCounts(): void
    {
        self::$passed = 0;
        self::$failed = 0;
        self::$skipped = 0;
        self::$failures = [];
        self::$curlErrors = [];
    }

    public static function getCurlErrors(): array
    {
        return self::$curlErrors;
    }
}
