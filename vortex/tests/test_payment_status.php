<?php

/**
 * ------------------------------------------------------------
 * test_payment_status.php
 * ------------------------------------------------------------
 * Automated tests for payment status lookup (get_payment_status.php).
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/TestHelper.php';
require_once __DIR__ . '/test_payment.php';

function run_payment_status_tests(?array $paymentContext = null): bool
{
    TestHelper::printSuiteHeader('6. Payment Status Tests');

    if (!$paymentContext || empty($paymentContext['vortex_transaction_id'])) {
        $paymentContext = run_payment_tests();
    }

    if (!$paymentContext || empty($paymentContext['vortex_transaction_id'])) {
        TestHelper::fail('PaymentStatus Setup', 'No active payment transaction available');
        return false;
    }

    $endpoint = '/api/get_payment_status.php';
    $client = $paymentContext['client'];
    $vtxTxId = $paymentContext['vortex_transaction_id'];

    // 1. Existing transaction status lookup
    $res1 = TestHelper::request('POST', $endpoint, [
        'api_key'               => $client['api_key'],
        'api_secret'            => $client['api_secret'],
        'vortex_transaction_id' => $vtxTxId
    ]);
    TestHelper::assertStatus(200, $res1['http_code'], 'Status 1: Existing transaction lookup HTTP 200');
    TestHelper::assertJsonStatus('success', $res1['json'], 'Status 1: Existing transaction JSON status success');
    TestHelper::assertFieldEquals($vtxTxId, $res1['json']['data']['vortex_transaction_id'] ?? '', 'vortex_transaction_id', 'Status 1: vortex_transaction_id matches');
    TestHelper::assertFieldExists('status', $res1['json']['data'] ?? [], 'Status 1: status field exists');

    // 2. Nonexistent transaction ID (404)
    $res2 = TestHelper::request('POST', $endpoint, [
        'api_key'               => $client['api_key'],
        'api_secret'            => $client['api_secret'],
        'vortex_transaction_id' => 'VTX_NONEXISTENT_999999'
    ]);
    TestHelper::assertStatus(404, $res2['http_code'], 'Status 2: Nonexistent transaction returns HTTP 404');
    TestHelper::assertFieldEquals('Transaction not found.', $res2['json']['message'] ?? '', 'message', 'Status 2: Not found message');

    // 3. Invalid API credentials (401)
    $res3 = TestHelper::request('POST', $endpoint, [
        'api_key'               => 'invalid_key',
        'api_secret'            => $client['api_secret'],
        'vortex_transaction_id' => $vtxTxId
    ]);
    TestHelper::assertStatus(401, $res3['http_code'], 'Status 3: Invalid credentials returns HTTP 401');

    // 4. Missing transaction ID (400)
    $res4 = TestHelper::request('POST', $endpoint, [
        'api_key'    => $client['api_key'],
        'api_secret' => $client['api_secret']
    ]);
    TestHelper::assertStatus(400, $res4['http_code'], 'Status 4: Missing transaction ID returns HTTP 400');
    TestHelper::assertFieldEquals('vortex_transaction_id is required.', $res4['json']['message'] ?? '', 'message', 'Status 4: Missing ID message');

    return true;
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    run_payment_status_tests();
}
