<?php

/**
 * ------------------------------------------------------------
 * test_webhook_queue.php
 * ------------------------------------------------------------
 * Automated test suite for Outbound Webhook Retry Queue
 * with Exponential Backoff (3m, 9m, 27m).
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/TestHelper.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/WebhookQueueService.php';

function run_webhook_queue_tests(): bool
{
    TestHelper::printSuiteHeader('20. Outbound Webhook Retry Queue (Exponential Backoff)');
    $db = Database::getInstance()->getConnection();

    // --------------------------------------------------------
    // 1. Schema & Migration Verification
    // --------------------------------------------------------
    echo "\n--- 1. Schema & Table Integrity ---\n";

    $tableCheck = $db->query("SHOW TABLES LIKE 'outbound_webhook_queue'")->fetchAll();
    TestHelper::assertTrue(!empty($tableCheck), "Queue 1.1: Table 'outbound_webhook_queue' exists");

    $cols = $db->query("DESCRIBE outbound_webhook_queue")->fetchAll(PDO::FETCH_COLUMN);
    $requiredCols = [
        'id', 'vortex_transaction_id', 'api_client_id', 'webhook_url',
        'event_type', 'payload', 'signature', 'attempts', 'max_attempts',
        'status', 'last_attempt_at', 'next_attempt_at', 'last_http_code',
        'last_error', 'delivered_at', 'created_at', 'updated_at'
    ];

    foreach ($requiredCols as $col) {
        TestHelper::assertTrue(in_array($col, $cols, true), "Queue 1.2: Column '{$col}' exists in outbound_webhook_queue");
    }

    // --------------------------------------------------------
    // 2. Setup Test Merchant
    // --------------------------------------------------------
    echo "
--- " . '2. Test Merchant Setup' . " ---
";

    // Create a client with a reachable local endpoint for success tests
    $db->prepare("
        INSERT INTO api_clients (
            client_name, email, api_key, api_secret,
            webhook_url, webhook_secret, is_active
        ) VALUES (
            'Webhook Queue Test Merchant', 'queue_test@rajagiri.edu',
            'key_queue_test_123', 'secret_queue_test_123',
            'http://localhost:8080/vortex/api/health_check.php',
            'whsec_queue_test_secret_999', 1
        ) ON DUPLICATE KEY UPDATE
            webhook_url = 'http://localhost:8080/vortex/api/health_check.php',
            webhook_secret = 'whsec_queue_test_secret_999',
            is_active = 1
    ")->execute();

    $clientId = (int) $db->query("SELECT id FROM api_clients WHERE api_key = 'key_queue_test_123'")->fetchColumn();
    TestHelper::assertTrue($clientId > 0, "Queue 2.1: Test merchant exists (ID #{$clientId})");

    $queueService = new WebhookQueueService($db);

    // --------------------------------------------------------
    // 3. Immediate Delivery (HTTP 200 -> DELIVERED)
    // --------------------------------------------------------
    echo "
--- " . '3. Immediate Delivery Success' . " ---
";

    $txnIdSuccess = 'VTX_QUEUE_TEST_SUCC_' . time();
    $resultSuccess = $queueService->dispatchOrEnqueue(
        $txnIdSuccess,
        'SUCCESS',
        $clientId,
        'payment.success',
        ['amount' => 500.00]
    );

    TestHelper::assertTrue(!empty($resultSuccess['delivered']), "Queue 3.1: Immediate delivery succeeded");
    TestHelper::assertTrue(!empty($resultSuccess['queue_id']), "Queue 3.2: Queue record created (#" . ($resultSuccess['queue_id'] ?? 0) . ")");
    TestHelper::assertTrue(($resultSuccess['attempts'] ?? 0) == (1), "Queue 3.3: Initial attempt counter is 1");

    // Check DB row
    $stmtRow = $db->prepare("SELECT * FROM outbound_webhook_queue WHERE id = ?");
    $stmtRow->execute([$resultSuccess['queue_id']]);
    $dbRow = $stmtRow->fetch(PDO::FETCH_ASSOC);

    TestHelper::assertTrue(($dbRow['status']) == ('DELIVERED'), "Queue 3.4: DB status is DELIVERED");
    TestHelper::assertTrue(!empty($dbRow['delivered_at']), "Queue 3.5: delivered_at timestamp is populated");
    TestHelper::assertTrue(((int)$dbRow['last_http_code']) == (200), "Queue 3.6: HTTP code is 200");

    // --------------------------------------------------------
    // 4. Initial Failure & Retry 1 Scheduling (3 Minutes)
    // --------------------------------------------------------
    echo "
--- " . '4. Failed Delivery & Exponential Backoff (Retry 1: +3 min)' . " ---
";

    // Update client to unreachable local port to guarantee failure
    $db->prepare("UPDATE api_clients SET webhook_url = 'http://localhost:59999/unreachable_webhook' WHERE id = ?")->execute([$clientId]);

    $txnIdFail = 'VTX_QUEUE_TEST_FAIL_' . time();
    $resultFail = $queueService->dispatchOrEnqueue(
        $txnIdFail,
        'SUCCESS',
        $clientId,
        'payment.success',
        ['amount' => 1200.00]
    );

    TestHelper::assertTrue(empty($resultFail['delivered']), "Queue 4.1: Delivery correctly reported failure");
    TestHelper::assertTrue(!empty($resultFail['queued_for_retry']), "Queue 4.2: Queued for retry flag is true");
    TestHelper::assertTrue(($resultFail['retry_delay_min'] ?? 0) == (3), "Queue 4.3: 1st retry delay is exactly 3 minutes");

    $failedQueueId = (int) $resultFail['queue_id'];
    $stmtFailedRow = $db->prepare("
        SELECT *, TIMESTAMPDIFF(MINUTE, last_attempt_at, next_attempt_at) as diff_min
        FROM outbound_webhook_queue
        WHERE id = ?
    ");
    $stmtFailedRow->execute([$failedQueueId]);
    $failRow = $stmtFailedRow->fetch(PDO::FETCH_ASSOC);

    TestHelper::assertTrue(($failRow['status']) == ('PENDING'), "Queue 4.4: Queue status is PENDING");
    TestHelper::assertTrue(((int)$failRow['attempts']) == (1), "Queue 4.5: Attempt count is 1");
    TestHelper::assertTrue(((int)$failRow['diff_min']) == (3), "Queue 4.6: next_attempt_at is scheduled 3 minutes after last_attempt_at");
    TestHelper::assertTrue(!empty($failRow['last_error']), "Queue 4.7: last_error captures failure description");

    // --------------------------------------------------------
    // 5. Retry 1 Fails -> Retry 2 Scheduled (9 Minutes)
    // --------------------------------------------------------
    echo "
--- " . '5. Exponential Backoff Retry 2 (+9 min)' . " ---
";

    // Simulate clock advancing 4 minutes into future
    $simulatedTime1 = date('Y-m-d H:i:s', strtotime('+4 minutes'));
    $ret1 = $queueService->processPendingRetries(10, $simulatedTime1);

    TestHelper::assertTrue($ret1['scanned'] >= 1, "Queue 5.1: Worker scanned due pending item");
    TestHelper::assertTrue($ret1['rescheduled'] >= 1, "Queue 5.2: Item was rescheduled");

    $stmtFailedRow->execute([$failedQueueId]);
    $rowAfterRetry1 = $stmtFailedRow->fetch(PDO::FETCH_ASSOC);

    TestHelper::assertTrue(((int)$rowAfterRetry1['attempts']) == (2), "Queue 5.3: Attempt counter incremented to 2");
    TestHelper::assertTrue(($rowAfterRetry1['status']) == ('PENDING'), "Queue 5.4: Status remains PENDING for 2nd retry");
    TestHelper::assertTrue(((int)$rowAfterRetry1['diff_min']) == (9), "Queue 5.5: next_attempt_at scheduled 9 minutes after attempt 2");

    // --------------------------------------------------------
    // 6. Retry 2 Fails -> Retry 3 Scheduled (27 Minutes)
    // --------------------------------------------------------
    echo "
--- " . '6. Exponential Backoff Retry 3 (+27 min)' . " ---
";

    // Simulate clock advancing +10 minutes after Retry 1
    $simulatedTime2 = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    $ret2 = $queueService->processPendingRetries(10, $simulatedTime2);

    TestHelper::assertTrue($ret2['scanned'] >= 1, "Queue 6.1: Worker scanned due item for attempt 3");

    $stmtFailedRow->execute([$failedQueueId]);
    $rowAfterRetry2 = $stmtFailedRow->fetch(PDO::FETCH_ASSOC);

    TestHelper::assertTrue(((int)$rowAfterRetry2['attempts']) == (3), "Queue 6.2: Attempt counter incremented to 3");
    TestHelper::assertTrue(($rowAfterRetry2['status']) == ('PENDING'), "Queue 6.3: Status remains PENDING for 3rd retry");
    TestHelper::assertTrue(((int)$rowAfterRetry2['diff_min']) == (27), "Queue 6.4: next_attempt_at scheduled 27 minutes after attempt 3");

    // --------------------------------------------------------
    // 7. Retry 3 Fails -> Max Attempts Reached -> PERMANENTLY_FAILED
    // --------------------------------------------------------
    echo "
--- " . '7. Max Attempts Exhaustion (PERMANENTLY_FAILED)' . " ---
";

    // Simulate clock advancing +28 minutes after Retry 2
    $simulatedTime3 = date('Y-m-d H:i:s', strtotime('+45 minutes'));
    $ret3 = $queueService->processPendingRetries(10, $simulatedTime3);

    TestHelper::assertTrue($ret3['permanently_failed'] >= 1, "Queue 7.1: Item transitioned to permanently failed");

    $stmtFailedRow->execute([$failedQueueId]);
    $rowFinal = $stmtFailedRow->fetch(PDO::FETCH_ASSOC);

    TestHelper::assertTrue((int)$rowFinal['attempts'] === 4, "Queue 7.2: Final attempts reached 4 (1 initial + 3 retries)");
    TestHelper::assertTrue(($rowFinal['status']) == ('PERMANENTLY_FAILED'), "Queue 7.3: Status is PERMANENTLY_FAILED");

    // Verify Notification Center received alert
    $stmtNotif = $db->prepare("
        SELECT id, type, severity, title
        FROM admin_notifications
        WHERE reference_id = ?
        ORDER BY id DESC LIMIT 1
    ");
    $stmtNotif->execute([$txnIdFail]);
    $notifAlert = $stmtNotif->fetch(PDO::FETCH_ASSOC);

    TestHelper::assertTrue(!empty($notifAlert), "Queue 7.4: Notification Center alert was triggered");
    TestHelper::assertTrue(($notifAlert['type'] ?? '') == ('failed_webhook'), "Queue 7.5: Alert type is 'failed_webhook'");
    TestHelper::assertTrue(($notifAlert['severity'] ?? '') == ('critical'), "Queue 7.6: Permanent failure severity is 'critical'");

    // --------------------------------------------------------
    // 8. Manual Retry
    // --------------------------------------------------------
    echo "
--- " . '8. Manual Retry Functionality' . " ---
";

    // Fix the merchant endpoint so manual retry succeeds
    $db->prepare("UPDATE api_clients SET webhook_url = 'http://localhost:8080/vortex/api/health_check.php' WHERE id = ?")->execute([$clientId]);
    $db->prepare("UPDATE outbound_webhook_queue SET webhook_url = 'http://localhost:8080/vortex/api/health_check.php' WHERE id = ?")->execute([$failedQueueId]);

    $manualRes = $queueService->retryManually($failedQueueId);

    TestHelper::assertTrue(!empty($manualRes['delivered']), "Queue 8.1: Manual retry succeeded");

    $stmtFailedRow->execute([$failedQueueId]);
    $manualRow = $stmtFailedRow->fetch(PDO::FETCH_ASSOC);
    TestHelper::assertTrue(($manualRow['status']) == ('DELIVERED'), "Queue 8.2: Status updated to DELIVERED after manual retry");
    TestHelper::assertTrue(((int)$manualRow['attempts']) == (5), "Queue 8.3: Attempt counter updated to 5");

    // --------------------------------------------------------
    // 9. Queue Statistics & Reporting
    // --------------------------------------------------------
    echo "
--- " . '9. Queue Statistics & Inspection' . " ---
";

    $stats = $queueService->getStats($clientId);
    TestHelper::assertTrue($stats['total'] >= 2, "Queue 9.1: Total items tracked in stats ({$stats['total']})");
    TestHelper::assertTrue($stats['delivered'] >= 2, "Queue 9.2: Delivered count reflects successes ({$stats['delivered']})");

    $items = $queueService->getQueueItems(10, $clientId);
    TestHelper::assertTrue(count($items) >= 2, "Queue 9.3: getQueueItems returns merchant records");

    // --------------------------------------------------------
    // 10. CLI Worker Smoke Test
    // --------------------------------------------------------
    echo "
--- " . '10. CLI Worker Execution' . " ---
";

    $cliOutput = shell_exec('php ' . escapeshellarg(dirname(__DIR__) . '/tools/process_webhook_queue.php') . ' --limit=5');
    TestHelper::assertTrue(strpos($cliOutput, 'VORTEX OUTBOUND WEBHOOK RETRY WORKER') !== false, "Queue 10.1: CLI worker executed and printed header");
    TestHelper::assertTrue(strpos($cliOutput, 'Scanned Due Items') !== false, "Queue 10.2: CLI worker reported scan metrics");

    // --------------------------------------------------------
    // 11. Cleanup Test Records
    // --------------------------------------------------------
    echo "
--- " . '11. Cleanup' . " ---
";
    $db->prepare("DELETE FROM outbound_webhook_queue WHERE api_client_id = ?")->execute([$clientId]);
    $db->prepare("DELETE FROM admin_notifications WHERE reference_id IN (?, ?)")->execute([$txnIdSuccess, $txnIdFail]);
    $db->prepare("DELETE FROM api_clients WHERE id = ?")->execute([$clientId]);
    TestHelper::assertTrue(true, "Queue 11.1: Cleaned up test records");

    echo "\n✓ Outbound Webhook Retry Queue with Exponential Backoff verification complete!\n";
    return true;
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    run_webhook_queue_tests();
}
