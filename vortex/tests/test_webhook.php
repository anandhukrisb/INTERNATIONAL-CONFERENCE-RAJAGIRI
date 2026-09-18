<?php

/**
 * ------------------------------------------------------------
 * test_webhook.php
 * ------------------------------------------------------------
 * Automated tests for webhook endpoint (process_webhook.php).
 * Note: process_webhook.php and Webhook.php are NOT modified.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/TestHelper.php';

function run_webhook_tests(): bool
{
    TestHelper::printSuiteHeader('9. Webhook Tests');

    $endpoint = '/api/process_webhook.php';

    $webhookSecret = $_ENV['RAZORPAY_WEBHOOK_SECRET'] ?? '';
    $isConfigured = !empty($webhookSecret) && $webhookSecret !== 'the_secret_configured_by_your_system_analyst';

    if (!$isConfigured) {
        TestHelper::skip('Webhook 1: GET method returns HTTP 405', 'FEATURE NOT CONFIGURED: Razorpay webhook secret is not configured.');
        TestHelper::skip('Webhook 1: Method not allowed message', 'FEATURE NOT CONFIGURED: Razorpay webhook secret is not configured.');
        TestHelper::skip('Webhook 2: Empty payload returns HTTP 400', 'FEATURE NOT CONFIGURED: Razorpay webhook secret is not configured.');
        TestHelper::skip('Webhook 2: Empty payload message', 'FEATURE NOT CONFIGURED: Razorpay webhook secret is not configured.');
        TestHelper::skip('Webhook 3: Missing signature returns HTTP 400', 'FEATURE NOT CONFIGURED: Razorpay webhook secret is not configured.');
        TestHelper::skip('Webhook 3: Missing signature message', 'FEATURE NOT CONFIGURED: Razorpay webhook secret is not configured.');
        TestHelper::skip('Webhook 4: Invalid signature returns HTTP 400', 'FEATURE NOT CONFIGURED: Razorpay webhook secret is not configured.');
        TestHelper::skip('Webhook 4: Invalid signature message', 'FEATURE NOT CONFIGURED: Razorpay webhook secret is not configured.');
        TestHelper::skip('Webhook 5: Live webhook event capture', 'Skipped: Live webhook testing requires registered Razorpay webhook endpoint and real incoming event signatures.');
        return true;
    }

    // 1. Wrong HTTP method (GET -> 405)
    $res1 = TestHelper::request('GET', $endpoint);
    TestHelper::assertStatus(405, $res1['http_code'], 'Webhook 1: GET method returns HTTP 405');
    TestHelper::assertFieldEquals('Method not allowed.', $res1['json']['message'] ?? '', 'message', 'Webhook 1: Method not allowed message');

    // 2. Empty payload (POST with empty body -> 400)
    $res2 = TestHelper::request('POST', $endpoint, '');
    TestHelper::assertStatus(400, $res2['http_code'], 'Webhook 2: Empty payload returns HTTP 400');
    TestHelper::assertFieldEquals('Webhook payload is empty.', $res2['json']['message'] ?? '', 'message', 'Webhook 2: Empty payload message');

    // 3. Missing Razorpay signature header (POST with payload but no header -> 400)
    $samplePayload = json_encode(['event' => 'payment.captured', 'payload' => []]);
    $res3 = TestHelper::request('POST', $endpoint, $samplePayload, [
        'Content-Type: application/json'
    ]);
    TestHelper::assertStatus(400, $res3['http_code'], 'Webhook 3: Missing signature returns HTTP 400');
    TestHelper::assertFieldEquals('Missing Razorpay webhook signature.', $res3['json']['message'] ?? '', 'message', 'Webhook 3: Missing signature message');

    // 4. Invalid signature header (POST with invalid signature -> 400)
    $res4 = TestHelper::request('POST', $endpoint, $samplePayload, [
        'Content-Type: application/json',
        'X-Razorpay-Signature: invalid_test_signature_abcdef123456'
    ]);
    TestHelper::assertStatus(400, $res4['http_code'], 'Webhook 4: Invalid signature returns HTTP 400');
    TestHelper::assertFieldEquals('Invalid webhook signature.', $res4['json']['message'] ?? '', 'message', 'Webhook 4: Invalid signature message');

    // 5. Valid Live Webhook Processing
    // Live webhook processing requires receiving genuine live event payloads signed with
    // a real secret configured in Razorpay dashboard. Per project constraints, we do not require
    // a production webhook secret and we must mark this test as SKIPPED.
    TestHelper::skip('Webhook 5: Live webhook event capture', 'Skipped: Live webhook testing requires registered Razorpay webhook endpoint and real incoming event signatures.');

    return true;
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    run_webhook_tests();
}
