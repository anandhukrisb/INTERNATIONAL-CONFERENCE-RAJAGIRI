<?php
/**
 * ------------------------------------------------------------
 * test_audit_logger.php
 * ------------------------------------------------------------
 * Automated Test Suite for Enterprise Audit Trail & Governance
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/TestHelper.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/AuditLogger.php';
require_once __DIR__ . '/../classes/ApiClient.php';

if (empty($_ENV['TEST_BASE_URL']) && empty(getenv('TEST_BASE_URL'))) {
    $_ENV['TEST_BASE_URL'] = 'http://localhost:8080/vortex';
    putenv('TEST_BASE_URL=http://localhost:8080/vortex');
}

function run_audit_logger_tests()
{
    echo "\n=== Running Enterprise Audit Trail Tests ===\n";

    $db = Database::getInstance()->getConnection();

    // ------------------------------------------------------------
    // 1. Database Schema & Table Structure
    // ------------------------------------------------------------
    echo "\n--- 1. Schema & Table Structure Tests ---\n";

    $tblStmt = $db->query("SHOW TABLES LIKE 'audit_logs'");
    TestHelper::assertTrue(
        $tblStmt->fetch() !== false,
        'Audit Schema 1.1: Table audit_logs exists in database'
    );

    $colStmt = $db->query("SHOW COLUMNS FROM audit_logs");
    $columns = $colStmt->fetchAll(PDO::FETCH_COLUMN);

    $requiredCols = [
        'id', 'entity_type', 'entity_id', 'action',
        'actor_type', 'actor_id', 'actor_username', 'actor_role',
        'ip_address', 'user_agent', 'old_values', 'new_values',
        'description', 'metadata', 'created_at'
    ];

    foreach ($requiredCols as $col) {
        TestHelper::assertTrue(
            in_array($col, $columns, true),
            "Audit Schema 1.2: Column '{$col}' exists in audit_logs"
        );
    }

    // ------------------------------------------------------------
    // 2. Logging Client Operations with Actor Attribution
    // ------------------------------------------------------------
    echo "\n--- 2. Client Action Logging Tests ---\n";

    $testClientId = 999901;
    $logSuccess = AuditLogger::log(
        'api_client',
        (string)$testClientId,
        AuditLogger::CLIENT_ACTIVATED,
        ['is_active' => 0],
        ['is_active' => 1],
        "API Client #{$testClientId} activated for testing",
        ['test_run' => true],
        [
            'actor_type'     => 'admin',
            'actor_id'       => 1,
            'actor_username' => 'test_super_admin',
            'actor_role'     => 'super_admin'
        ]
    );

    TestHelper::assertTrue(
        $logSuccess === true,
        'Audit Logging 2.1: AuditLogger::log returns true on successful insert'
    );

    // Retrieve logged entry
    $logs = AuditLogger::getClientLogs($testClientId, 5);
    TestHelper::assertTrue(
        !empty($logs),
        'Audit Logging 2.2: getClientLogs retrieves logged event'
    );

    $entry = $logs[0];
    TestHelper::assertTrue(
        $entry['action'] === AuditLogger::CLIENT_ACTIVATED &&
        $entry['actor_username'] === 'test_super_admin' &&
        $entry['actor_role'] === 'super_admin',
        'Audit Logging 2.3: Actor attribution preserved accurately'
    );

    $oldVal = json_decode($entry['old_values'], true);
    $newVal = json_decode($entry['new_values'], true);
    TestHelper::assertTrue(
        isset($oldVal['is_active']) && $oldVal['is_active'] === 0 &&
        isset($newVal['is_active']) && $newVal['is_active'] === 1,
        'Audit Logging 2.4: State diff JSON correctly serialized and parsed'
    );

    // ------------------------------------------------------------
    // 3. Secret Redaction / Sensitive Data Masking in Diffs
    // ------------------------------------------------------------
    echo "\n--- 3. Secret Redaction Tests ---\n";

    AuditLogger::log(
        'api_client',
        (string)$testClientId,
        AuditLogger::CLIENT_WEBHOOK_UPDATED,
        ['webhook_secret' => 'super_secret_plain_text_12345'],
        ['webhook_secret' => 'new_super_secret_plain_text_67890'],
        "Secret rotation test",
        null,
        ['actor_type' => 'admin', 'actor_id' => 1, 'actor_username' => 'admin', 'actor_role' => 'super_admin']
    );

    $latestLog = AuditLogger::getClientLogs($testClientId, 1)[0];
    $cleanOld = json_decode($latestLog['old_values'], true);
    $cleanNew = json_decode($latestLog['new_values'], true);

    TestHelper::assertTrue(
        $cleanOld['webhook_secret'] === '[REDACTED]' &&
        $cleanNew['webhook_secret'] === '[REDACTED]',
        'Audit Security 3.1: Cryptographic secrets automatically redacted from audit trail'
    );

    // ------------------------------------------------------------
    // 4. Querying, Search Filtering, and Pagination
    // ------------------------------------------------------------
    echo "\n--- 4. Query & Filter Tests ---\n";

    $countTotal = AuditLogger::countLogs();
    TestHelper::assertTrue(
        $countTotal > 0,
        'Audit Query 4.1: countLogs returns positive integer'
    );

    $clientActionCount = AuditLogger::countLogs(['entity_type' => 'api_client']);
    TestHelper::assertTrue(
        $clientActionCount > 0,
        'Audit Query 4.2: Filter by entity_type counts matching records'
    );

    $filteredRecent = AuditLogger::getRecentLogs(['entity_type' => 'api_client'], 10, 0);
    $allApiClient = true;
    foreach ($filteredRecent as $r) {
        if ($r['entity_type'] !== 'api_client') {
            $allApiClient = false;
            break;
        }
    }
    TestHelper::assertTrue(
        $allApiClient === true,
        'Audit Query 4.3: getRecentLogs respects entity_type filter'
    );

    // Search filter
    $searchLogs = AuditLogger::getRecentLogs(['search' => 'test_super_admin'], 10, 0);
    TestHelper::assertTrue(
        !empty($searchLogs),
        'Audit Query 4.4: Search keyword filter matches actor username'
    );

    // ------------------------------------------------------------
    // 5. Action Badge Helper Test
    // ------------------------------------------------------------
    echo "\n--- 5. Action Badge Helper Tests ---\n";

    $badgeHtml = AuditLogger::getActionBadge(AuditLogger::CLIENT_ACTIVATED);
    TestHelper::assertTrue(
        str_contains($badgeHtml, 'CLIENT.ACTIVATED') && str_contains($badgeHtml, '<span'),
        'Badge Helper 5.1: getActionBadge renders valid HTML badge'
    );

    // ------------------------------------------------------------
    // 6. HTTP Route Guard Tests on Admin Audit Trail Page
    // ------------------------------------------------------------
    echo "\n--- 6. HTTP Route Guard Tests ---\n";

    $routeRes = TestHelper::request('GET', '/admin/audit_logs.php');
    TestHelper::assertTrue(
        $routeRes['http_code'] === 302,
        'Route Guard 6.1: Unauthenticated request to /admin/audit_logs.php redirects (HTTP 302)'
    );

    // Cleanup synthetic test records
    $db->prepare("DELETE FROM audit_logs WHERE entity_id = ?")->execute([(string)$testClientId]);
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    run_audit_logger_tests();
    $counts = TestHelper::getCounts();
    echo "\nAudit Logger Tests Finished: Passed: {$counts['passed']}, Failed: {$counts['failed']}\n";
    exit($counts['failed'] === 0 ? 0 : 1);
}
