<?php

/**
 * ------------------------------------------------------------
 * test_event.php
 * ------------------------------------------------------------
 * Automated tests for event management endpoint (manage_event.php).
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/TestHelper.php';

function run_event_tests(): ?string
{
    TestHelper::printSuiteHeader('2. Manage Event Tests');

    $client = TestHelper::getPrimaryClient();
    if (!$client) {
        TestHelper::fail('Event Setup', 'No active API client available');
        return null;
    }

    $endpoint = '/api/manage_event.php';
    $auth = [
        'api_key'    => $client['api_key'],
        'api_secret' => $client['api_secret']
    ];

    // 1. Create event with valid data
    $uniqueName = 'Vortex Automated Test Event ' . date('Ymd_His') . '_' . bin2hex(random_bytes(2));
    $startDate = date('Y-m-d H:i:s', strtotime('+1 day'));
    $endDate = date('Y-m-d H:i:s', strtotime('+3 days'));

    $res1 = TestHelper::request('POST', $endpoint, array_merge($auth, [
        'action'      => 'create',
        'event_name'  => $uniqueName,
        'department'  => 'Computer Engineering',
        'start_date'  => $startDate,
        'end_date'    => $endDate
    ]));

    TestHelper::assertStatus(200, $res1['http_code'], 'Event 1: Create event HTTP 200');
    TestHelper::assertJsonStatus('success', $res1['json'], 'Event 1: Create event JSON status success');
    TestHelper::assertFieldExists('event_id', $res1['json']['data'] ?? [], 'Event 1: event_id generated');

    $createdEventId = $res1['json']['data']['event_id'] ?? null;
    if ($createdEventId) {
        TestHelper::trackEventId($createdEventId);
    }

    // 2. Create event with missing event_name
    $res2 = TestHelper::request('POST', $endpoint, array_merge($auth, [
        'action'      => 'create',
        'department'  => 'IT',
        'start_date'  => $startDate,
        'end_date'    => $endDate
    ]));
    TestHelper::assertStatus(400, $res2['http_code'], 'Event 2: Missing event_name HTTP 400');
    TestHelper::assertFieldEquals('Event name is required.', $res2['json']['message'] ?? '', 'message', 'Event 2: Missing event_name message');

    // 3. Create event with missing department
    $res3 = TestHelper::request('POST', $endpoint, array_merge($auth, [
        'action'      => 'create',
        'event_name'  => 'Test Event',
        'start_date'  => $startDate,
        'end_date'    => $endDate
    ]));
    TestHelper::assertStatus(400, $res3['http_code'], 'Event 3: Missing department HTTP 400');
    TestHelper::assertFieldEquals('Department is required.', $res3['json']['message'] ?? '', 'message', 'Event 3: Missing department message');

    // 4. Create event with missing start_date
    $res4 = TestHelper::request('POST', $endpoint, array_merge($auth, [
        'action'      => 'create',
        'event_name'  => 'Test Event',
        'department'  => 'IT',
        'end_date'    => $endDate
    ]));
    TestHelper::assertStatus(400, $res4['http_code'], 'Event 4: Missing start_date HTTP 400');
    TestHelper::assertFieldEquals('Start date is required.', $res4['json']['message'] ?? '', 'message', 'Event 4: Missing start_date message');

    // 5. Create event with missing end_date
    $res5 = TestHelper::request('POST', $endpoint, array_merge($auth, [
        'action'      => 'create',
        'event_name'  => 'Test Event',
        'department'  => 'IT',
        'start_date'  => $startDate
    ]));
    TestHelper::assertStatus(400, $res5['http_code'], 'Event 5: Missing end_date HTTP 400');
    TestHelper::assertFieldEquals('End date is required.', $res5['json']['message'] ?? '', 'message', 'Event 5: Missing end_date message');

    // 6. Invalid date range (end_date <= start_date)
    $res6 = TestHelper::request('POST', $endpoint, array_merge($auth, [
        'action'      => 'create',
        'event_name'  => 'Test Event Inverted Dates',
        'department'  => 'IT',
        'start_date'  => $endDate,
        'end_date'    => $startDate
    ]));
    TestHelper::assertStatus(400, $res6['http_code'], 'Event 6: Inverted dates HTTP 400');
    TestHelper::assertFieldEquals('End date must be later than start date.', $res6['json']['message'] ?? '', 'message', 'Event 6: Date range validation');

    // 7. Get existing event
    if ($createdEventId) {
        $res7 = TestHelper::request('POST', $endpoint, array_merge($auth, [
            'action'   => 'get',
            'event_id' => $createdEventId
        ]));
        TestHelper::assertStatus(200, $res7['http_code'], 'Event 7: Get existing event HTTP 200');
        TestHelper::assertFieldEquals($createdEventId, $res7['json']['data']['event_id'] ?? '', 'event_id', 'Event 7: Retrieved matching event_id');
        TestHelper::assertFieldEquals(1, $res7['json']['data']['is_active'] ?? 0, 'is_active', 'Event 7: Event is currently active');
    } else {
        TestHelper::skip('Event 7: Get existing event', 'Event creation did not return an event_id');
    }

    // 8. Get nonexistent event
    $res8 = TestHelper::request('POST', $endpoint, array_merge($auth, [
        'action'   => 'get',
        'event_id' => 'NONEXISTENT_EVENT_99999'
    ]));
    TestHelper::assertStatus(404, $res8['http_code'], 'Event 8: Nonexistent event HTTP 404');
    TestHelper::assertFieldEquals('Event not found or is inactive.', $res8['json']['message'] ?? '', 'message', 'Event 8: Nonexistent message');

    // 9. Deactivate event
    if ($createdEventId) {
        $res9 = TestHelper::request('POST', $endpoint, array_merge($auth, [
            'action'   => 'deactivate',
            'event_id' => $createdEventId
        ]));
        TestHelper::assertStatus(200, $res9['http_code'], 'Event 9: Deactivate event HTTP 200');
        TestHelper::assertFieldEquals('Event deactivated successfully.', $res9['json']['message'] ?? '', 'message', 'Event 9: Deactivate message');

        // 10. Verify deactivation (API returns 404 and DB has is_active = 0)
        $res10 = TestHelper::request('POST', $endpoint, array_merge($auth, [
            'action'   => 'get',
            'event_id' => $createdEventId
        ]));
        TestHelper::assertStatus(404, $res10['http_code'], 'Event 10: Deactivated event returns HTTP 404 on get');

        $dbRow = TestHelper::getDb()->query("SELECT is_active FROM events WHERE event_id = " . TestHelper::getDb()->quote($createdEventId))->fetch(PDO::FETCH_ASSOC);
        TestHelper::assertFieldEquals(0, (int) ($dbRow['is_active'] ?? 1), 'is_active', 'Event 10: Database is_active is 0');
    } else {
        TestHelper::skip('Event 9 & 10: Deactivate event', 'No test event available');
    }

    // 11. Activate event (document if supported)
    // As designed, manage_event.php supports create, get, and deactivate.
    TestHelper::skip('Event 11: Activate event', 'manage_event.php currently implements create, get, and deactivate operations');

    // 12. Invalid action
    $res12 = TestHelper::request('POST', $endpoint, array_merge($auth, [
        'action' => 'drop_table'
    ]));
    TestHelper::assertStatus(400, $res12['http_code'], 'Event 12: Invalid action HTTP 400');
    TestHelper::assertFieldEquals('Invalid action. Supported actions are create, get, and deactivate.', $res12['json']['message'] ?? '', 'message', 'Event 12: Invalid action message');

    // 13. Invalid JSON
    $res13 = TestHelper::request('POST', $endpoint, '{ not json');
    TestHelper::assertStatus(400, $res13['http_code'], 'Event 13: Malformed JSON HTTP 400');

    // 14. Wrong HTTP Method (GET)
    $res14 = TestHelper::request('GET', $endpoint);
    TestHelper::assertStatus(405, $res14['http_code'], 'Event 14: GET method rejected with HTTP 405');

    return $createdEventId;
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    run_event_tests();
}
