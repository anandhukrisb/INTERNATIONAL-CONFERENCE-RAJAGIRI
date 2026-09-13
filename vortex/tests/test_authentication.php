<?php

/**
 * ------------------------------------------------------------
 * test_authentication.php
 * ------------------------------------------------------------
 * Automated tests for developer authentication endpoint (authenticate_user.php).
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/TestHelper.php';

function run_authentication_tests(): bool
{
    TestHelper::printSuiteHeader('1. Authentication Tests');

    $client = TestHelper::getPrimaryClient();
    if (!$client) {
        TestHelper::fail('Authentication Setup', 'No active API client available in database');
        return false;
    }

    $endpoint = '/api/authenticate_user.php';

    // 1. Valid API credentials
    $res1 = TestHelper::request('POST', $endpoint, [
        'api_key'    => $client['api_key'],
        'api_secret' => $client['api_secret']
    ]);
    TestHelper::assertStatus(200, $res1['http_code'], 'Auth 1: Valid credentials HTTP 200');
    TestHelper::assertJsonStatus('success', $res1['json'], 'Auth 1: Valid credentials JSON status success');
    TestHelper::assertFieldEquals('Authentication successful.', $res1['json']['message'] ?? '', 'message', 'Auth 1: Valid credentials message');

    // 2. Invalid API key
    $res2 = TestHelper::request('POST', $endpoint, [
        'api_key'    => 'invalid_key_999999',
        'api_secret' => $client['api_secret']
    ]);
    TestHelper::assertStatus(401, $res2['http_code'], 'Auth 2: Invalid API key HTTP 401');
    TestHelper::assertJsonStatus('error', $res2['json'], 'Auth 2: Invalid API key JSON status error');
    TestHelper::assertFieldEquals('Invalid API credentials.', $res2['json']['message'] ?? '', 'message', 'Auth 2: Invalid API key message');

    // 3. Invalid API secret
    $res3 = TestHelper::request('POST', $endpoint, [
        'api_key'    => $client['api_key'],
        'api_secret' => 'invalid_secret_999999'
    ]);
    TestHelper::assertStatus(401, $res3['http_code'], 'Auth 3: Invalid API secret HTTP 401');
    TestHelper::assertJsonStatus('error', $res3['json'], 'Auth 3: Invalid API secret JSON status error');
    TestHelper::assertFieldEquals('Invalid API credentials.', $res3['json']['message'] ?? '', 'message', 'Auth 3: Invalid API secret message');

    // 4. Missing API key
    $res4 = TestHelper::request('POST', $endpoint, [
        'api_secret' => $client['api_secret']
    ]);
    TestHelper::assertStatus(400, $res4['http_code'], 'Auth 4: Missing API key HTTP 400');
    TestHelper::assertFieldEquals('API key is required.', $res4['json']['message'] ?? '', 'message', 'Auth 4: Missing API key message');

    // 5. Missing API secret
    $res5 = TestHelper::request('POST', $endpoint, [
        'api_key' => $client['api_key']
    ]);
    TestHelper::assertStatus(400, $res5['http_code'], 'Auth 5: Missing API secret HTTP 400');
    TestHelper::assertFieldEquals('API secret is required.', $res5['json']['message'] ?? '', 'message', 'Auth 5: Missing API secret message');

    // 6. Invalid JSON
    $res6 = TestHelper::request('POST', $endpoint, '{ malformed: json ...');
    TestHelper::assertStatus(400, $res6['http_code'], 'Auth 6: Malformed JSON HTTP 400');
    TestHelper::assertFieldEquals('Invalid JSON request.', $res6['json']['message'] ?? '', 'message', 'Auth 6: Malformed JSON message');

    // 7. Empty body / missing required fields
    $res7 = TestHelper::request('POST', $endpoint, []);
    TestHelper::assertStatus(400, $res7['http_code'], 'Auth 7: Empty body HTTP 400');

    // 8. Credential Leak Audit: confirm secrets never returned in any response
    $responsesToCheck = [$res1, $res2, $res3, $res4, $res5, $res6, $res7];
    $leaked = false;
    foreach ($responsesToCheck as $r) {
        if (str_contains($r['raw'], $client['api_secret'])) {
            $leaked = true;
        }
    }
    TestHelper::assertFalse($leaked, 'Auth 8: API secret is never returned in response bodies');

    return true;
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    run_authentication_tests();
}
