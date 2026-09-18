<?php

/**
 * ------------------------------------------------------------
 * test_payment_verification.php
 * ------------------------------------------------------------
 * Automated tests for payment verification endpoint (verify_payment.php).
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/TestHelper.php';
require_once __DIR__ . '/test_checkout_session.php';

function run_payment_verification_tests(?array $checkoutContext = null): bool
{
    TestHelper::printSuiteHeader('7. Payment Verification Tests');

    if (!$checkoutContext || empty($checkoutContext['session_token'])) {
        $checkoutContext = run_checkout_session_tests();
    }

    if (!$checkoutContext || empty($checkoutContext['session_token'])) {
        TestHelper::fail('Verification Setup', 'No active checkout session available');
        return false;
    }

    $endpoint = '/api/verify_payment.php';
    $vtxTxId = $checkoutContext['vortex_transaction_id'];
    $token = $checkoutContext['session_token'];
    $redirectUrl = $checkoutContext['redirect_url'];

    // Retrieve the Razorpay order ID created for this transaction
    $db = TestHelper::getDb();
    $stmt = $db->prepare("SELECT razorpay_order_id FROM transactions WHERE vortex_transaction_id = ?");
    $stmt->execute([$vtxTxId]);
    $rzpOrderId = $stmt->fetchColumn();

    $fakePaymentId = 'pay_test_' . bin2hex(random_bytes(6));
    $keySecret = trim($_ENV['RAZORPAY_KEY_SECRET'] ?? '');

    // 1. Invalid signature (tampered signature)
    $res1 = TestHelper::request('POST', $endpoint, [
        'vortex_transaction_id' => $vtxTxId,
        'razorpay_order_id'     => $rzpOrderId,
        'razorpay_payment_id'   => $fakePaymentId,
        'razorpay_signature'    => 'invalid_tampered_signature_12345',
        'session_token'         => $token
    ]);
    TestHelper::assertStatus(400, $res1['http_code'], 'Verify 1: Tampered signature returns HTTP 400');
    TestHelper::assertJsonStatus('error', $res1['json'], 'Verify 1: Tampered signature JSON status error');
    TestHelper::assertFieldEquals(400, $res1['json']['data']['status_code'] ?? 0, 'status_code', 'Verify 1: status_code is 400');
    TestHelper::assertFieldEquals($redirectUrl, $res1['json']['data']['redirect_url'] ?? '', 'redirect_url', 'Verify 1: Redirects to developer URL');

    // Confirm transaction did NOT falsely become SUCCESS
    $stmtCheck = $db->prepare("SELECT status FROM transactions WHERE vortex_transaction_id = ?");
    $stmtCheck->execute([$vtxTxId]);
    $statusAfterTamper = $stmtCheck->fetchColumn();
    TestHelper::assertFieldEquals('PENDING', $statusAfterTamper, 'status', 'Verify 1: Transaction status remains PENDING on tampered signature');

    // 2. Missing Razorpay payment ID
    $res2 = TestHelper::request('POST', $endpoint, [
        'vortex_transaction_id' => $vtxTxId,
        'razorpay_order_id'     => $rzpOrderId,
        'razorpay_signature'    => 'any_sig',
        'session_token'         => $token
    ]);
    TestHelper::assertStatus(400, $res2['http_code'], 'Verify 2: Missing payment ID returns HTTP 400');

    // 3. Invalid Razorpay order ID (mismatch)
    $res3 = TestHelper::request('POST', $endpoint, [
        'vortex_transaction_id' => $vtxTxId,
        'razorpay_order_id'     => 'order_MISMATCH_999999',
        'razorpay_payment_id'   => $fakePaymentId,
        'razorpay_signature'    => 'any_sig',
        'session_token'         => $token
    ]);
    TestHelper::assertStatus(400, $res3['http_code'], 'Verify 3: Order ID mismatch returns HTTP 400');

    // 4. Invalid session token
    $res4 = TestHelper::request('POST', $endpoint, [
        'vortex_transaction_id' => $vtxTxId,
        'razorpay_order_id'     => $rzpOrderId,
        'razorpay_payment_id'   => $fakePaymentId,
        'razorpay_signature'    => 'any_sig',
        'session_token'         => 'invalid_token_999999'
    ]);
    TestHelper::assertStatus(400, $res4['http_code'], 'Verify 4: Invalid session token returns HTTP 400');

    // 5. Expired session token
    $expiredToken = bin2hex(random_bytes(32));
    $db->prepare("INSERT INTO checkout_sessions (session_token, transaction_id, redirect_url, expires_at) VALUES (?, ?, ?, ?)")
       ->execute([$expiredToken, $checkoutContext['tx_id'], $redirectUrl, date('Y-m-d H:i:s', time() - 3600)]);

    $res5 = TestHelper::request('POST', $endpoint, [
        'vortex_transaction_id' => $vtxTxId,
        'razorpay_order_id'     => $rzpOrderId,
        'razorpay_payment_id'   => $fakePaymentId,
        'razorpay_signature'    => 'any_sig',
        'session_token'         => $expiredToken
    ]);
    TestHelper::assertStatus(410, $res5['http_code'], 'Verify 5: Expired session returns HTTP 410');

    // 6. Valid signature verification
    $validSignature = hash_hmac('sha256', $rzpOrderId . '|' . $fakePaymentId, $keySecret);
    $res6 = TestHelper::request('POST', $endpoint, [
        'vortex_transaction_id' => $vtxTxId,
        'razorpay_order_id'     => $rzpOrderId,
        'razorpay_payment_id'   => $fakePaymentId,
        'razorpay_signature'    => $validSignature,
        'session_token'         => $token
    ]);
    TestHelper::assertStatus(200, $res6['http_code'], 'Verify 6: Valid signature returns HTTP 200');
    TestHelper::assertJsonStatus('success', $res6['json'], 'Verify 6: Valid signature JSON status success');
    TestHelper::assertFieldEquals(200, $res6['json']['data']['status_code'] ?? 0, 'status_code', 'Verify 6: status_code is 200');
    TestHelper::assertFieldEquals($redirectUrl, $res6['json']['data']['redirect_url'] ?? '', 'redirect_url', 'Verify 6: redirect_url matches developer URL');

    // Confirm transaction status is now SUCCESS in database
    $stmtCheck->execute([$vtxTxId]);
    $statusNow = $stmtCheck->fetchColumn();
    TestHelper::assertFieldEquals('SUCCESS', $statusNow, 'status', 'Verify 6: Transaction status successfully updated to SUCCESS');

    // 7. Duplicate verification request (Idempotent replay)
    $res7 = TestHelper::request('POST', $endpoint, [
        'vortex_transaction_id' => $vtxTxId,
        'razorpay_order_id'     => $rzpOrderId,
        'razorpay_payment_id'   => $fakePaymentId,
        'razorpay_signature'    => $validSignature,
        'session_token'         => $token
    ]);
    TestHelper::assertStatus(200, $res7['http_code'], 'Verify 7: Duplicate verification returns HTTP 200');
    TestHelper::assertFieldEquals('Payment already verified.', $res7['json']['message'] ?? '', 'message', 'Verify 7: Idempotent message returned');

    return true;
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    run_payment_verification_tests();
}
