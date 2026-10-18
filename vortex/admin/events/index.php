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
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false)
    || (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $rawJson = file_get_contents('php://input');
        $parsedJson = json_decode($rawJson, true);
        if (is_array($parsedJson)) {
            $_POST = array_merge($_POST, $parsedJson);
        }
    }

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

        if ($isAjax) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success'            => $res['success'],
                'message'            => $res['success'] ? "Webhook endpoint for event '{$eventId}' updated successfully." : $res['message'],
                'event_id'           => $eventId,
                'webhook_url'        => $webhookUrl,
                'has_webhook_secret' => !empty($webhookSecret)
            ]);
            exit;
        }

        header('Location: index.php');
        exit;
    }

    // 3. Activate Event
    if ($action === 'activate_event') {
        RBAC::require('events.edit', null, 'index.php');
        $eventId = trim($_POST['event_id'] ?? '');
        $res = ['success' => false, 'message' => 'Valid Event ID is required.'];
        if (!empty($eventId)) {
            $res = $eventModel->activateEvent($eventId);
            if ($res['success']) {
                AuditLogger::log('event', $eventId, AuditLogger::EVENT_ACTIVATED, ['is_active' => 0], ['is_active' => 1], "Activated event #{$eventId}");
                $_SESSION['flash_success'] = "Event '{$eventId}' has been activated.";
            } else {
                $_SESSION['flash_error'] = "Failed to activate event: " . $res['message'];
            }
        }

        if ($isAjax) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success'   => $res['success'],
                'message'   => $res['success'] ? "Event '{$eventId}' has been activated." : ($res['message'] ?? 'Activation failed'),
                'event_id'  => $eventId,
                'is_active' => 1
            ]);
            exit;
        }

        header('Location: index.php');
        exit;
    }

    // 4. Deactivate Event
    if ($action === 'deactivate_event') {
        RBAC::require('events.edit', null, 'index.php');
        $eventId = trim($_POST['event_id'] ?? '');
        $res = ['success' => false, 'message' => 'Valid Event ID is required.'];
        if (!empty($eventId)) {
            $res = $eventModel->deactivateEvent($eventId);
            if ($res['success']) {
                AuditLogger::log('event', $eventId, AuditLogger::EVENT_DEACTIVATED, ['is_active' => 1], ['is_active' => 0], "Deactivated event #{$eventId}");
                $_SESSION['flash_success'] = "Event '{$eventId}' has been deactivated.";
            } else {
                $_SESSION['flash_error'] = "Failed to deactivate event: " . $res['message'];
            }
        }

        if ($isAjax) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success'   => $res['success'],
                'message'   => $res['success'] ? "Event '{$eventId}' has been deactivated." : ($res['message'] ?? 'Deactivation failed'),
                'event_id'  => $eventId,
                'is_active' => 0
            ]);
            exit;
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
    $now = time();
    $endTs = strtotime($evt['end_date'] ?? '');
    $isActive = (int)($evt['is_active'] ?? 0) === 1;
    $isConcluded = ($isActive && $endTs !== false && $endTs < $now);

    if ($statusFilter === 'ACTIVE' && (!$isActive || $isConcluded)) return false;
    if ($statusFilter === 'DISABLED' && $isActive) return false;
    if ($statusFilter === 'CONCLUDED' && !$isConcluded) return false;

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
            <select name="status" class="form-control" style="font-size: 0.82rem; padding: 5px 10px; width: 130px;" onchange="this.form.submit()">
                <option value="ALL" <?= $statusFilter === 'ALL' ? 'selected' : '' ?>>All Status</option>
                <option value="ACTIVE" <?= $statusFilter === 'ACTIVE' ? 'selected' : '' ?>>Active Only</option>
                <option value="DISABLED" <?= $statusFilter === 'DISABLED' ? 'selected' : '' ?>>Disabled</option>
                <option value="CONCLUDED" <?= $statusFilter === 'CONCLUDED' ? 'selected' : '' ?>>Concluded</option>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm" style="padding: 5px 10px;">Filter</button>
            <?php if ($searchQ !== '' || $statusFilter !== 'ALL'): ?>
                <a href="index.php" class="btn btn-secondary btn-sm" style="padding: 5px 10px; color: #dc2626;">Reset</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="section-body" style="padding: 0;">
        <div class="table-responsive" style="overflow-x: auto;">
            <table class="admin-table events-table">
                <thead>
                    <tr>
                        <th style="width: 17%;">Event ID</th>
                        <th style="width: 35%;">Event Name &amp; Department</th>
                        <th class="col-dates" style="width: 20%;">Dates Window</th>
                        <th class="col-revenue" style="width: 16%;">Revenue Collected</th>
                        <th class="col-status" style="width: 12%; text-align: right;">Status</th>
                    </tr>
                </thead>
                <tbody id="eventsTableBody">
                    <?php if (empty($filteredEvents)): ?>
                        <tr id="emptyEventsRow"><td colspan="5" style="text-align: center; padding: 28px; color: var(--text-gray);">No events matching criteria found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($filteredEvents as $event): ?>
                            <?php
                            $eId = $event['event_id'];
                            $revData = $eventRevenueMap[$eId] ?? ['tx_count' => 0, 'total_rev' => 0.0];
                            $now = time();
                            $endTs = strtotime($event['end_date'] ?? '');
                            $isActive = (int)($event['is_active'] ?? 0) === 1;

                            if ($isActive && $endTs !== false && $endTs < $now) {
                                $statusKey = 'CONCLUDED';
                                $statusLabel = 'Concluded';
                                $statusBadgeClass = 'badge-warning';
                                $isConcluded = true;
                            } elseif ($isActive) {
                                $statusKey = 'ACTIVE';
                                $statusLabel = 'Active';
                                $statusBadgeClass = 'badge-success';
                                $isConcluded = false;
                            } else {
                                $statusKey = 'DISABLED';
                                $statusLabel = 'Disabled';
                                $statusBadgeClass = 'badge-read-only';
                                $isConcluded = false;
                            }
                            ?>
                            <tr class="tx-overview-row event-table-row"
                                id="event-row-<?= htmlspecialchars($eId) ?>"
                                tabindex="0"
                                role="button"
                                aria-label="View details for <?= htmlspecialchars($event['event_name']) ?>"
                                data-event-id="<?= htmlspecialchars($eId) ?>"
                                data-event-name="<?= htmlspecialchars($event['event_name']) ?>"
                                data-department="<?= htmlspecialchars($event['department'] ?? 'General') ?>"
                                data-is-active="<?= $isActive ? '1' : '0' ?>"
                                data-is-concluded="<?= $isConcluded ? '1' : '0' ?>"
                                data-status-key="<?= $statusKey ?>"
                                data-status-label="<?= $statusLabel ?>"
                                data-tx-count="<?= (int)$revData['tx_count'] ?>"
                                data-total-rev="<?= (float)$revData['total_rev'] ?>">
                                
                                <!-- 1. Event ID -->
                                <td>
                                    <div class="copy-wrapper">
                                        <span class="font-mono" style="font-weight: 700; color: var(--primary-blue);"><?= htmlspecialchars($eId) ?></span>
                                        <button type="button" class="btn-copy btn-copy-sm" onclick="event.stopPropagation(); copyToClipboard('<?= htmlspecialchars($eId, ENT_QUOTES) ?>', this);" title="Copy Event ID">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                        </button>
                                    </div>
                                </td>

                                <!-- 2. Event Name & Department -->
                                <td>
                                    <div style="font-weight: 700; color: var(--text-dark); font-size: 0.92rem;">
                                        <?= htmlspecialchars($event['event_name']) ?>
                                    </div>
                                    <div style="font-size: 0.78rem; color: var(--text-gray); margin-top: 3px; display: flex; align-items: center; gap: 4px;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"></path><path d="M5 21V7l7-4 7 4v14"></path><path d="M9 10h1"></path><path d="M9 14h1"></path><path d="M14 10h1"></path><path d="M14 14h1"></path></svg>
                                        <span><?= htmlspecialchars($event['department'] ?? 'General') ?></span>
                                    </div>
                                </td>

                                <!-- 3. Dates Window -->
                                <td class="col-dates" style="font-size: 0.82rem; color: var(--text-gray); white-space: nowrap;">
                                    <?= date('M d, Y', strtotime($event['start_date'])) ?> &rarr; <?= date('M d, Y', strtotime($event['end_date'])) ?>
                                </td>

                                <!-- 4. Revenue Collected -->
                                <td class="col-revenue">
                                    <span style="font-weight: 700; color: var(--text-dark); font-size: 0.92rem;">₹<?= number_format((float)$revData['total_rev'], 2) ?></span>
                                    <div style="font-size: 0.75rem; color: var(--text-gray); margin-top: 2px;">
                                        <?= (int)$revData['tx_count'] ?> successful txs
                                    </div>
                                </td>

                                <!-- 5. Status Badge -->
                                <td class="col-status" style="text-align: right; white-space: nowrap;">
                                    <span class="badge <?= $statusBadgeClass ?> status-pill"><?= htmlspecialchars($statusLabel) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- Event Overview Modal (Uniform Size across tabs)              -->
<!-- ============================================================ -->
<div class="modal-overlay" id="eventOverviewModal" role="dialog" aria-modal="true" aria-labelledby="eventOverviewModalTitle" style="z-index: 9998;">
    <div class="modal-box modal-event-overview">
        
        <!-- Modal Top Header (Fixed) -->
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: var(--primary-light); display: flex; align-items: center; justify-content: center; color: var(--primary-blue); flex-shrink: 0;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                </div>
                <div>
                    <h2 id="eventOverviewModalTitle" style="font-size: 1.05rem; font-weight: 700; color: var(--text-dark); margin: 0;">Event Overview</h2>
                    <span id="eModalIdChip" class="font-mono" style="font-size: 0.76rem; color: var(--primary-blue); font-weight: 700; background: var(--bg-card); padding: 1px 6px; border-radius: 4px; border: 1px solid var(--border-color); display: inline-block; margin-top: 2px;"></span>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <span id="eModalStatusBadge" class="badge"></span>
                <button type="button" class="modal-close" onclick="closeEventOverviewModal()" style="font-size: 1.5rem; background: none; border: none; cursor: pointer; color: var(--text-gray); line-height: 1;" title="Close">&times;</button>
            </div>
        </div>

        <!-- Section / Fragment Switcher Tabs (Fixed) -->
        <div class="tx-modal-tabnav" role="tablist" aria-label="Event Sections">
            <button type="button" role="tab" class="tx-tab-btn active" id="eTabBtnDetails" aria-selected="true" aria-controls="eFragmentDetails" onclick="switchEventModalTab('details')">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                <span>Details Overview</span>
            </button>
            <button type="button" role="tab" class="tx-tab-btn" id="eTabBtnTimeline" aria-selected="false" aria-controls="eFragmentTimeline" onclick="switchEventModalTab('timeline')">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                <span>Timeline &amp; Audit Logs</span>
                <span id="eTabTimelineBadge" class="tab-badge" style="display: none;">0</span>
            </button>
        </div>

        <!-- Modal Body (Flex 1; Scrollable; Constant Height Across Tabs) -->
        <div class="modal-body e-modal-body-scrollable">
            
            <!-- Fragment 1: Two-Column Details Overview -->
            <div id="eFragmentDetails" class="tx-fragment-panel active" role="tabpanel" aria-labelledby="eTabBtnDetails">
                <table class="modal-details-table">
                    <tbody>
                        <tr>
                            <td class="dt-label">Event ID</td>
                            <td class="dt-value font-mono">
                                <span id="dtModalEventId" style="font-weight: 700; color: var(--primary-blue);"></span>
                                <button type="button" class="btn-copy btn-copy-sm" id="btnCopyModalEventId" onclick="copyModalField('dtModalEventId', this)" style="margin-left: 8px; padding: 2px 7px; font-size: 0.72rem;">Copy</button>
                            </td>
                        </tr>
                        <tr>
                            <td class="dt-label">Event Name</td>
                            <td class="dt-value" id="dtModalEventName" style="font-weight: 700;"></td>
                        </tr>
                        <tr>
                            <td class="dt-label">Department / Host School</td>
                            <td class="dt-value" id="dtModalDepartment"></td>
                        </tr>
                        <tr>
                            <td class="dt-label">Dates Window</td>
                            <td class="dt-value" id="dtModalDates"></td>
                        </tr>
                        <tr>
                            <td class="dt-label">Event Status</td>
                            <td class="dt-value" id="dtModalStatusContainer"></td>
                        </tr>
                        <tr>
                            <td class="dt-label">Revenue Collected</td>
                            <td class="dt-value" id="dtModalRevenue" style="font-size: 1.05rem; font-weight: 800; color: var(--text-dark);"></td>
                        </tr>
                        <tr>
                            <td class="dt-label">Successful Transactions</td>
                            <td class="dt-value" id="dtModalTxCount"></td>
                        </tr>
                        <tr>
                            <td class="dt-label">Webhook Delivery URL</td>
                            <td class="dt-value" id="dtModalWebhookContainer" style="word-break: break-all;"></td>
                        </tr>
                        <tr>
                            <td class="dt-label">Callback Status</td>
                            <td class="dt-value" id="dtModalCallbackStatus"></td>
                        </tr>
                        <tr id="dtModalSecretRow">
                            <td class="dt-label">Webhook Secret</td>
                            <td class="dt-value font-mono" id="dtModalSecretContainer"></td>
                        </tr>
                        <tr>
                            <td class="dt-label">Created At</td>
                            <td class="dt-value" id="dtModalCreatedAt"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Fragment 2: Timeline & Audit Logs -->
            <div id="eFragmentTimeline" class="tx-fragment-panel" role="tabpanel" aria-labelledby="eTabBtnTimeline" style="display: none;">
                <!-- Loading State (Centered Vertically) -->
                <div id="eTimelineLoading" class="e-timeline-center-state">
                    <div class="tx-spinner" style="margin-bottom: 14px;"></div>
                    <div style="font-size: 0.88rem; font-weight: 500; color: var(--text-dark);">Loading sequential lifecycle &amp; audit history...</div>
                </div>

                <!-- Empty State (Centered Vertically) -->
                <div id="eTimelineEmpty" class="e-timeline-center-state" style="display: none;">
                    <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--bg-hover); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; margin-bottom: 12px; color: var(--text-gray);">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    </div>
                    <div style="font-weight: 600; font-size: 0.95rem; color: var(--text-dark); margin-bottom: 4px;">No audit activity yet</div>
                    <div style="font-size: 0.8rem; color: var(--text-gray); max-width: 280px; line-height: 1.4;">Sequential audit entries and lifecycle events for this event campaign will appear here.</div>
                </div>

                <!-- Timeline Content (Top-Aligned) -->
                <div id="eTimelineContent" style="display: none; width: 100%;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-dark); display: flex; align-items: center; gap: 7px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary-blue);"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                            <span>Audit &amp; Lifecycle Activity Trail</span>
                        </div>
                        <span id="eTimelineEventsCountText" style="font-size: 0.76rem; color: var(--text-gray);"></span>
                    </div>

                    <!-- Chronological Timeline Nodes -->
                    <div id="eTimelineList" class="tx-timeline-track">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>

        </div>

        <!-- Modal Footer Actions (Fixed) -->
        <div class="modal-footer">
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <!-- Payments Link -->
                <a href="#" id="eModalPaymentsBtn" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 5px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                    <span>Payments</span>
                </a>

                <!-- Full Audit Page Link -->
                <a href="#" id="eModalAuditBtn" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 5px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                    <span>Full Audit Page</span>
                </a>

                <!-- Activate / Deactivate Toggle -->
                <?php if (RBAC::can('events.edit')): ?>
                <button type="button" id="eModalToggleActiveBtn" class="btn btn-secondary btn-sm" onclick="modalToggleEventStatus()" style="display: inline-flex; align-items: center; gap: 5px;">
                    <span>Deactivate</span>
                </button>
                <?php endif; ?>

                <!-- Delete Event Button (Super Admin Only) -->
                <?php if (RBAC::hasRole(RBAC::ROLE_SUPER_ADMIN)): ?>
                <button type="button" id="eModalDeleteBtn" class="btn btn-outline-danger btn-sm" onclick="modalDeleteEvent()" style="display: inline-flex; align-items: center; gap: 5px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                    <span>Delete Event</span>
                </button>
                <?php endif; ?>
            </div>

            <button type="button" class="btn btn-secondary btn-sm" onclick="closeEventOverviewModal()">
                Close
            </button>
        </div>

    </div>
</div>

<!-- Edit Webhook Modal -->
<div class="modal-overlay" id="editWebhookModal" role="dialog" aria-modal="true" style="z-index: 9999;">
    <div class="modal-box modal-md">
        <div class="modal-header">
            <h2 style="display: flex; align-items: center; gap: 8px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary-blue);"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                Configure Event Webhook
            </h2>
            <button type="button" class="modal-close" onclick="closeWebhookModal()" title="Close">&times;</button>
        </div>
        <form id="editWebhookForm" method="POST" action="index.php" onsubmit="handleWebhookFormSubmit(event)">
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
                <button type="submit" id="btnSaveWebhookSubmit" class="btn-submit-event">Save Webhook</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Event Confirmation Modal -->
<div class="modal-overlay" id="deleteEventModal" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle" style="z-index: 10000;">
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

<style>
/* ------------------------------------------------------------
   Table Styling & Row Hover (Matches dashboard.php)
   ------------------------------------------------------------ */
.events-table {
    width: 100%;
    table-layout: auto;
}
.event-table-row {
    cursor: pointer;
    transition: background-color 0.15s ease;
}
.event-table-row:hover,
.event-table-row:focus {
    background-color: var(--primary-light) !important;
    outline: none;
}

.col-status {
    text-align: right;
}

/* ------------------------------------------------------------
   Uniform Size Event Overview Modal (Fixed Dimensions Across Tabs)
   ------------------------------------------------------------ */
.modal-event-overview {
    width: min(850px, 94vw) !important;
    height: min(640px, 88vh) !important;
    max-width: 850px !important;
    max-height: 88vh !important;
    display: flex !important;
    flex-direction: column !important;
    border-radius: 12px;
    box-shadow: 0 20px 45px rgba(0,0,0,0.25);
    overflow: hidden;
    padding: 0 !important;
}

@media (max-width: 600px) {
    .modal-event-overview {
        width: 96vw !important;
        height: 92vh !important;
        max-height: 92vh !important;
    }
}

.modal-event-overview .modal-header {
    background: var(--bg-hover);
    border-bottom: 1px solid var(--border-color);
    padding: 16px 22px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}

.modal-event-overview .tx-modal-tabnav {
    display: flex;
    background: var(--bg-hover);
    border-bottom: 1px solid var(--border-color);
    padding: 0 16px;
    gap: 6px;
    flex-shrink: 0;
}

.modal-event-overview .e-modal-body-scrollable {
    flex: 1 1 0%;
    min-height: 0;
    overflow-y: auto;
    scrollbar-gutter: stable;
    padding: 0;
    background: var(--bg-card);
}

.modal-event-overview .modal-footer {
    padding: 14px 22px;
    border-top: 1px solid var(--border-color);
    background: var(--bg-hover);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    flex-shrink: 0;
}

/* Tab Panels */
.tx-fragment-panel {
    animation: panelFadeIn 0.15s ease-in-out;
    width: 100%;
    box-sizing: border-box;
}
@keyframes panelFadeIn {
    from { opacity: 0.6; }
    to { opacity: 1; }
}

#eFragmentTimeline {
    padding: 24px;
    min-height: 100%;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
}

.e-timeline-center-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 320px;
    text-align: center;
    color: var(--text-gray);
}

.tx-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-gray);
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    transition: color 0.15s ease, border-color 0.15s ease;
    text-decoration: none;
    position: relative;
    bottom: -1px;
}
.tx-tab-btn:hover {
    color: var(--text-dark);
}
.tx-tab-btn.active {
    color: var(--primary-blue) !important;
    border-bottom: 2px solid var(--primary-blue) !important;
    background: transparent;
    font-weight: 700;
}
.tab-badge {
    background: var(--primary-light);
    color: var(--primary-blue);
    font-size: 0.7rem;
    padding: 1px 7px;
    border-radius: 10px;
    font-weight: 700;
    line-height: 1.4;
}

/* Details Table */
.modal-details-table {
    width: 100%;
    border-collapse: collapse;
}
.modal-details-table tr {
    border-bottom: 1px solid var(--border-color);
}
.modal-details-table tr:last-child {
    border-bottom: none;
}
.modal-details-table .dt-label {
    width: 34%;
    padding: 11px 20px;
    font-size: 0.83rem;
    font-weight: 600;
    color: var(--text-gray);
    background: var(--bg-hover);
    border-right: 1px solid var(--border-color);
    vertical-align: middle;
}
.modal-details-table .dt-value {
    width: 66%;
    padding: 11px 20px;
    font-size: 0.86rem;
    color: var(--text-dark);
    word-break: break-word;
    vertical-align: middle;
}

/* Timeline Track */
@keyframes txSpin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
.tx-spinner {
    width: 26px;
    height: 26px;
    border: 3px solid var(--border-color);
    border-top-color: var(--primary-blue);
    border-radius: 50%;
    animation: txSpin 0.75s linear infinite;
}
.tx-timeline-track {
    position: relative;
    padding-left: 24px;
    margin-left: 8px;
    border-left: 2px solid var(--border-color);
}
.tx-timeline-node {
    position: relative;
    margin-bottom: 18px;
}
.tx-timeline-node:last-child {
    margin-bottom: 0;
}
.tx-timeline-bullet {
    position: absolute;
    left: -31px;
    top: 4px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--bg-card);
    border: 2px solid var(--primary-blue);
    box-shadow: 0 0 0 2px var(--bg-card);
}
.tx-timeline-bullet.bullet-blue {
    border-color: var(--primary-blue);
    background: var(--primary-blue);
}
.tx-timeline-bullet.bullet-green {
    border-color: #16a34a;
    background: #16a34a;
}
.tx-timeline-bullet.bullet-amber {
    border-color: #f59e0b;
    background: #f59e0b;
}
.tx-timeline-bullet.bullet-red {
    border-color: #dc2626;
    background: #dc2626;
}
.tx-timeline-card {
    background: var(--bg-hover);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 10px 14px;
    transition: border-color 0.15s ease;
}
.tx-timeline-card:hover {
    border-color: var(--primary-blue);
}

/* Responsive Column Hiding */
@media (max-width: 900px) {
    .col-dates { display: none !important; }
}
@media (max-width: 680px) {
    .col-revenue { display: none !important; }
}
</style>

<script>
/**
 * ============================================================
 * State & Global Variables
 * ============================================================
 */
const VORTEX_CSRF_TOKEN = '<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>';
let currentOverviewEventId = null;
let currentEventData = null;
let currentRevealedSecret = null;
let currentDeleteEventId = null;
let lastFocusedRow = null;
let activeModalTab = 'details';

/**
 * ============================================================
 * Row Click & Keyboard Accessibility Delegation
 * ============================================================
 */
document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.getElementById('eventsTableBody');
    if (tbody) {
        // Click delegation
        tbody.addEventListener('click', function(e) {
            // Never trigger row click if clicking interactive controls inside row
            if (e.target.closest('.btn-copy') || e.target.closest('a') || e.target.closest('button')) {
                return;
            }

            const row = e.target.closest('.event-table-row');
            if (row) {
                const eventId = row.getAttribute('data-event-id');
                if (eventId) {
                    openEventOverviewModal(eventId, row);
                }
            }
        });

        // Keyboard navigation (Enter / Space)
        tbody.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                const row = e.target.closest('.event-table-row');
                if (row && document.activeElement === row) {
                    e.preventDefault();
                    const eventId = row.getAttribute('data-event-id');
                    if (eventId) {
                        openEventOverviewModal(eventId, row);
                    }
                }
            }
        });
    }
});

/**
 * ============================================================
 * Event Overview Modal: Tab Switching & Data Loading
 * ============================================================
 */
function switchEventModalTab(tab) {
    activeModalTab = tab;
    const btnDetails = document.getElementById('eTabBtnDetails');
    const btnTimeline = document.getElementById('eTabBtnTimeline');
    const fragDetails = document.getElementById('eFragmentDetails');
    const fragTimeline = document.getElementById('eFragmentTimeline');

    if (!btnDetails || !btnTimeline || !fragDetails || !fragTimeline) return;

    // Reset scroll position to top when switching tabs
    const bodyEl = document.querySelector('#eventOverviewModal .modal-body');
    if (bodyEl) bodyEl.scrollTop = 0;

    if (tab === 'timeline') {
        btnDetails.classList.remove('active');
        btnDetails.setAttribute('aria-selected', 'false');
        btnTimeline.classList.add('active');
        btnTimeline.setAttribute('aria-selected', 'true');

        fragDetails.style.display = 'none';
        fragTimeline.style.display = 'flex';

        if (currentOverviewEventId) {
            loadEventTimeline(currentOverviewEventId);
        }
    } else {
        btnTimeline.classList.remove('active');
        btnTimeline.setAttribute('aria-selected', 'false');
        btnDetails.classList.add('active');
        btnDetails.setAttribute('aria-selected', 'true');

        fragTimeline.style.display = 'none';
        fragDetails.style.display = 'block';
    }
}

function openEventOverviewModal(eventId, sourceRow = null) {
    if (!eventId) return;
    currentOverviewEventId = eventId;
    currentRevealedSecret = null;
    if (sourceRow) {
        lastFocusedRow = sourceRow;
    }

    const modal = document.getElementById('eventOverviewModal');
    if (!modal) return;

    // Open modal container
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';

    // Reset view & scroll
    switchEventModalTab(activeModalTab || 'details');

    // Initial placeholder state
    document.getElementById('eModalIdChip').textContent = eventId;
    document.getElementById('dtModalEventId').textContent = eventId;
    document.getElementById('dtModalEventName').textContent = 'Loading details...';
    document.getElementById('dtModalDepartment').textContent = '—';
    document.getElementById('dtModalDates').textContent = '—';
    document.getElementById('dtModalStatusContainer').innerHTML = '<span class="badge badge-read-only">Loading...</span>';
    document.getElementById('eModalStatusBadge').className = 'badge badge-read-only';
    document.getElementById('eModalStatusBadge').textContent = 'Loading...';
    document.getElementById('dtModalRevenue').textContent = '₹0.00';
    document.getElementById('dtModalTxCount').textContent = '0';
    document.getElementById('dtModalWebhookContainer').innerHTML = '<span style="color:var(--text-gray)">Loading webhook...</span>';
    document.getElementById('dtModalCallbackStatus').innerHTML = '<span class="badge badge-read-only">—</span>';
    document.getElementById('dtModalSecretContainer').innerHTML = '—';
    document.getElementById('dtModalCreatedAt').textContent = '—';

    // Configure footer links
    const paymentsBtn = document.getElementById('eModalPaymentsBtn');
    if (paymentsBtn) paymentsBtn.href = '../payments/list_payments.php?event_id=' + encodeURIComponent(eventId);

    const auditBtn = document.getElementById('eModalAuditBtn');
    if (auditBtn) auditBtn.href = '../audit_logs.php?entity_type=event&entity_id=' + encodeURIComponent(eventId);

    // Trap focus inside modal
    trapModalFocus(modal);

    // Fetch details
    fetchEventDetails(eventId);
}

function fetchEventDetails(eventId) {
    fetch('event_details.php?id=' + encodeURIComponent(eventId))
        .then(async res => {
            const data = await res.json();
            return { ok: res.ok, data };
        })
        .then(({ ok, data }) => {
            if (!ok || !data.success) {
                showToast(data.message || 'Failed to load event details.', 'error');
                return;
            }
            renderEventDetails(data);
        })
        .catch(err => {
            console.error('Fetch error:', err);
            showToast('Unable to load event details: Network error.', 'error');
        });
}

function renderEventDetails(resData) {
    const event = resData.event;
    const revenue = resData.revenue || {};
    const auditLogs = resData.audit_logs || [];
    currentEventData = event;

    // Header ID & status
    document.getElementById('eModalIdChip').textContent = event.event_id;
    document.getElementById('eModalStatusBadge').className = 'badge ' + event.status_badge_class;
    document.getElementById('eModalStatusBadge').textContent = event.status_label;

    // Tab badge
    const timelineBadge = document.getElementById('eTabTimelineBadge');
    if (timelineBadge) {
        timelineBadge.textContent = auditLogs.length;
        timelineBadge.style.display = auditLogs.length > 0 ? 'inline-block' : 'none';
    }

    // Two-Column Table
    document.getElementById('dtModalEventId').textContent = event.event_id;
    document.getElementById('dtModalEventName').textContent = event.event_name;
    document.getElementById('dtModalDepartment').textContent = event.department || 'General';

    // Dates
    const startStr = formatDateString(event.start_date);
    const endStr = formatDateString(event.end_date);
    document.getElementById('dtModalDates').innerHTML = `${startStr} &rarr; ${endStr}`;

    // Status
    document.getElementById('dtModalStatusContainer').innerHTML = `<span class="badge ${event.status_badge_class}">${escapeHtml(event.status_label)}</span>`;

    // Revenue & Tx
    document.getElementById('dtModalRevenue').textContent = revenue.formatted_rev || '₹0.00';
    document.getElementById('dtModalTxCount').textContent = (revenue.successful_count || 0) + ' successful payment records';

    // Webhook Delivery URL (Wraps cleanly even if long)
    const whContainer = document.getElementById('dtModalWebhookContainer');
    const callbackStatusEl = document.getElementById('dtModalCallbackStatus');
    const secretRow = document.getElementById('dtModalSecretRow');
    const secretContainer = document.getElementById('dtModalSecretContainer');

    if (event.webhook_url && event.webhook_url.trim() !== '') {
        whContainer.innerHTML = `
            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;">
                <span class="font-mono" style="font-size: 0.82rem; word-break: break-all; color: var(--text-dark); max-width: 480px; line-height: 1.4;">${escapeHtml(event.webhook_url)}</span>
                <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 0.72rem; flex-shrink: 0;" onclick="openWebhookModalFromOverview()">Edit</button>
            </div>
            <small style="display: block; margin-top: 4px; font-size: 0.72rem; color: var(--text-gray);">
                Secret used by receiver listener to verify HMAC-SHA256 signature in X-Vortex-Signature.
            </small>
        `;
        callbackStatusEl.innerHTML = `<span class="badge badge-success" style="font-size: 0.72rem; padding: 2px 7px;">Active Callback</span>`;

        if (event.has_webhook_secret) {
            secretRow.style.display = '';
            secretContainer.innerHTML = `
                <span id="dtModalSecretValue" style="color: var(--text-dark); font-size: 0.82rem;">${escapeHtml(event.masked_secret)}</span>
                <button type="button" class="btn btn-secondary btn-sm" id="btnRevealSecret" onclick="revealModalSecret()" style="margin-left: 8px; padding: 2px 7px; font-size: 0.72rem;">Reveal</button>
                <button type="button" class="btn btn-secondary btn-sm" id="btnCopySecret" onclick="copyModalSecret(this)" style="margin-left: 4px; padding: 2px 7px; font-size: 0.72rem;">Copy</button>
            `;
        } else {
            secretRow.style.display = 'none';
        }
    } else {
        whContainer.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                <span style="font-size: 0.82rem; color: var(--text-gray); font-style: italic;">No webhook configured</span>
                <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 0.72rem; display: inline-flex; align-items: center; gap: 4px; flex-shrink: 0;" onclick="openWebhookModalFromOverview()">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    <span>+ Set URL</span>
                </button>
            </div>
        `;
        callbackStatusEl.innerHTML = `<span class="badge badge-read-only" style="font-size: 0.72rem; padding: 2px 7px;">No webhook configured</span>`;
        secretRow.style.display = 'none';
    }

    // Created At
    document.getElementById('dtModalCreatedAt').textContent = event.created_at ? formatDateString(event.created_at, true) : '—';

    // Footer Toggle Active Button
    const toggleBtn = document.getElementById('eModalToggleActiveBtn');
    if (toggleBtn) {
        const isActive = parseInt(event.is_active, 10) === 1;
        if (isActive) {
            toggleBtn.className = 'btn btn-secondary btn-sm';
            toggleBtn.innerHTML = `
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>
                <span>Deactivate</span>
            `;
        } else {
            toggleBtn.className = 'btn btn-primary btn-sm';
            toggleBtn.innerHTML = `
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <span>Activate</span>
            `;
        }
    }

    // Render timeline items
    renderTimelineItems(auditLogs);
}

function loadEventTimeline(eventId) {
    const loading = document.getElementById('eTimelineLoading');
    const content = document.getElementById('eTimelineContent');
    const empty = document.getElementById('eTimelineEmpty');

    if (!currentEventData || !currentEventData.audit_logs_loaded) {
        loading.style.display = 'flex';
        content.style.display = 'none';
        empty.style.display = 'none';
    }

    fetch('event_details.php?id=' + encodeURIComponent(eventId))
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (currentEventData) currentEventData.audit_logs_loaded = true;
                renderTimelineItems(data.audit_logs || []);
            }
        })
        .catch(err => {
            console.error('Timeline error:', err);
            loading.style.display = 'none';
            empty.style.display = 'flex';
        });
}

function renderTimelineItems(logs) {
    const loading = document.getElementById('eTimelineLoading');
    const content = document.getElementById('eTimelineContent');
    const list = document.getElementById('eTimelineList');
    const empty = document.getElementById('eTimelineEmpty');
    const countText = document.getElementById('eTimelineEventsCountText');

    loading.style.display = 'none';

    if (!logs || logs.length === 0) {
        list.innerHTML = '';
        content.style.display = 'none';
        empty.style.display = 'flex';
        countText.textContent = '0 entries';
        return;
    }

    empty.style.display = 'none';
    content.style.display = 'block';
    countText.textContent = logs.length + (logs.length === 1 ? ' entry' : ' sequential entries');

    let html = '';
    logs.forEach(log => {
        let bulletClass = 'bullet-blue';
        const act = (log.action || '').toLowerCase();
        if (act.includes('created') || act.includes('activated') || act.includes('success')) {
            bulletClass = 'bullet-green';
        } else if (act.includes('deactivated') || act.includes('deleted') || act.includes('failed')) {
            bulletClass = 'bullet-red';
        } else if (act.includes('edited') || act.includes('update') || act.includes('revealed')) {
            bulletClass = 'bullet-amber';
        }

        const actor = log.actor_username ? `${log.actor_username} (${log.actor_role || 'Admin'})` : 'System';
        const dateStr = formatDateString(log.created_at, true);

        html += `
            <div class="tx-timeline-node">
                <div class="tx-timeline-bullet ${bulletClass}"></div>
                <div class="tx-timeline-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <span class="badge badge-event" style="font-size: 0.72rem; padding: 2px 6px;">${escapeHtml(log.action || 'ACTIVITY')}</span>
                        <span style="font-size: 0.74rem; color: var(--text-gray);">${escapeHtml(dateStr)}</span>
                    </div>
                    <div style="font-weight: 600; font-size: 0.84rem; color: var(--text-dark); margin-bottom: 2px;">
                        ${escapeHtml(log.description || log.action || '')}
                    </div>
                    <div style="font-size: 0.74rem; color: var(--text-gray);">
                        By <strong>${escapeHtml(actor)}</strong> ${log.ip_address ? '• IP: ' + escapeHtml(log.ip_address) : ''}
                    </div>
                </div>
            </div>
        `;
    });

    list.innerHTML = html;
}

function revealModalSecret() {
    if (!currentOverviewEventId) return;
    const valueEl = document.getElementById('dtModalSecretValue');
    const btn = document.getElementById('btnRevealSecret');

    if (currentRevealedSecret) {
        if (btn.textContent === 'Hide') {
            valueEl.textContent = currentEventData ? currentEventData.masked_secret : '••••••••••••';
            btn.textContent = 'Reveal';
        } else {
            valueEl.textContent = currentRevealedSecret;
            btn.textContent = 'Hide';
        }
        return;
    }

    btn.disabled = true;
    btn.textContent = '...';

    fetch(`event_details.php?action=reveal_secret&id=${encodeURIComponent(currentOverviewEventId)}`)
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            if (data.success && data.webhook_secret) {
                currentRevealedSecret = data.webhook_secret;
                valueEl.textContent = currentRevealedSecret;
                btn.textContent = 'Hide';
                showToast('Webhook secret revealed.', 'info', 2500);
            } else {
                btn.textContent = 'Reveal';
                showToast(data.message || 'Unable to reveal secret.', 'error');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.textContent = 'Reveal';
            showToast('Failed to reveal secret.', 'error');
        });
}

function copyModalSecret(btn) {
    if (currentRevealedSecret) {
        copyToClipboard(currentRevealedSecret, btn);
        return;
    }
    // If not yet revealed, fetch directly and copy
    if (!currentOverviewEventId) return;
    fetch(`event_details.php?action=reveal_secret&id=${encodeURIComponent(currentOverviewEventId)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.webhook_secret) {
                currentRevealedSecret = data.webhook_secret;
                copyToClipboard(currentRevealedSecret, btn);
            } else {
                showToast(data.message || 'Unable to retrieve secret for copy.', 'error');
            }
        })
        .catch(() => showToast('Failed to copy secret.', 'error'));
}

function copyModalField(fieldId, btn) {
    const el = document.getElementById(fieldId);
    if (el) {
        copyToClipboard(el.textContent.trim(), btn);
    }
}

function closeEventOverviewModal() {
    const modal = document.getElementById('eventOverviewModal');
    if (modal) {
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }
    currentOverviewEventId = null;
    currentEventData = null;
    currentRevealedSecret = null;

    // Return focus to the clicked row
    if (lastFocusedRow && typeof lastFocusedRow.focus === 'function') {
        lastFocusedRow.focus();
    }
}

/**
 * ============================================================
 * Live Status Activation / Deactivation (AJAX)
 * ============================================================
 */
function toggleEventStatus(eventId, targetActive) {
    if (!eventId) return;
    const action = targetActive === 1 ? 'activate_event' : 'deactivate_event';

    fetch('index.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            action: action,
            event_id: eventId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || `Event #${eventId} updated.`, 'success');
            applyEventStatusUpdate(eventId, targetActive);
        } else {
            showToast(data.message || 'Failed to update event status.', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Network error while toggling status.', 'error');
    });
}

function modalToggleEventStatus() {
    if (!currentOverviewEventId || !currentEventData) return;
    const isCurrentlyActive = parseInt(currentEventData.is_active, 10) === 1;
    toggleEventStatus(currentOverviewEventId, isCurrentlyActive ? 0 : 1);
}

function applyEventStatusUpdate(eventId, newActive) {
    const isActive = parseInt(newActive, 10) === 1;
    const row = document.getElementById('event-row-' + eventId);

    // Update row attributes and badge
    if (row) {
        const wasActive = row.getAttribute('data-is-active') === '1';
        row.setAttribute('data-is-active', isActive ? '1' : '0');

        const statusPill = row.querySelector('.status-pill');
        if (statusPill) {
            if (isActive) {
                statusPill.className = 'badge badge-success status-pill';
                statusPill.textContent = 'Active';
            } else {
                statusPill.className = 'badge badge-read-only status-pill';
                statusPill.textContent = 'Disabled';
            }
        }

        // Update Stat Cards
        if (wasActive !== isActive) {
            if (isActive) {
                incrementStat('statActiveEvents');
                decrementStat('statInactiveEvents');
            } else {
                decrementStat('statActiveEvents');
                incrementStat('statInactiveEvents');
            }
        }
    }

    // If modal is open for this event, update modal controls
    if (currentOverviewEventId === eventId) {
        fetchEventDetails(eventId);
    }
}

/**
 * ============================================================
 * Webhook Modal Flow (Supports AJAX & Live Updates)
 * ============================================================
 */
function openWebhookModal(eventId, currentUrl, currentSecret) {
    document.getElementById('modalWebhookEventId').value = eventId;
    document.getElementById('modalWebhookEventDisplay').value = eventId;
    document.getElementById('modalWebhookUrlInput').value = currentUrl || '';
    document.getElementById('modalWebhookSecretInput').value = currentSecret || '';
    document.getElementById('modalWebhookSecretInput').type = 'password';
    var modal = document.getElementById('editWebhookModal');
    if (modal) { modal.classList.add('open'); document.body.style.overflow = 'hidden'; }
}

function openWebhookModalFromOverview() {
    if (!currentEventData) return;
    openWebhookModal(currentEventData.event_id, currentEventData.webhook_url, '');
}

function handleWebhookFormSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const submitBtn = document.getElementById('btnSaveWebhookSubmit');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Saving...';

    const eventId = document.getElementById('modalWebhookEventId').value;
    const webhookUrl = document.getElementById('modalWebhookUrlInput').value;
    const webhookSecret = document.getElementById('modalWebhookSecretInput').value;

    fetch('index.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            action: 'update_webhook',
            event_id: eventId,
            webhook_url: webhookUrl,
            webhook_secret: webhookSecret
        })
    })
    .then(res => res.json())
    .then(data => {
        closeWebhookModal();
        if (data.success) {
            showToast(data.message || 'Webhook updated successfully.', 'success');
            // If the overview modal is open for this event, refresh it
            if (currentOverviewEventId === eventId) {
                fetchEventDetails(eventId);
            }
        } else {
            showToast(data.message || 'Failed to update webhook.', 'error');
        }
    })
    .catch(err => {
        closeWebhookModal();
        console.error(err);
        showToast('Error saving webhook configuration.', 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    });
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

/**
 * ============================================================
 * Delete Modal Flow (Financial Guard & Safety Rule)
 * ============================================================
 */
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

function modalDeleteEvent() {
    if (!currentOverviewEventId || !currentEventData) return;
    openDeleteModal(currentOverviewEventId, currentEventData.event_name);
}

function closeDeleteModal() {
    const modal = document.getElementById('deleteEventModal');
    if (modal) {
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }
    currentDeleteEventId = null;
}

function executeDeleteEvent() {
    if (!currentDeleteEventId) return;

    const eventId = currentDeleteEventId;
    const confirmBtn = document.getElementById('confirmDeleteEventBtn');
    const cancelBtn = document.getElementById('cancelDeleteEventBtn');
    const originalText = confirmBtn.innerHTML;

    confirmBtn.disabled = true;
    if (cancelBtn) cancelBtn.disabled = true;
    confirmBtn.innerHTML = '<span class="vortex-btn-spinner" style="display:inline-block; width:14px; height:14px; border:2px solid #fff; border-top-color:transparent; border-radius:50%; animation:btn-spinner-anim 0.8s linear infinite; vertical-align:middle; margin-right:6px;"></span> Deleting...';

    const row = document.getElementById('event-row-' + eventId);

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
            
            // If the overview modal was open for this event, close it
            if (currentOverviewEventId === eventId) {
                closeEventOverviewModal();
            }

            // Fade out the row
            if (row) {
                const isActive = row.getAttribute('data-is-active') === '1';
                row.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
                row.style.opacity = '0';
                row.style.transform = 'translateX(20px)';
                
                setTimeout(() => {
                    row.remove();
                    decrementStat('statTotalEvents');
                    if (isActive) {
                        decrementStat('statActiveEvents');
                    } else {
                        decrementStat('statInactiveEvents');
                    }
                    decrementStat('tableEventsCount');

                    // Check if table is empty
                    const tbody = document.getElementById('eventsTableBody');
                    if (tbody) {
                        const remaining = tbody.querySelectorAll('tr[id^="event-row-"]');
                        if (remaining.length === 0) {
                            tbody.innerHTML = '<tr id="emptyEventsRow"><td colspan="5" style="text-align: center; padding: 28px; color: var(--text-gray);">No events configured.</td></tr>';
                        }
                    }
                }, 350);
            }
        } else {
            showToast(res.data.message || 'Failed to delete event.', 'error', 5000);
        }
    })
    .catch(err => {
        closeDeleteModal();
        console.error(err);
        showToast('Network error or server error occurred.', 'error');
    })
    .finally(() => {
        confirmBtn.disabled = false;
        if (cancelBtn) cancelBtn.disabled = false;
        confirmBtn.innerHTML = originalText;
    });
}

/**
 * ============================================================
 * Utility Functions (Stats, Focus Trap, Toast, Date, Escape)
 * ============================================================
 */
function decrementStat(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const current = parseInt(el.textContent.replace(/,/g, ''), 10);
    if (!isNaN(current) && current > 0) {
        el.textContent = (current - 1).toLocaleString();
    }
}

function incrementStat(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const current = parseInt(el.textContent.replace(/,/g, ''), 10);
    if (!isNaN(current)) {
        el.textContent = (current + 1).toLocaleString();
    }
}

function formatDateString(str, includeTime = false) {
    if (!str) return '—';
    const d = new Date(str.replace(' ', 'T'));
    if (isNaN(d.getTime())) return str;
    const options = { month: 'short', day: 'numeric', year: 'numeric' };
    if (includeTime) {
        options.hour = '2-digit';
        options.minute = '2-digit';
        options.second = '2-digit';
    }
    return d.toLocaleDateString('en-US', options);
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

function trapModalFocus(modalEl) {
    if (!modalEl) return;
    const focusableSelectors = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';
    const focusable = modalEl.querySelectorAll(focusableSelectors);
    if (focusable.length > 0) {
        focusable[0].focus();
    }
}

// Global modal keydown & backdrop handlers
(function() {
    const eModal = document.getElementById('eventOverviewModal');
    const wModal = document.getElementById('editWebhookModal');
    const dModal = document.getElementById('deleteEventModal');

    // Backdrop click
    if (eModal) {
        eModal.addEventListener('click', function(e) {
            if (e.target === eModal) closeEventOverviewModal();
        });
    }
    if (wModal) {
        wModal.addEventListener('click', function(e) {
            if (e.target === wModal) closeWebhookModal();
        });
    }
    if (dModal) {
        dModal.addEventListener('click', function(e) {
            if (e.target === dModal) closeDeleteModal();
        });
    }

    // Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (dModal && dModal.classList.contains('open')) {
                closeDeleteModal();
                return;
            }
            if (wModal && wModal.classList.contains('open')) {
                closeWebhookModal();
                return;
            }
            if (eModal && eModal.classList.contains('open')) {
                closeEventOverviewModal();
                return;
            }
        }

        // Focus trap cycle inside overview modal
        if (eModal && eModal.classList.contains('open') && e.key === 'Tab') {
            const focusables = Array.from(eModal.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex="0"]'));
            if (focusables.length === 0) return;
            const first = focusables[0];
            const last = focusables[focusables.length - 1];

            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
