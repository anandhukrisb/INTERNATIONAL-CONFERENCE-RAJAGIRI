<?php
/**
 * ------------------------------------------------------------
 * revenue.php – Event Revenue Analytics & Payment Breakdown
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway - Admin Panel
 * Purpose : Display gross and net revenue generated across specific
 *           events, provide event-wise visual cards, filter individual
 *           payments by event, status, date, and export CSV ledger.
 * ------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth Guard
if (empty($_SESSION['vortex_admin_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/RBAC.php';

// RBAC Guard
RBAC::require('payments.view', null, '../dashboard.php');

$db = Database::getInstance()->getConnection();

// ------------------------------------------------------------
// Request Parameters & Filters
// ------------------------------------------------------------
$eventIdFilter  = trim($_GET['event_id'] ?? '');
$statusFilter   = trim($_GET['status'] ?? 'SUCCESS'); // Default to SUCCESS for revenue perspective
$currencyFilter = strtoupper(trim($_GET['currency'] ?? ''));
$searchQ        = trim($_GET['q'] ?? '');
$fromDate       = trim($_GET['from_date'] ?? '');
$toDate         = trim($_GET['to_date'] ?? '');
$exportCsv      = (isset($_GET['export']) && $_GET['export'] === 'csv');

// ------------------------------------------------------------
// 1. Fetch Overall Gateway Revenue Metrics
// ------------------------------------------------------------
$totalGrossRevenue = 0.00;
$totalRevenueINR    = 0.00;
$totalRevenueUSD    = 0.00;
$totalSuccessCount = 0;
$totalPendingCount = 0;
$totalFailedCount  = 0;
$totalRefundedAmt  = 0.00;
$activeEventsCount = 0;

try {
    $totalGrossRevenue = (float) $db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'SUCCESS'")->fetchColumn();
    $totalRevenueINR    = (float) $db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'SUCCESS' AND currency = 'INR'")->fetchColumn();
    $totalRevenueUSD    = (float) $db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'SUCCESS' AND currency = 'USD'")->fetchColumn();
    $totalSuccessCount = (int) $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'SUCCESS'")->fetchColumn();
    $totalPendingCount = (int) $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'PENDING'")->fetchColumn();
    $totalFailedCount  = (int) $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'FAILED'")->fetchColumn();
    $totalRefundedAmt  = (float) $db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'REFUNDED'")->fetchColumn();
    $activeEventsCount = (int) $db->query("SELECT COUNT(*) FROM events WHERE is_active = 1")->fetchColumn();
} catch (\Throwable $e) {}

$avgTicketSize = ($totalSuccessCount > 0) ? ($totalGrossRevenue / $totalSuccessCount) : 0.00;

// ------------------------------------------------------------
// 2. Fetch Event-Wise Revenue Breakdown
// ------------------------------------------------------------
$eventRevenueList = [];
$eventMap = [];
try {
    $evRevSql = "SELECT e.event_id, e.event_name, e.department, e.is_active,
                        COUNT(t.id) AS total_tx_count,
                        SUM(CASE WHEN t.status = 'SUCCESS' THEN 1 ELSE 0 END) AS success_tx_count,
                        COALESCE(SUM(CASE WHEN t.status = 'SUCCESS' THEN t.amount ELSE 0 END), 0) AS total_revenue,
                        COALESCE(SUM(CASE WHEN t.status = 'REFUNDED' THEN t.amount ELSE 0 END), 0) AS total_refunded
                 FROM events e
                 LEFT JOIN transactions t ON e.event_id = t.event_id
                 GROUP BY e.event_id, e.event_name, e.department, e.is_active
                 ORDER BY total_revenue DESC, total_tx_count DESC, e.event_name ASC";
    $evRevStmt = $db->query($evRevSql);
    $eventRevenueList = $evRevStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($eventRevenueList as $evItem) {
        $eventMap[$evItem['event_id']] = $evItem;
    }
} catch (\Throwable $e) {
    $eventRevenueList = [];
}

// Identify active filtered event metadata
$selectedEventData = null;
if (!empty($eventIdFilter) && isset($eventMap[$eventIdFilter])) {
    $selectedEventData = $eventMap[$eventIdFilter];
}

// ------------------------------------------------------------
// 3. Build Query for Individual Payments
// ------------------------------------------------------------
$whereClauses = ["1=1"];
$queryParams  = [];

if ($eventIdFilter !== '' && $eventIdFilter !== 'ALL') {
    $whereClauses[] = "t.event_id = :event_id";
    $queryParams[':event_id'] = $eventIdFilter;
}

if ($statusFilter !== '' && $statusFilter !== 'ALL') {
    $whereClauses[] = "t.status = :status";
    $queryParams[':status'] = $statusFilter;
}

if ($currencyFilter !== '' && $currencyFilter !== 'ALL' && in_array($currencyFilter, ['INR', 'USD'])) {
    $whereClauses[] = "t.currency = :currency";
    $queryParams[':currency'] = $currencyFilter;
}

if ($searchQ !== '') {
    $whereClauses[] = "(t.vortex_transaction_id LIKE :sq OR t.razorpay_order_id LIKE :sq OR t.razorpay_payment_id LIKE :sq OR t.customer_email LIKE :sq OR t.customer_mobile LIKE :sq)";
    $queryParams[':sq'] = '%' . $searchQ . '%';
}

if ($fromDate !== '') {
    $whereClauses[] = "t.created_at >= :from_date";
    $queryParams[':from_date'] = $fromDate . ' 00:00:00';
}

if ($toDate !== '') {
    $whereClauses[] = "t.created_at <= :to_date";
    $queryParams[':to_date'] = $toDate . ' 23:59:59';
}

$whereSql = implode(" AND ", $whereClauses);

// ------------------------------------------------------------
// 4. Handle CSV Export if requested
// ------------------------------------------------------------
if ($exportCsv) {
    try {
        $exportSql = "SELECT t.vortex_transaction_id, t.razorpay_payment_id, t.razorpay_order_id,
                             e.event_name, e.department, c.client_name,
                             t.amount, t.currency, t.status, t.customer_email, t.customer_mobile,
                             t.created_at
                      FROM transactions t
                      LEFT JOIN events e ON t.event_id = e.event_id
                      LEFT JOIN api_clients c ON t.api_client_id = c.id
                      WHERE {$whereSql}
                      ORDER BY t.id DESC";
        $exportStmt = $db->prepare($exportSql);
        $exportStmt->execute($queryParams);
        $exportRows = $exportStmt->fetchAll(PDO::FETCH_ASSOC);

        $filename = 'rpay_event_revenue_' . (!empty($eventIdFilter) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $eventIdFilter) : 'all_events') . '_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Transaction ID', 'Razorpay Payment ID', 'Razorpay Order ID', 'Event Name', 'Department', 'Merchant / Client', 'Amount (INR)', 'Currency', 'Status', 'Customer Email', 'Customer Phone', 'Date & Time']);

        foreach ($exportRows as $row) {
            fputcsv($out, [
                $row['vortex_transaction_id'],
                $row['razorpay_payment_id'] ?? '',
                $row['razorpay_order_id'] ?? '',
                $row['event_name'] ?? 'Unassigned Event',
                $row['department'] ?? '',
                $row['client_name'] ?? 'Default Client',
                number_format((float)$row['amount'], 2, '.', ''),
                $row['currency'] ?? 'INR',
                $row['status'],
                $row['customer_email'] ?? '',
                $row['customer_mobile'] ?? '',
                $row['created_at']
            ]);
        }
        fclose($out);
        exit;
    } catch (\Throwable $e) {
        // Fall back to regular page render with error
        $exportError = "Failed to export CSV: " . $e->getMessage();
    }
}

// ------------------------------------------------------------
// 5. Paginated Payments Query
// ------------------------------------------------------------
$filteredRevenueSum = 0.00;
$filteredCount = 0;

try {
    // Total count & sum for active filter set
    $sumSql = "SELECT COUNT(*) AS total_count, COALESCE(SUM(t.amount), 0) AS total_sum
               FROM transactions t
               WHERE {$whereSql}";
    $sumStmt = $db->prepare($sumSql);
    $sumStmt->execute($queryParams);
    $sumRes = $sumStmt->fetch(PDO::FETCH_ASSOC);
    $filteredCount = (int) ($sumRes['total_count'] ?? 0);
    $filteredRevenueSum = (float) ($sumRes['total_sum'] ?? 0.00);

    $perPage = 25;
    $totalPages = max(1, (int) ceil($filteredCount / $perPage));
    $page = max(1, (int) ($_GET['page'] ?? $_GET['p'] ?? 1));
    if ($page > $totalPages && $filteredCount > 0) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $txSql = "SELECT t.*, e.event_name, e.department, c.client_name,
                     (SELECT w.event_type FROM webhooks w 
                      WHERE (w.order_id IS NOT NULL AND w.order_id != '' AND w.order_id = t.razorpay_order_id)
                         OR (w.payment_id IS NOT NULL AND w.payment_id != '' AND w.payment_id = t.razorpay_payment_id)
                      ORDER BY w.id DESC LIMIT 1) AS latest_event,
                     (SELECT tl.reason FROM transaction_timeline tl 
                      WHERE tl.vortex_transaction_id = t.vortex_transaction_id 
                      ORDER BY tl.id DESC LIMIT 1) AS latest_reason,
                     (SELECT COUNT(*) FROM transaction_timeline tl 
                      WHERE tl.vortex_transaction_id = t.vortex_transaction_id) AS timeline_count
              FROM transactions t
              LEFT JOIN events e ON t.event_id = e.event_id
              LEFT JOIN api_clients c ON t.api_client_id = c.id
              WHERE {$whereSql}
              ORDER BY t.id DESC
              LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;

    $txStmt = $db->prepare($txSql);
    $txStmt->execute($queryParams);
    $transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);

    $fromTx = ($filteredCount === 0) ? 0 : $offset + 1;
    $toTx = min($offset + count($transactions), $filteredCount);
} catch (\Throwable $e) {
    $transactions = [];
    $filteredCount = 0;
    $totalPages = 1;
    $page = 1;
    $fromTx = 0;
    $toTx = 0;
    $error = "Failed to load revenue transactions: " . $e->getMessage();
}

// Top performing event calculation
$topEvent = null;
foreach ($eventRevenueList as $ev) {
    if ((float)$ev['total_revenue'] > 0) {
        if ($topEvent === null || (float)$ev['total_revenue'] > (float)$topEvent['total_revenue']) {
            $topEvent = $ev;
        }
    }
}

$pageTitle = "Revenue & Event Payments - Rpay Admin";
$activeNav = "revenue";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb Navigation -->
<div style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: var(--text-gray); margin-bottom: 16px;">
    <a href="../dashboard.php" style="color: var(--primary-blue); text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
        Overview
    </a>
    <span>/</span>
    <span style="color: var(--text-dark); font-weight: 600;">Revenue by Events</span>
</div>

<!-- Page Header & Action Bar -->
<div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
    <div>
        <div class="section-title" style="margin-bottom: 4px; display: flex; align-items: center; gap: 10px;">
            <div style="width: 36px; height: 36px; border-radius: 10px; background: var(--primary-light); display: flex; align-items: center; justify-content: center;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary-blue);">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
            </div>
            <div>
                <span style="font-size: 1.35rem; font-weight: 700; color: var(--text-dark);">Event Revenue &amp; Payments</span>
            </div>
        </div>
        <div style="font-size: 0.86rem; color: var(--text-gray); max-width: 680px; margin-left: 46px;">
            Inspect individual payments collected per conference or event campaign. Filter by specific event, transaction status, or keyword.
        </div>
    </div>

    <!-- Quick Actions -->
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <!-- Export Filtered CSV -->
        <?php
        $exportParams = $_GET;
        $exportParams['export'] = 'csv';
        $exportUrl = 'revenue.php?' . http_build_query($exportParams);
        ?>
        <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;" title="Download filtered transactions as CSV">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            <span>Export CSV</span>
        </a>

        <!-- Switcher to All Transactions -->
        <a href="list_payments.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
            <span>All Transactions Ledger</span>
        </a>

        <!-- Manage Events -->
        <a href="../events/index.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            <span>Events Setup</span>
        </a>
    </div>
</div>

<!-- Revenue Analytics KPI Cards Grid -->
<div class="metrics-grid" style="margin-bottom: 24px;">
    <!-- Gross Total Revenue Card -->
    <div class="metric-card" style="position: relative; overflow: hidden;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="metric-label">Total Platform Revenue</span>
            <div style="width: 34px; height: 34px; border-radius: 8px; background: var(--primary-light); display: flex; align-items: center; justify-content: center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary-blue);"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            </div>
        </div>
        <div class="metric-value amount" style="font-size: 1.15rem; line-height: 1.35; margin-top: 4px;">
            <span>₹ <?= number_format($totalRevenueINR, 2) ?> <span style="font-size: 0.7rem; color: var(--text-gray); font-weight: 600;">INR</span></span>
            <?php if ($totalRevenueUSD > 0): ?>
                <span style="display: block; font-size: 0.95rem; color: #0284c7; margin-top: 2px;">$ <?= number_format($totalRevenueUSD, 2) ?> <span style="font-size: 0.65rem; color: var(--text-gray); font-weight: 500;">USD</span></span>
            <?php endif; ?>
        </div>
        <div style="font-size: 0.74rem; color: var(--text-gray); margin-top: 4px;">
            Across <?= number_format(count($eventRevenueList)) ?> total registered events
        </div>
    </div>

    <!-- Successful Transactions -->
    <div class="metric-card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="metric-label">Paid Transactions</span>
            <div style="width: 34px; height: 34px; border-radius: 8px; background: var(--success-bg); display: flex; align-items: center; justify-content: center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--success-text);"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            </div>
        </div>
        <span class="metric-value success"><?= number_format($totalSuccessCount) ?></span>
        <div style="font-size: 0.74rem; color: var(--text-gray); margin-top: 4px;">
            <?= number_format($totalPendingCount) ?> pending • <?= number_format($totalFailedCount) ?> failed
        </div>
    </div>

    <!-- Average Ticket Size -->
    <div class="metric-card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="metric-label">Average Ticket Size</span>
            <div style="width: 34px; height: 34px; border-radius: 8px; background: var(--purple-bg); display: flex; align-items: center; justify-content: center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--purple-text);"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            </div>
        </div>
        <span class="metric-value" style="color: var(--purple-text);">₹ <?= number_format($avgTicketSize, 2) ?></span>
        <div style="font-size: 0.74rem; color: var(--text-gray); margin-top: 4px;">
            Per successful transaction
        </div>
    </div>

    <!-- Active Events Generating Revenue -->
    <div class="metric-card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="metric-label">Active Events</span>
            <div style="width: 34px; height: 34px; border-radius: 8px; background: var(--warning-bg); display: flex; align-items: center; justify-content: center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--warning-text);"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            </div>
        </div>
        <span class="metric-value"><?= number_format($activeEventsCount) ?></span>
        <div style="font-size: 0.74rem; color: var(--text-gray); margin-top: 4px;">
            <?= $topEvent ? 'Top: ' . htmlspecialchars($topEvent['event_name']) . ' (₹' . number_format((float)$topEvent['total_revenue']) . ')' : 'No revenue events yet' ?>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- Event Breakdown Showcase: Clickable Cards to Filter Payments -->
<!-- ============================================================ -->
<div class="section-card" style="margin-bottom: 24px;">
    <div class="section-header" style="background: var(--bg-hover); border-bottom: 1px solid var(--border-color); padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary-blue);"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path></svg>
            <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--text-dark); margin: 0;">
                Revenue Breakdown by Specific Event
            </h3>
            <span style="font-size: 0.78rem; color: var(--text-gray);">
                (Click any card below to filter individual payments)
            </span>
        </div>
        <?php if (!empty($eventIdFilter)): ?>
            <a href="revenue.php?status=<?= urlencode($statusFilter) ?>" class="btn btn-secondary btn-sm" style="font-size: 0.75rem; padding: 4px 10px; display: inline-flex; align-items: center; gap: 4px;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                <span>Reset to All Events</span>
            </a>
        <?php endif; ?>
    </div>

    <div class="section-body" style="padding: 16px 20px;">
        <!-- Event Cards Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px;">
            
            <!-- 'All Events' Overview Tile -->
            <?php
            $isAllActive = empty($eventIdFilter) || $eventIdFilter === 'ALL';
            $allUrl = 'revenue.php?event_id=&status=' . urlencode($statusFilter) . ($searchQ !== '' ? '&q=' . urlencode($searchQ) : '');
            ?>
            <a href="<?= htmlspecialchars($allUrl) ?>"
               style="text-decoration: none; display: block; border-radius: 10px; border: 2px solid <?= $isAllActive ? 'var(--primary-blue)' : 'var(--border-color)' ?>; background: <?= $isAllActive ? 'var(--primary-light)' : 'var(--bg-card)' ?>; padding: 14px 16px; transition: all 0.2s ease; box-shadow: <?= $isAllActive ? '0 4px 12px rgba(26,115,232,0.18)' : 'none' ?>;"
               class="event-filter-tile"
               title="View payments from all events combined">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                    <div>
                        <span style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; color: <?= $isAllActive ? 'var(--primary-blue)' : 'var(--text-gray)' ?>;">Overview</span>
                        <div style="font-size: 1rem; font-weight: 700; color: var(--text-dark);">All Events Combined</div>
                    </div>
                    <?php if ($isAllActive): ?>
                        <span class="badge badge-success" style="font-size: 0.68rem; padding: 2px 7px;">Active Filter</span>
                    <?php endif; ?>
                </div>
                <div style="font-size: 1.25rem; font-weight: 800; color: var(--text-dark); margin-bottom: 4px;">
                    ₹ <?= number_format($totalGrossRevenue, 2) ?>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.78rem; color: var(--text-gray);">
                    <span><?= number_format($totalSuccessCount) ?> successful payments</span>
                    <span style="color: var(--primary-blue); font-weight: 600;">Show All &rarr;</span>
                </div>
            </a>

            <!-- Individual Event Tiles -->
            <?php foreach ($eventRevenueList as $evt): ?>
                <?php
                $eId = $evt['event_id'];
                $isSelected = ($eventIdFilter === $eId);
                $revAmt = (float)$evt['total_revenue'];
                $pctShare = ($totalGrossRevenue > 0) ? min(100, round(($revAmt / $totalGrossRevenue) * 100, 1)) : 0;
                $evUrl = 'revenue.php?event_id=' . urlencode($eId) . '&status=' . urlencode($statusFilter) . ($searchQ !== '' ? '&q=' . urlencode($searchQ) : '');
                ?>
                <a href="<?= htmlspecialchars($evUrl) ?>"
                   style="text-decoration: none; display: block; border-radius: 10px; border: 2px solid <?= $isSelected ? 'var(--primary-blue)' : 'var(--border-color)' ?>; background: <?= $isSelected ? 'var(--primary-light)' : 'var(--bg-card)' ?>; padding: 14px 16px; transition: all 0.2s ease; box-shadow: <?= $isSelected ? '0 4px 12px rgba(26,115,232,0.18)' : 'none' ?>;"
                   class="event-filter-tile"
                   title="Click to filter payments exclusively for <?= htmlspecialchars($evt['event_name']) ?>">
                    
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                        <div style="max-width: 190px;">
                            <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?= htmlspecialchars($evt['event_name']) ?>
                            </div>
                            <?php if (!empty($evt['department'])): ?>
                                <div style="font-size: 0.74rem; color: var(--text-gray); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= htmlspecialchars($evt['department']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                            <?php if ($isSelected): ?>
                                <span class="badge badge-success" style="font-size: 0.65rem; padding: 2px 6px;">Selected</span>
                            <?php else: ?>
                                <span class="badge <?= (int)$evt['is_active'] === 1 ? 'badge-success' : 'badge-danger' ?>" style="font-size: 0.65rem; padding: 2px 6px;">
                                    <?= (int)$evt['is_active'] === 1 ? 'Active' : 'Closed' ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="font-size: 1.25rem; font-weight: 800; color: var(--text-dark); margin: 6px 0 2px 0;">
                        ₹ <?= number_format($revAmt, 2) ?>
                    </div>

                    <!-- Revenue Share Bar -->
                    <div style="width: 100%; height: 5px; background: var(--border-color); border-radius: 3px; overflow: hidden; margin-bottom: 6px;">
                        <div style="width: <?= $pctShare ?>%; height: 100%; background: var(--primary-blue); border-radius: 3px;"></div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.76rem; color: var(--text-gray);">
                        <span><?= (int)$evt['success_tx_count'] ?> paid / <?= (int)$evt['total_tx_count'] ?> total txs</span>
                        <span style="font-weight: 600; color: <?= $isSelected ? 'var(--primary-blue)' : 'var(--text-dark)' ?>;"><?= $pctShare ?>% share &rarr;</span>
                    </div>
                </a>
            <?php endforeach; ?>

        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- Individual Payments Ledger with Granular Filters -->
<!-- ============================================================ -->
<div class="section-card">
    <!-- Filter Controls Toolbar -->
    <div class="section-header" style="background: var(--bg-hover); border-bottom: 1px solid var(--border-color); padding: 16px 20px;">
        <form method="GET" action="revenue.php" id="revenueFilterForm" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; width: 100%;">
            
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <!-- Filter by Specific Event -->
                <div style="display: flex; flex-direction: column; gap: 2px;">
                    <label for="eventFilterSelect" style="font-size: 0.74rem; font-weight: 700; color: var(--text-gray); text-transform: uppercase;">Event Campaign</label>
                    <select name="event_id" id="eventFilterSelect" class="form-control" style="font-size: 0.84rem; padding: 6px 12px; min-width: 220px; max-width: 280px;" onchange="this.form.submit()">
                        <option value="">All Events (₹<?= number_format($totalGrossRevenue, 2) ?>)</option>
                        <?php foreach ($eventRevenueList as $evtOption): ?>
                            <option value="<?= htmlspecialchars($evtOption['event_id']) ?>" <?= ($eventIdFilter === $evtOption['event_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($evtOption['event_name']) ?> — ₹<?= number_format((float)$evtOption['total_revenue'], 2) ?> (<?= (int)$evtOption['success_tx_count'] ?> paid)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filter by Payment Status -->
                <div style="display: flex; flex-direction: column; gap: 2px;">
                    <label for="statusFilterSelect" style="font-size: 0.74rem; font-weight: 700; color: var(--text-gray); text-transform: uppercase;">Status</label>
                    <select name="status" id="statusFilterSelect" class="form-control" style="font-size: 0.84rem; padding: 6px 12px; width: 140px;" onchange="this.form.submit()">
                        <option value="SUCCESS" <?= ($statusFilter === 'SUCCESS') ? 'selected' : '' ?>>Paid (Success)</option>
                        <option value="ALL" <?= ($statusFilter === 'ALL' || $statusFilter === '') ? 'selected' : '' ?>>All Statuses</option>
                        <option value="PENDING" <?= ($statusFilter === 'PENDING') ? 'selected' : '' ?>>Pending / Unpaid</option>
                        <option value="FAILED" <?= ($statusFilter === 'FAILED') ? 'selected' : '' ?>>Failed</option>
                        <option value="REFUNDED" <?= ($statusFilter === 'REFUNDED') ? 'selected' : '' ?>>Refunded</option>
                    </select>
                </div>

                <!-- Filter by Currency -->
                <div style="display: flex; flex-direction: column; gap: 2px;">
                    <label for="currencyFilterSelect" style="font-size: 0.74rem; font-weight: 700; color: var(--text-gray); text-transform: uppercase;">Currency</label>
                    <select name="currency" id="currencyFilterSelect" class="form-control" style="font-size: 0.84rem; padding: 6px 12px; width: 140px;" onchange="this.form.submit()">
                        <option value="">All Currencies</option>
                        <option value="INR" <?= ($currencyFilter === 'INR') ? 'selected' : '' ?>>₹ INR (Domestic)</option>
                        <option value="USD" <?= ($currencyFilter === 'USD') ? 'selected' : '' ?>>$ USD (International)</option>
                    </select>
                </div>

                <!-- Search Input -->
                <div style="display: flex; flex-direction: column; gap: 2px;">
                    <label for="filterSearchInput" style="font-size: 0.74rem; font-weight: 700; color: var(--text-gray); text-transform: uppercase;">Search Keywords</label>
                    <div style="position: relative;">
                        <input type="text" name="q" id="filterSearchInput" class="form-control" placeholder="Tx ID, customer email, order ID..." value="<?= htmlspecialchars($searchQ) ?>" style="font-size: 0.84rem; padding: 6px 12px 6px 32px; width: 240px;">
                        <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-gray); pointer-events: none;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        </span>
                    </div>
                </div>

                <!-- Date Range Filters -->
                <div style="display: flex; flex-direction: column; gap: 2px;">
                    <label for="fromDateInput" style="font-size: 0.74rem; font-weight: 700; color: var(--text-gray); text-transform: uppercase;">From</label>
                    <input type="date" name="from_date" id="fromDateInput" class="form-control" value="<?= htmlspecialchars($fromDate) ?>" style="font-size: 0.82rem; padding: 5px 8px; width: 130px;">
                </div>
                <div style="display: flex; flex-direction: column; gap: 2px;">
                    <label for="toDateInput" style="font-size: 0.74rem; font-weight: 700; color: var(--text-gray); text-transform: uppercase;">To</label>
                    <input type="date" name="to_date" id="toDateInput" class="form-control" value="<?= htmlspecialchars($toDate) ?>" style="font-size: 0.82rem; padding: 5px 8px; width: 130px;">
                </div>

                <div style="display: flex; gap: 6px; align-self: flex-end; padding-bottom: 2px;">
                    <button type="submit" class="btn btn-primary btn-sm" style="padding: 7px 14px; font-weight: 600;">
                        Apply Filter
                    </button>
                    <?php if (!empty($eventIdFilter) || ($statusFilter !== 'SUCCESS') || !empty($searchQ) || !empty($fromDate) || !empty($toDate)): ?>
                        <a href="revenue.php" class="btn btn-secondary btn-sm" style="padding: 7px 12px; color: var(--danger-text);" title="Clear all active filters">
                            Reset
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Active Filter Summary -->
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="text-align: right;">
                    <div style="font-size: 0.75rem; color: var(--text-gray); text-transform: uppercase; font-weight: 700;">Filtered Result</div>
                    <div style="font-size: 1.05rem; font-weight: 800; color: var(--text-dark);">
                        ₹ <?= number_format($filteredRevenueSum, 2) ?>
                        <span style="font-size: 0.8rem; font-weight: 500; color: var(--text-gray);">(<?= number_format($filteredCount) ?> txs)</span>
                    </div>
                </div>
            </div>

        </form>
    </div>

    <!-- Active Filter Banner & Pills -->
    <?php if (!empty($selectedEventData) || !empty($searchQ) || $statusFilter !== 'SUCCESS' || !empty($fromDate) || !empty($toDate)): ?>
        <div style="padding: 10px 20px; background: var(--primary-light); border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 0.82rem;">
            <span style="font-weight: 600; color: var(--text-dark);">Active Filters:</span>
            
            <?php if (!empty($selectedEventData)): ?>
                <span class="badge badge-success" style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px;">
                    Event: <?= htmlspecialchars($selectedEventData['event_name']) ?> (₹<?= number_format((float)$selectedEventData['total_revenue'], 2) ?>)
                    <a href="revenue.php?status=<?= urlencode($statusFilter) ?>" style="color: inherit; text-decoration: none; font-weight: bold; margin-left: 2px;">&times;</a>
                </span>
            <?php endif; ?>

            <?php if ($statusFilter !== 'SUCCESS'): ?>
                <span class="badge badge-pending" style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px;">
                    Status: <?= htmlspecialchars($statusFilter) ?>
                    <a href="revenue.php?<?= http_build_query(array_merge($_GET, ['status' => 'SUCCESS'])) ?>" style="color: inherit; text-decoration: none; font-weight: bold; margin-left: 2px;">&times;</a>
                </span>
            <?php endif; ?>

            <?php if (!empty($searchQ)): ?>
                <span class="badge badge-event" style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px;">
                    Search: "<?= htmlspecialchars($searchQ) ?>"
                    <a href="revenue.php?<?= http_build_query(array_merge($_GET, ['q' => ''])) ?>" style="color: inherit; text-decoration: none; font-weight: bold; margin-left: 2px;">&times;</a>
                </span>
            <?php endif; ?>

            <a href="revenue.php" style="font-size: 0.78rem; color: var(--primary-blue); font-weight: 600; text-decoration: underline; margin-left: auto;">
                Clear all filters
            </a>
        </div>
    <?php endif; ?>

    <!-- Table Body -->
    <div class="section-body" style="padding: 0;">
        <?php if (isset($error)): ?>
            <div style="padding: 24px; color: var(--danger-text); font-weight: 600;"><?= htmlspecialchars($error) ?></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Event Name</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 48px 20px;">
                                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px;">
                                        <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--bg-hover); display: flex; align-items: center; justify-content: center; color: var(--text-gray);">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"></circle><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                                        </div>
                                        <div style="font-weight: 600; color: var(--text-dark); font-size: 1rem;">No payments match the selected criteria</div>
                                        <div style="font-size: 0.84rem; color: var(--text-gray); max-width: 380px;">
                                            Try selecting "All Events" or switching the status filter to inspect pending or failed payment attempts.
                                        </div>
                                        <a href="revenue.php" class="btn btn-secondary btn-sm" style="margin-top: 6px;">Reset Filter View</a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $tx): ?>
                                <?php
                                $txJson = htmlspecialchars(json_encode($tx, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr class="tx-overview-row"
                                    tabindex="0"
                                    role="button"
                                    aria-label="View details for transaction <?= htmlspecialchars($tx['vortex_transaction_id']) ?>"
                                    title="Click / tap to view transaction details & actions"
                                    data-transaction-id="<?= htmlspecialchars($tx['vortex_transaction_id']) ?>"
                                    data-tx='<?= $txJson ?>'
                                    onclick="openTxOverviewModal(this)"
                                    onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openTxOverviewModal(this);}">

                                    <!-- 1. Email -->
                                    <td>
                                        <?= htmlspecialchars(!empty($tx['customer_email']) ? $tx['customer_email'] : '—') ?>
                                    </td>

                                    <!-- 2. Event Name -->
                                    <td>
                                        <span style="font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars($tx['event_name'] ?? $tx['event_id'] ?? '–') ?></span>
                                        <?php if (!empty($tx['department'])): ?>
                                            <div style="font-size: 0.74rem; color: var(--text-gray); margin-top: 2px;">
                                                <?= htmlspecialchars($tx['department']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- 3. Amount -->
                                    <td style="font-weight: 700; color: var(--text-dark); font-size: 0.95rem; white-space: nowrap;">
                                        <?php 
                                        $curr = strtoupper($tx['currency'] ?? 'INR');
                                        $currSymbol = ($curr === 'USD') ? '$' : '₹';
                                        ?>
                                        <span><?= $currSymbol ?><?= number_format((float)$tx['amount'], 2) ?></span>
                                        <?php if ($curr === 'USD'): ?>
                                            <span class="badge" style="font-size: 0.65rem; padding: 2px 5px; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; margin-left: 3px;" title="International USD Payment">USD</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- 4. Status -->
                                    <td>
                                        <?php
                                        $bClass = 'badge-pending';
                                        $displayStatus = $tx['status'];
                                        if ($tx['status'] === 'PENDING') $displayStatus = 'UNPAID';
                                        if ($tx['status'] === 'SUCCESS') $bClass = 'badge-success';
                                        if ($tx['status'] === 'FAILED') $bClass = 'badge-danger';
                                        if ($tx['status'] === 'REFUNDED') $bClass = 'badge-refunded';
                                        if ($tx['status'] === 'DISPUTED') $bClass = 'badge-disputed';
                                        ?>
                                        <span class="badge <?= $bClass ?>"><?= htmlspecialchars($displayStatus) ?></span>

                                        <?php if (!empty($tx['latest_event']) && $tx['status'] !== 'SUCCESS'): ?>
                                            <div style="margin-top: 3px;">
                                                <span class="badge badge-event" style="font-size: 0.68rem; padding: 2px 5px;" title="Latest Gateway Event">
                                                    <?= htmlspecialchars($tx['latest_event']) ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($tx['latest_reason']) && $tx['status'] !== 'SUCCESS'): ?>
                                            <div style="margin-top: 4px; font-size: 0.72rem; color: var(--danger-text); max-width: 220px; line-height: 1.25;">
                                                <?= htmlspecialchars($tx['latest_reason']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- 5. Timestamp -->
                                    <td style="font-size: 0.82rem; color: var(--text-gray); white-space: nowrap;">
                                        <div><?= date('M d, Y', strtotime($tx['created_at'])) ?></div>
                                        <div style="font-size: 0.74rem; color: var(--text-gray);"><?= date('H:i:s', strtotime($tx['created_at'])) ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <?php if ($filteredCount > 0): ?>
                <?php
                $buildPaginationUrl = function($p) {
                    $params = $_GET;
                    $params['page'] = $p;
                    return 'revenue.php?' . http_build_query($params);
                };
                ?>
                <div class="pagination-wrapper" style="padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); background: var(--bg-hover); flex-wrap: wrap; gap: 10px;">
                    <div class="pagination-info" style="font-size: 0.82rem; color: var(--text-gray);">
                        Showing <strong style="color: var(--text-dark);"><?= number_format($fromTx) ?>–<?= number_format($toTx) ?></strong> of <strong style="color: var(--text-dark);"><?= number_format($filteredCount) ?></strong> transactions
                        <span style="color: var(--border-color); margin: 0 4px;">•</span>
                        Page <strong style="color: var(--text-dark);"><?= (int)$page ?></strong> of <strong style="color: var(--text-dark);"><?= (int)$totalPages ?></strong>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div class="pagination-controls" style="display: flex; gap: 4px;">
                            <?php if ($page > 1): ?>
                                <a href="<?= htmlspecialchars($buildPaginationUrl(1)) ?>" class="pagination-btn pagination-nav-btn" title="First Page">« First</a>
                                <a href="<?= htmlspecialchars($buildPaginationUrl($page - 1)) ?>" class="pagination-btn pagination-nav-btn" title="Previous Page">‹ Prev</a>
                            <?php else: ?>
                                <span class="pagination-btn pagination-nav-btn disabled" aria-disabled="true">« First</span>
                                <span class="pagination-btn pagination-nav-btn disabled" aria-disabled="true">‹ Prev</span>
                            <?php endif; ?>

                            <?php
                            $window = 2;
                            $startPage = max(1, $page - $window);
                            $endPage = min($totalPages, $page + $window);

                            if ($startPage > 1): ?>
                                <a href="<?= htmlspecialchars($buildPaginationUrl(1)) ?>" class="pagination-btn">1</a>
                                <?php if ($startPage > 2): ?>
                                    <span class="pagination-ellipsis" style="padding: 0 4px; color: var(--text-gray); display: inline-flex; align-items: center;">…</span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                                <?php if ($p === $page): ?>
                                    <span class="pagination-btn active" aria-current="page"><?= $p ?></span>
                                <?php else: ?>
                                    <a href="<?= htmlspecialchars($buildPaginationUrl($p)) ?>" class="pagination-btn"><?= $p ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <span class="pagination-ellipsis" style="padding: 0 4px; color: var(--text-gray); display: inline-flex; align-items: center;">…</span>
                                <?php endif; ?>
                                <a href="<?= htmlspecialchars($buildPaginationUrl($totalPages)) ?>" class="pagination-btn"><?= $totalPages ?></a>
                            <?php endif; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="<?= htmlspecialchars($buildPaginationUrl($page + 1)) ?>" class="pagination-btn pagination-nav-btn" title="Next Page">Next ›</a>
                                <a href="<?= htmlspecialchars($buildPaginationUrl($totalPages)) ?>" class="pagination-btn pagination-nav-btn" title="Last Page">Last »</a>
                            <?php else: ?>
                                <span class="pagination-btn pagination-nav-btn disabled" aria-disabled="true">Next ›</span>
                                <span class="pagination-btn pagination-nav-btn disabled" aria-disabled="true">Last »</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<style>
.event-filter-tile:hover {
    transform: translateY(-2px);
    border-color: var(--primary-blue) !important;
}
</style>

<?php
// Include Shared Transaction Overview Modal
require_once __DIR__ . '/../includes/tx_overview_modal.php';

// Include Refund Modal Component
require_once __DIR__ . '/../includes/refund_modal.php';

// Include Admin Footer
require_once __DIR__ . '/../includes/footer.php';
?>
