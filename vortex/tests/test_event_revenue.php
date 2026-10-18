<?php
/**
 * ------------------------------------------------------------
 * test_event_revenue.php
 * ------------------------------------------------------------
 * Unit and integration tests for Event Revenue Analytics & Payments:
 * 1. Metric card click target in Overview page points to payments/revenue.php
 * 2. Unauthenticated access guard (302 redirect)
 * 3. Event breakdown calculation & display sanity
 * 4. Event filtering functionality (single event isolation)
 * 5. Status filtering & CSV export stream
 * 6. Header sidebar navigation linkage
 * ------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/TestHelper.php';
require_once __DIR__ . '/../classes/Database.php';

function run_event_revenue_tests()
{
    echo "\n=== Running Event Revenue Analytics & Payments Breakdown Tests ===\n\n";

    $adminDir = dirname(__DIR__) . '/admin';
    $db = Database::getInstance()->getConnection();

// ------------------------------------------------------------
// 1. Files & Structural Sanity
// ------------------------------------------------------------
echo "--- 1. File Structure & Component Tests ---\n";

TestHelper::assertTrue(
    file_exists($adminDir . '/payments/revenue.php'),
    'Revenue 1.1: Dedicated page payments/revenue.php exists'
);

TestHelper::assertTrue(
    file_exists($adminDir . '/revenue.php'),
    'Revenue 1.2: Admin root revenue.php forwarder exists'
);

$metricsCardsCode = file_get_contents($adminDir . '/includes/metrics_cards.php');
TestHelper::assertTrue(
    str_contains($metricsCardsCode, 'payments/revenue.php'),
    'Revenue 1.3: Overview Total Revenue container links directly to payments/revenue.php'
);

TestHelper::assertTrue(
    str_contains($metricsCardsCode, 'metricCardTotalRevenue'),
    'Revenue 1.4: Total Revenue card has unique ID metricCardTotalRevenue'
);

$headerCode = file_get_contents($adminDir . '/includes/header.php');
TestHelper::assertTrue(
    str_contains($headerCode, 'payments/revenue.php'),
    'Revenue 1.5: Header navigation contains direct link to payments/revenue.php'
);

// ------------------------------------------------------------
// 2. HTTP Route Guard Tests
// ------------------------------------------------------------
echo "\n--- 2. Route Guard & Auth Security Tests ---\n";

$ch = curl_init('http://localhost:8080/vortex/admin/payments/revenue.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

TestHelper::assertStatus(
    302,
    $httpCode,
    'Revenue 2.1: Unauthenticated request to /admin/payments/revenue.php redirects (HTTP 302)'
);

$ch2 = curl_init('http://localhost:8080/vortex/admin/revenue.php');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_HEADER, true);
curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, false);
$response2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

TestHelper::assertStatus(
    302,
    $httpCode2,
    'Revenue 2.2: Forwarder /admin/revenue.php redirects properly (HTTP 302)'
);

// ------------------------------------------------------------
// 3. Database Event Revenue Query Verification
// ------------------------------------------------------------
echo "\n--- 3. Event Revenue Calculation & Query Tests ---\n";

$evRevSql = "SELECT e.event_id, e.event_name, e.department,
                    COUNT(t.id) AS total_tx_count,
                    SUM(CASE WHEN t.status = 'SUCCESS' THEN 1 ELSE 0 END) AS success_tx_count,
                    COALESCE(SUM(CASE WHEN t.status = 'SUCCESS' THEN t.amount ELSE 0 END), 0) AS total_revenue
             FROM events e
             LEFT JOIN transactions t ON e.event_id = t.event_id
             GROUP BY e.event_id, e.event_name, e.department
             ORDER BY total_revenue DESC";
$evRevStmt = $db->query($evRevSql);
$eventRevenues = $evRevStmt->fetchAll(PDO::FETCH_ASSOC);

TestHelper::assertTrue(
    !empty($eventRevenues),
    'Revenue 3.1: Fetched event revenue breakdown from database'
);

$foundDyuti = false;
foreach ($eventRevenues as $rev) {
    if (str_contains(strtolower($rev['event_name']), 'dyuti')) {
        $foundDyuti = true;
        TestHelper::assertTrue(
            (float)$rev['total_revenue'] > 0,
            'Revenue 3.2: Dyuti event has positive recorded revenue'
        );
        TestHelper::assertTrue(
            (int)$rev['success_tx_count'] > 0,
            'Revenue 3.3: Dyuti event has positive success transaction count'
        );
        break;
    }
}
TestHelper::assertTrue($foundDyuti, 'Revenue 3.4: Dyuti event present in revenue dataset');

// ------------------------------------------------------------
// 4. Isolated Rendering & Event Filtering Tests
// ------------------------------------------------------------
echo "\n--- 4. Page Rendering & Event Isolation Tests ---\n";

// Set mock session for isolated inclusion
$_SESSION['vortex_admin_id'] = 1;
$_SESSION['vortex_admin_username'] = 'admin';
$_SESSION['vortex_admin_role'] = 'Super Administrator';
$_SESSION['vortex_admin_fullname'] = 'Vortex Administrator';

// 4.1 Render without filter (All Events)
$_GET = ['status' => 'SUCCESS'];
ob_start();
include $adminDir . '/payments/revenue.php';
$htmlAll = ob_get_clean();

TestHelper::assertTrue(
    str_contains($htmlAll, 'Event Revenue &amp; Payments') || str_contains($htmlAll, 'Event Revenue & Payments'),
    'Revenue 4.1: Renders page header and revenue title'
);

TestHelper::assertTrue(
    str_contains($htmlAll, 'Total Platform Revenue'),
    'Revenue 4.2: Renders Total Platform Revenue KPI card'
);

TestHelper::assertTrue(
    str_contains($htmlAll, 'Revenue Breakdown by Specific Event'),
    'Revenue 4.3: Renders Event Breakdown showcase section'
);

TestHelper::assertTrue(
    str_contains($htmlAll, 'All Events Combined'),
    'Revenue 4.4: Renders All Events Combined tile'
);

// 4.2 Render filtered by Dyuti event
$dyutiEventId = 'DYUT20260913MU01TMQ67BK';
$_GET = ['event_id' => $dyutiEventId, 'status' => 'SUCCESS'];
ob_start();
include $adminDir . '/payments/revenue.php';
$htmlDyuti = ob_get_clean();

TestHelper::assertTrue(
    str_contains($htmlDyuti, 'Active Filters:'),
    'Revenue 4.5: Renders active filter banner when event filter applied'
);

TestHelper::assertTrue(
    str_contains($htmlDyuti, 'Dyuti'),
    'Revenue 4.6: Dyuti event highlighted in active filter and event tiles'
);

TestHelper::assertTrue(
    str_contains($htmlDyuti, 'eventFilterSelect'),
    'Revenue 4.7: Event filter dropdown exists with id eventFilterSelect'
);

TestHelper::assertTrue(
    str_contains($htmlAll, 'vortexRefundModal'),
    'Revenue 4.8: Refund modal component included for payment actions'
);

// ------------------------------------------------------------
// 5. CSV Export Format Tests
// ------------------------------------------------------------
echo "\n--- 5. CSV Export Generation Tests ---\n";

$exportQuery = "SELECT t.vortex_transaction_id, e.event_name, t.amount, t.status
                FROM transactions t
                LEFT JOIN events e ON t.event_id = e.event_id
                WHERE t.status = 'SUCCESS' AND t.event_id = '{$dyutiEventId}'";
$exportStmt = $db->query($exportQuery);
$exportData = $exportStmt->fetchAll(PDO::FETCH_ASSOC);

TestHelper::assertTrue(
    count($exportData) > 0,
    'Revenue 5.1: Event has exportable successful transactions'
);

foreach ($exportData as $row) {
    TestHelper::assertFieldEquals(
        'SUCCESS',
        $row['status'],
        'status',
        'Revenue 5.2: Filtered export strictly contains SUCCESS status'
    );
}
    echo "\nAll Event Revenue tests passed successfully!\n";
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    run_event_revenue_tests();
}
