<?php
/**
 * ------------------------------------------------------------
 * Vortex Admin Dashboard
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 * Purpose : Traditional, clean admin dashboard for gateway management.
 * ------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Event.php';
require_once __DIR__ . '/../classes/ApiClient.php';

$eventModel = new Event();
$apiClientModel = new ApiClient();
$db = Database::getInstance()->getConnection();

$eventAlert = null;
$clientAlert = null;
$createdClientDetails = null;

// Handle Form Actions Submitted directly to dashboard.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Create Event
    if ($action === 'create_event') {
        $eventName  = trim($_POST['event_name'] ?? '');
        $department = trim($_POST['department'] ?? '');
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
            $_SESSION['flash_success'] = "Event '{$eventName}' created successfully with ID: " . $result['data']['event_id'];
        } else {
            $_SESSION['flash_error'] = "Failed to create event: " . $result['message'];
        }
        header('Location: dashboard.php#events');
        exit;
    }

    // 2. Create API Client
    if ($action === 'create_client') {
        $clientName = trim($_POST['client_name'] ?? '');
        $result = $apiClientModel->createClient($clientName);

        if ($result['success']) {
            $_SESSION['flash_success'] = "API Client '{$clientName}' created successfully!";
            $_SESSION['created_client'] = $result['data'];
        } else {
            $_SESSION['flash_error'] = "Failed to create API Client: " . $result['message'];
        }
        header('Location: dashboard.php#clients');
        exit;
    }

    // 3. Activate / Deactivate Event
    if ($action === 'activate_event') {
        $eventId = trim($_POST['event_id'] ?? '');
        if (!empty($eventId)) {
            $res = $eventModel->activateEvent($eventId);
            if ($res['success']) {
                $_SESSION['flash_success'] = "Event '{$eventId}' activated.";
            } else {
                $_SESSION['flash_error'] = "Failed: " . $res['message'];
            }
        }
        header('Location: dashboard.php#events');
        exit;
    }

    if ($action === 'deactivate_event') {
        $eventId = trim($_POST['event_id'] ?? '');
        if (!empty($eventId)) {
            $res = $eventModel->deactivateEvent($eventId);
            if ($res['success']) {
                $_SESSION['flash_success'] = "Event '{$eventId}' deactivated.";
            } else {
                $_SESSION['flash_error'] = "Failed: " . $res['message'];
            }
        }
        header('Location: dashboard.php#events');
        exit;
    }

    // 4. Activate / Deactivate API Client
    if ($action === 'activate_client') {
        $clientId = (int)($_POST['client_id'] ?? 0);
        if ($clientId > 0) {
            $res = $apiClientModel->activateClient($clientId);
            if ($res['success']) {
                $_SESSION['flash_success'] = "API Client #{$clientId} activated.";
            } else {
                $_SESSION['flash_error'] = "Failed: " . $res['message'];
            }
        }
        header('Location: dashboard.php#clients');
        exit;
    }

    if ($action === 'deactivate_client') {
        $clientId = (int)($_POST['client_id'] ?? 0);
        if ($clientId > 0) {
            $res = $apiClientModel->deactivateClient($clientId);
            if ($res['success']) {
                $_SESSION['flash_success'] = "API Client #{$clientId} deactivated.";
            } else {
                $_SESSION['flash_error'] = "Failed: " . $res['message'];
            }
        }
        header('Location: dashboard.php#clients');
        exit;
    }

    // 5. Update Webhook URL for API Client
    if ($action === 'update_webhook') {
        $clientId   = (int)($_POST['client_id'] ?? 0);
        $webhookUrl = trim($_POST['webhook_url'] ?? '');

        if ($clientId > 0) {
            $res = $apiClientModel->updateWebhook($clientId, $webhookUrl);
            if ($res['success']) {
                if ($webhookUrl === '') {
                    $_SESSION['flash_success'] = "Webhook cleared for Client #{$clientId}.";
                } else {
                    $_SESSION['flash_success'] = "Webhook URL updated for Client #{$clientId}.";
                    // Store new secret so it can be shown once
                    $_SESSION['new_webhook_secret'] = [
                        'client_id' => $clientId,
                        'secret'    => $res['data']['webhook_secret']
                    ];
                }
            } else {
                $_SESSION['flash_error'] = "Webhook update failed: " . $res['message'];
            }
        }
        header('Location: dashboard.php#clients');
        exit;
    }
}

// Read and clear Flash Messages
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
$createdClientDetails = $_SESSION['created_client'] ?? null;
$newWebhookSecret     = $_SESSION['new_webhook_secret'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['created_client'], $_SESSION['new_webhook_secret']);

// Database Metrics
$totalEvents        = $eventModel->getTotalCount();
$totalClients       = $apiClientModel->getTotalCount();
$totalPayments      = 0;
$successfulPayments = 0;
$failedPayments     = 0;
$totalPaymentAmount = 0.00;

try {
    $totalPayments      = (int) $db->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
    $successfulPayments = (int) $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'SUCCESS'")->fetchColumn();
    $failedPayments     = (int) $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'FAILED'")->fetchColumn();
    $totalPaymentAmount = (float) $db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'SUCCESS'")->fetchColumn();
} catch (\Throwable $e) {
    // Database fallback
}

// Correctly fetched database lists
$allEvents = $eventModel->getAllEvents(100) ?: [];
$allClients = $apiClientModel->getAllClients(100) ?: [];

// Recent Transactions
$recentTx = [];
try {
    $recentTxStmt = $db->query("SELECT t.*, e.event_name, c.client_name 
                                FROM transactions t 
                                LEFT JOIN events e ON t.event_id = e.event_id 
                                LEFT JOIN api_clients c ON t.api_client_id = c.id
                                ORDER BY t.id DESC LIMIT 15");
    $recentTx = $recentTxStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    $recentTx = [];
}

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

<?php if ($createdClientDetails): ?>
    <div class="alert alert-warning" style="border: 2px dashed #b45309;">
        <h3 style="margin-bottom: 8px; color: #78350f;">🔑 New API Client Generated!</h3>
        <p style="margin-bottom: 8px;">Please save these API credentials securely. The secret is shown only once:</p>
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Client Name</span>
                <span class="detail-value"><?= htmlspecialchars($createdClientDetails['client_name']) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">API Key</span>
                <div class="copy-wrapper" style="margin-top: 4px;">
                    <span class="detail-value font-mono"><?= htmlspecialchars($createdClientDetails['api_key']) ?></span>
                    <button type="button" class="btn-copy" onclick="copyToClipboard('<?= htmlspecialchars($createdClientDetails['api_key'], ENT_QUOTES) ?>', this)" title="Copy API Key">📋 Copy</button>
                </div>
            </div>
            <div class="detail-item" style="grid-column: span 2;">
                <span class="detail-label">API Secret</span>
                <div class="copy-wrapper" style="margin-top: 4px;">
                    <span class="detail-value font-mono" style="color: var(--danger-text); font-weight: 700;"><?= htmlspecialchars($createdClientDetails['api_secret']) ?></span>
                    <button type="button" class="btn-copy" onclick="copyToClipboard('<?= htmlspecialchars($createdClientDetails['api_secret'], ENT_QUOTES) ?>', this)" title="Copy API Secret">📋 Copy</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($newWebhookSecret): ?>
    <div class="alert alert-warning" style="border: 2px dashed #b45309;">
        <h3 style="margin-bottom: 8px; color: #78350f;">🔐 New Webhook Secret Generated for Client #<?= (int)$newWebhookSecret['client_id'] ?>!</h3>
        <p style="margin-bottom: 10px;">
            Share this secret with the client so they can verify incoming webhook signatures.
            <strong>It will not be shown again.</strong>
        </p>
        <div class="detail-grid">
            <div class="detail-item" style="grid-column: span 2;">
                <span class="detail-label">Webhook Secret</span>
                <div class="copy-wrapper" style="margin-top: 4px;">
                    <span class="detail-value font-mono" style="color: var(--danger-text); font-weight: 700; font-size: 0.9rem; word-break: break-all;">
                        <?= htmlspecialchars($newWebhookSecret['secret']) ?>
                    </span>
                    <button type="button" class="btn-copy" onclick="copyToClipboard('<?= htmlspecialchars($newWebhookSecret['secret'], ENT_QUOTES) ?>', this)" title="Copy Webhook Secret">📋 Copy</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>


<?php
$initialTab = trim($_GET['tab'] ?? '');
if (!in_array($initialTab, ['overview', 'clients', 'events'])) {
    $initialTab = 'overview';
}
?>

<!-- ============================================================
     TAB 1: OVERVIEW (Metrics & Transactions Details ONLY)
     ============================================================ -->
<div id="tab-overview" class="tab-content-panel" style="<?= $initialTab === 'overview' ? '' : 'display:none;' ?>">
    
    <div class="section-title">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary-blue);"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
        Dashboard Overview
    </div>

    <div class="section-label">Quick Access Metrics</div>

    <!-- Key Performance Metrics Grid -->
    <div class="metrics-grid">
        <div class="metric-card">
            <span class="metric-label">Total Revenue (Successful)</span>
            <span class="metric-value amount">₹ <?= number_format($totalPaymentAmount, 2) ?></span>
        </div>
        <div class="metric-card">
            <span class="metric-label">Successful Transactions</span>
            <span class="metric-value success"><?= number_format($successfulPayments) ?></span>
        </div>
        <div class="metric-card">
            <span class="metric-label">Failed Transactions</span>
            <span class="metric-value danger"><?= number_format($failedPayments) ?></span>
        </div>
        <div class="metric-card">
            <span class="metric-label">Active Events</span>
            <span class="metric-value"><?= count(array_filter($allEvents, fn($e) => $e['is_active'] == 1)) ?></span>
        </div>
        <div class="metric-card">
            <span class="metric-label">Registered Merchants / Clients</span>
            <span class="metric-value"><?= count($allClients) ?></span>
        </div>
    </div>

    <div class="section-label">Recent Transactions</div>

    <!-- Recent Transactions Table -->
    <div id="transactions" class="section-card">
        <div class="section-header" style="display:none;">
            <h2>💳 Recent Transactions Overview</h2>
            <a href="payments/payment_details.php" class="btn btn-secondary btn-sm">Search / View All Details</a>
        </div>
        <div class="section-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Vortex Transaction ID</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Razorpay Payment ID</th>
                            <th>Event</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentTx)): ?>
                            <tr><td colspan="8" style="text-align:center; padding: 24px;">No transactions recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentTx as $tx): ?>
                                <tr>
                                    <td>
                                        <a href="payments/payment_details.php?vortex_transaction_id=<?= urlencode($tx['vortex_transaction_id']) ?>" class="font-mono" style="color: var(--primary); font-weight: 700; text-decoration: underline;">
                                            <?= htmlspecialchars($tx['vortex_transaction_id']) ?>
                                        </a>
                                    </td>
                                    <td style="font-weight: 700; color: var(--text-main);">₹<?= number_format($tx['amount'], 2) ?></td>
                                    <td>
                                        <?php
                                        $bClass = 'badge-pending';
                                        if ($tx['status'] === 'SUCCESS') $bClass = 'badge-success';
                                        if ($tx['status'] === 'FAILED') $bClass = 'badge-danger';
                                        if ($tx['status'] === 'REFUNDED') $bClass = 'badge-refunded';
                                        ?>
                                        <span class="badge <?= $bClass ?>"><?= htmlspecialchars($tx['status']) ?></span>
                                    </td>
                                    <td><span class="font-mono"><?= htmlspecialchars($tx['razorpay_payment_id'] ?? 'N/A') ?></span></td>
                                    <td><?= htmlspecialchars($tx['event_name'] ?? $tx['event_id']) ?></td>
                                    <td><?= htmlspecialchars($tx['client_name'] ?? 'Default Client') ?></td>
                                    <td style="font-size: 0.85rem; color: var(--text-muted);"><?= htmlspecialchars($tx['created_at']) ?></td>
                                    <td>
                                        <a href="payments/payment_details.php?vortex_transaction_id=<?= urlencode($tx['vortex_transaction_id']) ?>" class="btn btn-secondary btn-sm">
                                            🔍 Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     TAB 2: API CLIENTS (MERCHANTS)
     ============================================================ -->
<div id="tab-clients" class="tab-content-panel" style="<?= $initialTab === 'clients' ? '' : 'display:none;' ?>">
    <div class="section-title">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary-blue);"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        API Clients
    </div>
    
    <div class="section-label">All Connected Clients</div>
    
    <div id="clients" class="section-card">
        <div class="section-header" style="display:none;">
            <h2>🔌 API Clients (Merchants)</h2>
        </div>
        <div class="section-body">

            <!-- Form to Create New Client -->
            <form action="dashboard.php" method="POST" style="margin-bottom: 24px; padding: 16px; background: #f8fafc; border: 1px solid var(--border-light); border-radius: 6px;">
                <input type="hidden" name="action" value="create_client">
                <h3 style="font-size: 0.95rem; margin-bottom: 12px; color: var(--text-main);">Register New API Client</h3>
                <div style="display: flex; gap: 12px; max-width: 600px;">
                    <input type="text" name="client_name" class="form-control" placeholder="Client Name (e.g. Rajagiri Conference App)" required style="flex: 1;">
                    <button type="submit" class="btn btn-primary">+ Register Client</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client Name</th>
                            <th>API Key</th>
                            <th>API Secret</th>
                            <th>Webhook URL</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allClients)): ?>
                            <tr><td colspan="8" style="text-align:center; padding: 16px;">No API Clients found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($allClients as $client): ?>
                                <tr>
                                    <td>#<?= (int)$client['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($client['client_name']) ?></strong></td>
                                    <td>
                                        <div class="copy-wrapper" title="<?= htmlspecialchars($client['api_key']) ?>">
                                            <span class="font-mono" style="max-width: 115px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; vertical-align: middle;">
                                                <?= htmlspecialchars($client['api_key']) ?>
                                            </span>
                                            <button type="button" class="btn-copy btn-copy-sm" onclick="copyToClipboard('<?= htmlspecialchars($client['api_key'], ENT_QUOTES) ?>', this)" title="Copy API Key">📋 Copy</button>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="copy-wrapper" title="<?= htmlspecialchars($client['api_secret'] ?? '') ?>">
                                            <span class="font-mono" style="max-width: 115px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; vertical-align: middle; color: var(--danger-text);">
                                                <?= htmlspecialchars($client['api_secret'] ?? 'N/A') ?>
                                            </span>
                                            <button type="button" class="btn-copy btn-copy-sm" onclick="copyToClipboard('<?= htmlspecialchars($client['api_secret'] ?? '', ENT_QUOTES) ?>', this)" title="Copy API Secret">📋 Copy</button>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($client['webhook_url'])): ?>
                                            <span style="display:inline-flex;align-items:center;gap:6px;">
                                                <span style="width:8px;height:8px;border-radius:50%;background:#15803d;flex-shrink:0;"></span>
                                                <span class="font-mono" style="font-size:0.78rem;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($client['webhook_url']) ?>">
                                                    <?= htmlspecialchars($client['webhook_url']) ?>
                                                </span>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);font-size:0.85rem;font-style:italic;">Not configured</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $client['is_active'] ? 'badge-success' : 'badge-failed' ?>">
                                            <?= $client['is_active'] ? 'Active' : 'Disabled' ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 0.85rem; color: var(--text-muted);"><?= htmlspecialchars($client['created_at']) ?></td>
                                    <td style="white-space:nowrap;">
                                        <!-- Toggle Active/Inactive -->
                                        <form action="dashboard.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="client_id" value="<?= (int)$client['id'] ?>">
                                            <?php if ($client['is_active']): ?>
                                                <input type="hidden" name="action" value="deactivate_client">
                                                <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Deactivate this client?')">Deactivate</button>
                                            <?php else: ?>
                                                <input type="hidden" name="action" value="activate_client">
                                                <button type="submit" class="btn btn-primary btn-sm">Activate</button>
                                            <?php endif; ?>
                                        </form>

                                        <!-- Configure Webhook button -->
                                        <button type="button"
                                                class="btn btn-secondary btn-sm"
                                                style="margin-left:6px;"
                                                onclick="openWebhookModal(<?= (int)$client['id'] ?>, '<?= htmlspecialchars(addslashes($client['webhook_url'] ?? ''), ENT_QUOTES) ?>')">
                                            🔗 Webhook
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Webhook Configure Modal (shared, populated via JS) -->
<div class="modal-overlay" id="webhookModal" role="dialog" aria-modal="true" aria-labelledby="webhookModalTitle">
    <div class="modal-box" style="max-width:500px;">
        <div class="modal-header">
            <h2 id="webhookModalTitle">🔗 Configure Webhook</h2>
            <button class="modal-close" onclick="closeWebhookModal()">&times;</button>
        </div>
        <form action="dashboard.php" method="POST" id="webhookForm">
            <input type="hidden" name="action" value="update_webhook">
            <input type="hidden" name="client_id" id="webhookClientId">
            <div class="modal-body">
                <p style="font-size:0.88rem;color:var(--text-muted);margin-bottom:18px;">
                    Set the endpoint Vortex will POST payment notifications to.
                    Saving generates a new <strong>webhook secret</strong> which will be shown once.
                </p>
                <div class="form-group">
                    <label for="webhookUrlInput">Webhook URL</label>
                    <input type="url" id="webhookUrlInput" name="webhook_url" class="form-control"
                           placeholder="https://yourapp.com/webhook/vortex">
                </div>
                <p style="font-size:0.82rem;color:var(--text-muted);margin-top:8px;">
                    Leave blank and save to <strong>remove</strong> the webhook configuration.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeWebhookModal()">Cancel</button>
                <button type="submit" class="btn-submit-event">💾 Save Webhook</button>
            </div>
        </form>
    </div>
</div>

<script>
function openWebhookModal(clientId, currentUrl) {
    document.getElementById('webhookClientId').value  = clientId;
    document.getElementById('webhookUrlInput').value  = currentUrl || '';
    document.getElementById('webhookModal').classList.add('open');
    document.body.style.overflow = 'hidden';
    document.getElementById('webhookUrlInput').focus();
}
function closeWebhookModal() {
    document.getElementById('webhookModal').classList.remove('open');
    document.body.style.overflow = '';
}
document.getElementById('webhookModal').addEventListener('click', function(e) {
    if (e.target === this) closeWebhookModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('webhookModal').classList.contains('open')) closeWebhookModal();
});
</script>

<!-- ============================================================
     TAB 3: EVENTS & DEPARTMENTS MANAGEMENT
     ============================================================ -->
<div id="tab-events" class="tab-content-panel" style="<?= $initialTab === 'events' ? '' : 'display:none;' ?>">
    <div class="section-title">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary-blue);"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
        Events Management
    </div>
    
    <div class="section-label">All Configured Events</div>
    
    <div id="events" class="section-card">
        <div class="section-header" style="display:none;">
            <h2>🎟️ Event Management</h2>
        </div>
        <div class="section-body">

            <!-- Form to Create New Event -->
            <form action="dashboard.php" method="POST" style="margin-bottom: 24px; padding: 16px; background: #f8fafc; border: 1px solid var(--border-light); border-radius: 6px;">
                <input type="hidden" name="action" value="create_event">
                <h3 style="font-size: 0.95rem; margin-bottom: 12px; color: var(--text-main);">Create New Event</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Event Name</label>
                        <input type="text" name="event_name" class="form-control" placeholder="e.g. International AI Summit 2026" required>
                    </div>
                    <div class="form-group">
                        <label>Department / Host</label>
                        <input type="text" name="event_department" class="form-control" placeholder="e.g. Computer Science Dept" required>
                    </div>
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" class="form-control" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top: 8px;">+ Create Event</button>
            </form>

            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Event ID</th>
                            <th>Event Name</th>
                            <th>Department</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allEvents)): ?>
                            <tr><td colspan="7" style="text-align:center; padding: 16px;">No events created yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($allEvents as $event): ?>
                                <tr>
                                    <td>
                                        <div class="copy-wrapper">
                                            <span class="font-mono"><?= htmlspecialchars($event['event_id']) ?></span>
                                            <button type="button" class="btn-copy btn-copy-sm" onclick="copyToClipboard('<?= htmlspecialchars($event['event_id'], ENT_QUOTES) ?>', this)" title="Copy Event ID">📋 Copy</button>
                                        </div>
                                    </td>
                                    <td><strong><?= htmlspecialchars($event['event_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($event['department'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($event['start_date']) ?></td>
                                    <td><?= htmlspecialchars($event['end_date']) ?></td>
                                    <td>
                                        <span class="badge <?= $event['is_active'] ? 'badge-success' : 'badge-failed' ?>">
                                            <?= $event['is_active'] ? 'Active' : 'Disabled' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form action="dashboard.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="event_id" value="<?= htmlspecialchars($event['event_id']) ?>">
                                            <?php if ($event['is_active']): ?>
                                                <input type="hidden" name="action" value="deactivate_event">
                                                <button type="submit" class="btn btn-secondary btn-sm">Deactivate</button>
                                            <?php else: ?>
                                                <input type="hidden" name="action" value="activate_event">
                                                <button type="submit" class="btn btn-primary btn-sm">Activate</button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     Floating Action Button – Create Event
     ============================================================ -->
<button class="fab-create-event" id="fabCreateEvent" title="Create New Event">
    <span class="fab-icon">🎟️</span>
    Create Event
</button>

<!-- Create Event Modal -->
<div class="modal-overlay" id="createEventModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-box">
        <div class="modal-header">
            <h2 id="modalTitle">🎟️ Create New Event</h2>
            <button class="modal-close" id="closeEventModal" title="Close">&times;</button>
        </div>

        <form action="dashboard.php" method="POST">
            <input type="hidden" name="action" value="create_event">

            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group" style="grid-column: span 2;">
                        <label for="modal_event_name">Event Name</label>
                        <input type="text" id="modal_event_name" name="event_name" class="form-control"
                               placeholder="e.g. International AI Summit 2026" required>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label for="modal_department">Department / Host</label>
                        <input type="text" id="modal_department" name="department" class="form-control"
                               placeholder="e.g. Computer Science Dept" required>
                    </div>
                    <div class="form-group">
                        <label for="modal_start_date">Start Date</label>
                        <input type="date" id="modal_start_date" name="start_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="modal_end_date">End Date</label>
                        <input type="date" id="modal_end_date" name="end_date" class="form-control" required>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" id="cancelEventModal">Cancel</button>
                <button type="submit" class="btn-submit-event">
                    <span>✚</span> Create Event
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const fab     = document.getElementById('fabCreateEvent');
    const sidebarBtn = document.getElementById('sidebarCreateEventBtn');
    const modal   = document.getElementById('createEventModal');
    const closeBtn = document.getElementById('closeEventModal');
    const cancelBtn = document.getElementById('cancelEventModal');

    function openModal()  { modal.classList.add('open');    document.body.style.overflow = 'hidden'; }
    function closeModal() { modal.classList.remove('open'); document.body.style.overflow = ''; }

    if(fab) fab.addEventListener('click', openModal);
    if(sidebarBtn) sidebarBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);

    // Close on overlay click
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
    });

    // Auto-open modal if there's a validation error returned (i.e. create_event was the last action)
    <?php if (isset($flashError) && $flashError && str_contains($flashError ?? '', 'event')): ?>
    openModal();
    <?php endif; ?>
}());
</script>

<!-- ============================================================
     Sidebar Tab Navigation Switcher Script
     ============================================================ -->
<script>
(function () {
    function getActiveTabName() {
        const hash = window.location.hash.replace('#', '');
        if (['overview', 'clients', 'events'].includes(hash)) {
            return hash;
        }
        const params = new URLSearchParams(window.location.search);
        const tabParam = params.get('tab');
        if (['overview', 'clients', 'events'].includes(tabParam)) {
            return tabParam;
        }
        return 'overview';
    }

    function switchDashboardTab(tabName) {
        if (!['overview', 'clients', 'events'].includes(tabName)) {
            tabName = 'overview';
        }

        // Hide all tab panels
        const panels = document.querySelectorAll('.tab-content-panel');
        panels.forEach(panel => {
            panel.style.display = 'none';
        });

        // Display targeted tab panel
        const activePanel = document.getElementById('tab-' + tabName);
        if (activePanel) {
            activePanel.style.display = 'block';
        }

        // Highlight matching sidebar navigation link
        const sidebarLinks = document.querySelectorAll('.app-sidebar .sidebar-menu a');
        sidebarLinks.forEach(link => {
            const href = link.getAttribute('href') || '';
            link.classList.remove('active');
            if (tabName === 'overview' && (href.includes('#overview') || (href.endsWith('dashboard.php') && !href.includes('#')))) {
                link.classList.add('active');
            } else if (tabName === 'clients' && href.includes('#clients')) {
                link.classList.add('active');
            } else if (tabName === 'events' && href.includes('#events')) {
                link.classList.add('active');
            }
        });

        // Toggle Floating Action Button visibility (shown on Events & Overview tabs)
        const fab = document.getElementById('fabCreateEvent');
        if (fab) {
            fab.style.display = (tabName === 'events' || tabName === 'overview') ? 'flex' : 'none';
        }
    }

    function syncTabState() {
        switchDashboardTab(getActiveTabName());
    }

    window.addEventListener('hashchange', syncTabState);
    document.addEventListener('DOMContentLoaded', syncTabState);
    syncTabState();
}());
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
