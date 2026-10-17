<?php
/**
 * ------------------------------------------------------------
 * notifications.php – Administrative Notification Center
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway - Admin Panel
 * Purpose : Centralized in-app notification center for administrators:
 *           - Failed Webhooks (Layer 2 merchant endpoint failures)
 *           - New Disputes & Chargebacks (Customer disputes)
 *           - High-Value Transactions (AML/fraud monitoring)
 *           - System & Security Alerts
 * ------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Notification Center — Vortex Admin';
$activeNav = 'notifications';

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/RBAC.php';
require_once __DIR__ . '/../classes/NotificationCenter.php';

// Auth Guard
if (empty($_SESSION['vortex_admin_id'])) {
    header('Location: login.php');
    exit;
}

$adminId = (int)$_SESSION['vortex_admin_id'];

// Initial sync of live gateway transactions on direct visit
NotificationCenter::syncGatewayAlerts();

// Handle Post Actions (mark all read, clear, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_all_read') {
        NotificationCenter::markAllAsRead($adminId);
        header('Location: notifications.php?msg=all_read');
        exit;
    }

    if ($action === 'mark_read' && !empty($_POST['id'])) {
        NotificationCenter::markAsRead((int)$_POST['id'], $adminId);
        header('Location: notifications.php?msg=read');
        exit;
    }

    if ($action === 'delete' && !empty($_POST['id'])) {
        NotificationCenter::delete((int)$_POST['id']);
        header('Location: notifications.php?msg=deleted');
        exit;
    }

    if ($action === 'simulate' && !empty($_POST['sim_type'])) {
        $simType = $_POST['sim_type'];
        if ($simType === 'failed_webhook') {
            NotificationCenter::create(
                NotificationCenter::TYPE_FAILED_WEBHOOK,
                "Webhook Dispatch Failed: Rajagiri Event Portal",
                "Outbound Layer 2 POST to 'https://events.rajagiri.edu/api/webhook' failed with HTTP 500 Internal Server Error.",
                NotificationCenter::SEVERITY_CRITICAL,
                "api_clients/index.php",
                "wh_sim_" . time(),
                'webhook'
            );
        } elseif ($simType === 'new_dispute') {
            NotificationCenter::create(
                NotificationCenter::TYPE_NEW_DISPUTE,
                "Payment Dispute Lodged: ₹25,000.00",
                "Customer filed cardholder chargeback for transaction VTX_DISP_" . substr(md5((string)time()), 0, 6) . ". Reason: Unauthorized transaction claimed by cardholder.",
                NotificationCenter::SEVERITY_CRITICAL,
                "payments/list_payments.php",
                "disp_sim_" . time(),
                'transaction'
            );
        } else {
            NotificationCenter::create(
                NotificationCenter::TYPE_HIGH_VALUE_TXN,
                "High-Value Transaction: ₹50,000.00",
                "Payment of ₹50,000.00 captured from corporate.sponsor@techcorp.in for Platinum Sponsor Booth.",
                NotificationCenter::SEVERITY_WARNING,
                "payments/list_payments.php",
                "high_sim_" . time(),
                'transaction'
            );
        }
        header('Location: notifications.php?msg=simulated');
        exit;
    }
}

// Filters & Query Parameters
$typeFilter     = trim($_GET['type'] ?? 'all');
$severityFilter = trim($_GET['severity'] ?? 'all');
$statusFilter   = trim($_GET['status'] ?? 'all');
$searchQuery    = trim($_GET['q'] ?? '');
$page           = max(1, (int)($_GET['p'] ?? 1));
$limit          = 15;
$offset         = ($page - 1) * $limit;

$filterArgs = [
    'type'     => $typeFilter,
    'severity' => $severityFilter,
    'search'   => $searchQuery
];

if ($statusFilter === 'unread') {
    $filterArgs['unread_only'] = true;
} elseif ($statusFilter === 'read') {
    $filterArgs['is_read'] = 1;
}

$notifications = NotificationCenter::getNotifications($adminId, $limit, $offset, $filterArgs);
$stats = NotificationCenter::getStats($adminId);

require_once __DIR__ . '/includes/header.php';
?>

<div class="notifications-center-page">

    <!-- Page Header & Actions -->
    <div class="section-header" style="margin-bottom: 24px;">
        <div>
            <h1 class="section-title" style="margin-bottom: 4px;">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary-blue);"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                In-App Notification Center
            </h1>
            <p style="font-size: 0.9rem; color: var(--text-gray); margin: 0;">
                Real-time operational alerts for failed webhooks, customer disputes, and high-value payments.
            </p>
        </div>

        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <?php if ($stats['unread'] > 0): ?>
            <form method="POST" action="notifications.php" style="display: inline;">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Mark All as Read
                </button>
            </form>
            <?php endif; ?>

            <!-- Test Simulation Trigger Dropdown -->
            <div style="position: relative; display: inline-block;">
                <button type="button" class="btn btn-primary btn-sm" id="btnSimulateTrigger" onclick="document.getElementById('simMenu').classList.toggle('show');" style="display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    Simulate Test Alert ▾
                </button>
                <div id="simMenu" class="sim-dropdown-menu">
                    <form method="POST" action="notifications.php">
                        <input type="hidden" name="action" value="simulate">
                        <input type="hidden" name="sim_type" value="failed_webhook">
                        <button type="submit" class="sim-menu-item">
                            <span class="sim-dot red"></span>
                            <span>Failed Webhook (HTTP 500)</span>
                        </button>
                    </form>
                    <form method="POST" action="notifications.php">
                        <input type="hidden" name="action" value="simulate">
                        <input type="hidden" name="sim_type" value="new_dispute">
                        <button type="submit" class="sim-menu-item">
                            <span class="sim-dot amber"></span>
                            <span>Customer Payment Dispute</span>
                        </button>
                    </form>
                    <form method="POST" action="notifications.php">
                        <input type="hidden" name="action" value="simulate">
                        <input type="hidden" name="sim_type" value="high_value_transaction">
                        <button type="submit" class="sim-menu-item">
                            <span class="sim-dot green"></span>
                            <span>High-Value Transaction (₹50,000)</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert / Toast Messages -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'all_read'): ?>
        <div class="alert alert-success" style="margin-bottom: 20px;">✓ All notifications have been marked as read.</div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'simulated'): ?>
        <div class="alert alert-success" style="margin-bottom: 20px;">✓ Test alert simulated and captured in Notification Center.</div>
    <?php endif; ?>

    <!-- Summary Metrics Cards -->
    <div class="metrics-grid" style="margin-bottom: 24px;">
        <div class="metric-card">
            <div class="metric-label">Unread Alerts</div>
            <div class="metric-value <?= $stats['unread'] > 0 ? 'amount' : '' ?>" style="<?= $stats['unread'] > 0 ? 'color: var(--danger-text);' : '' ?>">
                <?= number_format($stats['unread']) ?>
            </div>
            <span style="font-size: 0.78rem; color: var(--text-gray);">Pending your review</span>
        </div>

        <div class="metric-card">
            <div class="metric-label">Failed Webhooks</div>
            <div class="metric-value" style="color: #ea4335;">
                <?= number_format($stats['failed_webhooks']) ?>
            </div>
            <span style="font-size: 0.78rem; color: var(--text-gray);">Layer 2 dispatch issues</span>
        </div>

        <div class="metric-card">
            <div class="metric-label">Disputes & Chargebacks</div>
            <div class="metric-value" style="color: #fbbc04;">
                <?= number_format($stats['disputes']) ?>
            </div>
            <span style="font-size: 0.78rem; color: var(--text-gray);">Customer payment claims</span>
        </div>

        <div class="metric-card">
            <div class="metric-label">High-Value Txns (&gt; ₹10k)</div>
            <div class="metric-value" style="color: #34a853;">
                <?= number_format($stats['high_value']) ?>
            </div>
            <span style="font-size: 0.78rem; color: var(--text-gray);">AML / compliance vigilance</span>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="notif-toolbar-card" style="margin-bottom: 24px; padding: 18px 24px; background: var(--bg-card); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <form method="GET" action="notifications.php" style="display: flex; flex-wrap: wrap; gap: 14px; align-items: center; justify-content: space-between;">
            
            <!-- Category Tabs / Pills -->
            <div class="notif-pill-filter-group" style="display: flex; gap: 8px; flex-wrap: wrap;">
                <a href="notifications.php?type=all&status=<?= urlencode($statusFilter) ?>" 
                   class="tab-pill <?= $typeFilter === 'all' ? 'active' : '' ?>">
                   All Alerts
                </a>
                <a href="notifications.php?type=failed_webhook&status=<?= urlencode($statusFilter) ?>" 
                   class="tab-pill <?= $typeFilter === 'failed_webhook' ? 'active' : '' ?>">
                   Failed Webhooks
                </a>
                <a href="notifications.php?type=new_dispute&status=<?= urlencode($statusFilter) ?>" 
                   class="tab-pill <?= $typeFilter === 'new_dispute' ? 'active' : '' ?>">
                   Disputes
                </a>
                <a href="notifications.php?type=high_value_transaction&status=<?= urlencode($statusFilter) ?>" 
                   class="tab-pill <?= $typeFilter === 'high_value_transaction' ? 'active' : '' ?>">
                   High-Value
                </a>
                <a href="notifications.php?type=system_alert&status=<?= urlencode($statusFilter) ?>" 
                   class="tab-pill <?= $typeFilter === 'system_alert' ? 'active' : '' ?>">
                   System
                </a>
            </div>

            <!-- Status & Search Inputs -->
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <select name="status" class="form-control" style="padding: 6px 12px; font-size: 0.85rem; height: 36px;" onchange="this.form.submit()">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="unread" <?= $statusFilter === 'unread' ? 'selected' : '' ?>>Unread Only</option>
                    <option value="read" <?= $statusFilter === 'read' ? 'selected' : '' ?>>Read Only</option>
                </select>

                <select name="severity" class="form-control" style="padding: 6px 12px; font-size: 0.85rem; height: 36px;" onchange="this.form.submit()">
                    <option value="all" <?= $severityFilter === 'all' ? 'selected' : '' ?>>All Severities</option>
                    <option value="critical" <?= $severityFilter === 'critical' ? 'selected' : '' ?>>Critical</option>
                    <option value="warning" <?= $severityFilter === 'warning' ? 'selected' : '' ?>>Warning</option>
                    <option value="info" <?= $severityFilter === 'info' ? 'selected' : '' ?>>Info</option>
                </select>

                <div style="position: relative;">
                    <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" 
                           placeholder="Search alerts..." 
                           class="form-control" 
                           style="padding: 6px 12px; font-size: 0.85rem; height: 36px; min-width: 180px;">
                </div>

                <input type="hidden" name="type" value="<?= htmlspecialchars($typeFilter) ?>">
                <button type="submit" class="btn btn-secondary btn-sm" style="height: 36px;">Filter</button>
                <?php if ($typeFilter !== 'all' || $statusFilter !== 'all' || $severityFilter !== 'all' || !empty($searchQuery)): ?>
                    <a href="notifications.php" class="btn btn-secondary btn-sm" style="height: 36px; color: var(--text-gray);" title="Reset filters">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Notifications Stream List -->
    <div class="notifications-stream">
        <?php if (empty($notifications)): ?>
            <div style="text-align: center; padding: 60px 20px; background: var(--bg-card); border-radius: 12px;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text-gray); margin-bottom: 12px; opacity: 0.6;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                <h3 style="font-size: 1.1rem; color: var(--text-dark); margin-bottom: 6px;">All Clear!</h3>
                <p style="font-size: 0.88rem; color: var(--text-gray); max-width: 420px; margin: 0 auto;">
                    No administrative alerts found matching your selected criteria.
                </p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($notifications as $item): ?>
                    <?php
                    $isUnread = (int)$item['is_read'] === 0;
                    $type = $item['type'];
                    $sev = $item['severity'];

                    // Color and icon determination
                    $typeBadgeClass = 'badge-secondary';
                    $typeLabel = 'System';
                    $badgeBg = '#e8f0fe';
                    $badgeColor = '#1a73e8';

                    if ($type === NotificationCenter::TYPE_FAILED_WEBHOOK) {
                        $typeLabel = 'Failed Webhook';
                        $badgeBg = 'rgba(234, 67, 53, 0.12)';
                        $badgeColor = '#ea4335';
                    } elseif ($type === NotificationCenter::TYPE_NEW_DISPUTE) {
                        $typeLabel = 'Dispute / Chargeback';
                        $badgeBg = 'rgba(251, 188, 4, 0.15)';
                        $badgeColor = '#b06000';
                    } elseif ($type === NotificationCenter::TYPE_HIGH_VALUE_TXN) {
                        $typeLabel = 'High Value Payment';
                        $badgeBg = 'rgba(52, 168, 83, 0.14)';
                        $badgeColor = '#188038';
                    }
                    ?>

                    <div class="notif-card <?= $isUnread ? 'unread' : 'read' ?>" id="notif-card-<?= $item['id'] ?>" style="background: var(--bg-card); border-radius: 12px; padding: 18px 24px; display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); transition: transform 0.15s, box-shadow 0.15s;">
                        
                        <div style="display: flex; align-items: flex-start; gap: 16px; flex: 1;">
                            <!-- Unread Indicator Dot & Type Icon -->
                            <div style="position: relative; flex-shrink: 0; margin-top: 2px;">
                                <div style="width: 40px; height: 40px; border-radius: 10px; background: <?= $badgeBg ?>; color: <?= $badgeColor ?>; display: flex; align-items: center; justify-content: center;">
                                    <?php if ($type === NotificationCenter::TYPE_FAILED_WEBHOOK): ?>
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path><line x1="2" y1="2" x2="22" y2="22"></line></svg>
                                    <?php elseif ($type === NotificationCenter::TYPE_NEW_DISPUTE): ?>
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                    <?php elseif ($type === NotificationCenter::TYPE_HIGH_VALUE_TXN): ?>
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                                    <?php else: ?>
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                                    <?php endif; ?>
                                </div>
                                <?php if ($isUnread): ?>
                                    <span style="position: absolute; top: -3px; right: -3px; width: 10px; height: 10px; background-color: #ea4335; border-radius: 50%; box-shadow: 0 0 0 2px var(--bg-card);"></span>
                                <?php endif; ?>
                            </div>

                            <!-- Notification Body -->
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 4px;">
                                    <h3 style="font-size: 0.98rem; font-weight: 600; color: var(--text-dark); margin: 0;">
                                        <?= htmlspecialchars($item['title']) ?>
                                    </h3>
                                    <span style="font-size: 0.72rem; font-weight: 600; padding: 2px 8px; border-radius: 4px; background: <?= $badgeBg ?>; color: <?= $badgeColor ?>;">
                                        <?= $typeLabel ?>
                                    </span>
                                    <?php if ($sev === 'critical'): ?>
                                        <span style="font-size: 0.72rem; font-weight: 600; padding: 2px 8px; border-radius: 4px; background: rgba(234, 67, 53, 0.15); color: #ea4335;">CRITICAL</span>
                                    <?php endif; ?>
                                </div>

                                <p style="font-size: 0.88rem; color: var(--text-gray); margin: 0 0 8px 0; line-height: 1.45;">
                                    <?= htmlspecialchars($item['message']) ?>
                                </p>

                                <div style="display: flex; align-items: center; gap: 14px; font-size: 0.78rem; color: var(--text-gray);">
                                    <span title="<?= htmlspecialchars($item['created_at']) ?>">
                                        <?= NotificationCenter::timeAgo($item['created_at']) ?>
                                    </span>
                                    <?php if (!empty($item['reference_id'])): ?>
                                        <span>• Ref: <code style="font-family: var(--font-mono); font-size: 0.75rem; background: var(--bg-gray); padding: 1px 5px; border-radius: 3px;"><?= htmlspecialchars($item['reference_id']) ?></code></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Card Right Actions -->
                        <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
                            <?php if (!empty($item['link'])): ?>
                                <a href="<?= htmlspecialchars($item['link']) ?>" class="btn btn-secondary btn-sm" style="font-size: 0.82rem; padding: 5px 10px;">
                                    Inspect Source →
                                </a>
                            <?php endif; ?>

                            <?php if ($isUnread): ?>
                                <form method="POST" action="notifications.php" style="display: inline;">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm" title="Mark as read" style="padding: 5px 8px;">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    </button>
                                </form>
                            <?php endif; ?>

                            <form method="POST" action="notifications.php" style="display: inline;" onsubmit="return confirm('Delete this alert?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                <button type="submit" class="btn btn-secondary btn-sm" title="Delete notification" style="padding: 5px 8px; color: var(--text-gray);">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                </button>
                            </form>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<style>
/* Simulation Dropdown Styling */
.sim-dropdown-menu {
    display: none;
    position: absolute;
    right: 0;
    top: calc(100% + 6px);
    background: var(--bg-card);
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.18);
    z-index: 1000;
    min-width: 250px;
    overflow: hidden;
    padding: 6px 0;
}
.sim-dropdown-menu.show {
    display: block;
}
.sim-menu-item {
    width: 100%;
    padding: 10px 16px;
    background: transparent;
    border: none;
    text-align: left;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.86rem;
    color: var(--text-dark);
    cursor: pointer;
    transition: background-color 0.15s;
}
.sim-menu-item:hover {
    background-color: var(--bg-hover);
}
.sim-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.sim-dot.red { background-color: #ea4335; }
.sim-dot.amber { background-color: #fbbc04; }
.sim-dot.green { background-color: #34a853; }

.notif-card.unread {
    border-left: 4px solid var(--primary-blue) !important;
}
.notif-card.read {
    opacity: 0.85;
}
.notif-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.06) !important;
}
</style>

<script>
// Close simulation dropdown when clicking outside
document.addEventListener('click', function(e) {
    const trigger = document.getElementById('btnSimulateTrigger');
    const menu = document.getElementById('simMenu');
    if (trigger && menu && !trigger.contains(e.target) && !menu.contains(e.target)) {
        menu.classList.remove('show');
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
