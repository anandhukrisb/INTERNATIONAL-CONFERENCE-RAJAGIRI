<?php
/**
 * ------------------------------------------------------------
 * index.php – Events & Conferences Management
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway - Admin Panel
 * Purpose : Modular dedicated page for event lifecycle,
 *           departments, scheduling, activation, and payments.
 * ------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Event.php';
require_once __DIR__ . '/../../classes/RBAC.php';
require_once __DIR__ . '/../../classes/AuditLogger.php';
require_once __DIR__ . '/../../classes/Logger.php';

// Auth Guard
if (empty($_SESSION['vortex_admin_id'])) {
    header('Location: ../login.php');
    exit;
}

// RBAC Guard
RBAC::require('events.view', null, '../dashboard.php');

// CSRF Token Initialization
if (empty($_SESSION['vortex_csrf_token'])) {
    $_SESSION['vortex_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['vortex_csrf_token'];

$eventModel = new Event();
$db = Database::getInstance()->getConnection();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Create Event
    if ($action === 'create_event') {
        RBAC::require('events.create', null, 'index.php');
        $eventName  = trim($_POST['event_name'] ?? '');
        $department = trim($_POST['department'] ?? $_POST['event_department'] ?? '');
        $rawStart   = trim($_POST['start_date'] ?? '');
        $rawEnd     = trim($_POST['end_date'] ?? '');

        $webhookUrl = trim($_POST['webhook_url'] ?? '');

        if (!empty($rawStart) && !empty($rawEnd) && strtotime($rawStart) !== false && strtotime($rawEnd) !== false) {
            $startDate = date('Y-m-d 00:00:00', strtotime($rawStart));
            $endDate   = date('Y-m-d 23:59:59', strtotime($rawEnd));
        } else {
            $startDate = $rawStart;
            $endDate   = $rawEnd;
        }

        $result = $eventModel->createEvent($eventName, $department, $startDate, $endDate, $webhookUrl);

        if ($result['success']) {
            $createdId = $result['data']['event_id'] ?? '';
            AuditLogger::log(
                'event',
                $createdId,
                AuditLogger::EVENT_CREATED,
                null,
                ['event_name' => $eventName, 'department' => $department, 'start_date' => $startDate, 'end_date' => $endDate, 'webhook_url' => $webhookUrl],
                "Created new event '{$eventName}' (#{$createdId})"
            );
            $_SESSION['flash_success'] = "Event '{$eventName}' created successfully with ID: {$createdId}";
        } else {
            $_SESSION['flash_error'] = "Failed to create event: " . $result['message'];
        }
        header('Location: index.php');
        exit;
    }

    // 2. Update Event Webhook
    if ($action === 'update_webhook') {
        RBAC::require('events.edit', null, 'index.php');
        $eventId       = trim($_POST['event_id'] ?? '');
        $webhookUrl    = trim($_POST['webhook_url'] ?? '');
        $webhookSecret = trim($_POST['webhook_secret'] ?? '');
        $res = $eventModel->updateWebhook($eventId, $webhookUrl, $webhookSecret ?: null);
        if ($res['success']) {
            AuditLogger::log('event', $eventId, AuditLogger::EVENT_EDITED, null, ['webhook_url' => $webhookUrl], "Updated webhook endpoint for event #{$eventId}");
            $_SESSION['flash_success'] = "Webhook endpoint for event '{$eventId}' updated successfully.";
        } else {
            $_SESSION['flash_error'] = "Failed to update webhook: " . $res['message'];
        }
        header('Location: index.php');
        exit;
    }

    // 2. Activate Event
    if ($action === 'activate_event') {
        RBAC::require('events.edit', null, 'index.php');
        $eventId = trim($_POST['event_id'] ?? '');
        if (!empty($eventId)) {
            $res = $eventModel->activateEvent($eventId);
            if ($res['success']) {
                AuditLogger::log('event', $eventId, AuditLogger::EVENT_ACTIVATED, ['is_active' => 0], ['is_active' => 1], "Activated event #{$eventId}");
                $_SESSION['flash_success'] = "Event '{$eventId}' has been activated.";
            } else {
                $_SESSION['flash_error'] = "Failed to activate event: " . $res['message'];
            }
        }
        header('Location: index.php');
        exit;
    }

    // 3. Deactivate Event
    if ($action === 'deactivate_event') {
        RBAC::require('events.edit', null, 'index.php');
        $eventId = trim($_POST['event_id'] ?? '');
        if (!empty($eventId)) {
            $res = $eventModel->deactivateEvent($eventId);
            if ($res['success']) {
                AuditLogger::log('event', $eventId, AuditLogger::EVENT_DEACTIVATED, ['is_active' => 1], ['is_active' => 0], "Deactivated event #{$eventId}");
                $_SESSION['flash_success'] = "Event '{$eventId}' has been deactivated.";
            } else {
                $_SESSION['flash_error'] = "Failed to deactivate event: " . $res['message'];
            }
        }
        header('Location: index.php');
        exit;
    }
}

// Flash Messages
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Query All Events
$allEvents = $eventModel->getAllEvents(200) ?: [];

// Metrics
$totalEventsCount    = count($allEvents);
$activeEventsCount   = count(array_filter($allEvents, fn($e) => (int)($e['is_active'] ?? 0) === 1));
$inactiveEventsCount = $totalEventsCount - $activeEventsCount;

// Calculate revenue per event
$eventRevenueMap = [];
try {
    $revStmt = $db->query("
        SELECT event_id, COUNT(*) as tx_count, COALESCE(SUM(amount), 0) as total_rev 
        FROM transactions 
        WHERE status = 'SUCCESS' 
        GROUP BY event_id
    ");
    while ($r = $revStmt->fetch(PDO::FETCH_ASSOC)) {
        $eventRevenueMap[$r['event_id']] = $r;
    }
} catch (\Throwable $e) {}

// Search & Filter
$searchQ = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'ALL');

$filteredEvents = array_filter($allEvents, function($evt) use ($searchQ, $statusFilter) {
    if ($statusFilter === 'ACTIVE' && (int)$evt['is_active'] !== 1) return false;
    if ($statusFilter === 'DISABLED' && (int)$evt['is_active'] === 1) return false;

    if ($searchQ !== '') {
        $haystack = strtolower(($evt['event_name'] ?? '') . ' ' . ($evt['event_id'] ?? '') . ' ' . ($evt['department'] ?? ''));
        if (!str_contains($haystack, strtolower($searchQ))) return false;
    }
    return true;
});

$pageTitle = "Events Management - Rpay Admin";
$activeNav = "events";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Flash Alerts -->
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

<!-- Header & Title -->
<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 24px;">
    <div>
        <div class="section-title" style="margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary-blue);"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            Events &amp; Campaigns Management
        </div>
        <div style="font-size: 0.85rem; color: var(--text-gray);">
            Configure active event campaigns, dedicated webhook endpoints, dates, and payment collections.
        </div>
    </div>
    <?php if (RBAC::can('events.create')): ?>
    <button type="button" class="btn btn-primary" onclick="if(typeof openEventModal === 'function') openEventModal(event);" style="display: inline-flex; align-items: center; gap: 6px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        <span>New Event</span>
    </button>
    <?php endif; ?>
</div>

<!-- Event Metrics Grid -->
<div class="metrics-grid" style="margin-bottom: 24px;">
    <div class="metric-card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="metric-label">Total Events</span>
            <div style="width: 32px; height: 32px; border-radius: 8px; background: var(--primary-light); display: flex; align-items: center; justify-content: center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary-blue);"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            </div>
        </div>
        <span class="metric-value" id="statTotalEvents"><?= number_format($totalEventsCount) ?></span>
    </div>
    <div class="metric-card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="metric-label">Active For Payments</span>
            <div style="width: 32px; height: 32px; border-radius: 8px; background: var(--success-bg); display: flex; align-items: center; justify-content: center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--success-text);"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            </div>
        </div>
        <span class="metric-value success" id="statActiveEvents"><?= number_format($activeEventsCount) ?></span>
    </div>
    <div class="metric-card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="metric-label">Disabled / Concluded</span>
            <div style="width: 32px; height: 32px; border-radius: 8px; background: var(--danger-bg); display: flex; align-items: center; justify-content: center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--danger-text);"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
            </div>
        </div>
        <span class="metric-value danger" id="statInactiveEvents"><?= number_format($inactiveEventsCount) ?></span>
    </div>
</div>

<!-- Events Table & Filter Card -->
<div class="section-card">
    <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 14px 20px; border-bottom: 1px solid var(--border-color); background: #fafbfc;">
        <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-dark);">
            Configured Events (<span id="tableEventsCount"><?= count($filteredEvents) ?></span>)
        </div>
        <!-- Search & Status Filter Form -->
        <form method="GET" action="index.php" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <input type="text" name="q" class="form-control" placeholder="Search event / host..." value="<?= htmlspecialchars($searchQ) ?>" style="font-size: 0.82rem; padding: 5px 10px; width: 200px;">
            <select name="status" class="form-control" style="font-size: 0.82rem; padding: 5px 10px; width: 120px;" onchange="this.form.submit()">
                <option value="ALL" <?= $statusFilter === 'ALL' ? 'selected' : '' ?>>All Status</option>
                <option value="ACTIVE" <?= $statusFilter === 'ACTIVE' ? 'selected' : '' ?>>Active Only</option>
                <option value="DISABLED" <?= $statusFilter === 'DISABLED' ? 'selected' : '' ?>>Disabled</option>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm" style="padding: 5px 10px;">Filter</button>
            <?php if ($searchQ !== '' || $statusFilter !== 'ALL'): ?>
                <a href="index.php" class="btn btn-secondary btn-sm" style="padding: 5px 10px; color: #dc2626;">Reset</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="section-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Event ID</th>
                        <th>Event Title &amp; Department</th>
                        <th>Webhook Endpoint</th>
                        <th>Dates Window</th>
                        <th>Revenue Collected</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($filteredEvents)): ?>
                        <tr><td colspan="7" style="text-align: center; padding: 28px; color: var(--text-gray);">No events matching criteria found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($filteredEvents as $event): ?>
                            <?php
                            $eId = $event['event_id'];
                            $revData = $eventRevenueMap[$eId] ?? ['tx_count' => 0, 'total_rev' => 0.0];
                            $hasCustomWebhook = !empty($event['webhook_url']);
                            ?>
                            <tr id="event-row-<?= htmlspecialchars($eId) ?>" data-event-id="<?= htmlspecialchars($eId) ?>" data-event-name="<?= htmlspecialchars($event['event_name']) ?>" data-is-active="<?= (int)$event['is_active'] ?>" data-tx-count="<?= (int)$revData['tx_count'] ?>" data-total-rev="<?= (float)$revData['total_rev'] ?>">
                                <td>
                                    <div class="copy-wrapper">
                                        <span class="font-mono" style="font-weight: 700; color: var(--primary-blue);"><?= htmlspecialchars($eId) ?></span>
                                        <button type="button" class="btn-copy btn-copy-sm" onclick="copyToClipboard('<?= htmlspecialchars($eId, ENT_QUOTES) ?>', this)" title="Copy Event ID">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-dark); font-size: 0.92rem;">
                                        <?= htmlspecialchars($event['event_name']) ?>
                                    </div>
                                    <div style="font-size: 0.78rem; color: var(--text-gray); margin-top: 3px; display: flex; align-items: center; gap: 4px;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"></path><path d="M5 21V7l7-4 7 4v14"></path><path d="M9 10h1"></path><path d="M9 14h1"></path><path d="M14 10h1"></path><path d="M14 14h1"></path></svg>
                                        <span><?= htmlspecialchars($event['department'] ?? 'General') ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($hasCustomWebhook): ?>
                                        <div style="font-size: 0.8rem; font-family: monospace; color: var(--text-dark); word-break: break-all; max-width: 220px;">
                                            <?= htmlspecialchars($event['webhook_url']) ?>
                                        </div>
                                        <div style="font-size: 0.74rem; color: var(--text-gray); margin-top: 3px; display: flex; align-items: center; gap: 4px;">
                                            <span>Secret:</span>
                                            <span class="font-mono" style="font-size: 0.72rem; color: var(--text-dark);"><?= htmlspecialchars(substr($event['webhook_secret'] ?? '', 0, 8)) ?>••••</span>
                                            <button type="button" class="btn-copy btn-copy-sm" onclick="copyToClipboard('<?= htmlspecialchars($event['webhook_secret'] ?? '', ENT_QUOTES) ?>', this)" title="Copy Secret" style="padding: 1px 4px; font-size: 0.68rem;">Copy</button>
                                        </div>
                                        <div style="display: flex; gap: 6px; align-items: center; margin-top: 4px;">
                                            <span class="badge badge-success" style="font-size: 0.65rem; padding: 2px 6px;">Active Callback</span>
                                            <?php if (RBAC::can('events.edit')): ?>
                                                <button type="button" class="btn btn-secondary btn-sm" style="padding: 1px 6px; font-size: 0.72rem;" onclick="openWebhookModal('<?= htmlspecialchars($eId, ENT_QUOTES) ?>', '<?= htmlspecialchars($event['webhook_url'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($event['webhook_secret'] ?? '', ENT_QUOTES) ?>')">Edit</button>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="font-size: 0.78rem; color: var(--text-gray); font-style: italic;">No webhook configured</div>
                                        <?php if (RBAC::can('events.edit')): ?>
                                            <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 7px; font-size: 0.72rem; margin-top: 4px; display: inline-flex; align-items: center; gap: 4px;" onclick="openWebhookModal('<?= htmlspecialchars($eId, ENT_QUOTES) ?>', '', '')">
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                                <span>Set URL</span>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.82rem; color: var(--text-gray); white-space: nowrap;">
                                    <?= date('M d, Y', strtotime($event['start_date'])) ?> &rarr; <?= date('M d, Y', strtotime($event['end_date'])) ?>
                                </td>
                                <td>
                                    <span style="font-weight: 700; color: var(--text-dark);">₹<?= number_format((float)$revData['total_rev'], 2) ?></span>
                                    <div style="font-size: 0.75rem; color: var(--text-gray);">
                                        <?= (int)$revData['tx_count'] ?> successful txs
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= (int)$event['is_active'] === 1 ? 'badge-success' : 'badge-danger' ?>">
                                        <?= (int)$event['is_active'] === 1 ? 'Active' : 'Disabled' ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                        <?php if (RBAC::can('events.edit')): ?>
                                        <form action="index.php" method="POST" style="display: inline;">
                                            <input type="hidden" name="event_id" value="<?= htmlspecialchars($eId) ?>">
                                            <?php if ((int)$event['is_active'] === 1): ?>
                                                <input type="hidden" name="action" value="deactivate_event">
                                                <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Deactivate event <?= htmlspecialchars($eId) ?>? Customers will not be able to pay.');">
                                                    Deactivate
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="action" value="activate_event">
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    Activate
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                        <?php endif; ?>

                                        <!-- Direct Link to Transactions -->
                                        <a href="../payments/list_payments.php?event_id=<?= urlencode($eId) ?>" class="btn btn-secondary btn-sm" title="View payments for this event" style="display: inline-flex; align-items: center; gap: 4px;">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                                            <span>Payments</span>
                                        </a>

                                        <!-- Event Audit Trail -->
                                        <a href="../audit_logs.php?entity_type=event&entity_id=<?= urlencode($eId) ?>" class="btn btn-secondary btn-sm" title="Inspect audit trail for this event" style="display: inline-flex; align-items: center; gap: 4px;">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                            <span>Audit</span>
                                        </a>

                                        <!-- Delete Event (Super Admin Only) -->
                                        <?php if (RBAC::hasRole(RBAC::ROLE_SUPER_ADMIN)): ?>
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="openDeleteModal('<?= htmlspecialchars($eId, ENT_QUOTES) ?>', '<?= htmlspecialchars($event['event_name'], ENT_QUOTES) ?>')" title="Delete this event" style="display: inline-flex; align-items: center; gap: 4px;">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                            <span>Delete</span>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Webhook Modal -->
<div class="modal-overlay" id="editWebhookModal" role="dialog" aria-modal="true">
    <div class="modal-box modal-md">
        <div class="modal-header">
            <h2 style="display: flex; align-items: center; gap: 8px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary-blue);"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                Configure Event Webhook
            </h2>
            <button type="button" class="modal-close" onclick="closeWebhookModal()" title="Close">&times;</button>
        </div>
        <form method="POST" action="index.php">
            <input type="hidden" name="action" value="update_webhook">
            <input type="hidden" name="event_id" id="modalWebhookEventId">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 600; color: var(--text-dark); margin-bottom: 6px;">Event Identifier</label>
                    <input type="text" id="modalWebhookEventDisplay" class="form-control" readonly style="background: var(--bg-hover); font-family: monospace; font-size: 0.85rem; color: var(--text-gray);">
                </div>
                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 600; color: var(--text-dark); margin-bottom: 6px;">Webhook Destination URL</label>
                    <input type="url" name="webhook_url" id="modalWebhookUrlInput" class="form-control font-mono" placeholder="https://dyuti.in/webhook.php" style="font-size: 0.85rem;">
                    <small style="display: block; margin-top: 4px; font-size: 0.74rem; color: var(--text-gray);">
                        Outbound payment notifications for this specific event will be dispatched here. Leave blank to disable webhooks for this event.
                    </small>
                </div>
                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label style="margin: 0; font-size: 0.82rem; font-weight: 600; color: var(--text-dark);">Webhook Secret</label>
                        <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 0.72rem;" onclick="generateAdminWebhookSecret()">🎲 Generate New</button>
                    </div>
                    <div style="display: flex; gap: 6px;">
                        <input type="password" id="modalWebhookSecretInput" name="webhook_secret" class="form-control font-mono" style="font-size: 0.82rem;" placeholder="Auto-generated if empty">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleAdminWebhookSecret(this)">Reveal</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="copyToClipboard(document.getElementById('modalWebhookSecretInput').value, this)">Copy</button>
                    </div>
                    <small style="display: block; margin-top: 4px; font-size: 0.72rem; color: var(--text-gray);">
                        Secret used by receiver listener to verify HMAC-SHA256 signature in X-Vortex-Signature.
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeWebhookModal()">Cancel</button>
                <button type="submit" class="btn-submit-event">Save Webhook</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Event Confirmation Modal -->
<div class="modal-overlay" id="deleteEventModal" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
    <div class="modal-box modal-md">
        <div class="modal-header">
            <h2 id="deleteModalTitle" style="display: flex; align-items: center; gap: 8px; color: var(--danger-text);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                <span>Delete Event</span>
            </h2>
            <button type="button" class="modal-close" onclick="closeDeleteModal()" title="Close">&times;</button>
        </div>
        <div class="modal-body">
            <p style="color: var(--text-dark); font-size: 0.92rem; margin-bottom: 14px; line-height: 1.4;">
                Are you sure you want to delete this event? This action cannot be undone.
            </p>
            <div style="background: var(--bg-gray); padding: 14px 16px; border-radius: 8px; margin-bottom: 14px; border: 1px solid var(--border-color);">
                <div style="font-size: 0.74rem; text-transform: uppercase; font-weight: 700; color: var(--text-gray); margin-bottom: 4px; letter-spacing: 0.5px;">Event Name</div>
                <div id="deleteModalEventName" style="font-weight: 700; font-size: 0.98rem; color: var(--text-dark); margin-bottom: 10px; word-break: break-word;"></div>
                <div style="font-size: 0.74rem; text-transform: uppercase; font-weight: 700; color: var(--text-gray); margin-bottom: 4px; letter-spacing: 0.5px;">Event ID</div>
                <div id="deleteModalEventId" class="font-mono" style="font-size: 0.85rem; font-weight: 700; color: var(--primary-blue);"></div>
            </div>
            <div class="alert alert-danger" style="margin: 0; font-size: 0.82rem; padding: 12px 14px; display: flex; align-items: flex-start; gap: 8px; border-radius: 6px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0; margin-top: 1px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <div>
                    <strong>Financial Safety Guard:</strong> If this event has any transaction or payment records, permanent deletion will be blocked to maintain compliance and audit logs.
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" id="cancelDeleteEventBtn" onclick="closeDeleteModal()">Cancel</button>
            <button type="button" id="confirmDeleteEventBtn" class="btn btn-danger" onclick="executeDeleteEvent()">
                Delete Event
            </button>
        </div>
    </div>
</div>

<!-- Floating Toast Notifications Container -->
<div class="vortex-toast-container" id="vortexToastContainer"></div>

<script>
const VORTEX_CSRF_TOKEN = '<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>';
let currentDeleteEventId = null;

function openDeleteModal(eventId, eventName) {
    currentDeleteEventId = eventId;
    const nameEl = document.getElementById('deleteModalEventName');
    const idEl = document.getElementById('deleteModalEventId');
    if (nameEl) nameEl.textContent = eventName;
    if (idEl) idEl.textContent = eventId;

    const modal = document.getElementById('deleteEventModal');
    if (modal) {
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
}

function closeDeleteModal() {
    const modal = document.getElementById('deleteEventModal');
    if (modal) {
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }
    currentDeleteEventId = null;
}

function decrementStat(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const current = parseInt(el.textContent.replace(/,/g, ''), 10);
    if (!isNaN(current) && current > 0) {
        el.textContent = (current - 1).toLocaleString();
    }
}

function executeDeleteEvent() {
    if (!currentDeleteEventId) return;

    const eventId = currentDeleteEventId;
    const confirmBtn = document.getElementById('confirmDeleteEventBtn');
    const cancelBtn = document.getElementById('cancelDeleteEventBtn');
    const originalText = confirmBtn.innerHTML;

    // In-flight state: disable buttons & show spinner
    confirmBtn.disabled = true;
    if (cancelBtn) cancelBtn.disabled = true;
    confirmBtn.innerHTML = '<span class="vortex-btn-spinner" style="display:inline-block; width:14px; height:14px; border:2px solid #fff; border-top-color:transparent; border-radius:50%; animation:btn-spinner-anim 0.8s linear infinite; vertical-align:middle; margin-right:6px;"></span> Deleting...';

    // Also disable delete buttons in row to prevent double submit
    const row = document.getElementById('event-row-' + eventId);
    if (row) {
        const rowDeleteBtn = row.querySelector('.btn-outline-danger');
        if (rowDeleteBtn) rowDeleteBtn.disabled = true;
    }

    fetch('delete.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': VORTEX_CSRF_TOKEN,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            event_id: eventId,
            csrf_token: VORTEX_CSRF_TOKEN
        })
    })
    .then(async response => {
        const data = await response.json();
        return { ok: response.ok, status: response.status, data };
    })
    .then(res => {
        closeDeleteModal();
        if (res.ok && res.data.success) {
            showToast(res.data.message || 'Event deleted successfully.', 'success');
            
            // Fade out the row
            if (row) {
                const isActive = row.getAttribute('data-is-active') === '1';
                row.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
                row.style.opacity = '0';
                row.style.transform = 'translateX(20px)';
                
                setTimeout(() => {
                    row.remove();
                    // Update stat cards
                    decrementStat('statTotalEvents');
                    if (isActive) {
                        decrementStat('statActiveEvents');
                    } else {
                        decrementStat('statInactiveEvents');
                    }
                    // Update Configured Events (N) header count
                    decrementStat('tableEventsCount');

                    // Check if table is empty
                    const tbody = document.querySelector('.admin-table tbody');
                    if (tbody) {
                        const remaining = tbody.querySelectorAll('tr[id^="event-row-"]');
                        if (remaining.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 28px; color: var(--text-gray);">No events configured.</td></tr>';
                        }
                    }
                }, 350);
            }
        } else {
            showToast(res.data.message || 'Failed to delete event.', 'error', 4500);
            if (row) {
                const rowDeleteBtn = row.querySelector('.btn-outline-danger');
                if (rowDeleteBtn) rowDeleteBtn.disabled = false;
            }
        }
    })
    .catch(err => {
        closeDeleteModal();
        showToast('Network error or server error occurred.', 'error');
        if (row) {
            const rowDeleteBtn = row.querySelector('.btn-outline-danger');
            if (rowDeleteBtn) rowDeleteBtn.disabled = false;
        }
    })
    .finally(() => {
        confirmBtn.disabled = false;
        if (cancelBtn) cancelBtn.disabled = false;
        confirmBtn.innerHTML = originalText;
    });
}

function showToast(message, type = 'success', duration = 3500) {
    const container = document.getElementById('vortexToastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `vortex-toast toast-${type}`;

    let iconSvg = '';
    if (type === 'success') {
        iconSvg = '<span class="toast-icon" style="color:var(--success-text)">✓</span>';
    } else if (type === 'error') {
        iconSvg = '<span class="toast-icon" style="color:var(--danger-text)">⚠</span>';
    } else {
        iconSvg = '<span class="toast-icon" style="color:var(--primary-blue)">ℹ</span>';
    }

    toast.innerHTML = `
        ${iconSvg}
        <span class="toast-message">${escapeHtml(message)}</span>
        <button type="button" class="toast-close" title="Dismiss">&times;</button>
    `;

    toast.querySelector('.toast-close').addEventListener('click', () => {
        toast.classList.add('toast-leave');
        setTimeout(() => toast.remove(), 250);
    });

    container.appendChild(toast);

    setTimeout(() => {
        if (toast.parentElement) {
            toast.classList.add('toast-leave');
            setTimeout(() => toast.remove(), 250);
        }
    }, duration);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>

<script>
function openWebhookModal(eventId, currentUrl, currentSecret) {
    document.getElementById('modalWebhookEventId').value = eventId;
    document.getElementById('modalWebhookEventDisplay').value = eventId;
    document.getElementById('modalWebhookUrlInput').value = currentUrl || '';
    document.getElementById('modalWebhookSecretInput').value = currentSecret || '';
    document.getElementById('modalWebhookSecretInput').type = 'password';
    var modal = document.getElementById('editWebhookModal');
    if (modal) { modal.classList.add('open'); document.body.style.overflow = 'hidden'; }
}
function toggleAdminWebhookSecret(btn) {
    var inp = document.getElementById('modalWebhookSecretInput');
    if (!inp) return;
    if (inp.type === 'password') {
        inp.type = 'text';
        btn.textContent = 'Hide';
    } else {
        inp.type = 'password';
        btn.textContent = 'Reveal';
    }
}
function generateAdminWebhookSecret() {
    var arr = new Uint8Array(24);
    window.crypto.getRandomValues(arr);
    var hex = Array.from(arr, function(b) { return b.toString(16).padStart(2, '0'); }).join('');
    var inp = document.getElementById('modalWebhookSecretInput');
    inp.value = hex;
    inp.type = 'text';
}
function closeWebhookModal() {
    var modal = document.getElementById('editWebhookModal');
    if (modal) { modal.classList.remove('open'); document.body.style.overflow = ''; }
}
(function() {
    var modal = document.getElementById('editWebhookModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeWebhookModal();
        });
    }
    var delModal = document.getElementById('deleteEventModal');
    if (delModal) {
        delModal.addEventListener('click', function(e) {
            if (e.target === delModal) closeDeleteModal();
        });
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (delModal && delModal.classList.contains('open')) closeDeleteModal();
            if (modal && modal.classList.contains('open')) closeWebhookModal();
        }
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
