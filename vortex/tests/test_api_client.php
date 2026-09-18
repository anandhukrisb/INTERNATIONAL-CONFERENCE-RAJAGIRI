<?php

/**
 * ------------------------------------------------------------
 * test_api_client.php
 * ------------------------------------------------------------
 * Automated tests for API client management endpoint (manage_api_client.php).
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/TestHelper.php';

function run_api_client_tests(): ?int
{
    TestHelper::printSuiteHeader('3. Manage API Client Tests');

    $endpoint = '/api/manage_api_client.php';
    $uniqueClientName = 'Vortex Test Client ' . date('Ymd_His') . '_' . bin2hex(random_bytes(2));

    // 1. Create client
    $res1 = TestHelper::request('POST', $endpoint, [
        'action'      => 'create',
        'client_name' => $uniqueClientName
    ]);
    TestHelper::assertStatus(200, $res1['http_code'], 'ApiClient 1: Create client HTTP 200');
    TestHelper::assertJsonStatus('success', $res1['json'], 'ApiClient 1: Create client JSON status success');

    $clientId = $res1['json']['data']['client_id'] ?? null;
    $apiKey = $res1['json']['data']['api_key'] ?? null;
    $apiSecret = $res1['json']['data']['api_secret'] ?? null;

    if ($clientId) {
        TestHelper::trackClientId((int) $clientId);
    }

    // 2. Verify database insertion
    $dbRow = null;
    if ($clientId) {
        $stmt = TestHelper::getDb()->prepare("SELECT * FROM api_clients WHERE id = ?");
        $stmt->execute([$clientId]);
        $dbRow = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    TestHelper::assertTrue(!empty($dbRow), 'ApiClient 2: Client record exists in api_clients table');

    // 3. Verify returned client_id
    TestHelper::assertTrue(!empty($clientId) && is_int($clientId), 'ApiClient 3: Valid integer client_id returned');

    // 4. Verify returned api_key
    TestHelper::assertTrue(!empty($apiKey) && strlen($apiKey) >= 16, 'ApiClient 4: Valid api_key returned');

    // 5. Verify returned api_secret ONLY during creation
    TestHelper::assertTrue(!empty($apiSecret) && strlen($apiSecret) >= 16, 'ApiClient 5: api_secret returned at creation');

    // 6 & 7. Get client & verify GET does NOT return api_secret
    if ($clientId) {
        $res6 = TestHelper::request('POST', $endpoint, [
            'action'    => 'get',
            'client_id' => $clientId
        ]);
        TestHelper::assertStatus(200, $res6['http_code'], 'ApiClient 6: Get client HTTP 200');
        TestHelper::assertFieldEquals($clientId, $res6['json']['data']['client_id'] ?? 0, 'client_id', 'ApiClient 6: Matching client_id in get');
        TestHelper::assertFieldEquals(1, $res6['json']['data']['is_active'] ?? 0, 'is_active', 'ApiClient 6: Default is_active is 1');
        TestHelper::assertFieldNotExists('api_secret', $res6['json']['data'] ?? [], 'ApiClient 7: GET response does NOT contain api_secret');
    } else {
        TestHelper::skip('ApiClient 6 & 7: Get client', 'No client_id created');
    }

    // 8. Get nonexistent client (404)
    $res8 = TestHelper::request('POST', $endpoint, [
        'action'    => 'get',
        'client_id' => 9999999
    ]);
    TestHelper::assertStatus(404, $res8['http_code'], 'ApiClient 8: Nonexistent client returns HTTP 404');
    TestHelper::assertFieldEquals('API client not found.', $res8['json']['message'] ?? '', 'message', 'ApiClient 8: Not found message');

    // 9 & 10. Deactivate client and verify in database
    if ($clientId) {
        $res9 = TestHelper::request('POST', $endpoint, [
            'action'    => 'deactivate',
            'client_id' => $clientId
        ]);
        TestHelper::assertStatus(200, $res9['http_code'], 'ApiClient 9: Deactivate client HTTP 200');

        $stmt = TestHelper::getDb()->prepare("SELECT is_active FROM api_clients WHERE id = ?");
        $stmt->execute([$clientId]);
        $activeVal = (int) $stmt->fetchColumn();
        TestHelper::assertFieldEquals(0, $activeVal, 'is_active', 'ApiClient 10: Database is_active is 0');

        // 11 & 12. Activate client and verify in database
        $res11 = TestHelper::request('POST', $endpoint, [
            'action'    => 'activate',
            'client_id' => $clientId
        ]);
        TestHelper::assertStatus(200, $res11['http_code'], 'ApiClient 11: Activate client HTTP 200');

        $stmt->execute([$clientId]);
        $activeValNow = (int) $stmt->fetchColumn();
        TestHelper::assertFieldEquals(1, $activeValNow, 'is_active', 'ApiClient 12: Database is_active is 1');
    } else {
        TestHelper::skip('ApiClient 9-12: Activate/deactivate client', 'No client_id created');
    }

    // 13. Missing client_id
    $res13 = TestHelper::request('POST', $endpoint, [
        'action' => 'get'
    ]);
    TestHelper::assertStatus(400, $res13['http_code'], 'ApiClient 13: Missing client_id returns HTTP 400');
    TestHelper::assertFieldEquals('Valid client_id is required.', $res13['json']['message'] ?? '', 'message', 'ApiClient 13: Missing client_id message');

    // 14. Invalid client_id
    $res14 = TestHelper::request('POST', $endpoint, [
        'action'    => 'get',
        'client_id' => -10
    ]);
    TestHelper::assertStatus(400, $res14['http_code'], 'ApiClient 14: Negative client_id returns HTTP 400');

    // 15. Invalid action
    $res15 = TestHelper::request('POST', $endpoint, [
        'action' => 'destroy'
    ]);
    TestHelper::assertStatus(400, $res15['http_code'], 'ApiClient 15: Invalid action returns HTTP 400');

    // 16. Invalid JSON
    $res16 = TestHelper::request('POST', $endpoint, '{ broken json');
    TestHelper::assertStatus(400, $res16['http_code'], 'ApiClient 16: Malformed JSON returns HTTP 400');

    // 17. Wrong HTTP method
    $res17 = TestHelper::request('GET', $endpoint);
    TestHelper::assertStatus(405, $res17['http_code'], 'ApiClient 17: GET method rejected with HTTP 405');

    return $clientId;
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    run_api_client_tests();
}
