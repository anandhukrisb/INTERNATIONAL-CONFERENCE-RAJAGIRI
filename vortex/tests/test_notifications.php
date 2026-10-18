<?php
/**
 * ------------------------------------------------------------
 * test_notifications.php
 * ------------------------------------------------------------
 * Automated Test Suite for Real-Time Client Notifications
 * (Email via Google Accounts SMTP and Multi-Driver SMS)
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/TestHelper.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/NotificationService.php';
require_once __DIR__ . '/../classes/ApiClient.php';

if (empty($_ENV['TEST_BASE_URL']) && empty(getenv('TEST_BASE_URL'))) {
    $_ENV['TEST_BASE_URL'] = 'http://localhost:8080/vortex';
    putenv('TEST_BASE_URL=http://localhost:8080/vortex');
}

function run_notifications_tests()
{
    echo "\n=== Running Client Notifications & Alerts Tests ===\n";

    $db = Database::getInstance()->getConnection();

    // ------------------------------------------------------------
    // 1. Database Schema & Columns Verification
    // ------------------------------------------------------------
    echo "\n--- 1. Schema & Table Structure Tests ---\n";

    // 1.1 notification_logs table exists
    $stmt = $db->query("SHOW TABLES LIKE 'notification_logs'");
    TestHelper::assertTrue(
        $stmt->fetch() !== false,
        'Notif Schema 1.1: Table notification_logs exists in database'
    );

    // 1.2 Check notification_logs columns
    $colStmt = $db->query("SHOW COLUMNS FROM notification_logs");
    $columns = $colStmt->fetchAll(PDO::FETCH_COLUMN);
    $requiredCols = ['id', 'client_id', 'recipient', 'channel', 'event_type', 'subject', 'content', 'status', 'error_message', 'created_at'];
    foreach ($requiredCols as $c) {
        TestHelper::assertTrue(
            in_array($c, $columns, true),
            "Notif Schema 1.2: Column '{$c}' exists in notification_logs"
        );
    }

    // 1.3 Check api_clients notification columns
    $clientColStmt = $db->query("SHOW COLUMNS FROM api_clients");
    $clientCols = $clientColStmt->fetchAll(PDO::FETCH_COLUMN);
    $notifPrefs = [
        'phone',
        'notify_email_payment',
        'notify_email_refund',
        'notify_email_dispute',
        'notify_sms_payment',
        'notify_sms_refund',
        'notify_sms_dispute'
    ];
    foreach ($notifPrefs as $pref) {
        TestHelper::assertTrue(
            in_array($pref, $clientCols, true),
            "Notif Schema 1.3: Column '{$pref}' exists in api_clients"
        );
    }

    // ------------------------------------------------------------
    // 2. Client Notification Preference Management
    // ------------------------------------------------------------
    echo "\n--- 2. Preference Updates & Validation Tests ---\n";

    // Create a dedicated test client
    $uniqueSuffix = substr(md5(uniqid('notif_client_', true)), 0, 8);
    $testClientName = 'Notif Test Corp ' . $uniqueSuffix;
    $testClientEmail = "alerts_{$uniqueSuffix}@payvortextest.io";
    $testClientPhone = '+919876543210';

    $insStmt = $db->prepare("
        INSERT INTO api_clients (
            client_name, email, phone, api_key, api_secret,
            notify_email_payment, notify_email_refund, notify_email_dispute,
            notify_sms_payment, notify_sms_refund, notify_sms_dispute, is_active
        ) VALUES (
            ?, ?, ?, ?, ?,
            1, 1, 1,
            0, 1, 1, 1
        )
    ");
    $testApiKey = 'test_key_' . $uniqueSuffix;
    $testApiSecret = 'test_sec_' . $uniqueSuffix;
    $insStmt->execute([
        $testClientName, $testClientEmail, $testClientPhone,
        $testApiKey, $testApiSecret
    ]);
    $testClientId = (int)$db->lastInsertId();

    TestHelper::assertTrue($testClientId > 0, 'Notif Prefs 2.1: Created test client #' . $testClientId);

    // 2.2 Update settings: toggle flags and change phone
    $newPhone = '+919988776655';
    $updateRes = NotificationService::updateClientNotificationSettings($testClientId, [
        'email'                => $testClientEmail,
        'phone'                => $newPhone,
        'notify_email_payment' => 1,
        'notify_email_refund'  => 0, // disabled
        'notify_email_dispute' => 1,
        'notify_sms_payment'   => 1, // enabled
        'notify_sms_refund'    => 0, // disabled
        'notify_sms_dispute'   => 1,
    ]);
    TestHelper::assertTrue($updateRes['success'], 'Notif Prefs 2.2: Preferences saved via NotificationService');

    // Verify stored values in DB
    $checkStmt = $db->prepare("SELECT * FROM api_clients WHERE id = ?");
    $checkStmt->execute([$testClientId]);
    $updatedClient = $checkStmt->fetch(PDO::FETCH_ASSOC);

    TestHelper::assertFieldEquals($newPhone, $updatedClient['phone'], 'phone', 'Notif Prefs 2.3: Stored phone correctly updated');
    TestHelper::assertFieldEquals(0, (int)$updatedClient['notify_email_refund'], 'notify_email_refund', 'Notif Prefs 2.4: notify_email_refund updated to 0');
    TestHelper::assertFieldEquals(1, (int)$updatedClient['notify_sms_payment'], 'notify_sms_payment', 'Notif Prefs 2.5: notify_sms_payment updated to 1');

    // 2.3 Reject invalid email
    $invalidRes = NotificationService::updateClientNotificationSettings($testClientId, [
        'email' => 'not-an-email-address',
        'phone' => $newPhone
    ]);
    TestHelper::assertFalse($invalidRes['success'], 'Notif Prefs 2.6: Rejects malformed email address');

    // ------------------------------------------------------------
    // 3. Real-Time Alert Event Dispatches
    // ------------------------------------------------------------
    echo "\n--- 3. Event Alert Dispatches & Preference Enforcement ---\n";

    // 3.1 Payment Success Event: notifyPaymentSuccess
    $mockTx = [
        'id'                    => 88801,
        'vortex_transaction_id' => 'VTX_NOTIF_TEST_01',
        'amount'                => 1500.00,
        'currency'              => 'INR',
        'api_client_id'         => $testClientId,
        'razorpay_order_id'     => 'order_mock_notif_1',
        'razorpay_payment_id'   => 'pay_mock_notif_1',
        'customer_name'         => 'John Doe',
        'customer_email'        => 'john@example.com',
        'event_name'            => 'Annual Tech Conference',
        'created_at'            => date('Y-m-d H:i:s')
    ];

    $payRes = NotificationService::notifyPaymentSuccess($mockTx);
    TestHelper::assertTrue($payRes['success'], 'Notif Dispatch 3.1: notifyPaymentSuccess executed');
    TestHelper::assertTrue(!empty($payRes['dispatched']['email']), 'Notif Dispatch 3.2: Email alert triggered (flag=1)');
    TestHelper::assertTrue(!empty($payRes['dispatched']['sms']), 'Notif Dispatch 3.3: SMS alert triggered (flag=1)');

    // 3.2 Verify log entry recorded in notification_logs
    $logStmt = $db->prepare("
        SELECT * FROM notification_logs 
        WHERE client_id = ? AND event_type = ? 
        ORDER BY id DESC LIMIT 2
    ");
    $logStmt->execute([$testClientId, NotificationService::EVENT_PAYMENT_SUCCESS]);
    $loggedAlerts = $logStmt->fetchAll(PDO::FETCH_ASSOC);

    TestHelper::assertTrue(count($loggedAlerts) >= 1, 'Notif Dispatch 3.4: Payment success logged in notification_logs');

    // 3.3 Refund Processed Event: notify_email_refund is 0, notify_sms_refund is 0
    $refundRes = NotificationService::notifyRefundProcessed($mockTx, [
        'refund_id' => 'rfnd_mock_123',
        'amount'    => 500.00,
        'reason'    => 'Customer cancellation'
    ]);
    TestHelper::assertTrue($refundRes['success'], 'Notif Dispatch 3.5: notifyRefundProcessed executed');
    TestHelper::assertTrue($refundRes['dispatched']['email'] === null, 'Notif Dispatch 3.6: Email alert skipped when preference flag=0');
    TestHelper::assertTrue($refundRes['dispatched']['sms'] === null, 'Notif Dispatch 3.7: SMS alert skipped when preference flag=0');

    // 3.4 Payment Failure / Dispute Event: notifyFailureOrDispute
    $dispRes = NotificationService::notifyFailureOrDispute($mockTx, 'Bank suspected fraud', NotificationService::EVENT_PAYMENT_DISPUTED);
    TestHelper::assertTrue($dispRes['success'], 'Notif Dispatch 3.8: notifyFailureOrDispute executed');
    TestHelper::assertTrue(!empty($dispRes['dispatched']['email']), 'Notif Dispatch 3.9: Dispute email alert triggered');

    // ------------------------------------------------------------
    // 4. Test Alert Pipeline & Log Query API
    // ------------------------------------------------------------
    echo "\n--- 4. Test Alert Pipeline & Logs Retrieval ---\n";

    $testAlertRes = NotificationService::sendTestNotification($testClientId);
    TestHelper::assertTrue($testAlertRes['success'], 'Notif Pipeline 4.1: sendTestNotification dispatched test alert');

    $logs = NotificationService::getClientNotificationLogs($testClientId, 10);
    TestHelper::assertTrue(count($logs) >= 2, 'Notif Pipeline 4.2: getClientNotificationLogs retrieved dispatched logs');
    TestHelper::assertFieldEquals($testClientId, (int)$logs[0]['client_id'], 'client_id', 'Notif Pipeline 4.3: Log client_id matches target');

    // ------------------------------------------------------------
    // 5. Cleanup Test Records
    // ------------------------------------------------------------
    echo "\n--- 5. Cleanup Test Records ---\n";
    $db->prepare("DELETE FROM notification_logs WHERE client_id = ?")->execute([$testClientId]);
    $db->prepare("DELETE FROM api_clients WHERE id = ?")->execute([$testClientId]);
    echo "✓ Test client and logs cleaned up successfully.\n";
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "Running standalone Notification Service test suite...\n";
    run_notifications_tests();
    $counts = TestHelper::getCounts();
    echo "\nPassed: {$counts['passed']}, Failed: {$counts['failed']}\n";
    if ($counts['failed'] > 0) {
        exit(1);
    }
}
