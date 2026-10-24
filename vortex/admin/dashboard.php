<?php
/**
 * ------------------------------------------------------------
 * Vortex Admin Dashboard – Operations Hub & Gateway Overview
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 * Purpose : Modular overview dashboard displaying real-time metrics,
 *           quick operations launchpad, and paginated recent stream.
 * ------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication Guard
if (empty($_SESSION['vortex_admin_id'])) {
    header('Location: login.php');
    exit;
}

// Legacy tab redirection for backwards compatibility
$tabRedirect = $_GET['tab'] ?? '';
if ($tabRedirect === 'clients') {
    header('Location: api_clients/index.php');
    exit;
}
if ($tabRedirect === 'events') {
    header('Location: events/index.php');
    exit;
}

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/RBAC.php';
require_once __DIR__ . '/../classes/AuditLogger.php';

$db = Database::getInstance()->getConnection();

// Flash Messages
$flashSuccess         = $_SESSION['flash_success'] ?? null;
$flashError           = $_SESSION['flash_error'] ?? null;
$createdClientDetails = $_SESSION['created_client'] ?? null;
$newWebhookSecret     = $_SESSION['new_webhook_secret'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['created_client'], $_SESSION['new_webhook_secret']);

// Database Metrics
$totalPayments      = 0;
$successfulPayments = 0;
$failedPayments     = 0;
$totalPaymentAmount = 0.00;
$totalRevenueINR    = 0.00;
$totalRevenueUSD    = 0.00;
$activeEventsCount  = 0;
$totalClientsCount  = 0;

try {
    $totalPayments      = (int) $db->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
    $successfulPayments = (int) $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'SUCCESS'")->fetchColumn();
    $failedPayments     = (int) $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'FAILED'")->fetchColumn();
    $totalPaymentAmount = (float) $db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'SUCCESS'")->fetchColumn();
    $totalRevenueINR    = (float) $db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'SUCCESS' AND currency = 'INR'")->fetchColumn();
    $totalRevenueUSD    = (float) $db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'SUCCESS' AND currency = 'USD'")->fetchColumn();
    $activeEventsCount  = (int) $db->query("SELECT COUNT(*) FROM events WHERE is_active = 1")->fetchColumn();
    $totalClientsCount  = (int) $db->query("SELECT COUNT(*) FROM api_clients")->fetchColumn();
} catch (\Throwable $e) {}

// Filter Inputs
$filterStartDate = trim($_GET['start_date'] ?? '');
$filterEndDate   = trim($_GET['end_date'] ?? '');
$filterStatus    = trim($_GET['status'] ?? '');
$filterEventId   = trim($_GET['event_id'] ?? '');
$filterClientId  = trim($_GET['api_client_id'] ?? '');
$filterCurrency  = strtoupper(trim($_GET['currency'] ?? ''));

// Filter options data
$filterEvents  = [];
$filterClients = [];
try {
    $filterEvents  = $db->query("SELECT event_id, event_name FROM events ORDER BY is_active DESC, event_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $filterClients = $db->query("SELECT id, client_name FROM api_clients ORDER BY is_active DESC, client_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

// Build filtering WHERE clauses
$whereConditions = [];
$queryParams     = [];

if ($filterStartDate !== '') {
    $whereConditions[] = "t.created_at >= :start_date";
    $queryParams[':start_date'] = $filterStartDate . ' 00:00:00';
}
if ($filterEndDate !== '') {
    $whereConditions[] = "t.created_at <= :end_date";
    $queryParams[':end_date'] = $filterEndDate . ' 23:59:59';
}
if ($filterStatus !== '' && $filterStatus !== 'ALL') {
    $whereConditions[] = "t.status = :status";
    $queryParams[':status'] = $filterStatus;
}
if ($filterEventId !== '' && $filterEventId !== 'ALL') {
    $whereConditions[] = "t.event_id = :event_id";
    $queryParams[':event_id'] = $filterEventId;
}
if ($filterClientId !== '' && $filterClientId !== 'ALL') {
    $whereConditions[] = "t.api_client_id = :api_client_id";
    $queryParams[':api_client_id'] = (int)$filterClientId;
}
if ($filterCurrency !== '' && $filterCurrency !== 'ALL' && in_array($filterCurrency, ['INR', 'USD'])) {
    $whereConditions[] = "t.currency = :currency";
    $queryParams[':currency'] = $filterCurrency;
}

$activeFilterCount = count($whereConditions);
$isFiltered        = ($activeFilterCount > 0);
$whereSql          = $isFiltered ? (" WHERE " . implode(" AND ", $whereConditions)) : "";

// Transactions Overview (Paginated with filters)
$txPerPage = 15;
$txTotalCount = $totalPayments;

try {
    if ($isFiltered) {
        $countSql = "SELECT COUNT(*) FROM transactions t" . $whereSql;
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($queryParams);
        $txTotalCount = (int)$countStmt->fetchColumn();
    } else {
        $txTotalCount = $totalPayments;
    }
} catch (\Throwable $e) {
    $txTotalCount = 0;
}

$txTotalPages = max(1, (int) ceil($txTotalCount / $txPerPage));
$txPage = max(1, (int) ($_GET['page'] ?? $_GET['p'] ?? 1));
if ($txPage > $txTotalPages && $txTotalCount > 0) {
    $txPage = $txTotalPages;
}
$txOffset = ($txPage - 1) * $txPerPage;

$recentTx = [];
try {
    $recentTxSql = "SELECT t.*, e.event_name, e.department, c.client_name,
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
                    {$whereSql}
                    ORDER BY t.id DESC LIMIT " . (int)$txPerPage . " OFFSET " . (int)$txOffset;

    $recentTxStmt = $db->prepare($recentTxSql);
    $recentTxStmt->execute($queryParams);
    $recentTx = $recentTxStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    $recentTx = [];
}

$fromTx = ($txTotalCount === 0) ? 0 : $txOffset + 1;
$toTx = min($txOffset + count($recentTx), $txTotalCount);

$pageTitle = "Rpay Gateway Dashboard";
$activeNav = "dashboard";
require_once __DIR__ . '/includes/header.php';
?>

<!-- Alerts -->
<?php if ($flashSuccess): ?>
    <div class="alert alert-success">
        <strong>Success!</strong> <?= htmlspecialchars($flashSuccess) ?>
    </div>
<?php endif; ?>

<?php if ($flashError): ?>
    <div class="alert alert-danger">
        <strong>Error!</strong> <?= htmlspecialchars($flashError) ?>
    </div>
<?php endif; ?>

<!-- Credentials Alert Component -->
<?php require_once __DIR__ . '/includes/client_credentials_alert.php'; ?>

<!-- Dashboard Title & Operations Summary -->
<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 24px;">
    <div>
        <div class="section-title" style="margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary-blue);"><rect x="3" y="3" width="7" height="9"></rect><rect x="14" y="3" width="7" height="5"></rect><rect x="14" y="12" width="7" height="9"></rect><rect x="3" y="16" width="7" height="5"></rect></svg>
            Operations Overview
        </div>
        <div style="font-size: 0.85rem; color: var(--text-gray);">
            Real-time payment volume, settlement metrics, and connected merchant activity.
        </div>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
        <?php if (RBAC::can('clients.create')): ?>
        <a href="api_clients/onboarding.php" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            <span>Onboard Merchant</span>
        </a>
        <?php endif; ?>
        <a href="payments/list_payments.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
            <span>View All Payments</span>
        </a>
    </div>
</div>

<!-- Metrics Cards Component -->
<?php require_once __DIR__ . '/includes/metrics_cards.php'; ?>

<!-- Filtering Section Component (Above Overview Transactions Table) -->
<?php require_once __DIR__ . '/includes/overview_filter.php'; ?>

<!-- Transactions Table Component -->
<div style="margin-top: 0;">
    <?php
    $tableTitle = $isFiltered ? 'Filtered Transactions Stream' : 'Real-Time Transactions Stream';
    require_once __DIR__ . '/includes/transactions_table.php';
    ?>
</div>

<?php require_once __DIR__ . '/includes/refund_modal.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
