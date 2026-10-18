<?php
/**
 * ------------------------------------------------------------
 * test_client_onboarding.php
 * ------------------------------------------------------------
 * Automated Test Suite for Client Onboarding Wizard & Webhook
 * Diagnostic Ping Engine.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/TestHelper.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/ApiClient.php';
require_once __DIR__ . '/../classes/NotificationService.php';

if (empty($_ENV['TEST_BASE_URL']) && empty(getenv('TEST_BASE_URL'))) {
    $_ENV['TEST_BASE_URL'] = 'http://localhost:8080/vortex';
    putenv('TEST_BASE_URL=http://localhost:8080/vortex');
}

function run_client_onboarding_tests()
{
    echo "\n=== Running Client Onboarding Wizard & Webhook Tests ===\n";

    $db = Database::getInstance()->getConnection();
    $apiClientModel = new ApiClient();
    $createdTestClientIds = [];

    // ------------------------------------------------------------
    // 1. Wizard Files Existence & Structure
    // ------------------------------------------------------------
    echo "\n--- 1. Wizard Files & Components Structure ---\n";

    $onboardingFile = dirname(__DIR__) . '/admin/api_clients/onboarding.php';
    TestHelper::assertTrue(
        file_exists($onboardingFile),
        "Onboarding 1.1: 'admin/api_clients/onboarding.php' exists"
    );

    $testWebhookAjaxFile = dirname(__DIR__) . '/admin/api_clients/test_webhook_ajax.php';
    TestHelper::assertTrue(
        file_exists($testWebhookAjaxFile),
        "Onboarding 1.2: 'admin/api_clients/test_webhook_ajax.php' exists"
    );

    // ------------------------------------------------------------
    // 2. HTTP Access Security Guards
    // ------------------------------------------------------------
    echo "\n--- 2. HTTP Route Guards (Unauthenticated Requests) ---\n";

    // Unauthenticated GET to onboarding.php should redirect to login
    $resWizard = TestHelper::request('GET', '/admin/api_clients/onboarding.php');
    TestHelper::assertTrue(
        in_array($resWizard['http_code'], [302, 303, 307]),
        "Onboarding 2.1: Unauthenticated access to onboarding.php redirects to login (HTTP " . $resWizard['http_code'] . ")"
    );

    // Unauthenticated POST to test_webhook_ajax.php should return 401
    $resAjax = TestHelper::request('POST', '/admin/api_clients/test_webhook_ajax.php', [
        'webhook_url' => 'https://example.com/webhook'
    ]);
    TestHelper::assertTrue(
        $resAjax['http_code'] === 401,
        "Onboarding 2.2: Unauthenticated POST to test_webhook_ajax.php returns HTTP 401"
    );
    TestHelper::assertTrue(
        ($resAjax['json']['success'] ?? null) === false,
        "Onboarding 2.3: Ajax response has success=false"
    );

    // ------------------------------------------------------------
    // 3. ApiClient::onboardClient() Validation & Creation
    // ------------------------------------------------------------
    echo "\n--- 3. ApiClient::onboardClient() Functional Tests ---\n";

    // 3.1 Empty client name
    $resEmpty = $apiClientModel->onboardClient(['client_name' => '   ']);
    TestHelper::assertTrue(
        $resEmpty['success'] === false,
        "Onboarding 3.1: Rejects onboarding with empty client name"
    );

    // 3.2 Invalid email format
    $resBadEmail = $apiClientModel->onboardClient([
        'client_name' => 'Test Corp',
        'email'       => 'not-an-email'
    ]);
    TestHelper::assertTrue(
        $resBadEmail['success'] === false,
        "Onboarding 3.2: Rejects onboarding with invalid email format"
    );

    // 3.3 Invalid phone number
    $resBadPhone = $apiClientModel->onboardClient([
        'client_name' => 'Test Corp',
        'email'       => 'test@corp.com',
        'phone'       => '123'
    ]);
    TestHelper::assertTrue(
        $resBadPhone['success'] === false,
        "Onboarding 3.3: Rejects onboarding with invalid phone format"
    );

    // 3.4 Valid Onboarding with Full Profile
    $testClientName = 'PayVortex Test Merchant ' . bin2hex(random_bytes(3));
    $resValid = $apiClientModel->onboardClient([
        'client_name'          => $testClientName,
        'email'                => 'merchant_' . bin2hex(random_bytes(3)) . '@example.com',
        'phone'                => '+91 9876543210',
        'is_active'            => 1,
        'notify_email_payment' => 1,
        'notify_email_refund'  => 1,
        'notify_email_dispute' => 1,
        'notify_sms_payment'   => 0,
        'notify_sms_refund'    => 1,
        'notify_sms_dispute'   => 1,
    ]);

    TestHelper::assertTrue(
        $resValid['success'] === true,
        "Onboarding 3.4: Onboarding succeeds with valid full profile"
    );

    $createdData = $resValid['data'] ?? [];
    $newClientId = (int)($createdData['id'] ?? 0);
    TestHelper::assertTrue($newClientId > 0, "Onboarding 3.5: Generated client ID > 0 (#{$newClientId})");

    if ($newClientId > 0) {
        $createdTestClientIds[] = $newClientId;
    }

    // Check generated Live and Test Keys
    TestHelper::assertTrue(
        !empty($createdData['api_key']) && strlen($createdData['api_key']) === 32,
        "Onboarding 3.6: Live API Key is 32-character hex"
    );
    TestHelper::assertTrue(
        !empty($createdData['api_secret']) && strlen($createdData['api_secret']) === 32,
        "Onboarding 3.7: Live API Secret is 32-character hex"
    );
    TestHelper::assertTrue(
        str_starts_with($createdData['test_api_key'] ?? '', 'test_'),
        "Onboarding 3.8: Test API Key starts with 'test_'"
    );
    TestHelper::assertTrue(
        !empty($createdData['test_api_secret']) && strlen($createdData['test_api_secret']) === 32,
        "Onboarding 3.9: Test API Secret is 32-character hex"
    );
    TestHelper::assertTrue(
        !isset($createdData['webhook_url']) && !isset($createdData['webhook_secret']),
        "Onboarding 3.10: Client record created without webhook columns (managed per event)"
    );

    // Verify DB columns
    $stmt = $db->prepare("SELECT * FROM api_clients WHERE id = ?");
    $stmt->execute([$newClientId]);
    $dbRow = $stmt->fetch(PDO::FETCH_ASSOC);

    TestHelper::assertTrue(
        $dbRow && $dbRow['client_name'] === $testClientName,
        "Onboarding 3.11: Merchant row exists in api_clients table with correct name"
    );
    TestHelper::assertTrue(
        (int)$dbRow['notify_email_payment'] === 1 && (int)$dbRow['notify_sms_payment'] === 0,
        "Onboarding 3.12: Notification preferences saved accurately in database"
    );

    // ------------------------------------------------------------
    // 4. ApiClient::testWebhookEndpoint() Diagnostic Engine
    // ------------------------------------------------------------
    echo "\n--- 4. ApiClient::testWebhookEndpoint() Diagnostic Tests ---\n";

    // 4.1 Invalid URL
    $diagBadUrl = ApiClient::testWebhookEndpoint('invalid_url', 'secret123');
    TestHelper::assertTrue(
        $diagBadUrl['success'] === false,
        "Onboarding 4.1: testWebhookEndpoint fails on invalid URL"
    );

    // 4.2 Empty Secret
    $diagNoSecret = ApiClient::testWebhookEndpoint('https://example.com/webhook', '');
    TestHelper::assertTrue(
        $diagNoSecret['success'] === false,
        "Onboarding 4.2: testWebhookEndpoint fails on missing secret"
    );

    // 4.3 Test against local health check endpoint (live reachable server)
    $localHealthUrl = rtrim($_ENV['TEST_BASE_URL'], '/') . '/api/health_check.php';
    $localPing = ApiClient::testWebhookEndpoint($localHealthUrl, 'test_diagnostic_secret_123');

    TestHelper::assertTrue(
        $localPing['http_code'] === 200,
        "Onboarding 4.3: Diagnostic ping to local health endpoint returned HTTP 200"
    );
    TestHelper::assertTrue(
        $localPing['success'] === true,
        "Onboarding 4.4: Diagnostic ping flagged as success"
    );
    TestHelper::assertTrue(
        $localPing['response_time_ms'] > 0,
        "Onboarding 4.5: Latency recorded (" . $localPing['response_time_ms'] . "ms)"
    );
    TestHelper::assertTrue(
        !empty($localPing['signature_sample']),
        "Onboarding 4.6: HMAC signature sample generated"
    );

    // ------------------------------------------------------------
    // 5. Welcome Email Notification Integration
    // ------------------------------------------------------------
    echo "\n--- 5. NotificationService::sendWelcomeEmail() ---\n";

    $welcomeRes = NotificationService::sendWelcomeEmail([
        'id'              => $newClientId,
        'client_name'     => $testClientName,
        'email'           => 'merchant_welcome_test@example.com',
        'api_key'         => $createdData['api_key'],
        'api_secret'      => $createdData['api_secret'],
        'test_api_key'    => $createdData['test_api_key'],
        'test_api_secret' => $createdData['test_api_secret'],
    ]);

    TestHelper::assertTrue(
        $welcomeRes['success'] === true,
        "Onboarding 5.1: sendWelcomeEmail executed successfully"
    );

    // ------------------------------------------------------------
    // 6. Cleanup Created Test Merchants
    // ------------------------------------------------------------
    echo "\n--- 6. Cleanup ---\n";
    foreach ($createdTestClientIds as $cid) {
        $db->prepare("DELETE FROM api_clients WHERE id = ?")->execute([$cid]);
        $db->prepare("DELETE FROM audit_logs WHERE entity_type = 'api_client' AND entity_id = ?")->execute([(string)$cid]);
    }
    TestHelper::assertTrue(true, "Onboarding 6.1: Cleaned up test merchants");

    echo "\n=== Client Onboarding & Webhook Tests Completed ===\n";
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "Running standalone Client Onboarding test suite...\n";
    run_client_onboarding_tests();
    $counts = TestHelper::getCounts();
    echo "\nPassed: {$counts['passed']}, Failed: {$counts['failed']}\n";
    if ($counts['failed'] > 0) {
        exit(1);
    }
}
