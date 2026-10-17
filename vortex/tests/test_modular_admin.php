<?php
/**
 * ------------------------------------------------------------
 * test_modular_admin.php
 * ------------------------------------------------------------
 * Automated Test Suite for Modular Admin Dashboard & Pages
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/TestHelper.php';

if (empty($_ENV['TEST_BASE_URL']) && empty(getenv('TEST_BASE_URL'))) {
    $_ENV['TEST_BASE_URL'] = 'http://localhost:8080/vortex';
    putenv('TEST_BASE_URL=http://localhost:8080/vortex');
}

function run_modular_admin_tests()
{
    echo "\n=== Running Modular Admin Dashboard Tests ===\n";

    $adminDir = dirname(__DIR__) . '/admin';

    // ------------------------------------------------------------
    // 1. Component Files & Modular Pages Existence
    // ------------------------------------------------------------
    echo "\n--- 1. Components & Modular Pages File Structure ---\n";

    $requiredComponents = [
        'includes/metrics_cards.php',
        'includes/transactions_table.php',
        'includes/webhook_modal.php',
        'includes/client_credentials_alert.php',
        'includes/header.php',
        'includes/footer.php',
        'includes/refund_modal.php'
    ];

    foreach ($requiredComponents as $comp) {
        $path = $adminDir . '/' . $comp;
        TestHelper::assertTrue(
            file_exists($path),
            "Admin Modular 1.1: Component file '{$comp}' exists"
        );
    }

    $requiredPages = [
        'dashboard.php',
        'events/index.php',
        'api_clients/index.php',
        'payments/list_payments.php',
        'payments/payment_details.php',
        'audit_logs.php',
        'profile.php'
    ];

    foreach ($requiredPages as $page) {
        $path = $adminDir . '/' . $page;
        TestHelper::assertTrue(
            file_exists($path),
            "Admin Modular 1.2: Dedicated admin page '{$page}' exists"
        );
    }

    // Verify dashboard.php line count was drastically reduced from 949 lines
    $dashboardLines = count(file($adminDir . '/dashboard.php'));
    TestHelper::assertTrue(
        $dashboardLines < 350,
        "Admin Modular 1.3: dashboard.php is concise and modular ({$dashboardLines} lines < 350, reduced from 949)"
    );

    // ------------------------------------------------------------
    // 2. HTTP Route Guards (Unauthenticated 302 Redirects)
    // ------------------------------------------------------------
    echo "\n--- 2. HTTP Route Guard Tests (302 Redirects) ---\n";

    $protectedPages = [
        '/admin/dashboard.php',
        '/admin/events/index.php',
        '/admin/api_clients/index.php',
        '/admin/payments/list_payments.php',
        '/admin/audit_logs.php',
        '/admin/profile.php'
    ];

    foreach ($protectedPages as $route) {
        $res = TestHelper::request('GET', $route);
        TestHelper::assertTrue(
            $res['http_code'] === 302,
            "Admin Modular 2.1: Route {$route} requires admin session (HTTP 302 redirect)"
        );
    }

    // ------------------------------------------------------------
    // 3. Header Navigation Links Verification
    // ------------------------------------------------------------
    echo "\n--- 3. Header Navigation Links Structure ---\n";

    $headerContent = file_get_contents($adminDir . '/includes/header.php');
    TestHelper::assertTrue(
        str_contains($headerContent, 'api_clients/index.php'),
        'Admin Modular 3.1: Header navigation contains direct link to api_clients/index.php'
    );
    TestHelper::assertTrue(
        str_contains($headerContent, 'events/index.php'),
        'Admin Modular 3.2: Header navigation contains direct link to events/index.php'
    );
    TestHelper::assertTrue(
        str_contains($headerContent, 'dashboard.php'),
        'Admin Modular 3.3: Header navigation contains direct link to dashboard.php'
    );

    // ------------------------------------------------------------
    // 4. Backwards Compatibility Redirection
    // ------------------------------------------------------------
    echo "\n--- 4. Legacy Tabs & Redirect Compatibility ---\n";

    $dashboardContent = file_get_contents($adminDir . '/dashboard.php');
    TestHelper::assertTrue(
        str_contains($dashboardContent, "tabRedirect === 'clients'") &&
        str_contains($dashboardContent, "api_clients/index.php"),
        'Admin Modular 4.1: dashboard.php gracefully redirects tab=clients to api_clients/index.php'
    );
    TestHelper::assertTrue(
        str_contains($dashboardContent, "tabRedirect === 'events'") &&
        str_contains($dashboardContent, "events/index.php"),
        'Admin Modular 4.2: dashboard.php gracefully redirects tab=events to events/index.php'
    );

    // ------------------------------------------------------------
    // 5. Component Isolated Rendering Sanity Tests
    // ------------------------------------------------------------
    echo "\n--- 5. Component Isolated Rendering Sanity Tests ---\n";

    // 5.1 metrics_cards.php
    ob_start();
    $totalPaymentAmount = 125000.50;
    $successfulPayments = 450;
    $failedPayments     = 12;
    $activeEventsCount  = 5;
    $totalClientsCount  = 8;
    $adminBase          = '';
    include $adminDir . '/includes/metrics_cards.php';
    $renderedMetrics = ob_get_clean();

    TestHelper::assertTrue(
        str_contains($renderedMetrics, '125,000.50') &&
        str_contains($renderedMetrics, 'metric-card') &&
        str_contains($renderedMetrics, 'Total Revenue'),
        'Admin Modular 5.1: metrics_cards.php renders valid HTML with currency and counts'
    );

    // 5.2 transactions_table.php
    ob_start();
    $recentTx = [
        [
            'vortex_transaction_id' => 'VTX_TEST_SAMPLE_01',
            'amount'                => 2500.00,
            'status'                => 'SUCCESS',
            'razorpay_payment_id'   => 'pay_samp_123',
            'event_name'            => 'AI Summit',
            'client_name'           => 'Engineering Faculty',
            'created_at'            => '2026-10-17 12:00:00',
            'customer_email'        => 'user@example.com',
            'timeline_count'        => 3,
            'latest_event'          => 'payment.captured'
        ]
    ];
    $txTotalCount = 1;
    $fromTx = 1;
    $toTx = 1;
    $txPage = 1;
    $txTotalPages = 1;
    $tableTitle = 'Test Transactions Stream';
    include $adminDir . '/includes/transactions_table.php';
    $renderedTable = ob_get_clean();

    TestHelper::assertTrue(
        str_contains($renderedTable, 'VTX_TEST_SAMPLE_01') &&
        str_contains($renderedTable, 'badge-success') &&
        str_contains($renderedTable, 'AI Summit'),
        'Admin Modular 5.2: transactions_table.php renders transactions with badges & customer metadata'
    );

    // 5.3 webhook_modal.php
    ob_start();
    $webhookFormAction = 'index.php';
    include $adminDir . '/includes/webhook_modal.php';
    $renderedModal = ob_get_clean();

    TestHelper::assertTrue(
        str_contains($renderedModal, 'webhookModal') &&
        str_contains($renderedModal, 'openWebhookModal') &&
        str_contains($renderedModal, 'generateRandomWebhookSecret'),
        'Admin Modular 5.3: webhook_modal.php renders modal structure and JS controllers'
    );

    // 5.4 client_credentials_alert.php
    ob_start();
    $createdClientDetails = [
        'client_name' => 'Demo App',
        'api_key'     => 'vtx_live_key_xyz',
        'api_secret'  => 'vtx_live_sec_abc',
        'test_api_key' => 'vtx_test_key_123',
        'test_api_secret' => 'vtx_test_sec_456'
    ];
    include $adminDir . '/includes/client_credentials_alert.php';
    $renderedAlert = ob_get_clean();

    TestHelper::assertTrue(
        str_contains($renderedAlert, 'Demo App') &&
        str_contains($renderedAlert, 'vtx_live_key_xyz') &&
        str_contains($renderedAlert, 'vtx_live_sec_abc'),
        'Admin Modular 5.4: client_credentials_alert.php renders newly created API keys with copy triggers'
    );
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "Running standalone Modular Admin test suite...\n";
    run_modular_admin_tests();
    $counts = TestHelper::getCounts();
    echo "\nPassed: {$counts['passed']}, Failed: {$counts['failed']}\n";
    if ($counts['failed'] > 0) {
        exit(1);
    }
}
