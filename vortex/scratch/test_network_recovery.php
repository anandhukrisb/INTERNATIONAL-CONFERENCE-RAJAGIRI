<?php

/**
 * ------------------------------------------------------------
 * test_network_recovery.php
 * ------------------------------------------------------------
 * Automated verification of network failure recovery, checkout
 * status polling, and webhook race conditions.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/CheckoutSession.php';
require_once __DIR__ . '/../classes/Webhook.php';
require_once __DIR__ . '/../classes/Logger.php';

$db = Database::getInstance()->getConnection();
$checkoutSession = new CheckoutSession();
$webhook = new Webhook();

echo "========================================================\n";
echo "VORTEX PAYMENT RECOVERY & WEBHOOK COMPREHENSIVE TESTS\n";
echo "========================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertCheck($condition, $description) {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] $description\n";
        $passCount++;
    } else {
        echo "[FAIL] $description\n";
        $failCount++;
    }
}

// Ensure an event and API client exist
$client = $db->query("SELECT id, api_secret FROM api_clients LIMIT 1")->fetch();
$clientId = $client ? (int)$client['id'] : 1;
$apiSecret = $client ? $client['api_secret'] : 'secret123';

$event = $db->query("SELECT event_id FROM events LIMIT 1")->fetch();
$eventId = $event ? $event['event_id'] : 'TEST_EVT_01';

// Helper to create transaction & session
function createTxAndSession($db, $status = 'PENDING', $amount = 250.00, $currency = 'INR', $durationMin = 5) {
    global $clientId, $eventId;
    $vtxTxId = 'VTX_REC_' . bin2hex(random_bytes(4));
    $orderId = 'order_rec_' . bin2hex(random_bytes(4));
    
    $stmt = $db->prepare("INSERT INTO transactions 
        (vortex_transaction_id, event_id, api_client_id, customer_email, customer_mobile, amount, currency, razorpay_order_id, status)
        VALUES (?, ?, ?, 'recovery_test@example.com', '9876543210', ?, ?, ?, ?)");
    $stmt->execute([$vtxTxId, $eventId, $clientId, $amount, $currency, $orderId, $status]);
    $txId = $db->lastInsertId();

    $sessionToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + ($durationMin * 60));
    $redirectUrl = 'https://merchant.example.com/checkout/complete';

    $sStmt = $db->prepare("INSERT INTO checkout_sessions (session_token, transaction_id, redirect_url, expires_at) VALUES (?, ?, ?, ?)");
    $sStmt->execute([$sessionToken, $txId, $redirectUrl, $expiresAt]);

    return [
        'tx_id' => $txId,
        'vortex_transaction_id' => $vtxTxId,
        'order_id' => $orderId,
        'session_token' => $sessionToken,
        'redirect_url' => $redirectUrl,
        'amount' => $amount,
        'currency' => $currency
    ];
}

// ------------------------------------------------------------
// 1. Valid checkout session status
// ------------------------------------------------------------
$f1 = createTxAndSession($db, 'PENDING');
$res1 = $checkoutSession->getCheckoutPaymentStatus($f1['session_token']);
assertCheck($res1['success'] === true, "1. Valid checkout session status returns success: true");
assertCheck($res1['data']['vortex_transaction_id'] === $f1['vortex_transaction_id'], "1. Returns correct vortex_transaction_id");

// ------------------------------------------------------------
// 2. Invalid session token (format/missing)
// ------------------------------------------------------------
$res2a = $checkoutSession->getCheckoutPaymentStatus('');
assertCheck($res2a['success'] === false && $res2a['error_code'] === 400, "2a. Missing session token returns HTTP 400");

$res2b = $checkoutSession->getCheckoutPaymentStatus('invalid_hex_token_123');
assertCheck($res2b['success'] === false && $res2b['error_code'] === 400, "2b. Non-64 hex session token returns HTTP 400");

// ------------------------------------------------------------
// 3. Unauthorized/non-existent session access
// ------------------------------------------------------------
$res3 = $checkoutSession->getCheckoutPaymentStatus(bin2hex(random_bytes(32)));
assertCheck($res3['success'] === false && $res3['error_code'] === 404, "3. Non-existent session token returns HTTP 404");

// ------------------------------------------------------------
// 4. Expired session
// ------------------------------------------------------------
$f4 = createTxAndSession($db, 'PENDING', 100.00, 'INR', -10); // 10 minutes ago
$res4 = $checkoutSession->getCheckoutPaymentStatus($f4['session_token']);
assertCheck($res4['success'] === false && $res4['error_code'] === 410, "4. Expired checkout session returns HTTP 410");

// ------------------------------------------------------------
// 5. PENDING transaction status retrieval
// ------------------------------------------------------------
$f5 = createTxAndSession($db, 'PENDING');
$res5 = $checkoutSession->getCheckoutPaymentStatus($f5['session_token']);
assertCheck($res5['data']['payment_status'] === 'PENDING', "5. PENDING transaction accurately reported");

// ------------------------------------------------------------
// 6. SUCCESS transaction status retrieval
// ------------------------------------------------------------
$f6 = createTxAndSession($db, 'SUCCESS');
$res6 = $checkoutSession->getCheckoutPaymentStatus($f6['session_token']);
assertCheck($res6['data']['payment_status'] === 'SUCCESS', "6. SUCCESS transaction accurately reported");
assertCheck($res6['data']['redirect_url'] === $f6['redirect_url'], "6. SUCCESS includes developer redirect_url");

// ------------------------------------------------------------
// 7. FAILED transaction status retrieval
// ------------------------------------------------------------
$f7 = createTxAndSession($db, 'FAILED');
$res7 = $checkoutSession->getCheckoutPaymentStatus($f7['session_token']);
assertCheck($res7['data']['payment_status'] === 'FAILED', "7. FAILED transaction accurately reported");

// ------------------------------------------------------------
// 8. Zero credentials or API secrets exposed in session status
// ------------------------------------------------------------
$statusDataJson = json_encode($res6);
$leakFound = strpos($statusDataJson, $apiSecret) !== false
          || strpos($statusDataJson, 'the_secret') !== false
          || strpos($statusDataJson, 'password') !== false;
assertCheck(!$leakFound, "8. Zero API secrets or database passwords in session status payload");

// ------------------------------------------------------------
// 9. Webhook PENDING -> SUCCESS
// ------------------------------------------------------------
$f9 = createTxAndSession($db, 'PENDING', 500.00, 'INR');
$evtId9 = 'evt_test_' . bin2hex(random_bytes(4));
$payId9 = 'pay_test_' . bin2hex(random_bytes(4));
$webhookRes9 = $webhook->process([
    'id' => $evtId9,
    'event' => 'payment.captured',
    'payload' => [
        'payment' => [
            'entity' => [
                'id' => $payId9,
                'order_id' => $f9['order_id'],
                'amount' => 50000,
                'currency' => 'INR'
            ]
        ]
    ]
], 'dummy_sig');
assertCheck($webhookRes9['status'] === 'SUCCESS' && $webhookRes9['payment_status'] === 'SUCCESS', "9. Webhook payment.captured transitions PENDING -> SUCCESS");

// Confirm database status
$txCheckStmt = $db->prepare("SELECT status FROM transactions WHERE id = ?");
$txCheckStmt->execute([$f9['tx_id']]);
$txRow9 = $txCheckStmt->fetch();
assertCheck($txRow9['status'] === 'SUCCESS', "9. Database record updated to SUCCESS");

// ------------------------------------------------------------
// 10. Duplicate Webhook Handling (Idempotency)
// ------------------------------------------------------------
$webhookRes10 = $webhook->process([
    'id' => $evtId9, // Re-sent duplicate event ID
    'event' => 'payment.captured',
    'payload' => [
        'payment' => [
            'entity' => [
                'id' => $payId9,
                'order_id' => $f9['order_id'],
                'amount' => 50000,
                'currency' => 'INR'
            ]
        ]
    ]
], 'dummy_sig');
assertCheck($webhookRes10['status'] === 'SUCCESS' && $webhookRes10['message'] === 'Webhook already processed.', "10. Duplicate webhook ignored idempotently with SUCCESS");

// ------------------------------------------------------------
// 11. Webhook PENDING -> FAILED
// ------------------------------------------------------------
$f11 = createTxAndSession($db, 'PENDING', 300.00, 'INR');
$evtId11 = 'evt_test_' . bin2hex(random_bytes(4));
$payId11 = 'pay_test_' . bin2hex(random_bytes(4));
$webhookRes11 = $webhook->process([
    'id' => $evtId11,
    'event' => 'payment.failed',
    'payload' => [
        'payment' => [
            'entity' => [
                'id' => $payId11,
                'order_id' => $f11['order_id'],
                'amount' => 30000,
                'currency' => 'INR'
            ]
        ]
    ]
], 'dummy_sig');
assertCheck($webhookRes11['status'] === 'SUCCESS' && $webhookRes11['payment_status'] === 'FAILED', "11. Webhook payment.failed transitions PENDING -> FAILED");

$txCheckStmt->execute([$f11['tx_id']]);
$txRow11 = $txCheckStmt->fetch();
assertCheck($txRow11['status'] === 'FAILED', "11. Database record updated to FAILED");

// ------------------------------------------------------------
// 12. SUCCESS cannot become FAILED
// ------------------------------------------------------------
$evtId12 = 'evt_test_' . bin2hex(random_bytes(4));
$webhookRes12 = $webhook->process([
    'id' => $evtId12,
    'event' => 'payment.failed',
    'payload' => [
        'payment' => [
            'entity' => [
                'id' => $payId9,
                'order_id' => $f9['order_id'],
                'amount' => 50000,
                'currency' => 'INR'
            ]
        ]
    ]
], 'dummy_sig');
$txCheckStmt->execute([$f9['tx_id']]);
$txRow12 = $txCheckStmt->fetch();
assertCheck($txRow12['status'] === 'SUCCESS', "12. SUCCESS transaction NEVER altered to FAILED by incoming webhook");

// ------------------------------------------------------------
// 13. FAILED cannot become SUCCESS
// ------------------------------------------------------------
$evtId13 = 'evt_test_' . bin2hex(random_bytes(4));
$webhookRes13 = $webhook->process([
    'id' => $evtId13,
    'event' => 'payment.captured',
    'payload' => [
        'payment' => [
            'entity' => [
                'id' => $payId11,
                'order_id' => $f11['order_id'],
                'amount' => 30000,
                'currency' => 'INR'
            ]
        ]
    ]
], 'dummy_sig');
$txCheckStmt->execute([$f11['tx_id']]);
$txRow13 = $txCheckStmt->fetch();
assertCheck($txRow13['status'] === 'FAILED', "13. FAILED transaction NEVER altered back to SUCCESS by webhook");

// ------------------------------------------------------------
// 14. Webhook + verify_payment race condition:
//     verify_payment wins first (PENDING -> SUCCESS)
//     Then webhook arrives.
// ------------------------------------------------------------
$f14 = createTxAndSession($db, 'PENDING', 400.00, 'INR');
// verify_payment completes
$db->prepare("UPDATE transactions SET status = 'SUCCESS', razorpay_payment_id = 'pay_verify_first' WHERE id = ?")
   ->execute([$f14['tx_id']]);

// Webhook arrives
$evtId14 = 'evt_test_' . bin2hex(random_bytes(4));
$webhookRes14 = $webhook->process([
    'id' => $evtId14,
    'event' => 'payment.captured',
    'payload' => [
        'payment' => [
            'entity' => [
                'id' => 'pay_verify_first',
                'order_id' => $f14['order_id'],
                'amount' => 40000,
                'currency' => 'INR'
            ]
        ]
    ]
], 'dummy_sig');
assertCheck($webhookRes14['status'] === 'SUCCESS', "14. Webhook safely acknowledges transaction already completed by verification");

// ------------------------------------------------------------
// 15. Network failure recovery simulation:
//     Customer pays -> Browser loses connection -> verify_payment not called ->
//     Webhook arrives from Razorpay -> Updates PENDING -> SUCCESS ->
//     Network restores -> Browser checkout polls get_checkout_payment_status ->
//     Detects SUCCESS -> Ready to redirect!
// ------------------------------------------------------------
$f15 = createTxAndSession($db, 'PENDING', 750.00, 'INR');

// Step A: Customer drops offline. verify_payment is NEVER reached.
// Polling during outage would fail on client, but let's check initial DB state:
$statusBefore = $checkoutSession->getCheckoutPaymentStatus($f15['session_token']);
assertCheck($statusBefore['data']['payment_status'] === 'PENDING', "15a. Transaction is initially PENDING while customer is offline");

// Step B: Razorpay server delivers webhook directly to backend
$evtId15 = 'evt_test_' . bin2hex(random_bytes(4));
$payId15 = 'pay_recovery_' . bin2hex(random_bytes(4));
$webhookRes15 = $webhook->process([
    'id' => $evtId15,
    'event' => 'payment.captured',
    'payload' => [
        'payment' => [
            'entity' => [
                'id' => $payId15,
                'order_id' => $f15['order_id'],
                'amount' => 75000,
                'currency' => 'INR'
            ]
        ]
    ]
], 'dummy_sig');
assertCheck($webhookRes15['status'] === 'SUCCESS', "15b. Razorpay webhook successfully processed during customer network outage");

// Step C: Customer network reconnects. checkout.php polls get_checkout_payment_status.php
$statusAfter = $checkoutSession->getCheckoutPaymentStatus($f15['session_token']);
assertCheck($statusAfter['data']['payment_status'] === 'SUCCESS', "15c. Recovery polling detects SUCCESS on network restore");
assertCheck($statusAfter['data']['vortex_transaction_id'] === $f15['vortex_transaction_id'], "15d. Recovery returns correct transaction ID for redirect");
assertCheck($statusAfter['data']['redirect_url'] === $f15['redirect_url'], "15e. Recovery returns merchant redirect_url");

// Clean up test data
$db->query("DELETE FROM webhooks WHERE razorpay_event_id LIKE 'evt_test_%'");
$db->query("DELETE FROM checkout_sessions WHERE redirect_url = 'https://merchant.example.com/checkout/complete'");
$db->query("DELETE FROM transactions WHERE customer_email = 'recovery_test@example.com'");

echo "\n--------------------------------------------------------\n";
echo "Total assertions: " . ($passCount + $failCount) . "\n";
echo "Passed: $passCount\n";
echo "Failed: $failCount\n";
echo "--------------------------------------------------------\n";

if ($failCount === 0) {
    echo "ALL PAYMENT RECOVERY & WEBHOOK TESTS PASSED SUCCESSFULLY!\n";
    exit(0);
} else {
    echo "SOME TESTS FAILED!\n";
    exit(1);
}
