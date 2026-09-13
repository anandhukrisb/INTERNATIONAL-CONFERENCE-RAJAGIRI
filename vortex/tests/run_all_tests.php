<?php

/**
 * ------------------------------------------------------------
 * run_all_tests.php
 * ------------------------------------------------------------
 * Master Test Runner for the Vortex Unified Payment Gateway.
 *
 * Usage:
 * php tests/run_all_tests.php
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/TestHelper.php';
require_once __DIR__ . '/test_authentication.php';
require_once __DIR__ . '/test_event.php';
require_once __DIR__ . '/test_api_client.php';
require_once __DIR__ . '/test_payment.php';
require_once __DIR__ . '/test_checkout_session.php';
require_once __DIR__ . '/test_payment_status.php';
require_once __DIR__ . '/test_payment_verification.php';
require_once __DIR__ . '/test_refund.php';
require_once __DIR__ . '/test_webhook.php';
require_once __DIR__ . '/test_security.php';

echo "========================================\n";
echo " STARTING VORTEX TEST SUITE RUNNER\n";
echo "========================================\n";

// ------------------------------------------------------------
// 1. Verify Target Base URL & Health Check
// ------------------------------------------------------------

$baseUrl = TestHelper::getBaseUrl();
echo "Target Base URL: $baseUrl\n";
echo "Testing base URL health at $baseUrl/api/health_check.php...\n";

register_shutdown_function(function () {
    TestHelper::cleanup();
});

$healthRes = TestHelper::request('GET', '/api/health_check.php');

if ($healthRes['http_code'] !== 200 || ($healthRes['json']['status'] ?? '') !== 'success') {
    echo "\n[ERROR] Health check failed against $baseUrl/api/health_check.php!\n";
    echo "HTTP Status Code: " . $healthRes['http_code'] . "\n";
    if (!empty($healthRes['raw'])) {
        echo "Response: " . $healthRes['raw'] . "\n";
    }
    echo "\nAborting test execution: Target server is not healthy or unreachable.\n";
    exit(1);
}

echo "Health Check Result: " . ($healthRes['json']['message'] ?? 'OK') . " (Status: " . ($healthRes['json']['data']['status'] ?? 'UP') . ")\n";

// ------------------------------------------------------------
// 2. Verify Razorpay Environment Safety
// ------------------------------------------------------------

$isTestMode = TestHelper::isRazorpayTestMode();
$keyId = $_ENV['RAZORPAY_KEY_ID'] ?? '';

echo "Razorpay Key: " . TestHelper::maskSecret($keyId, 8) . "\n";
if ($isTestMode) {
    echo "Environment:  RAZORPAY TEST MODE CONFIRMED (Safe for automated tests)\n";
} else {
    echo "ENVIRONMENT WARNING: Not in Razorpay Test Mode! Live payments will be skipped.\n";
}

// ------------------------------------------------------------
// 3. Execute Test Suites
// ------------------------------------------------------------

$suiteResults = [];

// 1. Authentication
$beforeFailures = count(TestHelper::getCounts()['failures']);
run_authentication_tests();
$suiteResults['Authentication'] = count(TestHelper::getCounts()['failures']) === $beforeFailures ? 'PASS' : 'FAIL';

// 2. Manage Event
$beforeFailures = count(TestHelper::getCounts()['failures']);
run_event_tests();
$suiteResults['Manage Event'] = count(TestHelper::getCounts()['failures']) === $beforeFailures ? 'PASS' : 'FAIL';

// 3. Manage API Client
$beforeFailures = count(TestHelper::getCounts()['failures']);
run_api_client_tests();
$suiteResults['Manage API Client'] = count(TestHelper::getCounts()['failures']) === $beforeFailures ? 'PASS' : 'FAIL';

// 4. Payment Order
$beforeFailures = count(TestHelper::getCounts()['failures']);
$paymentContext = run_payment_tests();
$suiteResults['Payment Order'] = count(TestHelper::getCounts()['failures']) === $beforeFailures ? 'PASS' : 'FAIL';

// 5. Checkout Session
$beforeFailures = count(TestHelper::getCounts()['failures']);
$checkoutContext = run_checkout_session_tests($paymentContext);
$suiteResults['Checkout Session'] = count(TestHelper::getCounts()['failures']) === $beforeFailures ? 'PASS' : 'FAIL';

// 6. Payment Status
$beforeFailures = count(TestHelper::getCounts()['failures']);
run_payment_status_tests($paymentContext);
$suiteResults['Payment Status'] = count(TestHelper::getCounts()['failures']) === $beforeFailures ? 'PASS' : 'FAIL';

// 7. Payment Verification
$beforeFailures = count(TestHelper::getCounts()['failures']);
run_payment_verification_tests($checkoutContext);
$suiteResults['Payment Verification'] = count(TestHelper::getCounts()['failures']) === $beforeFailures ? 'PASS' : 'FAIL';

// 8. Refund
$beforeFailures = count(TestHelper::getCounts()['failures']);
run_refund_tests();
$suiteResults['Refund'] = count(TestHelper::getCounts()['failures']) === $beforeFailures ? 'PASS' : 'FAIL';

// 9. Webhook
$beforeFailures = count(TestHelper::getCounts()['failures']);
run_webhook_tests();
$suiteResults['Webhook'] = count(TestHelper::getCounts()['failures']) === $beforeFailures ? 'PASS' : 'FAIL';

// 10. Security, Database, Single Redirect
$beforeFailures = count(TestHelper::getCounts()['failures']);
run_security_tests();
$securityPass = count(TestHelper::getCounts()['failures']) === $beforeFailures;
$suiteResults['Security'] = $securityPass ? 'PASS' : 'FAIL';
$suiteResults['Database'] = $securityPass ? 'PASS' : 'FAIL';
$suiteResults['Single Redirect'] = $securityPass ? 'PASS' : 'FAIL';

// ------------------------------------------------------------
// 4. Safe Resource Cleanup
// ------------------------------------------------------------

TestHelper::cleanup();

// ------------------------------------------------------------
// 5. Output Summary Report
// ------------------------------------------------------------

$counts = TestHelper::getCounts();
$totalTests = $counts['passed'] + $counts['failed'] + $counts['skipped'];

echo "\n";
echo "========================================\n";
echo " VORTEX AUTOMATED TEST SUITE\n";
echo "========================================\n\n";

foreach ($suiteResults as $suiteName => $status) {
    printf("%-21s %s\n", $suiteName, $status);
}

echo "\n----------------------------------------\n";
printf("Total Tests: %d\n", $totalTests);
printf("Passed:      %d\n", $counts['passed']);
printf("Failed:      %d\n", $counts['failed']);
printf("Skipped:     %d\n", $counts['skipped']);
echo "----------------------------------------\n";

$curlErrors = TestHelper::getCurlErrors();
if (!empty($curlErrors)) {
    echo "\ncURL Errors Encountered (" . count($curlErrors) . "):\n";
    foreach ($curlErrors as $idx => $err) {
        printf("  %d. %s\n", $idx + 1, $err);
    }
} else {
    echo "cURL Errors: None\n";
}
echo "----------------------------------------\n\n";

if ($counts['failed'] === 0) {
    echo "RESULT: ALL AUTOMATED TESTS PASSED\n";
    echo "========================================\n";
    exit(0);
} else {
    echo "RESULT: TESTS FAILED\n";
    echo "========================================\n\n";
    echo "FAILURES SUMMARY:\n";
    foreach ($counts['failures'] as $i => $f) {
        printf("  %d. %s: %s\n", $i + 1, $f['test'], $f['reason']);
    }
    exit(1);
}
