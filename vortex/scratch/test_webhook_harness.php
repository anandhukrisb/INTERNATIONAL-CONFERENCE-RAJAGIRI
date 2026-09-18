<?php

require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Webhook.php';
require_once __DIR__ . '/../classes/Logger.php';

$db = Database::getInstance()->getConnection();
$webhook = new Webhook();

echo "==================================================\n";
echo "VORTEX WEBHOOK HARDENING DIRECT HARNESS TEST\n";
echo "==================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertCondition($condition, $description) {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] $description\n";
        $passCount++;
    } else {
        echo "[FAIL] $description\n";
        $failCount++;
    }
}

// Ensure an API client and event exist for test transactions
$clientStmt = $db->query("SELECT id FROM api_clients LIMIT 1");
$client = $clientStmt->fetch();
$clientId = $client ? $client['id'] : 1;

$eventStmt = $db->query("SELECT event_id FROM events LIMIT 1");
$eventRow = $eventStmt->fetch();
$eventId = $eventRow ? $eventRow['event_id'] : 'TEST_EVT_01';

// Helper to create a test transaction
function createTestTx($db, $orderId, $amount = 100.00, $status = 'PENDING', $currency = 'INR', $clientId = 1, $eventId = 'TEST_EVT') {
    $vortexTxId = 'VTX_TEST_' . bin2hex(random_bytes(4));
    $stmt = $db->prepare("INSERT INTO transactions 
        (vortex_transaction_id, event_id, api_client_id, customer_email, customer_mobile, amount, currency, razorpay_order_id, status)
        VALUES (?, ?, ?, 'test@example.com', '9999999999', ?, ?, ?, ?)");
    $stmt->execute([$vortexTxId, $eventId, $clientId, $amount, $currency, $orderId, $status]);
    return $vortexTxId;
}

// 1. Malformed payload test
$res1 = $webhook->process([], 'sig123');
assertCondition($res1['status'] === 'FAILED' && $res1['message'] === 'Invalid webhook event data.', "Reject empty event payload");

// 2. Missing event name
$res2 = $webhook->process(['id' => 'evt_123'], 'sig123');
assertCondition($res2['status'] === 'FAILED' && $res2['message'] === 'Webhook event name is missing.', "Reject missing event name");

// 3. Missing event ID
$res3 = $webhook->process(['event' => 'payment.captured'], 'sig123');
assertCondition($res3['status'] === 'FAILED' && $res3['message'] === 'Razorpay webhook event ID is missing.', "Reject missing event ID");

// 4. Unsupported event type
$testEvtId_unsupported = 'evt_unsupp_' . bin2hex(random_bytes(4));
$res4 = $webhook->process([
    'id' => $testEvtId_unsupported,
    'event' => 'payment.authorized',
    'payload' => []
], 'sig123');
assertCondition($res4['status'] === 'IGNORED' && $res4['message'] === 'Webhook event not handled.', "Handle unsupported event gracefully (IGNORED)");

// 5. Missing payment entity
$testEvtId_no_entity = 'evt_no_ent_' . bin2hex(random_bytes(4));
$res5 = $webhook->process([
    'id' => $testEvtId_no_entity,
    'event' => 'payment.captured',
    'payload' => []
], 'sig123');
assertCondition($res5['status'] === 'FAILED' && $res5['message'] === 'Payment entity is missing from webhook.', "Reject missing payment entity");

// 6. Transaction not found
$testEvtId_not_found = 'evt_not_found_' . bin2hex(random_bytes(4));
$res6 = $webhook->process([
    'id' => $testEvtId_not_found,
    'event' => 'payment.captured',
    'payload' => [
        'payment' => [
            'entity' => [
                'id' => 'pay_nonexistent_123',
                'order_id' => 'order_nonexistent_123',
                'amount' => 10000,
                'currency' => 'INR'
            ]
        ]
    ]
], 'sig123');
assertCondition($res6['status'] === 'FAILED' && $res6['message'] === 'Transaction not found for the provided order ID.', "Reject non-existent transaction without leaking internals");

// 7. Amount mismatch
$testOrderId_amt = 'order_amt_' . bin2hex(random_bytes(4));
$vtxId_amt = createTestTx($db, $testOrderId_amt, 250.00, 'PENDING', 'INR', $clientId, $eventId);
$testEvtId_amt = 'evt_amt_' . bin2hex(random_bytes(4));
$res7 = $webhook->process([
    'id' => $testEvtId_amt,
    'event' => 'payment.captured',
    'payload' => [
        'payment' => [
            'entity' => [
                'id' => 'pay_amt_123',
                'order_id' => $testOrderId_amt,
                'amount' => 50000, // 500.00 vs 250.00
                'currency' => 'INR'
            ]
        ]
    ]
], 'sig123');
assertCondition($res7['status'] === 'FAILED' && $res7['message'] === 'Payment amount mismatch.', "Reject amount mismatch");

// Verify transaction status remained PENDING
$checkStmt = $db->prepare("SELECT status FROM transactions WHERE razorpay_order_id = ?");
$checkStmt->execute([$testOrderId_amt]);
$checkTx = $checkStmt->fetch();
assertCondition($checkTx['status'] === 'PENDING', "Transaction remains PENDING after amount mismatch");

// 8. Currency mismatch
$testOrderId_curr = 'order_curr_' . bin2hex(random_bytes(4));
$vtxId_curr = createTestTx($db, $testOrderId_curr, 100.00, 'PENDING', 'INR', $clientId, $eventId);
$testEvtId_curr = 'evt_curr_' . bin2hex(random_bytes(4));
$res8 = $webhook->process([
    'id' => $testEvtId_curr,
    'event' => 'payment.captured',
    'payload' => [
        'payment' => [
            'entity' => [
                'id' => 'pay_curr_123',
                'order_id' => $testOrderId_curr,
                'amount' => 10000,
                'currency' => 'USD'
            ]
        ]
    ]
], 'sig123');
assertCondition($res8['status'] === 'FAILED' && $res8['message'] === 'Payment currency mismatch.', "Reject currency mismatch");

// 9. Legitimate payment.captured: PENDING -> SUCCESS
$testOrderId_success = 'order_succ_' . bin2hex(random_bytes(4));
$vtxId_success = createTestTx($db, $testOrderId_success, 150.00, 'PENDING', 'INR', $clientId, $eventId);
$testEvtId_success = 'evt_succ_' . bin2hex(random_bytes(4));
$res9 = $webhook->process([
    'id' => $testEvtId_success,
    'event' => 'payment.captured',
    'payload' => [
        'payment' => [
            'entity' => [
                'id' => 'pay_succ_123',
                'order_id' => $testOrderId_success,
                'amount' => 15000,
                'currency' => 'INR'
            ]
        ]
    ]
], 'sig123');
assertCondition($res9['status'] === 'SUCCESS' && $res9['payment_status'] === 'SUCCESS', "payment.captured returns SUCCESS status");

$checkStmt->execute([$testOrderId_success]);
$checkTx = $checkStmt->fetch();
assertCondition($checkTx['status'] === 'SUCCESS', "Database transaction transitioned to SUCCESS");

// 10. Duplicate webhook delivery for the same event ID
$res10 = $webhook->process([
    'id' => $testEvtId_success, // Same event ID
    'event' => 'payment.captured',
    'payload' => [
        'payment' => [
            'entity' => [
                'id' => 'pay_succ_123',
                'order_id' => $testOrderId_success,
                'amount' => 15000,
                'currency' => 'INR'
            ]
        ]
    ]
], 'sig123');
assertCondition($res10['status'] === 'SUCCESS' && $res10['message'] === 'Webhook already processed.', "Duplicate webhook ignored idempotently with SUCCESS");

// 11. Race condition: Transaction already SUCCESS (e.g. from verify_payment.php)
$testOrderId_race = 'order_race_' . bin2hex(random_bytes(4));
$vtxId_race = createTestTx($db, $testOrderId_race, 200.00, 'SUCCESS', 'INR', $clientId, $eventId);
$testEvtId_race = 'evt_race_' . bin2hex(random_bytes(4));
$res11 = $webhook->process([
    'id' => $testEvtId_race,
    'event' => 'payment.captured',
    'payload' => [
        'payment' => [
            'entity' => [
                'id' => 'pay_race_123',
                'order_id' => $testOrderId_race,
                'amount' => 20000,
                'currency' => 'INR'
            ]
        ]
    ]
], 'sig123');
assertCondition($res11['status'] === 'SUCCESS' && strpos($res11['message'], 'already marked as SUCCESS') !== false, "Acknowledge already SUCCESS transaction without error");

// 12. Race condition: Transaction already SUCCESS, incoming webhook is payment.failed!
$testEvtId_race_fail = 'evt_race_fail_' . bin2hex(random_bytes(4));
$res12 = $webhook->process([
    'id' => $testEvtId_race_fail,
    'event' => 'payment.failed',
    'payload' => [
        'payment' => [
            'entity' => [
                'id' => 'pay_race_fail_123',
                'order_id' => $testOrderId_race,
                'amount' => 20000,
                'currency' => 'INR'
            ]
        ]
    ]
], 'sig123');
assertCondition($res12['status'] === 'SUCCESS', "payment.failed acknowledged without breaking existing SUCCESS");

$checkStmt->execute([$testOrderId_race]);
$checkTx = $checkStmt->fetch();
assertCondition($checkTx['status'] === 'SUCCESS', "SUCCESS transaction NEVER overwritten to FAILED by webhook");

// 13. Legitimate payment.failed: PENDING -> FAILED
$testOrderId_fail = 'order_fail_' . bin2hex(random_bytes(4));
$vtxId_fail = createTestTx($db, $testOrderId_fail, 300.00, 'PENDING', 'INR', $clientId, $eventId);
$testEvtId_fail = 'evt_fail_' . bin2hex(random_bytes(4));
$res13 = $webhook->process([
    'id' => $testEvtId_fail,
    'event' => 'payment.failed',
    'payload' => [
        'payment' => [
            'entity' => [
                'id' => 'pay_fail_123',
                'order_id' => $testOrderId_fail,
                'amount' => 30000,
                'currency' => 'INR'
            ]
        ]
    ]
], 'sig123');
assertCondition($res13['status'] === 'SUCCESS' && $res13['payment_status'] === 'FAILED', "payment.failed processed successfully");

$checkStmt->execute([$testOrderId_fail]);
$checkTx = $checkStmt->fetch();
assertCondition($checkTx['status'] === 'FAILED', "Database transaction transitioned PENDING -> FAILED");

// 14. Terminal state protection: Transaction already FAILED, incoming payment.captured
$testEvtId_revive = 'evt_revive_' . bin2hex(random_bytes(4));
$res14 = $webhook->process([
    'id' => $testEvtId_revive,
    'event' => 'payment.captured',
    'payload' => [
        'payment' => [
            'entity' => [
                'id' => 'pay_fail_123',
                'order_id' => $testOrderId_fail,
                'amount' => 30000,
                'currency' => 'INR'
            ]
        ]
    ]
], 'sig123');
assertCondition($res14['status'] === 'SUCCESS', "Webhook handles already FAILED state gracefully");

$checkStmt->execute([$testOrderId_fail]);
$checkTx = $checkStmt->fetch();
assertCondition($checkTx['status'] === 'FAILED', "FAILED transaction NEVER overwritten back to SUCCESS");

// Clean up test transactions & webhooks created in this run
$db->query("DELETE FROM webhooks WHERE razorpay_event_id LIKE 'evt_%'");
$db->query("DELETE FROM transactions WHERE razorpay_order_id LIKE 'order_%'");

echo "\n--------------------------------------------------\n";
echo "Total assertions: " . ($passCount + $failCount) . "\n";
echo "Passed: $passCount\n";
echo "Failed: $failCount\n";
echo "--------------------------------------------------\n";

if ($failCount === 0) {
    echo "ALL DIRECT WEBHOOK HARDENING ASSERTIONS PASSED!\n";
    exit(0);
} else {
    echo "SOME ASSERTIONS FAILED!\n";
    exit(1);
}
