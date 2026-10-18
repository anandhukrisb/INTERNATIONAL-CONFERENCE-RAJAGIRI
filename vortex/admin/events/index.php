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

        if (!empty($rawStart) && !empty($rawEnd) && strtotime($rawStart) !== false && strtotime($rawEnd) !== false) {
            $startDate = date('Y-m-d 00:00:00', strtotime($rawStart));
            $endDate   = date('Y-m-d 23:59:59', strtotime($rawEnd));
        } else {
            $startDate = $rawStart;
            $endDate   = $rawEnd;
        }

        $result = $eventModel->createEvent($eventName, $department, $startDate, $endDate);

        if ($result['success']) {
            $createdId = $result['data']['event_id'] ?? '';
            AuditLogger::log(
                'event',
                $createdId,
                AuditLogger::EVENT_CREATED,
                null,
                ['event_name' => $eventName, 'department' => $department, 'start_date' => $startDate, 'end_date' => $endDate],
                "Created new event '{$eventName}' (#{$createdId})"
            );
            $_SESSION['flash_success'] = "Event '{$eventName}' created successfully with ID: {$createdId}";
        } else {
            $_SESSION['flash_error'] = "Failed to create event: " . $result['message'];
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

$pageTitle = "Events Management - Vortex Admin";
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
<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
    <div>
        <div class="section-title" style="margin-bottom: 4px;">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary-blue); vertical-align: -4px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            Events &amp; Conferences Management
        </div>
        <div style="font-size: 0.85rem; color: var(--text-gray);">
            Configure university events, track schedules, departments, and active payment collections.
        </div>
    </div>
    <?php if (RBAC::can('events.create')): ?>
    <button type="button" class="btn btn-primary" onclick="if(typeof openEventModal === 'function') openEventModal(event);" style="display: inline-flex; align-items: center; gap: 6px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        New Event
    </button>
    <?php endif; ?>
</div>

<!-- Event Metrics Grid -->
<div class="metrics-grid" style="margin-bottom: 24px;">
    <div class="metric-card">
        <span class="metric-label">Total Events</span>
        <span class="metric-value"><?= number_format($totalEventsCount) ?></span>
    </div>
    <div class="metric-card">
        <span class="metric-label">Active For Payments</span>
        <span class="metric-value success"><?= number_format($activeEventsCount) ?></span>
    </div>
    <div class="metric-card">
        <span class="metric-label">Disabled / Concluded</span>
        <span class="metric-value danger"><?= number_format($inactiveEventsCount) ?></span>
    </div>
</div>

<!-- Create Event Box (inline, Super Admin & Event Managers) -->
<?php if (RBAC::can('events.create')): ?>
<div class="section-card" style="margin-bottom: 24px;">
    <div class="section-header" style="background: #fafbfc; border-bottom: 1px solid var(--border-color); padding: 14px 20px;">
        <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--text-dark); margin: 0;">
            ➕ Register New Event
        </h3>
    </div>
    <div class="section-body" style="padding: 20px;">
        <form action="index.php" method="POST">
            <input type="hidden" name="action" value="create_event">
            <div class="form-grid">
                <div class="form-group" style="grid-column: span 2;">
                    <label>Event Name *</label>
                    <input type="text" name="event_name" class="form-control" placeholder="e.g. International Conference on Computational Science 2026" required>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Department / Host School *</label>
                    <input type="text" name="department" class="form-control" placeholder="e.g. Department of Computer Science &amp; Engineering" required>
                </div>
                <div class="form-group">
                    <label>Start Date *</label>
                    <input type="date" name="start_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>End Date *</label>
                    <input type="date" name="end_date" class="form-control" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top: 14px;">Create Event</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Events Table & Filter Card -->
<div class="section-card">
    <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 14px 20px; border-bottom: 1px solid var(--border-color); background: #fafbfc;">
        <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-dark);">
            Configured Events (<?= count($filteredEvents) ?>)
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
                        <th>Dates Window</th>
                        <th>Revenue Collected</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($filteredEvents)): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 28px; color: var(--text-gray);">No events matching criteria found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($filteredEvents as $event): ?>
                            <?php
                            $eId = $event['event_id'];
                            $revData = $eventRevenueMap[$eId] ?? ['tx_count' => 0, 'total_rev' => 0.0];
                            ?>
                            <tr>
                                <td>
                                    <div class="copy-wrapper">
                                        <span class="font-mono" style="font-weight: 700; color: var(--primary-blue);"><?= htmlspecialchars($eId) ?></span>
                                        <button type="button" class="btn-copy btn-copy-sm" onclick="copyToClipboard('<?= htmlspecialchars($eId, ENT_QUOTES) ?>', this)" title="Copy Event ID">📋</button>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-dark); font-size: 0.92rem;">
                                        <?= htmlspecialchars($event['event_name']) ?>
                                    </div>
                                    <div style="font-size: 0.78rem; color: var(--text-gray); margin-top: 3px;">
                                        🏛️ <?= htmlspecialchars($event['department'] ?? 'General') ?>
                                    </div>
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
                                        <a href="../payments/list_payments.php?event_id=<?= urlencode($eId) ?>" class="btn btn-secondary btn-sm" title="View payments for this event">
                                            💳 Payments
                                        </a>

                                        <!-- Event Audit Trail -->
                                        <a href="../audit_logs.php?entity_type=event&entity_id=<?= urlencode($eId) ?>" class="btn btn-secondary btn-sm" title="Inspect audit trail for this event">
                                            📜 Audit
                                        </a>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
