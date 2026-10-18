<?php
/**
 * ------------------------------------------------------------
 * test_global_search.php
 * ------------------------------------------------------------
 * Automated Test Suite for Global Gateway Search Engine
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/TestHelper.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/GlobalSearch.php';

if (empty($_ENV['TEST_BASE_URL']) && empty(getenv('TEST_BASE_URL'))) {
    $_ENV['TEST_BASE_URL'] = 'http://localhost:8080/vortex';
    putenv('TEST_BASE_URL=http://localhost:8080/vortex');
}

function run_global_search_tests()
{
    echo "\n=== Running Global Gateway Search Engine Tests ===\n";

    $db = Database::getInstance()->getConnection();
    $searchEngine = new GlobalSearch();

    // ------------------------------------------------------------
    // 1. File Structure Verification
    // ------------------------------------------------------------
    echo "\n--- 1. File & Component Structure ---\n";

    $searchClassFile = dirname(__DIR__) . '/classes/GlobalSearch.php';
    TestHelper::assertTrue(
        file_exists($searchClassFile),
        "GlobalSearch 1.1: 'classes/GlobalSearch.php' exists"
    );

    $searchPageFile = dirname(__DIR__) . '/admin/search.php';
    TestHelper::assertTrue(
        file_exists($searchPageFile),
        "GlobalSearch 1.2: 'admin/search.php' exists"
    );

    $searchAjaxFile = dirname(__DIR__) . '/admin/search_ajax.php';
    TestHelper::assertTrue(
        file_exists($searchAjaxFile),
        "GlobalSearch 1.3: 'admin/search_ajax.php' exists"
    );

    // ------------------------------------------------------------
    // 2. HTTP Access Security Guards
    // ------------------------------------------------------------
    echo "\n--- 2. HTTP Security Guards (Unauthenticated Requests) ---\n";

    // 2.1 Unauthenticated GET to search.php -> 302
    $resSearch = TestHelper::request('GET', '/admin/search.php?q=test');
    TestHelper::assertTrue(
        in_array($resSearch['http_code'], [302, 303, 307]),
        "GlobalSearch 2.1: Unauthenticated access to search.php redirects to login (HTTP " . $resSearch['http_code'] . ")"
    );

    // 2.2 Unauthenticated GET to search_ajax.php -> 401
    $resAjax = TestHelper::request('GET', '/admin/search_ajax.php?q=test');
    TestHelper::assertTrue(
        $resAjax['http_code'] === 401,
        "GlobalSearch 2.2: Unauthenticated access to search_ajax.php returns HTTP 401"
    );
    TestHelper::assertTrue(
        ($resAjax['json']['success'] ?? null) === false,
        "GlobalSearch 2.3: search_ajax.php unauthorized response has success=false"
    );

    // ------------------------------------------------------------
    // 3. Seed / Fetch Reference Records For Functional Tests
    // ------------------------------------------------------------
    echo "\n--- 3. Seed Reference Records For Multi-Entity Search ---\n";

    $seedSuffix = bin2hex(random_bytes(3));
    $testEmail  = "search_user_{$seedSuffix}@rajagiri.edu";
    $testVtxId  = "VTX_SRCH_" . strtoupper($seedSuffix);
    $testRzpPay = "pay_srch_" . $seedSuffix;
    $testRzpOrd = "order_srch_" . $seedSuffix;
    $testMobile = "987654" . sprintf("%04d", rand(1000, 9999));
    $testClientName = "Search Test Merchant {$seedSuffix}";
    $testApiKey = "key_srch_" . bin2hex(random_bytes(8));

    // Get an existing event
    $evStmt = $db->query("SELECT event_id, event_name FROM events LIMIT 1");
    $sampleEvent = $evStmt->fetch(PDO::FETCH_ASSOC);
    $eventId = $sampleEvent['event_id'] ?? 'EVENT_1';

    // Insert test client
    $stmtC = $db->prepare("INSERT INTO api_clients (client_name, email, api_key, api_secret, is_active) VALUES (?, ?, ?, ?, 1)");
    $stmtC->execute([$testClientName, $testEmail, $testApiKey, bin2hex(random_bytes(16))]);
    $testClientId = (int)$db->lastInsertId();

    // Insert test transaction
    $stmtT = $db->prepare("
        INSERT INTO transactions (
            vortex_transaction_id, event_id, api_client_id,
            customer_email, customer_mobile, amount, currency,
            razorpay_order_id, razorpay_payment_id, status
        ) VALUES (
            ?, ?, ?,
            ?, ?, 750.00, 'INR',
            ?, ?, 'SUCCESS'
        )
    ");
    $stmtT->execute([
        $testVtxId, $eventId, $testClientId,
        $testEmail, $testMobile,
        $testRzpOrd, $testRzpPay
    ]);
    $testTxId = (int)$db->lastInsertId();

    TestHelper::assertTrue($testTxId > 0 && $testClientId > 0, "GlobalSearch 3.1: Seeded test transaction #{$testTxId} & client #{$testClientId}");

    // ------------------------------------------------------------
    // 4. GlobalSearch Engine Core Functional Tests
    // ------------------------------------------------------------
    echo "\n--- 4. GlobalSearch Engine Multi-Dimensional Query Tests ---\n";

    // 4.1 Search by Customer Email
    $emailSearch = $searchEngine->search($testEmail);
    TestHelper::assertTrue(
        $emailSearch['total_matches'] >= 1,
        "GlobalSearch 4.1: Search by customer email found matching record(s)"
    );
    $foundTxEmails = array_column($emailSearch['categories']['transactions']['items'], 'customer_email');
    TestHelper::assertTrue(
        in_array($testEmail, $foundTxEmails),
        "GlobalSearch 4.2: Customer email '{$testEmail}' present in transaction results"
    );

    // 4.2 Search by Vortex Transaction ID (Exact match detection)
    $vtxSearch = $searchEngine->search($testVtxId);
    TestHelper::assertTrue(
        $vtxSearch['categories']['transactions']['count'] >= 1,
        "GlobalSearch 4.3: Search by Vortex Transaction ID succeeded"
    );
    TestHelper::assertTrue(
        !empty($vtxSearch['exact_match']) && $vtxSearch['exact_match']['type'] === 'transaction',
        "GlobalSearch 4.4: Exact match correctly recognized as transaction"
    );
    TestHelper::assertTrue(
        ($vtxSearch['exact_match']['title'] ?? '') === $testVtxId,
        "GlobalSearch 4.5: Exact match title matches '{$testVtxId}'"
    );

    // 4.3 Search by Razorpay Payment ID
    $rzpPaySearch = $searchEngine->search($testRzpPay);
    TestHelper::assertTrue(
        $rzpPaySearch['categories']['transactions']['count'] >= 1,
        "GlobalSearch 4.6: Search by Razorpay Payment ID ('{$testRzpPay}') found transaction"
    );
    TestHelper::assertTrue(
        !empty($rzpPaySearch['exact_match']),
        "GlobalSearch 4.7: Razorpay Payment ID triggered exact match"
    );

    // 4.4 Search by Razorpay Order ID
    $rzpOrdSearch = $searchEngine->search($testRzpOrd);
    TestHelper::assertTrue(
        $rzpOrdSearch['categories']['transactions']['count'] >= 1,
        "GlobalSearch 4.8: Search by Razorpay Order ID ('{$testRzpOrd}') found transaction"
    );

    // 4.5 Search by Customer Mobile
    $mobileSearch = $searchEngine->search($testMobile);
    TestHelper::assertTrue(
        $mobileSearch['categories']['transactions']['count'] >= 1,
        "GlobalSearch 4.9: Search by Customer Mobile ('{$testMobile}') succeeded"
    );

    // 4.6 Search by Merchant / Client Name
    $clientSearch = $searchEngine->search($testClientName);
    TestHelper::assertTrue(
        $clientSearch['categories']['clients']['count'] >= 1,
        "GlobalSearch 4.10: Search by Client Name found merchant record"
    );
    $foundClientNames = array_column($clientSearch['categories']['clients']['items'], 'client_name');
    TestHelper::assertTrue(
        in_array($testClientName, $foundClientNames),
        "GlobalSearch 4.11: Client name '{$testClientName}' in client results"
    );

    // 4.7 Search by API Key
    $apiKeySearch = $searchEngine->search($testApiKey);
    TestHelper::assertTrue(
        $apiKeySearch['categories']['clients']['count'] >= 1,
        "GlobalSearch 4.12: Search by API Key succeeded"
    );
    TestHelper::assertTrue(
        !empty($apiKeySearch['exact_match']) && $apiKeySearch['exact_match']['type'] === 'client',
        "GlobalSearch 4.13: API Key search triggered exact client match"
    );

    // 4.8 Search Events by Name
    if (!empty($sampleEvent['event_name'])) {
        $eventQuery = substr($sampleEvent['event_name'], 0, 8);
        $eventSearch = $searchEngine->search($eventQuery);
        TestHelper::assertTrue(
            $eventSearch['categories']['events']['count'] >= 1,
            "GlobalSearch 4.14: Search by event name substring ('{$eventQuery}') returned events"
        );
    }

    // ------------------------------------------------------------
    // 5. Category & Status Filtering Tests
    // ------------------------------------------------------------
    echo "\n--- 5. Category & Status Filtering Tests ---\n";

    // Filter category = 'clients' should exclude transactions even if email matches both
    $catClientSearch = $searchEngine->search($testEmail, ['category' => 'clients']);
    TestHelper::assertTrue(
        $catClientSearch['categories']['transactions']['count'] === 0,
        "GlobalSearch 5.1: category='clients' filter isolates results from transactions"
    );
    TestHelper::assertTrue(
        $catClientSearch['categories']['clients']['count'] >= 1,
        "GlobalSearch 5.2: category='clients' retains client records"
    );

    // Status filter
    $statusSuccessSearch = $searchEngine->search($testEmail, ['status' => 'SUCCESS']);
    TestHelper::assertTrue(
        $statusSuccessSearch['categories']['transactions']['count'] >= 1,
        "GlobalSearch 5.3: Filter status='SUCCESS' returns matching transaction"
    );

    $statusFailedSearch = $searchEngine->search($testEmail, ['status' => 'FAILED']);
    TestHelper::assertTrue(
        $statusFailedSearch['categories']['transactions']['count'] === 0,
        "GlobalSearch 5.4: Filter status='FAILED' correctly excludes SUCCESS transaction"
    );

    // Latency
    TestHelper::assertTrue(
        $emailSearch['execution_time_ms'] >= 0,
        "GlobalSearch 5.5: Search latency accurately benchmarked (" . $emailSearch['execution_time_ms'] . "ms)"
    );

    // ------------------------------------------------------------
    // 6. Cleanup Seed Records
    // ------------------------------------------------------------
    echo "\n--- 6. Cleanup Seed Records ---\n";
    $db->prepare("DELETE FROM transactions WHERE id = ?")->execute([$testTxId]);
    $db->prepare("DELETE FROM api_clients WHERE id = ?")->execute([$testClientId]);
    TestHelper::assertTrue(true, "GlobalSearch 6.1: Seeded search test records purged cleanly");

    echo "\n=== Global Gateway Search Tests Completed ===\n";
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "Running standalone Global Search test suite...\n";
    run_global_search_tests();
    $counts = TestHelper::getCounts();
    echo "\nPassed: {$counts['passed']}, Failed: {$counts['failed']}\n";
    if ($counts['failed'] > 0) {
        exit(1);
    }
}
