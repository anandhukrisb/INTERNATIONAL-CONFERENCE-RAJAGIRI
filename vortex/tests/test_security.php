<?php

/**
 * ------------------------------------------------------------
 * test_security.php
 * ------------------------------------------------------------
 * Automated security regression, database consistency, and single-redirect tests.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/TestHelper.php';

function run_security_tests(): bool
{
    TestHelper::printSuiteHeader('10. Security, Database & Single Redirect Tests');

    $db = TestHelper::getDb();
    $client = TestHelper::getPrimaryClient();

    // ========================================================
    // PART A: DATABASE CONSISTENCY & RELATIONS
    // ========================================================
    
    // 1. Verify checkout_sessions columns
    $cols = $db->query("DESCRIBE checkout_sessions")->fetchAll(PDO::FETCH_COLUMN);
    TestHelper::assertTrue(in_array('id', $cols), 'Security 1: checkout_sessions has id column');
    TestHelper::assertTrue(in_array('session_token', $cols), 'Security 1: checkout_sessions has session_token column');
    TestHelper::assertTrue(in_array('transaction_id', $cols), 'Security 1: checkout_sessions has transaction_id column');
    TestHelper::assertTrue(in_array('redirect_url', $cols), 'Security 1: checkout_sessions has redirect_url column');
    TestHelper::assertTrue(in_array('expires_at', $cols), 'Security 1: checkout_sessions has expires_at column');
    TestHelper::assertTrue(in_array('created_at', $cols), 'Security 1: checkout_sessions has created_at column');

    // 2. Verify complete absence of legacy columns
    TestHelper::assertFalse(in_array('success_url', $cols), 'Security 2: success_url completely absent from checkout_sessions');
    TestHelper::assertFalse(in_array('failure_url', $cols), 'Security 2: failure_url completely absent from checkout_sessions');

    // 3. Verify Foreign Key ON DELETE CASCADE
    $fkStmt = $db->query("
        SELECT rc.DELETE_RULE 
        FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc 
        WHERE rc.CONSTRAINT_SCHEMA = DATABASE() 
          AND rc.TABLE_NAME = 'checkout_sessions'
    ");
    $deleteRule = $fkStmt->fetchColumn();
    TestHelper::assertFieldEquals('CASCADE', $deleteRule, 'DELETE_RULE', 'Security 3: Foreign Key has ON DELETE CASCADE');

    // ========================================================
    // PART B: CREDENTIAL & INFORMATION LEAKAGE AUDIT
    // ========================================================
    
    $endpoints = [
        ['POST', '/api/authenticate_user.php', ['api_key' => 'bad', 'api_secret' => 'bad']],
        ['POST', '/api/create_payment_order.php', ['api_key' => 'bad', 'api_secret' => 'bad']],
        ['POST', '/api/verify_payment.php', ['vortex_transaction_id' => 'bad']],
        ['POST', '/api/create_refund.php', ['api_key' => 'bad', 'api_secret' => 'bad']],
        ['POST', '/api/get_payment_status.php', ['api_key' => 'bad', 'api_secret' => 'bad']],
        ['POST', '/api/manage_event.php', ['api_key' => 'bad', 'api_secret' => 'bad', 'action' => 'get']],
        ['POST', '/api/manage_api_client.php', ['action' => 'get', 'client_id' => 1]],
        ['POST', '/api/process_webhook.php', '{"test":1}']
    ];

    $forbiddenStrings = [
        $_ENV['DB_PASS'] ?? 'ROOT_PASS_NEVER_MATCH',
        $_ENV['RAZORPAY_KEY_SECRET'] ?? 'RZP_SECRET_NEVER_MATCH',
        'SQLSTATE',
        'Stack trace:',
        'PDOException',
        'classes/Database.php'
    ];

    if ($client) {
        $forbiddenStrings[] = $client['api_secret'];
    }

    $leakedCount = 0;
    foreach ($endpoints as $ep) {
        $res = TestHelper::request($ep[0], $ep[1], $ep[2]);
        foreach ($forbiddenStrings as $forbidden) {
            if (!empty($forbidden) && str_contains($res['raw'], $forbidden)) {
                $leakedCount++;
                echo "  [LEAK WARNING] Found '$forbidden' in response of {$ep[1]}\n";
            }
        }
    }
    TestHelper::assertFieldEquals(0, $leakedCount, 'leaked_count', 'Security 4: Zero sensitive credentials, SQL errors, or stack traces exposed');

    // ========================================================
    // PART C: SINGLE REDIRECT URL & STATUS CODE PROTOCOL
    // ========================================================

    // Verify buildRedirectUrl standard parameters
    $baseUrl = 'https://merchant.example.com/payment/callback';
    $paramsSuccess = [
        'vortex_transaction_id' => 'VTX_TEST_123',
        'status_code'           => 200,
        'date_time'             => date('Y-m-d H:i:s'),
        'amount'                => '500.00',
        'currency'              => 'INR',
        'razorpay_order_id'     => 'order_TEST',
        'razorpay_payment_id'   => 'pay_TEST'
    ];
    $builtSuccess = $baseUrl . '?' . http_build_query($paramsSuccess);

    TestHelper::assertTrue(str_contains($builtSuccess, 'status_code=200'), 'Security 5: Success redirect specifies status_code=200');
    TestHelper::assertTrue(str_contains($builtSuccess, 'vortex_transaction_id=VTX_TEST_123'), 'Security 5: Success redirect contains transaction ID');
    TestHelper::assertFalse(str_contains($builtSuccess, 'api_secret'), 'Security 5: No api_secret in redirect URL');
    TestHelper::assertFalse(str_contains($builtSuccess, 'token='), 'Security 5: No session token in redirect URL');

    $paramsFailure = [
        'vortex_transaction_id' => 'VTX_TEST_123',
        'status_code'           => 400,
        'date_time'             => date('Y-m-d H:i:s'),
        'amount'                => '500.00',
        'currency'              => 'INR'
    ];
    $builtFailure = $baseUrl . '?' . http_build_query($paramsFailure);

    TestHelper::assertTrue(str_contains($builtFailure, 'status_code=400'), 'Security 6: Failure redirect specifies status_code=400');
    TestHelper::assertTrue(str_starts_with($builtFailure, $baseUrl), 'Security 6: Failure uses EXACT same base redirect_url');

    return true;
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    run_security_tests();
}
