<?php

/**
 * ------------------------------------------------------------
 * test_checkout_session.php
 * ------------------------------------------------------------
 * Automated tests for checkout session architecture and checkout.php rendering.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/TestHelper.php';
require_once __DIR__ . '/test_payment.php';

function run_checkout_session_tests(?array $paymentContext = null): ?array
{
    TestHelper::printSuiteHeader('5. Checkout Session & Checkout URL Tests');

    if (!$paymentContext || empty($paymentContext['vortex_transaction_id'])) {
        $paymentContext = run_payment_tests();
    }

    if (!$paymentContext || empty($paymentContext['vortex_transaction_id'])) {
        TestHelper::fail('CheckoutSession Setup', 'No active payment transaction available');
        return null;
    }

    $vtxTxId = $paymentContext['vortex_transaction_id'];
    $db = TestHelper::getDb();

    // 1. Fetch transaction record
    $txStmt = $db->prepare("SELECT id, vortex_transaction_id, amount, currency FROM transactions WHERE vortex_transaction_id = ?");
    $txStmt->execute([$vtxTxId]);
    $tx = $txStmt->fetch(PDO::FETCH_ASSOC);

    TestHelper::assertTrue(!empty($tx), 'CheckoutSession 1: Transaction found in database');
    $txId = (int) $tx['id'];

    // 2. Query checkout_sessions record
    $sessionStmt = $db->prepare("SELECT * FROM checkout_sessions WHERE transaction_id = ? ORDER BY id DESC LIMIT 1");
    $sessionStmt->execute([$txId]);
    $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);

    TestHelper::assertTrue(!empty($session), 'CheckoutSession 2: checkout_sessions record exists');
    TestHelper::assertFieldExists('id', $session, 'CheckoutSession 2: id exists');
    TestHelper::assertFieldExists('session_token', $session, 'CheckoutSession 2: session_token exists');
    TestHelper::assertFieldEquals($txId, (int) ($session['transaction_id'] ?? 0), 'transaction_id', 'CheckoutSession 2: transaction_id matches');
    TestHelper::assertFieldEquals($paymentContext['redirect_url'], $session['redirect_url'] ?? '', 'redirect_url', 'CheckoutSession 2: redirect_url matches developer URL');
    TestHelper::assertFieldExists('expires_at', $session, 'CheckoutSession 2: expires_at exists');
    TestHelper::assertFieldExists('created_at', $session, 'CheckoutSession 2: created_at exists');

    // 3. Confirm absence of legacy columns
    TestHelper::assertFieldNotExists('success_url', $session, 'CheckoutSession 3: success_url not in checkout_sessions');
    TestHelper::assertFieldNotExists('failure_url', $session, 'CheckoutSession 3: failure_url not in checkout_sessions');

    // 4. Verify transactions table does NOT contain redirect_url
    $txCols = $db->query("DESCRIBE transactions")->fetchAll(PDO::FETCH_COLUMN);
    TestHelper::assertFalse(in_array('redirect_url', $txCols), 'CheckoutSession 4: transactions table does not store redirect_url');

    // 5. Expiry duration check (~5 minutes)
    $createdAtTs = strtotime($session['created_at']);
    $expiresAtTs = strtotime($session['expires_at']);
    $durationMin = round(($expiresAtTs - $createdAtTs) / 60);
    TestHelper::assertTrue($durationMin >= 4 && $durationMin <= 6, 'CheckoutSession 5: Expiration is approximately 5 minutes');

    // 6. Token format & masking
    $token = $session['session_token'];
    TestHelper::assertTrue(strlen($token) === 64 && ctype_xdigit($token), 'CheckoutSession 6: session_token is 64-character hex');
    echo "  [INFO] Session token: " . TestHelper::maskSecret($token, 6) . "\n";

    // 7. Verify checkout URL
    $paymentUrl = $paymentContext['payment_url'];
    TestHelper::assertTrue(str_contains($paymentUrl, 'checkout.php?token=' . $token), 'CheckoutSession 7: payment_url contains checkout.php and session token');

    // 8. Open checkout.php via HTTP GET
    $checkoutReq = TestHelper::request('GET', 'checkout.php?token=' . urlencode($token));
    TestHelper::assertStatus(200, $checkoutReq['http_code'], 'CheckoutSession 8: checkout.php loads with HTTP 200');
    TestHelper::assertTrue(str_contains($checkoutReq['raw'], 'Razorpay'), 'CheckoutSession 8: Razorpay elements rendered');
    TestHelper::assertTrue(str_contains($checkoutReq['raw'], 'checkout.razorpay.com/v1/checkout.js'), 'CheckoutSession 8: Razorpay checkout.js script loaded');
    TestHelper::assertTrue(str_contains($checkoutReq['raw'], (string) $paymentContext['amount']), 'CheckoutSession 8: Formatted amount displayed in checkout');

    // 9. Confirm NO secrets in checkout.php HTML markup
    $leakedSecrets = false;
    if (str_contains($checkoutReq['raw'], $paymentContext['client']['api_secret']) ||
        str_contains($checkoutReq['raw'], $_ENV['RAZORPAY_KEY_SECRET'] ?? 'NOT_SET') ||
        str_contains($checkoutReq['raw'], $_ENV['DB_PASS'] ?? 'NOT_SET')) {
        $leakedSecrets = true;
    }
    TestHelper::assertFalse($leakedSecrets, 'CheckoutSession 9: Zero secrets or passwords in checkout.php markup');

    // --------------------------------------------------------
    // Checkout Recovery Endpoint: api/get_checkout_payment_status.php
    // --------------------------------------------------------

    // 10. Valid checkout session status lookup (PENDING)
    $statusReq1 = TestHelper::request('POST', 'api/get_checkout_payment_status.php', json_encode([
        'session_token' => $token
    ]), ['Content-Type: application/json']);

    TestHelper::assertStatus(200, $statusReq1['http_code'], 'CheckoutSession 10: Valid checkout session status returns HTTP 200');
    TestHelper::assertFieldEquals('success', $statusReq1['json']['status'] ?? '', 'status', 'CheckoutSession 10: Status is success');
    TestHelper::assertFieldEquals($vtxTxId, $statusReq1['json']['data']['vortex_transaction_id'] ?? '', 'vortex_transaction_id', 'CheckoutSession 10: vortex_transaction_id matches');
    TestHelper::assertFieldEquals('PENDING', $statusReq1['json']['data']['payment_status'] ?? '', 'payment_status', 'CheckoutSession 10: payment_status is PENDING');

    // 11. Missing session_token returns HTTP 400
    $statusReq2 = TestHelper::request('POST', 'api/get_checkout_payment_status.php', json_encode([]), ['Content-Type: application/json']);
    TestHelper::assertStatus(400, $statusReq2['http_code'], 'CheckoutSession 11: Missing session_token returns HTTP 400');

    // 12. Invalid format session_token returns HTTP 400
    $statusReq3 = TestHelper::request('POST', 'api/get_checkout_payment_status.php', json_encode([
        'session_token' => 'invalid_short_token'
    ]), ['Content-Type: application/json']);
    TestHelper::assertStatus(400, $statusReq3['http_code'], 'CheckoutSession 12: Invalid session token format returns HTTP 400');

    // 13. Non-existent session_token returns HTTP 404
    $fakeToken = bin2hex(random_bytes(32));
    $statusReq4 = TestHelper::request('POST', 'api/get_checkout_payment_status.php', json_encode([
        'session_token' => $fakeToken
    ]), ['Content-Type: application/json']);
    TestHelper::assertStatus(404, $statusReq4['http_code'], 'CheckoutSession 13: Nonexistent session token returns HTTP 404');

    // 14. Expired session returns HTTP 410
    $expiredToken = bin2hex(random_bytes(32));
    $pastExpires = date('Y-m-d H:i:s', time() - 3600);
    $expStmt = $db->prepare("INSERT INTO checkout_sessions (session_token, transaction_id, redirect_url, expires_at) VALUES (?, ?, 'https://example.com', ?)");
    $expStmt->execute([$expiredToken, $txId, $pastExpires]);

    $statusReq5 = TestHelper::request('POST', 'api/get_checkout_payment_status.php', json_encode([
        'session_token' => $expiredToken
    ]), ['Content-Type: application/json']);
    TestHelper::assertStatus(410, $statusReq5['http_code'], 'CheckoutSession 14: Expired session returns HTTP 410');

    // Clean up temporary expired session
    $db->prepare("DELETE FROM checkout_sessions WHERE session_token = ?")->execute([$expiredToken]);

    // 15. Transaction state reflects SUCCESS
    // Create temporary transaction & session for testing SUCCESS state
    $succTxId = 'VTX_STAT_SUCC_' . bin2hex(random_bytes(4));
    $succToken = bin2hex(random_bytes(32));
    $futureExpires = date('Y-m-d H:i:s', time() + 300);
    $db->prepare("INSERT INTO transactions (vortex_transaction_id, event_id, api_client_id, customer_email, customer_mobile, amount, currency, status)
                  VALUES (?, ?, ?, 'cust@example.com', '9999999999', 150.00, 'INR', 'SUCCESS')")
       ->execute([$succTxId, $paymentContext['event']['event_id'], $paymentContext['client']['id']]);
    $succDbId = $db->lastInsertId();
    $db->prepare("INSERT INTO checkout_sessions (session_token, transaction_id, redirect_url, expires_at) VALUES (?, ?, 'https://merchant.example.com/callback', ?)")
       ->execute([$succToken, $succDbId, $futureExpires]);

    $statusReq6 = TestHelper::request('POST', 'api/get_checkout_payment_status.php', json_encode([
        'session_token' => $succToken
    ]), ['Content-Type: application/json']);
    TestHelper::assertStatus(200, $statusReq6['http_code'], 'CheckoutSession 15: SUCCESS transaction returns HTTP 200');
    TestHelper::assertFieldEquals('SUCCESS', $statusReq6['json']['data']['payment_status'] ?? '', 'payment_status', 'CheckoutSession 15: payment_status is SUCCESS');
    TestHelper::assertFieldEquals('https://merchant.example.com/callback', $statusReq6['json']['data']['redirect_url'] ?? '', 'redirect_url', 'CheckoutSession 15: redirect_url present on SUCCESS');
    TestHelper::assertFieldEquals(150.00, (float)($statusReq6['json']['data']['amount'] ?? 0), 'amount', 'CheckoutSession 15: amount matches');

    // 16. Transaction state reflects FAILED
    $failTxId = 'VTX_STAT_FAIL_' . bin2hex(random_bytes(4));
    $failToken = bin2hex(random_bytes(32));
    $db->prepare("INSERT INTO transactions (vortex_transaction_id, event_id, api_client_id, customer_email, customer_mobile, amount, currency, status)
                  VALUES (?, ?, ?, 'cust@example.com', '9999999999', 200.00, 'INR', 'FAILED')")
       ->execute([$failTxId, $paymentContext['event']['event_id'], $paymentContext['client']['id']]);
    $failDbId = $db->lastInsertId();
    $db->prepare("INSERT INTO checkout_sessions (session_token, transaction_id, redirect_url, expires_at) VALUES (?, ?, 'https://merchant.example.com/callback', ?)")
       ->execute([$failToken, $failDbId, $futureExpires]);

    $statusReq7 = TestHelper::request('POST', 'api/get_checkout_payment_status.php', json_encode([
        'session_token' => $failToken
    ]), ['Content-Type: application/json']);
    TestHelper::assertStatus(200, $statusReq7['http_code'], 'CheckoutSession 16: FAILED transaction returns HTTP 200');
    TestHelper::assertFieldEquals('FAILED', $statusReq7['json']['data']['payment_status'] ?? '', 'payment_status', 'CheckoutSession 16: payment_status is FAILED');

    // Clean up temporary test data
    $db->prepare("DELETE FROM checkout_sessions WHERE session_token IN (?, ?)")->execute([$succToken, $failToken]);
    $db->prepare("DELETE FROM transactions WHERE id IN (?, ?)")->execute([$succDbId, $failDbId]);

    // 17. Zero secrets in get_checkout_payment_status.php response
    $statusBodyRaw = $statusReq1['raw'];
    $secretLeakedInStatus = str_contains($statusBodyRaw, $paymentContext['client']['api_secret'])
        || str_contains($statusBodyRaw, $_ENV['RAZORPAY_KEY_SECRET'] ?? 'NOT_SET')
        || str_contains($statusBodyRaw, $_ENV['DB_PASS'] ?? 'NOT_SET');
    TestHelper::assertFalse($secretLeakedInStatus, 'CheckoutSession 17: Zero secrets or passwords in recovery API response');

    // 18. Malformed JSON returns HTTP 400
    $statusReq8 = TestHelper::request('POST', 'api/get_checkout_payment_status.php', '{malformed json...', ['Content-Type: application/json']);
    TestHelper::assertStatus(400, $statusReq8['http_code'], 'CheckoutSession 18: Malformed JSON returns HTTP 400');

    return array_merge($paymentContext, [
        'session_token' => $token,
        'session_id'    => (int) $session['id'],
        'tx_id'         => $txId
    ]);
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    run_checkout_session_tests();
}
