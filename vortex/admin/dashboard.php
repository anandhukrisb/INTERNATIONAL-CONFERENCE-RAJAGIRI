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

// Seamless Backwards Compatibility for query parameter tabs
$tabRedirect = trim($_GET['tab'] ?? '');
if ($tabRedirect === 'clients') {
    header('Location: api_clients/index.php');
    exit;
} elseif ($tabRedirect === 'events') {
    header('Location: events/index.php');
    exit;
}

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Event.php';
require_once __DIR__ . '/../classes/ApiClient.php';
require_once __DIR__ . '/../classes/RBAC.php';
require_once __DIR__ . '/../classes/AuditLogger.php';

$eventModel = new Event();
$apiClientModel = new ApiClient();
$db = Database::getInstance()->getConnection();

// Backwards-compatible POST handling for legacy forms submitting to dashboard.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_event') {
        RBAC::require('events.create', null, 'events/index.php');
        $eventName  = trim($_POST['event_name'] ?? '');
        $department = trim($_POST['department'] ?? $_POST['event_department'] ?? '');
        $startDate  = trim($_POST['start_date'] ?? '');
        $endDate    = trim($_POST['end_date'] ?? '');
        $result = $eventModel->createEvent($eventName, $department, $startDate, $endDate);
        if ($result['success']) {
            AuditLogger::log('event', $result['data']['event_id'] ?? '', AuditLogger::EVENT_CREATED, null, $_POST, "Created event '{$eventName}'");
            $_SESSION['flash_success'] = "Event '{$eventName}' created successfully!";
        } else {
            $_SESSION['flash_error'] = "Failed to create event: " . $result['message'];
        }
        header('Location: events/index.php');
        exit;
    }

    if ($action === 'create_client') {
        RBAC::require('clients.create', null, 'api_clients/index.php');
        $clientName = trim($_POST['client_name'] ?? '');
        $result = $apiClientModel->createClient($clientName);
        if ($result['success']) {
            AuditLogger::log('api_client', (string)($result['data']['client_id'] ?? ''), AuditLogger::CLIENT_CREATED, null, $_POST, "Registered client '{$clientName}'");
            $_SESSION['flash_success'] = "API Client '{$clientName}' created successfully!";
            $_SESSION['created_client'] = $result['data'];
        } else {
            $_SESSION['flash_error'] = "Failed to create API Client: " . $result['message'];
        }
        header('Location: api_clients/index.php');
        exit;
    }

    if (in_array($action, ['activate_event', 'deactivate_event'], true)) {
        header('Location: events/index.php');
        exit;
    }

    if (in_array($action, ['activate_client', 'deactivate_client', 'update_webhook'], true)) {
        header('Location: api_clients/index.php');
        exit;
    }
}

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
$activeEventsCount  = 0;
$totalClientsCount  = 0;

try {
    $totalPayments      = (int) $db->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
    $successfulPayments = (int) $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'SUCCESS'")->fetchColumn();
    $failedPayments     = (int) $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'FAILED'")->fetchColumn();
    $totalPaymentAmount = (float) $db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'SUCCESS'")->fetchColumn();
    $activeEventsCount  = (int) $db->query("SELECT COUNT(*) FROM events WHERE is_active = 1")->fetchColumn();
    $totalClientsCount  = (int) $db->query("SELECT COUNT(*) FROM api_clients")->fetchColumn();
} catch (\Throwable $e) {}

// Transactions Overview (Paginated)
$txPerPage = 15;
$txTotalCount = $totalPayments;
$txTotalPages = max(1, (int) ceil($txTotalCount / $txPerPage));
$txPage = max(1, (int) ($_GET['page'] ?? $_GET['p'] ?? 1));
if ($txPage > $txTotalPages && $txTotalCount > 0) {
    $txPage = $txTotalPages;
}
$txOffset = ($txPage - 1) * $txPerPage;

$recentTx = [];
try {
    $recentTxSql = "SELECT t.*, e.event_name, c.client_name,
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
                    ORDER BY t.id DESC LIMIT " . (int)$txPerPage . " OFFSET " . (int)$txOffset;
    $recentTxStmt = $db->query($recentTxSql);
    $recentTx = $recentTxStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    $recentTx = [];
}

$fromTx = ($txTotalCount === 0) ? 0 : $txOffset + 1;
$toTx = min($txOffset + count($recentTx), $txTotalCount);

$pageTitle = "Vortex Gateway Dashboard";
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
<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
    <div>
        <div class="section-title" style="margin-bottom: 4px;">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary-blue); vertical-align: -4px;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
            Gateway Operations Overview
        </div>
        <div style="font-size: 0.85rem; color: var(--text-gray);">
            Real-time transaction settlement volume, merchant integrations, and active university events.
        </div>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <?php if (RBAC::can('clients.create')): ?>
        <a href="api_clients/onboarding.php" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
            <span>🚀</span> Onboard Merchant
        </a>
        <?php endif; ?>
        <?php if (RBAC::can('events.create')): ?>
        <a href="events/index.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
            <span>🎟️</span> Manage Events
        </a>
        <?php endif; ?>
        <?php if (RBAC::can('clients.view')): ?>
        <a href="api_clients/index.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
            <span>🔌</span> API Clients
        </a>
        <?php endif; ?>
        <a href="audit_logs.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
            <span>📜</span> Audit Trail
        </a>
        <a href="search.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
            <span>🔍</span> Global Search
        </a>
    </div>
</div>

<!-- Metrics Cards Component -->
<?php require_once __DIR__ . '/includes/metrics_cards.php'; ?>

<!-- Transactions Table Component -->
<div style="margin-top: 24px;">
    <?php
    $tableTitle = 'Real-Time Transactions Stream';
    require_once __DIR__ . '/includes/transactions_table.php';
    ?>
</div>

<!-- Backwards Compatibility Hash Redirect Script -->
<script>
(function() {
    if (window.location.hash === '#clients') {
        window.location.replace('api_clients/index.php');
    } else if (window.location.hash === '#events') {
        window.location.replace('events/index.php');
    }
})();
</script>

<?php require_once __DIR__ . '/includes/refund_modal.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
