<?php
/**
 * ------------------------------------------------------------
 * index.php – API Clients (Merchants) Management
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway - Admin Panel
 * Purpose : Modular dedicated page for merchant onboarding,
 *           live/test API credentials, webhooks, and audit logs.
 * ------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/ApiClient.php';
require_once __DIR__ . '/../../classes/RBAC.php';
require_once __DIR__ . '/../../classes/AuditLogger.php';
require_once __DIR__ . '/../../classes/Logger.php';

// Auth Guard
if (empty($_SESSION['vortex_admin_id'])) {
    header('Location: ../login.php');
    exit;
}

// RBAC Guard
RBAC::require('clients.view', null, '../dashboard.php');

$apiClientModel = new ApiClient();
$db = Database::getInstance()->getConnection();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Register Client
    if ($action === 'create_client') {
        RBAC::require('clients.create', null, 'index.php');
        $clientName = trim($_POST['client_name'] ?? '');
        $contactEmail = trim($_POST['email'] ?? '');

        $result = $apiClientModel->createClient($clientName);

        if ($result['success']) {
            $createdId = (int)($result['data']['client_id'] ?? 0);

            // If contact email provided, update it
            if ($createdId > 0 && !empty($contactEmail) && filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
                $db->prepare("UPDATE api_clients SET email = ? WHERE id = ?")->execute([$contactEmail, $createdId]);
                $result['data']['email'] = $contactEmail;
            }

            AuditLogger::log(
                'api_client',
                (string)$createdId,
                AuditLogger::CLIENT_CREATED,
                null,
                ['client_name' => $clientName, 'email' => $contactEmail],
                "Registered new API Client '{$clientName}' (#{$createdId})"
            );

            $_SESSION['flash_success'] = "API Client '{$clientName}' registered successfully!";
            $_SESSION['created_client'] = $result['data'];
        } else {
            $_SESSION['flash_error'] = "Failed to create API Client: " . $result['message'];
        }
        header('Location: index.php');
        exit;
    }

    // 2. Activate Client
    if ($action === 'activate_client') {
        RBAC::require('clients.edit', null, 'index.php');
        $clientId = (int)($_POST['client_id'] ?? 0);
        if ($clientId > 0) {
            $res = $apiClientModel->activateClient($clientId);
            if ($res['success']) {
                AuditLogger::log('api_client', (string)$clientId, AuditLogger::CLIENT_ACTIVATED, ['is_active' => 0], ['is_active' => 1], "Activated client #{$clientId}");
                $_SESSION['flash_success'] = "API Client #{$clientId} activated.";
            } else {
                $_SESSION['flash_error'] = "Failed: " . $res['message'];
            }
        }
        header('Location: index.php');
        exit;
    }

    // 3. Deactivate Client
    if ($action === 'deactivate_client') {
        RBAC::require('clients.edit', null, 'index.php');
        $clientId = (int)($_POST['client_id'] ?? 0);
        if ($clientId > 0) {
            $res = $apiClientModel->deactivateClient($clientId);
            if ($res['success']) {
                AuditLogger::log('api_client', (string)$clientId, AuditLogger::CLIENT_DEACTIVATED, ['is_active' => 1], ['is_active' => 0], "Deactivated client #{$clientId}");
                $_SESSION['flash_success'] = "API Client #{$clientId} deactivated.";
            } else {
                $_SESSION['flash_error'] = "Failed: " . $res['message'];
            }
        }
        header('Location: index.php');
        exit;
    }

    // 4. Update Webhook
    if ($action === 'update_webhook') {
        RBAC::require('clients.edit', null, 'index.php');
        $clientId      = (int)($_POST['client_id'] ?? 0);
        $webhookUrl    = trim($_POST['webhook_url'] ?? '');
        $webhookSecret = trim($_POST['webhook_secret'] ?? '');

        if ($clientId > 0) {
            $res = $apiClientModel->updateWebhook($clientId, $webhookUrl, $webhookSecret !== '' ? $webhookSecret : null);
            if ($res['success']) {
                if ($webhookUrl === '') {
                    AuditLogger::log('api_client', (string)$clientId, AuditLogger::CLIENT_WEBHOOK_UPDATED, null, ['webhook_url' => null], "Cleared webhook endpoint for Client #{$clientId}");
                    $_SESSION['flash_success'] = "Webhook cleared for Client #{$clientId}.";
                } else {
                    AuditLogger::log('api_client', (string)$clientId, AuditLogger::CLIENT_WEBHOOK_UPDATED, null, ['webhook_url' => $webhookUrl], "Configured webhook endpoint for Client #{$clientId}");
                    $_SESSION['flash_success'] = "Webhook configuration saved for Client #{$clientId}.";
                    $_SESSION['new_webhook_secret'] = [
                        'client_id' => $clientId,
                        'secret'    => $res['data']['webhook_secret']
                    ];
                }
            } else {
                $_SESSION['flash_error'] = "Webhook update failed: " . $res['message'];
            }
        }
        header('Location: index.php');
        exit;
    }
}

// Flash Messages
$flashSuccess         = $_SESSION['flash_success'] ?? null;
$flashError           = $_SESSION['flash_error'] ?? null;
$createdClientDetails = $_SESSION['created_client'] ?? null;
$newWebhookSecret     = $_SESSION['new_webhook_secret'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['created_client'], $_SESSION['new_webhook_secret']);

// Fetch All Clients
$allClients = $apiClientModel->getAllClients(200) ?: [];

// Metrics
$totalClientsCount     = count($allClients);
$activeClientsCount    = count(array_filter($allClients, fn($c) => (int)($c['is_active'] ?? 0) === 1));
$disabledClientsCount  = $totalClientsCount - $activeClientsCount;
$webhookConfiguredCount= count(array_filter($allClients, fn($c) => !empty($c['webhook_url'])));

// Search & Filter
$searchQ = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'ALL');

$filteredClients = array_filter($allClients, function($c) use ($searchQ, $statusFilter) {
    if ($statusFilter === 'ACTIVE' && (int)$c['is_active'] !== 1) return false;
    if ($statusFilter === 'DISABLED' && (int)$c['is_active'] === 1) return false;

    if ($searchQ !== '') {
        $haystack = strtolower(($c['client_name'] ?? '') . ' ' . ($c['api_key'] ?? '') . ' ' . ($c['email'] ?? ''));
        if (!str_contains($haystack, strtolower($searchQ))) return false;
    }
    return true;
});

$pageTitle = "API Clients Management - Vortex Admin";
$activeNav = "clients";
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

<!-- Credentials Alert Component -->
<?php require_once __DIR__ . '/../includes/client_credentials_alert.php'; ?>

<!-- Header & Title -->
<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
    <div>
        <div class="section-title" style="margin-bottom: 4px;">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary-blue); vertical-align: -4px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            API Clients &amp; Merchant Accounts
        </div>
        <div style="font-size: 0.85rem; color: var(--text-gray);">
            Manage merchant integrations, live and test cryptographic API credentials, webhooks, and access controls.
        </div>
    </div>
    <?php if (RBAC::can('clients.create')): ?>
    <div>
        <a href="onboarding.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; font-weight: 600; box-shadow: 0 2px 6px rgba(37,99,235,0.25);">
            <span>🚀</span> Launch Onboarding Wizard
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Clients Metrics Grid -->
<div class="metrics-grid" style="margin-bottom: 24px;">
    <div class="metric-card">
        <span class="metric-label">Registered Merchants</span>
        <span class="metric-value"><?= number_format($totalClientsCount) ?></span>
    </div>
    <div class="metric-card">
        <span class="metric-label">Active Gateway Clients</span>
        <span class="metric-value success"><?= number_format($activeClientsCount) ?></span>
    </div>
    <div class="metric-card">
        <span class="metric-label">Suspended Clients</span>
        <span class="metric-value danger"><?= number_format($disabledClientsCount) ?></span>
    </div>
    <div class="metric-card">
        <span class="metric-label">Webhook Endpoints</span>
        <span class="metric-value"><?= number_format($webhookConfiguredCount) ?></span>
    </div>
</div>

<!-- Register New Client Form (Super Admin only) -->
<?php if (RBAC::can('clients.create')): ?>
<div class="section-card" style="margin-bottom: 24px;">
    <div class="section-header" style="background: #fafbfc; border-bottom: 1px solid var(--border-color); padding: 14px 20px;">
        <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--text-dark); margin: 0;">
            🔌 Register New API Client
        </h3>
    </div>
    <div class="section-body" style="padding: 20px;">
        <form action="index.php" method="POST">
            <input type="hidden" name="action" value="create_client">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 16px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Client / Merchant Name *</label>
                    <input type="text" name="client_name" class="form-control" placeholder="e.g. Rajagiri Conference App" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Contact / Notifications Email (Optional)</label>
                    <input type="email" name="email" class="form-control" placeholder="merchant@rajagiri.edu">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">+ Register Client &amp; Generate Keys</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Clients Table & Filter Card -->
<div class="section-card">
    <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 14px 20px; border-bottom: 1px solid var(--border-color); background: #fafbfc;">
        <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-dark);">
            Connected Merchants (<?= count($filteredClients) ?>)
        </div>
        <!-- Search & Status Filter Form -->
        <form method="GET" action="index.php" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <input type="text" name="q" class="form-control" placeholder="Search merchant name..." value="<?= htmlspecialchars($searchQ) ?>" style="font-size: 0.82rem; padding: 5px 10px; width: 200px;">
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
                        <th>ID</th>
                        <th>Merchant / Client</th>
                        <th>API Keys (Live &amp; Test)</th>
                        <th>API Secrets (Live &amp; Test)</th>
                        <th>Webhook Endpoint</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($filteredClients)): ?>
                        <tr><td colspan="7" style="text-align: center; padding: 28px; color: var(--text-gray);">No clients matching criteria found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($filteredClients as $client): ?>
                            <tr>
                                <td>#<?= (int)$client['id'] ?></td>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-dark); font-size: 0.92rem;">
                                        <?= htmlspecialchars($client['client_name']) ?>
                                    </div>
                                    <?php if (!empty($client['email'])): ?>
                                        <div style="font-size: 0.76rem; color: var(--text-gray); margin-top: 3px;">
                                            ✉ <?= htmlspecialchars($client['email']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="copy-wrapper" title="<?= htmlspecialchars($client['api_key']) ?>">
                                        <span style="font-size: 0.7rem; color: #64748b; font-weight: 700; margin-right: 2px;">LIVE</span>
                                        <span class="font-mono" style="max-width: 95px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; vertical-align: middle;">
                                            <?= htmlspecialchars($client['api_key']) ?>
                                        </span>
                                        <button type="button" class="btn-copy btn-copy-sm" onclick="copyToClipboard('<?= htmlspecialchars($client['api_key'], ENT_QUOTES) ?>', this)" title="Copy Live Key">📋</button>
                                    </div>
                                    <div class="copy-wrapper" style="margin-top: 4px;" title="<?= htmlspecialchars($client['test_api_key'] ?? '') ?>">
                                        <span style="font-size: 0.7rem; color: #64748b; font-weight: 700; margin-right: 2px;">TEST</span>
                                        <span class="font-mono" style="max-width: 95px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; vertical-align: middle;">
                                            <?= htmlspecialchars($client['test_api_key'] ?? 'N/A') ?>
                                        </span>
                                        <button type="button" class="btn-copy btn-copy-sm" onclick="copyToClipboard('<?= htmlspecialchars($client['test_api_key'] ?? '', ENT_QUOTES) ?>', this)" title="Copy Test Key">📋</button>
                                    </div>
                                </td>
                                <td>
                                    <?php if (RBAC::can('clients.view_secret')): ?>
                                        <div class="copy-wrapper" title="<?= htmlspecialchars($client['api_secret'] ?? '') ?>">
                                            <span style="font-size: 0.7rem; color: #64748b; font-weight: 700; margin-right: 2px;">LIVE</span>
                                            <span class="font-mono" style="max-width: 95px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; vertical-align: middle; color: var(--danger-text);">
                                                <?= htmlspecialchars($client['api_secret'] ?? 'N/A') ?>
                                            </span>
                                            <button type="button" class="btn-copy btn-copy-sm" onclick="copyToClipboard('<?= htmlspecialchars($client['api_secret'] ?? '', ENT_QUOTES) ?>', this)" title="Copy Live Secret">📋</button>
                                        </div>
                                        <div class="copy-wrapper" style="margin-top: 4px;" title="<?= htmlspecialchars($client['test_api_secret'] ?? '') ?>">
                                            <span style="font-size: 0.7rem; color: #64748b; font-weight: 700; margin-right: 2px;">TEST</span>
                                            <span class="font-mono" style="max-width: 95px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; vertical-align: middle; color: var(--danger-text);">
                                                <?= htmlspecialchars($client['test_api_secret'] ?? 'N/A') ?>
                                            </span>
                                            <button type="button" class="btn-copy btn-copy-sm" onclick="copyToClipboard('<?= htmlspecialchars($client['test_api_secret'] ?? '', ENT_QUOTES) ?>', this)" title="Copy Test Secret">📋</button>
                                        </div>
                                    <?php else: ?>
                                        <span class="font-mono" style="color: var(--text-muted); font-size: 0.85rem; letter-spacing: 2px;">••••••••••••</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($client['webhook_url'])): ?>
                                        <div class="copy-wrapper" title="<?= htmlspecialchars($client['webhook_url']) ?>">
                                            <span style="font-size: 0.7rem; color: #15803d; font-weight: 700; margin-right: 2px;">URL</span>
                                            <span class="font-mono" style="font-size: 0.78rem; max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; vertical-align: middle;">
                                                <?= htmlspecialchars($client['webhook_url']) ?>
                                            </span>
                                            <button type="button" class="btn-copy btn-copy-sm" onclick="copyToClipboard('<?= htmlspecialchars($client['webhook_url'], ENT_QUOTES) ?>', this)" title="Copy Webhook URL">📋</button>
                                        </div>
                                        <?php if (!empty($client['webhook_secret'])): ?>
                                            <div class="copy-wrapper" style="margin-top: 4px;" title="<?= htmlspecialchars($client['webhook_secret']) ?>">
                                                <span style="font-size: 0.7rem; color: #64748b; font-weight: 700; margin-right: 2px;">SECRET</span>
                                                <span class="font-mono" style="font-size: 0.78rem; max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; vertical-align: middle; color: var(--danger-text);">
                                                    <?php if (RBAC::can('clients.view_secret')): ?>
                                                        <?= htmlspecialchars($client['webhook_secret']) ?>
                                                    <?php else: ?>
                                                        ••••••••••••
                                                    <?php endif; ?>
                                                </span>
                                                <?php if (RBAC::can('clients.view_secret')): ?>
                                                <button type="button" class="btn-copy btn-copy-sm" onclick="copyToClipboard('<?= htmlspecialchars($client['webhook_secret'], ENT_QUOTES) ?>', this)" title="Copy Webhook Secret">📋</button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.82rem; font-style: italic;">Not configured</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= (int)$client['is_active'] === 1 ? 'badge-success' : 'badge-danger' ?>">
                                        <?= (int)$client['is_active'] === 1 ? 'Active' : 'Disabled' ?>
                                    </span>
                                </td>
                                <td style="white-space: nowrap;">
                                    <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                        <?php if (RBAC::can('clients.edit')): ?>
                                        <!-- Toggle Active / Disabled -->
                                        <form action="index.php" method="POST" style="display: inline;">
                                            <input type="hidden" name="client_id" value="<?= (int)$client['id'] ?>">
                                            <?php if ((int)$client['is_active'] === 1): ?>
                                                <input type="hidden" name="action" value="deactivate_client">
                                                <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Deactivate Client #<?= (int)$client['id'] ?>?')">Deactivate</button>
                                            <?php else: ?>
                                                <input type="hidden" name="action" value="activate_client">
                                                <button type="submit" class="btn btn-primary btn-sm">Activate</button>
                                            <?php endif; ?>
                                        </form>

                                        <!-- Configure Webhook Modal Trigger -->
                                        <button type="button"
                                                class="btn btn-secondary btn-sm"
                                                onclick="openWebhookModal(<?= (int)$client['id'] ?>, '<?= htmlspecialchars(addslashes($client['webhook_url'] ?? ''), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($client['webhook_secret'] ?? ''), ENT_QUOTES) ?>')">
                                            🔗 Webhook
                                        </button>
                                        <?php endif; ?>

                                        <!-- View Client Payments -->
                                        <a href="../payments/list_payments.php?api_client_id=<?= (int)$client['id'] ?>" class="btn btn-secondary btn-sm" title="View payments for this client">
                                            💳 Payments
                                        </a>

                                        <!-- View Client Audit Trail -->
                                        <a href="../audit_logs.php?entity_type=api_client&entity_id=<?= (int)$client['id'] ?>" class="btn btn-secondary btn-sm" title="Inspect Audit Trail">
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

<!-- Webhook Modal Component -->
<?php
$webhookFormAction = 'index.php';
require_once __DIR__ . '/../includes/webhook_modal.php';
?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
