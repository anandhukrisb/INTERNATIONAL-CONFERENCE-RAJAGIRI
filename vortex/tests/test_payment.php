<?php

/**
 * ------------------------------------------------------------
 * test_payment.php
 * ------------------------------------------------------------
 * Automated tests for payment order creation (create_payment_order.php).
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/TestHelper.php';

function run_payment_tests(): ?array
{
    TestHelper::printSuiteHeader('4. Payment Order Tests');

    // Confirm Razorpay test mode
    if (!TestHelper::isRazorpayTestMode()) {
        TestHelper::skip('Payment Suite', 'Skipped: Razorpay test environment could not be safely confirmed (non-test key detected).');
        return null;
    }

    $client = TestHelper::getPrimaryClient();
    $event = TestHelper::getPrimaryEvent();

    if (!$client || !$event) {
        TestHelper::fail('Payment Setup', 'No active API client or event available');
        return null;
    }

    $endpoint = '/api/create_payment_order.php';
    $validRedirectUrl = 'https://developer.example.com/payment/callback';

    $basePayload = [
        'api_key'      => $client['api_key'],
        'api_secret'   => $client['api_secret'],
        'event_id'     => $event['event_id'],
        'email'        => 'customer_autotest@example.com',
        'mobile'       => '9876543210',
        'amount'       => 500.00,
        'currency'     => 'INR',
        'redirect_url' => $validRedirectUrl
    ];

    // 1. Valid payment order creation
    $res1 = TestHelper::request('POST', $endpoint, $basePayload);
    TestHelper::assertStatus(200, $res1['http_code'], 'Payment 1: Valid order creation HTTP 200');
    TestHelper::assertJsonStatus('success', $res1['json'], 'Payment 1: Order creation JSON status success');
    TestHelper::assertFieldExists('vortex_transaction_id', $res1['json']['data'] ?? [], 'Payment 1: vortex_transaction_id exists');
    TestHelper::assertFieldExists('payment_url', $res1['json']['data'] ?? [], 'Payment 1: payment_url exists');
    TestHelper::assertFieldEquals('PENDING', $res1['json']['data']['payment_status'] ?? '', 'payment_status', 'Payment 1: payment_status is PENDING');

    $vtxTxId = $res1['json']['data']['vortex_transaction_id'] ?? null;
    $paymentUrl = $res1['json']['data']['payment_url'] ?? null;

    if ($vtxTxId) {
        $stmt = TestHelper::getDb()->prepare("SELECT id FROM transactions WHERE vortex_transaction_id = ?");
        $stmt->execute([$vtxTxId]);
        $txDbId = (int) $stmt->fetchColumn();
        if ($txDbId > 0) {
            TestHelper::trackTransactionId($txDbId);
        }
    }

    // 2. Missing amount
    $p2 = $basePayload;
    unset($p2['amount']);
    $res2 = TestHelper::request('POST', $endpoint, $p2);
    TestHelper::assertStatus(400, $res2['http_code'], 'Payment 2: Missing amount returns HTTP 400');

    // 3. Invalid amount (<= 0)
    $p3 = array_merge($basePayload, ['amount' => -100]);
    $res3 = TestHelper::request('POST', $endpoint, $p3);
    TestHelper::assertStatus(400, $res3['http_code'], 'Payment 3: Negative amount returns HTTP 400');

    // 4. Missing currency
    $p4 = $basePayload;
    unset($p4['currency']);
    $res4 = TestHelper::request('POST', $endpoint, $p4);
    TestHelper::assertStatus(400, $res4['http_code'], 'Payment 4: Missing currency returns HTTP 400');

    // 5. Invalid currency (EUR not in INR/USD allowed list)
    $p5 = array_merge($basePayload, ['currency' => 'EUR']);
    $res5 = TestHelper::request('POST', $endpoint, $p5);
    TestHelper::assertStatus(400, $res5['http_code'], 'Payment 5: Invalid currency returns HTTP 400');

    // 6. Missing event_id
    $p6 = $basePayload;
    unset($p6['event_id']);
    $res6 = TestHelper::request('POST', $endpoint, $p6);
    TestHelper::assertStatus(400, $res6['http_code'], 'Payment 6: Missing event_id returns HTTP 400');

    // 7. Invalid event_id
    $p7 = array_merge($basePayload, ['event_id' => 'NONEXISTENT_EVENT_ID_999']);
    $res7 = TestHelper::request('POST', $endpoint, $p7);
    TestHelper::assertStatus(404, $res7['http_code'], 'Payment 7: Nonexistent event_id returns HTTP 404');

    // 8. Missing customer details (email)
    $p8 = $basePayload;
    unset($p8['email']);
    $res8 = TestHelper::request('POST', $endpoint, $p8);
    TestHelper::assertStatus(400, $res8['http_code'], 'Payment 8: Missing customer email returns HTTP 400');

    // 9. Missing redirect_url
    $p9 = $basePayload;
    unset($p9['redirect_url']);
    $res9 = TestHelper::request('POST', $endpoint, $p9);
    TestHelper::assertStatus(400, $res9['http_code'], 'Payment 9: Missing redirect_url returns HTTP 400');

    // 10. Invalid redirect_url scheme
    $p10 = array_merge($basePayload, ['redirect_url' => 'javascript:alert(1)']);
    $res10 = TestHelper::request('POST', $endpoint, $p10);
    TestHelper::assertStatus(400, $res10['http_code'], 'Payment 10: Dangerous redirect_url scheme returns HTTP 400');

    // 11. Invalid API credentials
    $p11 = array_merge($basePayload, ['api_key' => 'invalid_key']);
    $res11 = TestHelper::request('POST', $endpoint, $p11);
    TestHelper::assertStatus(401, $res11['http_code'], 'Payment 11: Invalid API key returns HTTP 401');

    // 12. Invalid JSON
    $res12 = TestHelper::request('POST', $endpoint, '{ broken json');
    TestHelper::assertStatus(400, $res12['http_code'], 'Payment 12: Malformed JSON returns HTTP 400');

    return [
        'vortex_transaction_id' => $vtxTxId,
        'payment_url'           => $paymentUrl,
        'redirect_url'          => $validRedirectUrl,
        'client'                => $client,
        'event'                 => $event,
        'amount'                => 500.00,
        'currency'              => 'INR'
    ];
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    run_payment_tests();
}
