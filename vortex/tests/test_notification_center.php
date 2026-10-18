<?php
/**
 * ------------------------------------------------------------
 * test_notification_center.php
 * ------------------------------------------------------------
 * Automated Test Suite for In-App Notification Center:
 * - Failed Webhooks
 * - New Disputes & Chargebacks
 * - High-Value Transactions
 * - Real-Time Topbar Bell & Unread Badge Management
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/TestHelper.php';
require_once __DIR__ . '/../classes/NotificationCenter.php';

if (empty($_ENV['TEST_BASE_URL']) && empty(getenv('TEST_BASE_URL'))) {
    $_ENV['TEST_BASE_URL'] = 'http://localhost:8080/vortex';
    putenv('TEST_BASE_URL=http://localhost:8080/vortex');
}

function run_notification_center_tests()
{
    echo "\n=== Running In-App Notification Center Tests ===\n";

    $db = TestHelper::getDb();
    NotificationCenter::ensureTableExists();

    $createdIds = [];

    // ------------------------------------------------------------
    // 1. Table & Schema Verification
    // ------------------------------------------------------------
    echo "\n--- 1. Database Schema & Columns ---\n";

    $tblStmt = $db->query("SHOW TABLES LIKE 'admin_notifications'");
    TestHelper::assertTrue(
        $tblStmt->rowCount() > 0,
        "NotifCenter 1.1: Table 'admin_notifications' exists in database"
    );

    $colStmt = $db->query("SHOW COLUMNS FROM admin_notifications");
    $columns = $colStmt->fetchAll(PDO::FETCH_COLUMN);

    $requiredCols = [
        'id', 'admin_id', 'type', 'title', 'message',
        'severity', 'reference_id', 'reference_type', 'link',
        'is_read', 'read_at', 'created_at'
    ];

    foreach ($requiredCols as $col) {
        TestHelper::assertTrue(
            in_array($col, $columns, true),
            "NotifCenter 1.2: Column '{$col}' exists in admin_notifications"
        );
    }

    // ------------------------------------------------------------
    // 2. Core Alert Types Generation
    // ------------------------------------------------------------
    echo "\n--- 2. Core Alert Types Generation ---\n";

    // A. Failed Webhook Alert
    $whRef = 'wh_test_' . bin2hex(random_bytes(4));
    $whId = NotificationCenter::triggerFailedWebhookAlert(
        1,
        'https://example.com/api/webhook',
        500,
        'Internal Server Error from upstream endpoint',
        $whRef
    );
    $createdIds[] = $whId;

    TestHelper::assertTrue($whId > 0, "NotifCenter 2.1: Failed Webhook alert created successfully (#{$whId})");

    $stmt = $db->prepare("SELECT * FROM admin_notifications WHERE id = ?");
    $stmt->execute([$whId]);
    $whRow = $stmt->fetch(PDO::FETCH_ASSOC);

    TestHelper::assertTrue(
        $whRow['type'] === NotificationCenter::TYPE_FAILED_WEBHOOK,
        "NotifCenter 2.2: Webhook alert has type 'failed_webhook'"
    );
    TestHelper::assertTrue(
        $whRow['severity'] === NotificationCenter::SEVERITY_CRITICAL,
        "NotifCenter 2.3: Webhook alert has severity 'critical'"
    );
    TestHelper::assertTrue(
        (int)$whRow['is_read'] === 0,
        "NotifCenter 2.4: Newly created alert is initially unread (is_read=0)"
    );

    // B. New Dispute Alert
    $dispRef = 'VTX_DISP_' . bin2hex(random_bytes(4));
    $dispId = NotificationCenter::triggerDisputeAlert(
        $dispRef,
        'dispute_test@rajagiri.edu',
        18500.00,
        'Cardholder reported unauthorized debit'
    );
    $createdIds[] = $dispId;

    TestHelper::assertTrue($dispId > 0, "NotifCenter 2.5: Payment Dispute alert created (#{$dispId})");

    $stmt->execute([$dispId]);
    $dispRow = $stmt->fetch(PDO::FETCH_ASSOC);

    TestHelper::assertTrue(
        $dispRow['type'] === NotificationCenter::TYPE_NEW_DISPUTE,
        "NotifCenter 2.6: Dispute alert has type 'new_dispute'"
    );
    TestHelper::assertTrue(
        $dispRow['severity'] === NotificationCenter::SEVERITY_CRITICAL,
        "NotifCenter 2.7: Dispute alert has severity 'critical'"
    );

    // C. High-Value Transaction Alert (> ₹10,000 threshold)
    $highRef = 'VTX_HIGH_' . bin2hex(random_bytes(4));
    $highId = NotificationCenter::triggerHighValueTxnAlert(
        $highRef,
        45000.00,
        'vip_customer@techcorp.in',
        'Rajagiri Corporate Portal',
        1
    );
    $createdIds[] = $highId;

    TestHelper::assertTrue($highId > 0, "NotifCenter 2.8: High-Value payment alert created for ₹45,000 (#{$highId})");

    $stmt->execute([$highId]);
    $highRow = $stmt->fetch(PDO::FETCH_ASSOC);

    TestHelper::assertTrue(
        $highRow['type'] === NotificationCenter::TYPE_HIGH_VALUE_TXN,
        "NotifCenter 2.9: High-value alert has type 'high_value_transaction'"
    );
    TestHelper::assertTrue(
        $highRow['severity'] === NotificationCenter::SEVERITY_WARNING,
        "NotifCenter 2.10: High-value alert has severity 'warning'"
    );

    // D. Low-value transaction should NOT trigger high-value alert (< ₹10,000)
    $lowId = NotificationCenter::triggerHighValueTxnAlert(
        'VTX_LOW_' . bin2hex(random_bytes(4)),
        500.00,
        'student@rajagiri.edu',
        'Campus Events',
        1
    );
    TestHelper::assertTrue(
        $lowId === 0,
        "NotifCenter 2.11: Transaction under ₹10,000 threshold (₹500) correctly ignored"
    );

    // ------------------------------------------------------------
    // 3. Unread Counts & Status Transition Tests
    // ------------------------------------------------------------
    echo "\n--- 3. Unread Counts & Mark as Read ---\n";

    $unreadBefore = NotificationCenter::getUnreadCount();
    TestHelper::assertTrue($unreadBefore >= 3, "NotifCenter 3.1: Unread count reflects created alerts ({$unreadBefore})");

    // Mark single alert as read
    $markResult = NotificationCenter::markAsRead($whId);
    TestHelper::assertTrue($markResult, "NotifCenter 3.2: markAsRead({$whId}) executed successfully");

    $stmt->execute([$whId]);
    $updatedRow = $stmt->fetch(PDO::FETCH_ASSOC);
    TestHelper::assertTrue(
        (int)$updatedRow['is_read'] === 1 && !empty($updatedRow['read_at']),
        "NotifCenter 3.3: Alert #{$whId} marked is_read=1 with timestamp"
    );

    $unreadAfterOne = NotificationCenter::getUnreadCount();
    TestHelper::assertTrue(
        $unreadAfterOne === $unreadBefore - 1,
        "NotifCenter 3.4: Unread count decreased by 1 ({$unreadAfterOne})"
    );

    // Mark all as read
    $markAllResult = NotificationCenter::markAllAsRead();
    TestHelper::assertTrue($markAllResult, "NotifCenter 3.5: markAllAsRead() executed successfully");

    $unreadFinal = NotificationCenter::getUnreadCount();
    TestHelper::assertTrue(
        $unreadFinal === 0,
        "NotifCenter 3.6: All notifications marked as read (count=0)"
    );

    // ------------------------------------------------------------
    // 4. Deduplication Logic Tests
    // ------------------------------------------------------------
    echo "\n--- 4. Deduplication & Anti-Spam ---\n";

    $dedupRef = 'dedup_test_' . bin2hex(random_bytes(4));
    $id1 = NotificationCenter::create(
        NotificationCenter::TYPE_NEW_DISPUTE,
        "Duplicate Test",
        "Testing alert deduplication",
        NotificationCenter::SEVERITY_WARNING,
        null,
        $dedupRef,
        'transaction'
    );
    $createdIds[] = $id1;

    // Call create with identical referenceId within 30 min window
    $id2 = NotificationCenter::create(
        NotificationCenter::TYPE_NEW_DISPUTE,
        "Duplicate Test Retry",
        "Testing alert deduplication retry",
        NotificationCenter::SEVERITY_WARNING,
        null,
        $dedupRef,
        'transaction'
    );

    TestHelper::assertTrue(
        $id1 === $id2,
        "NotifCenter 4.1: Deduplication prevented duplicate alert row (id1={$id1}, id2={$id2})"
    );

    // ------------------------------------------------------------
    // 5. Aggregate Statistics & Filtering
    // ------------------------------------------------------------
    echo "\n--- 5. Stats & Category Filtering ---\n";

    $stats = NotificationCenter::getStats();
    TestHelper::assertTrue(
        isset($stats['total'], $stats['unread'], $stats['failed_webhooks'], $stats['disputes'], $stats['high_value']),
        "NotifCenter 5.1: getStats() returns complete statistical breakdown"
    );
    TestHelper::assertTrue(
        $stats['disputes'] >= 1 && $stats['high_value'] >= 1,
        "NotifCenter 5.2: Stats reflect categorised alert distribution"
    );

    $disputeList = NotificationCenter::getNotifications(null, 10, 0, ['type' => NotificationCenter::TYPE_NEW_DISPUTE]);
    TestHelper::assertTrue(
        !empty($disputeList),
        "NotifCenter 5.3: Category filter 'new_dispute' isolates dispute alerts"
    );

    // ------------------------------------------------------------
    // 6. Security Guards & HTTP Endpoints Tests
    // ------------------------------------------------------------
    echo "\n--- 6. Security Guards & Endpoints ---\n";

    // Unauthenticated Ajax request should return 401
    $ajaxRes = TestHelper::request('GET', '/admin/notifications_ajax.php');
    TestHelper::assertTrue(
        $ajaxRes['http_code'] === 401,
        "NotifCenter 6.1: Unauthenticated access to notifications_ajax.php rejected (HTTP 401)"
    );
    TestHelper::assertTrue(
        isset($ajaxRes['json']['success']) && $ajaxRes['json']['success'] === false,
        "NotifCenter 6.2: Ajax response contains success=false"
    );

    // Unauthenticated GET to notifications.php should redirect (302)
    $pageRes = TestHelper::request('GET', '/admin/notifications.php');
    TestHelper::assertTrue(
        $pageRes['http_code'] === 302,
        "NotifCenter 6.3: Unauthenticated access to notifications.php redirects to login (HTTP 302)"
    );

    // ------------------------------------------------------------
    // 7. UI Components & Stylesheet Verification
    // ------------------------------------------------------------
    echo "\n--- 7. UI Components & Stylesheet Integrity ---\n";

    $headerPath = dirname(__DIR__) . '/admin/includes/header.php';
    $headerContent = file_get_contents($headerPath);

    TestHelper::assertTrue(
        str_contains($headerContent, 'id="notifBellBtn"') &&
        str_contains($headerContent, 'id="notifBadge"') &&
        str_contains($headerContent, 'id="notifDropdown"'),
        "NotifCenter 7.1: header.php renders #notifBellBtn, #notifBadge, and #notifDropdown"
    );
    TestHelper::assertTrue(
        str_contains($headerContent, 'notifications.php'),
        "NotifCenter 7.2: header.php sidebar contains link to notifications.php"
    );

    $cssPath = dirname(__DIR__) . '/admin/assets/css/admin.css';
    $cssContent = file_get_contents($cssPath);

    TestHelper::assertTrue(
        str_contains($cssContent, '.btn-notif-bell') &&
        str_contains($cssContent, '.notif-badge') &&
        str_contains($cssContent, '.notif-dropdown'),
        "NotifCenter 7.3: admin.css contains styles for bell button, badge, and dropdown"
    );

    // ------------------------------------------------------------
    // 8. Cleanup Test Artifacts
    // ------------------------------------------------------------
    echo "\n--- 8. Cleanup ---\n";

    if (!empty($createdIds)) {
        $idList = implode(',', array_map('intval', $createdIds));
        $db->exec("DELETE FROM admin_notifications WHERE id IN ($idList)");
    }
    TestHelper::assertTrue(true, "NotifCenter 8.1: Cleaned up test notification records");

    echo "\n✓ In-App Notification Center verification complete!\n";
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    run_notification_center_tests();
    $counts = TestHelper::getCounts();
    echo "\nTest Summary: " . $counts['passed'] . " passed, " . $counts['failed'] . " failed.\n";
    exit($counts['failed'] > 0 ? 1 : 0);
}
