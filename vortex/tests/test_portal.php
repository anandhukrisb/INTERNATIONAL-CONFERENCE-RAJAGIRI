<?php

/**
 * ------------------------------------------------------------
 * test_portal.php
 * ------------------------------------------------------------
 * Test Suite for the Self-Service Merchant Client Portal
 * Tests:
 * 1. Dual-mode Portal Authentication (API Key/Secret & Email/Password)
 * 2. Strict Multi-Tenant Data Isolation
 * 3. Client Transaction Filtering & Pagination
 * 4. Tenant Overview KPI Metrics Aggregation
 * 5. Self-Service Webhook Management & Secret Rotation
 * 6. Profile & Password Management
 * 7. Unauthenticated Session Route Guards (302 Redirects)
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/TestHelper.php';
require_once __DIR__ . '/../classes/ApiClient.php';

if (empty($_ENV['TEST_BASE_URL']) && empty(getenv('TEST_BASE_URL'))) {
    $_ENV['TEST_BASE_URL'] = 'http://localhost:8080/vortex';
    putenv('TEST_BASE_URL=http://localhost:8080/vortex');
}

function run_portal_tests()
{
    echo "\n=== Running Merchant Portal & Tenant Isolation Tests ===\n";

    $apiClientModel = new ApiClient();
    $db = Database::getInstance();

    // Fetch primary active client for tests (ID 1)
    $client1 = $apiClientModel->getClientById(1);
    TestHelper::assertTrue(
        !empty($client1) && (int)$client1['is_active'] === 1,
        'Merchant Portal: Primary test client (ID 1) exists and is active'
    );

    $c1Key    = $client1['api_key'];
    $c1Secret = $client1['api_secret'];
    $c1TestKey = $client1['test_api_key'];
    $c1TestSecret = $client1['test_api_secret'];

    // ------------------------------------------------------------
    // 1. Dual-Mode Authentication Tests
    // ------------------------------------------------------------
    echo "\n--- 1. Portal Authentication Tests ---\n";

    // A. Login with Live Key + Live Secret
    $authLive = $apiClientModel->authenticatePortalUser($c1Key, $c1Secret);
    TestHelper::assertTrue(
        $authLive !== null && (int)$authLive['id'] === 1 && $authLive['login_mode'] === 'live_key',
        'Portal Auth: Authenticates successfully with Live API Key + Secret'
    );

    // B. Login with Test Key + Test Secret
    $authTest = $apiClientModel->authenticatePortalUser($c1TestKey, $c1TestSecret);
    TestHelper::assertTrue(
        $authTest !== null && (int)$authTest['id'] === 1 && $authTest['login_mode'] === 'test_key',
        'Portal Auth: Authenticates successfully with Test API Key + Secret'
    );

    // C. Rejects invalid credentials
    $authFail = $apiClientModel->authenticatePortalUser($c1Key, 'invalid_secret_xyz');
    TestHelper::assertTrue(
        $authFail === null,
        'Portal Auth: Rejects invalid API Secret'
    );

    $authEmpty = $apiClientModel->authenticatePortalUser('', '');
    TestHelper::assertTrue(
        $authEmpty === null,
        'Portal Auth: Rejects empty credentials'
    );

    // D. Password Authentication
    $testPassword = 'TestMerchantPassword123!';
    $pwdRes = $apiClientModel->updateClientPassword(1, $testPassword);
    TestHelper::assertTrue(
        $pwdRes['success'] === true,
        'Portal Auth: Password successfully updated for merchant'
    );

    // Login with client_name + password
    $authNamePwd = $apiClientModel->authenticatePortalUser($client1['client_name'], $testPassword);
    TestHelper::assertTrue(
        $authNamePwd !== null && (int)$authNamePwd['id'] === 1 && $authNamePwd['login_mode'] === 'password',
        'Portal Auth: Authenticates with Client Name + Password'
    );

    // Login with wrong password
    $authWrongPwd = $apiClientModel->authenticatePortalUser($client1['client_name'], 'wrong_pass');
    TestHelper::assertTrue(
        $authWrongPwd === null,
        'Portal Auth: Rejects incorrect merchant password'
    );

    // ------------------------------------------------------------
    // 2. Strict Multi-Tenant Data Isolation Tests
    // ------------------------------------------------------------
    echo "\n--- 2. Multi-Tenant Data Isolation Tests ---\n";

    // Fetch transactions for client 1
    $txsC1 = $apiClientModel->getClientTransactions(1, ['limit' => 50]);
    TestHelper::assertTrue(
        isset($txsC1['total']) && isset($txsC1['rows']),
        'Tenant Isolation: getClientTransactions returns structured rows and total count'
    );

    // Verify all returned rows belong STRICTLY to client 1
    $allClient1 = true;
    foreach ($txsC1['rows'] as $r) {
        if ((int)$r['api_client_id'] !== 1) {
            $allClient1 = false;
            break;
        }
    }
    TestHelper::assertTrue(
        $allClient1 === true,
        'Tenant Isolation: All returned transactions strictly have api_client_id = 1'
    );

    // Cross-tenant lookup test
    if (!empty($txsC1['rows'])) {
        $firstTx = $txsC1['rows'][0];
        $firstTxId = $firstTx['vortex_transaction_id'];

        // Access with proper tenant ID
        $detailsOwner = $apiClientModel->getClientTransactionDetails(1, $firstTxId);
        TestHelper::assertTrue(
            $detailsOwner !== null && $detailsOwner['vortex_transaction_id'] === $firstTxId,
            'Tenant Isolation: Owner merchant can view own transaction details'
        );

        // Attempt access from another tenant ID (e.g. 9999 or 2)
        $detailsIntruder = $apiClientModel->getClientTransactionDetails(9999, $firstTxId);
        TestHelper::assertTrue(
            $detailsIntruder === null,
            'Tenant Isolation: Unauthorized tenant ID 9999 receives NULL for other tenant tx (Tenant Isolation Enforced)'
        );
    }

    // ------------------------------------------------------------
    // 3. Metrics Aggregation Tests
    // ------------------------------------------------------------
    echo "\n--- 3. Client KPI Metrics Tests ---\n";

    $metrics = $apiClientModel->getClientMetrics(1);
    TestHelper::assertTrue(
        isset($metrics['gross_volume']) &&
        isset($metrics['volume_30d']) &&
        isset($metrics['total_transactions']) &&
        isset($metrics['success_rate']) &&
        isset($metrics['avg_ticket_size']),
        'KPI Metrics: getClientMetrics returns all required dashboard summary fields'
    );

    TestHelper::assertTrue(
        $metrics['success_rate'] >= 0.0 && $metrics['success_rate'] <= 100.0,
        'KPI Metrics: Success rate is a valid percentage between 0 and 100'
    );

    // ------------------------------------------------------------
    // 4. Webhook Self-Service Tests (Per-Event)
    // ------------------------------------------------------------
    echo "\n--- 4. Event Webhook Management Tests ---\n";

    require_once __DIR__ . '/../classes/Event.php';
    $eventModel = new Event();

    // Create or ensure an isolated test event for webhook tests
    $testEvtId = 'EVT_PORTAL_WH_TEST';
    $conn = $db->getConnection();
    $conn->exec("DELETE FROM events WHERE event_id = '{$testEvtId}'");
    $conn->exec("INSERT INTO events (event_id, event_name, department, start_date, end_date, is_active)
                 VALUES ('{$testEvtId}', 'Portal Webhook Test Event', 'Engineering', NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 1)");

    // Set valid webhook URL on test event
    $whRes = $eventModel->updateWebhook($testEvtId, 'https://test-merchant-domain.com/vortex-webhook.php');
    TestHelper::assertTrue(
        $whRes['success'] === true && !empty($whRes['webhook_secret']),
        'Event Webhooks: Successfully sets valid HTTPS webhook URL with generated secret on event'
    );

    // Reject invalid webhook URL
    $whBadRes = $eventModel->updateWebhook($testEvtId, 'not-a-valid-url-format');
    TestHelper::assertTrue(
        $whBadRes['success'] === false,
        'Event Webhooks: Correctly rejects invalid webhook URL format'
    );

    // Rotate webhook secret on test event
    $newCustomSecret = bin2hex(random_bytes(24));
    $whRotRes = $eventModel->updateWebhook($testEvtId, 'https://test-merchant-domain.com/vortex-webhook.php', $newCustomSecret);
    TestHelper::assertTrue(
        $whRotRes['success'] === true && $whRotRes['webhook_secret'] === $newCustomSecret,
        'Event Webhooks: Secret rotation updates cryptographic secret correctly on event'
    );

    // Clear test event webhook
    $whClearRes = $eventModel->updateWebhook($testEvtId, '');
    TestHelper::assertTrue(
        $whClearRes['success'] === true && empty($whClearRes['data']['webhook_url']),
        'Event Webhooks: Clearing webhook URL successfully removes URL and secret'
    );

    // Cleanup isolated test event
    $conn->exec("DELETE FROM events WHERE event_id = '{$testEvtId}'");

    // ------------------------------------------------------------
    // 5. Merchant Profile Updating Tests
    // ------------------------------------------------------------
    echo "\n--- 5. Profile Updating Tests ---\n";

    $profRes = $apiClientModel->updateClientProfile(1, 'Vortex Demo Developer', 'demo.dev@payvortex.io');
    TestHelper::assertTrue(
        $profRes['success'] === true,
        'Profile: Successfully updates merchant name and business email'
    );

    $badEmailRes = $apiClientModel->updateClientProfile(1, 'Vortex Demo Developer', 'not-an-email');
    TestHelper::assertTrue(
        $badEmailRes['success'] === false,
        'Profile: Correctly rejects malformed email address'
    );

    // ------------------------------------------------------------
    // 6. HTTP Portal Session Route Guards (302 Redirects)
    // ------------------------------------------------------------
    echo "\n--- 6. HTTP Route Guard Tests ---\n";

    // Login page should be accessible without session (HTTP 200)
    $loginRes = TestHelper::request('GET', '/portal/login.php');
    TestHelper::assertTrue(
        $loginRes['http_code'] === 200,
        'Route Guard: portal/login.php is publicly accessible (HTTP 200)'
    );

    // Protected pages must redirect (HTTP 302) when unauthenticated
    $protectedRoutes = [
        '/portal/index.php',
        '/portal/dashboard.php',
        '/portal/transactions.php',
        '/portal/export_report.php',
        '/portal/api_keys.php',
        '/portal/webhooks.php',
        '/portal/settings.php',
    ];

    foreach ($protectedRoutes as $route) {
        $res = TestHelper::request('GET', $route);
        TestHelper::assertTrue(
            $res['http_code'] === 302,
            "Route Guard: Unauthenticated access to {$route} redirects (HTTP 302)"
        );
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    run_portal_tests();
    $counts = TestHelper::getCounts();
    echo "\nPortal Tests Finished: Passed: {$counts['passed']}, Failed: {$counts['failed']}\n";
    exit($counts['failed'] === 0 ? 0 : 1);
}
