<?php

/**
 * ------------------------------------------------------------
 * test_refund.php
 * ------------------------------------------------------------
 * Automated tests for refund creation and validations (create_refund.php).
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/TestHelper.php';

function run_refund_tests(): bool
{
    TestHelper::printSuiteHeader('8. Refund Tests');

    if (!TestHelper::isRazorpayTestMode()) {
        TestHelper::skip('Refund Suite', 'Skipped: Razorpay test environment could not be safely confirmed.');
        return false;
    }

    $client = TestHelper::getPrimaryClient();
    if (!$client) {
        TestHelper::fail('Refund Setup', 'No active API client available');
        return false;
    }

    $endpoint = '/api/create_refund.php';
    $auth = [
        'api_key'    => $client['api_key'],
        'api_secret' => $client['api_secret']
    ];

    // 1. Inspect existing verified test refund in the database
    $db = TestHelper::getDb();
    $existingRefundTx = 'VTX1788286930702ED81AD8B';
    $stmt = $db->prepare("SELECT t.id, t.vortex_transaction_id, t.amount, t.status, r.razorpay_refund_id, r.status AS refund_status, r.amount AS refund_amount 
                          FROM transactions t 
                          INNER JOIN refunds r ON t.id = r.transaction_id 
                          WHERE t.vortex_transaction_id = ? LIMIT 1");
    $stmt->execute([$existingRefundTx]);
    $existingRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingRecord) {
        TestHelper::assertTrue(!empty($existingRecord['razorpay_refund_id']), 'Refund 1: Existing test refund ID verified in database');
        TestHelper::assertTrue((float) $existingRecord['refund_amount'] > 0, 'Refund 1: Existing refund amount verified');
        echo "  [INFO] Verified existing test refund: " . TestHelper::maskSecret($existingRecord['razorpay_refund_id'], 6) . " (Status: {$existingRecord['refund_status']})\n";
    } else {
        TestHelper::skip('Refund 1: Existing test refund check', 'Historical refund record not found in local database');
    }

    // 2. Refund nonexistent transaction (404)
    $res2 = TestHelper::request('POST', $endpoint, array_merge($auth, [
        'vortex_transaction_id' => 'VTX_NONEXISTENT_999999',
        'amount'                => 50.00
    ]));
    TestHelper::assertStatus(404, $res2['http_code'], 'Refund 2: Nonexistent transaction returns HTTP 404');
    TestHelper::assertFieldEquals('Transaction not found.', $res2['json']['message'] ?? '', 'message', 'Refund 2: Not found message');

    // 3. Refund unsuccessful transaction (PENDING status -> 400)
    // Find or create a pending transaction for this client
    $pendingTx = $db->query("SELECT vortex_transaction_id FROM transactions WHERE api_client_id = {$client['id']} AND status = 'PENDING' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($pendingTx) {
        $res3 = TestHelper::request('POST', $endpoint, array_merge($auth, [
            'vortex_transaction_id' => $pendingTx['vortex_transaction_id'],
            'amount'                => 10.00
        ]));
        TestHelper::assertStatus(400, $res3['http_code'], 'Refund 3: Refund rejected for non-SUCCESS transaction');
        TestHelper::assertFieldEquals('Only successful transactions can be refunded.', $res3['json']['message'] ?? '', 'message', 'Refund 3: Non-success message');
    } else {
        TestHelper::skip('Refund 3: Non-SUCCESS refund', 'No pending transaction available to test');
    }

    // 4. Refund amount <= 0
    $res4 = TestHelper::request('POST', $endpoint, array_merge($auth, [
        'vortex_transaction_id' => $existingRefundTx,
        'amount'                => -50.00
    ]));
    TestHelper::assertStatus(400, $res4['http_code'], 'Refund 4: Negative refund amount returns HTTP 400');

    $res4b = TestHelper::request('POST', $endpoint, array_merge($auth, [
        'vortex_transaction_id' => $existingRefundTx,
        'amount'                => 0
    ]));
    TestHelper::assertStatus(400, $res4b['http_code'], 'Refund 4: Zero refund amount returns HTTP 400');

    // 5. Refund amount greater than transaction amount
    // If a success transaction exists, test excessive amount
    $successTx = $db->query("SELECT vortex_transaction_id, amount FROM transactions WHERE api_client_id = {$client['id']} AND status = 'SUCCESS' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($successTx) {
        $excessiveAmount = (float) $successTx['amount'] + 10000.00;
        $res5 = TestHelper::request('POST', $endpoint, array_merge($auth, [
            'vortex_transaction_id' => $successTx['vortex_transaction_id'],
            'amount'                => $excessiveAmount
        ]));
        TestHelper::assertStatus(400, $res5['http_code'], 'Refund 5: Excessive refund amount returns HTTP 400');
    } else {
        TestHelper::skip('Refund 5: Excessive refund amount', 'No successful transaction available');
    }

    // 6. Invalid credentials (401)
    $res6 = TestHelper::request('POST', $endpoint, [
        'api_key'               => 'invalid_key',
        'api_secret'            => 'invalid_secret',
        'vortex_transaction_id' => $existingRefundTx,
        'amount'                => 10.00
    ]);
    TestHelper::assertStatus(401, $res6['http_code'], 'Refund 6: Invalid credentials returns HTTP 401');

    // 7. Invalid JSON (400)
    $res7 = TestHelper::request('POST', $endpoint, '{ malformed json');
    TestHelper::assertStatus(400, $res7['http_code'], 'Refund 7: Malformed JSON returns HTTP 400');

    // 8. Missing transaction ID (400)
    $res8 = TestHelper::request('POST', $endpoint, array_merge($auth, [
        'amount' => 50.00
    ]));
    TestHelper::assertStatus(400, $res8['http_code'], 'Refund 8: Missing transaction ID returns HTTP 400');

    return true;
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    run_refund_tests();
}
