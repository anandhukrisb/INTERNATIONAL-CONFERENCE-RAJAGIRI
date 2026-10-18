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

    // 4. Rename Client
    if ($action === 'rename_client') {
        RBAC::require('clients.edit', null, 'index.php');
        $clientId   = (int)($_POST['client_id'] ?? 0);
        $clientName = trim($_POST['client_name'] ?? '');

        $res = $apiClientModel->renameClient($clientId, $clientName);

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        if ($isAjax) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success'     => $res['success'],
                'message'     => $res['message'],
                'client_id'   => $clientId,
                'client_name' => $clientName
            ]);
            exit;
        }

        if ($res['success']) {
            $_SESSION['flash_success'] = "API Client renamed successfully to '{$clientName}'.";
        } else {
            $_SESSION['flash_error'] = "Failed to rename client: " . $res['message'];
        }
        header('Location: index.php');
        exit;
    }
}

// Flash Messages
$flashSuccess         = $_SESSION['flash_success'] ?? null;
$flashError           = $_SESSION['flash_error'] ?? null;
$createdClientDetails = $_SESSION['created_client'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['created_client']);

// Fetch All Clients
$allClients = $apiClientModel->getAllClients(200) ?: [];

// Metrics
$totalClientsCount     = count($allClients);
$activeClientsCount    = count(array_filter($allClients, fn($c) => (int)($c['is_active'] ?? 0) === 1));
$disabledClientsCount  = $totalClientsCount - $activeClientsCount;

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

$pageTitle = "API Clients Management - Rpay Admin";
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
<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 24px;">
    <div>
        <div class="section-title" style="margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary-blue);"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            API Clients &amp; Merchant Accounts
        </div>
        <div style="font-size: 0.85rem; color: var(--text-gray);">
            Manage merchant integrations, live and test cryptographic API credentials, webhooks, and access controls.
        </div>
    </div>
    <?php if (RBAC::can('clients.create')): ?>
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <button type="button" class="btn btn-primary" id="openCreateClientModalBtn" onclick="if(typeof openCreateClientModal === 'function') openCreateClientModal(event);" style="display: inline-flex; align-items: center; gap: 6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            <span>New API Client</span>
        </button>
        <a href="onboarding.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem;" title="Interactive Onboarding Wizard">
            <span>🚀 Onboarding Wizard</span>
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
        <span class="metric-label">Event Webhooks</span>
        <span class="metric-value" style="font-size: 1rem; padding-top: 6px;">
            <a href="../events/index.php" style="color: var(--primary-blue); text-decoration: none; font-weight: 600;">Managed per Event &rarr;</a>
        </span>
    </div>
</div>

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
                        <th>Merchant / Client</th>
                        <th>API Keys (Live &amp; Test)</th>
                        <th>API Secrets (Live &amp; Test)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($filteredClients)): ?>
                        <tr><td colspan="4" style="text-align: center; padding: 28px; color: var(--text-gray);">No clients matching criteria found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($filteredClients as $client): ?>
                            <?php
                            $clientData = $client;
                            if (!RBAC::can('clients.view_secret')) {
                                $clientData['api_secret'] = '••••••••••••';
                                $clientData['test_api_secret'] = '••••••••••••';
                            }
                            $clientJson = htmlspecialchars(json_encode($clientData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr class="client-overview-row"
                                id="client-row-<?= (int)$client['id'] ?>"
                                tabindex="0"
                                role="button"
                                aria-label="View details for merchant <?= htmlspecialchars($client['client_name']) ?>"
                                title="Click / tap to view merchant details & audit logs"
                                data-client-id="<?= (int)$client['id'] ?>"
                                data-client='<?= $clientJson ?>'
                                onclick="openClientOverviewModal(this)"
                                onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openClientOverviewModal(this);}">
                                <td>
                                    <div class="client-name-cell-text" style="font-weight: 700; color: var(--text-dark); font-size: 0.92rem;">
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
                                        <span class="font-mono" style="max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; vertical-align: middle;">
                                            <?= htmlspecialchars($client['api_key']) ?>
                                        </span>
                                        <button type="button" class="btn-copy btn-copy-sm" onclick="event.stopPropagation(); copyToClipboard('<?= htmlspecialchars($client['api_key'], ENT_QUOTES) ?>', this);" title="Copy Live Key">📋</button>
                                    </div>
                                    <div class="copy-wrapper" style="margin-top: 4px;" title="<?= htmlspecialchars($client['test_api_key'] ?? '') ?>">
                                        <span style="font-size: 0.7rem; color: #64748b; font-weight: 700; margin-right: 2px;">TEST</span>
                                        <span class="font-mono" style="max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; vertical-align: middle;">
                                            <?= htmlspecialchars($client['test_api_key'] ?? 'N/A') ?>
                                        </span>
                                        <button type="button" class="btn-copy btn-copy-sm" onclick="event.stopPropagation(); copyToClipboard('<?= htmlspecialchars($client['test_api_key'] ?? '', ENT_QUOTES) ?>', this);" title="Copy Test Key">📋</button>
                                    </div>
                                </td>
                                <td>
                                    <?php if (RBAC::can('clients.view_secret')): ?>
                                        <div class="copy-wrapper" title="<?= htmlspecialchars($client['api_secret'] ?? '') ?>">
                                            <span style="font-size: 0.7rem; color: #64748b; font-weight: 700; margin-right: 2px;">LIVE</span>
                                            <span class="font-mono" style="max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; vertical-align: middle; color: var(--danger-text);">
                                                <?= htmlspecialchars($client['api_secret'] ?? 'N/A') ?>
                                            </span>
                                            <button type="button" class="btn-copy btn-copy-sm" onclick="event.stopPropagation(); copyToClipboard('<?= htmlspecialchars($client['api_secret'], ENT_QUOTES) ?>', this);" title="Copy Live Secret">📋</button>
                                        </div>
                                        <div class="copy-wrapper" style="margin-top: 4px;" title="<?= htmlspecialchars($client['test_api_secret'] ?? '') ?>">
                                            <span style="font-size: 0.7rem; color: #64748b; font-weight: 700; margin-right: 2px;">TEST</span>
                                            <span class="font-mono" style="max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; vertical-align: middle; color: var(--danger-text);">
                                                <?= htmlspecialchars($client['test_api_secret'] ?? 'N/A') ?>
                                            </span>
                                            <button type="button" class="btn-copy btn-copy-sm" onclick="event.stopPropagation(); copyToClipboard('<?= htmlspecialchars($client['test_api_secret'] ?? '', ENT_QUOTES) ?>', this);" title="Copy Test Secret">📋</button>
                                        </div>
                                    <?php else: ?>
                                        <span class="font-mono" style="color: var(--text-muted); font-size: 0.85rem; letter-spacing: 2px;">••••••••••••</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= (int)$client['is_active'] === 1 ? 'badge-success' : 'badge-danger' ?>">
                                        <?= (int)$client['is_active'] === 1 ? 'Active' : 'Disabled' ?>
                                    </span>
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
<!-- Merchant Details & Timeline Modal (Two Fragments) -->
<!-- ============================================================ -->
<div class="modal-overlay" id="clientOverviewModal" role="dialog" aria-modal="true" aria-labelledby="clientOverviewModalTitle" style="z-index: 9998;">
    <div class="modal-box modal-lg" id="clientOverviewModalBox" style="max-width: 880px; width: 95%; height: 620px; max-height: 88vh; min-height: 440px; border-radius: 12px; box-shadow: 0 20px 45px rgba(0,0,0,0.25); overflow: hidden; display: flex; flex-direction: column;">
        
        <!-- Modal Top Header -->
        <div class="modal-header" style="background: var(--bg-hover); border-bottom: 1px solid var(--border-color); padding: 16px 22px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: var(--primary-light); display: flex; align-items: center; justify-content: center; color: var(--primary-blue);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                </div>
                <div>
                    <h2 id="clientOverviewModalTitle" style="font-size: 1.05rem; font-weight: 700; color: var(--text-dark); margin: 0;">Merchant Overview</h2>
                    <span id="clientModalSubtitle" class="font-mono" style="font-size: 0.76rem; color: var(--text-gray);"></span>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <span id="clientModalBadge" class="badge"></span>
                <button type="button" class="modal-close" onclick="closeClientOverviewModal()" style="font-size: 1.5rem; background: none; border: none; cursor: pointer; color: var(--text-gray); line-height: 1;">&times;</button>
            </div>
        </div>

        <!-- Section / Fragment Switcher Tabs -->
        <div class="client-modal-tabnav" role="tablist" aria-label="Merchant Sections">
            <button type="button" role="tab" class="client-tab-btn active" id="clientTabBtnDetails" aria-selected="true" aria-controls="clientFragmentDetails" onclick="switchClientModalTab('details')">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                <span>Details Overview</span>
            </button>
            <button type="button" role="tab" class="client-tab-btn" id="clientTabBtnPayments" aria-selected="false" aria-controls="clientFragmentPayments" onclick="switchClientModalTab('payments')">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                <span>Payments</span>
                <span id="clientTabPaymentsBadge" class="tab-badge" style="display: none;">0</span>
            </button>
            <button type="button" role="tab" class="client-tab-btn" id="clientTabBtnTimeline" aria-selected="false" aria-controls="clientFragmentTimeline" onclick="switchClientModalTab('timeline')">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                <span>Timeline &amp; Audit Logs</span>
                <span id="clientTabTimelineBadge" class="tab-badge" style="display: none;">0</span>
            </button>
        </div>

        <!-- Modal Body: Fragment 1, Fragment 2 & Fragment 3 -->
        <div class="modal-body" style="padding: 0; overflow-y: auto; flex: 1 1 0; min-height: 0;">
            
            <!-- Fragment 1: Two-Column Details -->
            <div id="clientFragmentDetails" class="client-fragment-panel active" role="tabpanel" aria-labelledby="clientTabBtnDetails">
                <table class="modal-details-table">
                    <tbody>
                        <tr>
                            <td class="dt-label">Merchant Client ID</td>
                            <td class="dt-value font-mono">
                                <span id="dtModalClientId" style="font-weight: 700; color: var(--primary-blue);"></span>
                                <button type="button" class="btn-copy btn-copy-sm" id="btnCopyClientId" onclick="copyClientModalField('dtModalClientId', this)" style="margin-left: 8px; padding: 2px 7px; font-size: 0.72rem;">Copy</button>
                            </td>
                        </tr>
                        <tr>
                            <td class="dt-label">Merchant / Client Name</td>
                            <td class="dt-value" id="dtModalClientNameWrapper">
                                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                                    <span id="dtModalClientName" style="font-weight: 700; font-size: 0.95rem; color: var(--text-dark); word-break: break-word;"></span>
                                    <?php if (RBAC::can('clients.edit')): ?>
                                    <button type="button" class="btn btn-secondary btn-sm" id="btnEditClientName" onclick="toggleEditClientName(true)" style="padding: 2px 8px; font-size: 0.74rem; display: inline-flex; align-items: center; gap: 4px;" title="Rename Client">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                        <span>Rename</span>
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <?php if (RBAC::can('clients.edit')): ?>
                                <div id="clientRenameFormContainer" style="display: none; margin-top: 8px; padding: 10px; background: var(--bg-hover); border: 1px solid var(--border-color); border-radius: 6px;">
                                    <div style="font-size: 0.75rem; font-weight: 600; color: var(--text-gray); margin-bottom: 6px;">Rename API Client / Merchant</div>
                                    <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                        <input type="text" id="inputEditClientName" class="form-control" style="font-size: 0.85rem; padding: 5px 10px; flex: 1; min-width: 180px;" maxlength="150" placeholder="Enter new merchant name" onkeydown="if(event.key==='Enter'){event.preventDefault();saveClientName();}">
                                        <button type="button" class="btn btn-primary btn-sm" id="btnSaveClientName" onclick="saveClientName()" style="padding: 5px 12px; font-size: 0.78rem;">Save</button>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleEditClientName(false)" style="padding: 5px 10px; font-size: 0.78rem;">Cancel</button>
                                    </div>
                                    <div id="clientRenameFeedback" style="font-size: 0.75rem; margin-top: 6px; display: none;"></div>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="dt-label">Contact Email</td>
                            <td class="dt-value" id="dtModalClientEmail" style="font-weight: 600;"></td>
                        </tr>
                        <tr>
                            <td class="dt-label">Contact Phone</td>
                            <td class="dt-value" id="dtModalClientPhone"></td>
                        </tr>
                        <tr>
                            <td class="dt-label">Gateway Status</td>
                            <td class="dt-value" id="dtModalClientStatus"></td>
                        </tr>
                        <tr>
                            <td class="dt-label">LIVE API Key</td>
                            <td class="dt-value font-mono">
                                <span id="dtModalClientApiKey" style="word-break: break-all;"></span>
                                <button type="button" class="btn-copy btn-copy-sm" id="btnCopyClientApiKey" onclick="copyClientModalField('dtModalClientApiKey', this)" style="margin-left: 8px; padding: 2px 7px; font-size: 0.72rem;">Copy</button>
                            </td>
                        </tr>
                        <tr>
                            <td class="dt-label">LIVE API Secret</td>
                            <td class="dt-value font-mono">
                                <span id="dtModalClientApiSecret" style="word-break: break-all; color: var(--danger-text);"></span>
                                <button type="button" class="btn-copy btn-copy-sm" id="btnCopyClientApiSecret" onclick="copyClientModalField('dtModalClientApiSecret', this)" style="margin-left: 8px; padding: 2px 7px; font-size: 0.72rem; display: none;">Copy</button>
                            </td>
                        </tr>
                        <tr>
                            <td class="dt-label">TEST API Key</td>
                            <td class="dt-value font-mono">
                                <span id="dtModalClientTestKey" style="word-break: break-all;"></span>
                                <button type="button" class="btn-copy btn-copy-sm" id="btnCopyClientTestKey" onclick="copyClientModalField('dtModalClientTestKey', this)" style="margin-left: 8px; padding: 2px 7px; font-size: 0.72rem;">Copy</button>
                            </td>
                        </tr>
                        <tr>
                            <td class="dt-label">TEST API Secret</td>
                            <td class="dt-value font-mono">
                                <span id="dtModalClientTestSecret" style="word-break: break-all; color: var(--danger-text);"></span>
                                <button type="button" class="btn-copy btn-copy-sm" id="btnCopyClientTestSecret" onclick="copyClientModalField('dtModalClientTestSecret', this)" style="margin-left: 8px; padding: 2px 7px; font-size: 0.72rem; display: none;">Copy</button>
                            </td>
                        </tr>
                        <tr>
                            <td class="dt-label">Processed Volume</td>
                            <td class="dt-value" id="dtModalClientVolume">
                                <span style="color: var(--text-gray); font-style: italic;">Loading metrics...</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="dt-label">Notification Channels</td>
                            <td class="dt-value" id="dtModalClientNotifications"></td>
                        </tr>
                        <tr>
                            <td class="dt-label">Onboarding Date</td>
                            <td class="dt-value" id="dtModalClientCreatedAt"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Fragment 2: Payments Tabular View -->
            <div id="clientFragmentPayments" class="client-fragment-panel" role="tabpanel" aria-labelledby="clientTabBtnPayments" style="display: none; padding: 20px;">
                <!-- Loading State -->
                <div id="clientPaymentsLoading" style="text-align: center; padding: 40px 20px; color: var(--text-gray);">
                    <div class="client-spinner" style="margin: 0 auto 12px auto;"></div>
                    <div style="font-size: 0.85rem; font-weight: 500;">Loading payment transactions...</div>
                </div>

                <!-- Empty State -->
                <div id="clientPaymentsEmpty" style="display: none; text-align: center; padding: 40px 20px;">
                    <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--bg-hover); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; margin: 0 auto 12px auto; color: var(--text-gray);">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                    </div>
                    <div style="font-weight: 600; font-size: 0.95rem; color: var(--text-dark); margin-bottom: 4px;">No payments recorded</div>
                    <div style="font-size: 0.8rem; color: var(--text-gray); max-width: 300px; margin: 0 auto; line-height: 1.4;">
                        No transactions have been processed by this merchant yet.
                    </div>
                </div>

                <!-- Tabular Payments Content -->
                <div id="clientPaymentsContent" style="display: none;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 14px;">
                        <div style="font-weight: 700; font-size: 0.88rem; color: var(--text-dark); display: flex; align-items: center; gap: 7px;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary-blue);"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                            <span id="clientPaymentsHeaderCount">Payments (0)</span>
                        </div>
                        <div id="clientPaymentsHeaderStats" style="font-size: 0.78rem; color: var(--text-gray); display: flex; align-items: center; flex-wrap: wrap; gap: 6px;"></div>
                    </div>

                    <div class="table-responsive" style="border: 1px solid var(--border-color); border-radius: 8px; overflow-x: auto;">
                        <table class="admin-table" style="margin: 0; font-size: 0.8rem; width: 100%; min-width: 700px;">
                            <thead style="background: var(--bg-hover);">
                                <tr>
                                    <th style="padding: 10px 12px;">Transaction ID</th>
                                    <th style="padding: 10px 12px;">Event Campaign</th>
                                    <th style="padding: 10px 12px;">Customer</th>
                                    <th style="padding: 10px 12px;">Amount</th>
                                    <th style="padding: 10px 12px; text-align: center;">Status</th>
                                    <th style="padding: 10px 12px; text-align: right;">Date</th>
                                </tr>
                            </thead>
                            <tbody id="clientPaymentsTableBody">
                                <!-- Populated dynamically by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Fragment 3: Timeline & Audit Logs -->
            <div id="clientFragmentTimeline" class="client-fragment-panel" role="tabpanel" aria-labelledby="clientTabBtnTimeline" style="display: none; padding: 20px;">
                
                <!-- Loading State -->
                <div id="clientTimelineLoading" style="text-align: center; padding: 40px 20px; color: var(--text-gray);">
                    <div class="client-spinner" style="margin: 0 auto 12px auto;"></div>
                    <div style="font-size: 0.85rem; font-weight: 500;">Loading merchant activity &amp; audit history...</div>
                </div>

                <!-- Timeline Content -->
                <div id="clientTimelineContent" style="display: none;">
                    
                    <!-- Section: Recent Transaction Activity -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                        <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-dark); display: flex; align-items: center; gap: 7px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary-blue);"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                            <span>Recent Activity Trail</span>
                        </div>
                        <span id="clientActivityCountText" style="font-size: 0.76rem; color: var(--text-gray);"></span>
                    </div>

                    <!-- Chronological Activity Track (Recent on top) -->
                    <div id="clientActivityList" class="client-timeline-track">
                        <!-- Populated by JS -->
                    </div>

                    <!-- Admin Governance & Audit Logs -->
                    <div id="clientAuditRecordsContainer" style="margin-top: 24px; display: none;">
                        <div style="font-weight: 700; font-size: 0.88rem; color: var(--text-dark); margin-bottom: 12px; display: flex; align-items: center; gap: 7px;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #8b5cf6;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                            <span>Governance &amp; Administrative Logs</span>
                        </div>
                        <div id="clientAuditRecordsList" class="client-audit-cards-list">
                            <!-- Populated by JS -->
                        </div>
                    </div>

                </div>

            </div>

        </div>

        <!-- Modal Footer Actions -->
        <div class="modal-footer" style="padding: 14px 22px; border-top: 1px solid var(--border-color); background: var(--bg-hover); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; flex-shrink: 0;">
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <?php if (RBAC::can('clients.edit')): ?>
                <form id="clientModalStatusForm" action="index.php" method="POST" style="display: inline;">
                    <input type="hidden" name="client_id" id="clientModalStatusId" value="">
                    <input type="hidden" name="action" id="clientModalStatusAction" value="">
                    <button type="submit" id="clientModalStatusBtn" class="btn btn-sm" style="font-weight: 600; padding: 6px 12px;"></button>
                </form>
                <?php endif; ?>
            </div>

            <button type="button" class="btn btn-secondary btn-sm" onclick="closeClientOverviewModal()">
                Close
            </button>
        </div>

    </div>
</div>

<!-- Create API Client Modal -->
<?php if (RBAC::can('clients.create')): ?>
<div class="modal-overlay" id="createClientModal" role="dialog" aria-modal="true" aria-labelledby="createClientModalTitle">
    <div class="modal-box">
        <div class="modal-header">
            <h2 id="createClientModalTitle" style="display: flex; align-items: center; gap: 8px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary-blue);"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                <span>Register New API Client</span>
            </h2>
            <button type="button" class="modal-close" id="closeCreateClientModal" title="Close">&times;</button>
        </div>

        <form action="index.php" method="POST" id="createClientModalForm">
            <input type="hidden" name="action" value="create_client">

            <div class="modal-body">
                <div style="background: #f0f7ff; border: 1px solid #cbe4ff; border-radius: 8px; padding: 12px 14px; margin-bottom: 18px; font-size: 0.82rem; color: #1e40af; line-height: 1.45;">
                    <strong>🔑 Cryptographic Key Generation:</strong> Registering an API client automatically generates isolated Live &amp; Sandbox 256-bit API keys and cryptographic HMAC signing secrets.
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="modal_client_name" style="font-weight: 600; font-size: 0.85rem; color: var(--text-dark); margin-bottom: 6px; display: block;">
                        Client / Merchant Name <span style="color: #dc2626;">*</span>
                    </label>
                    <input type="text" id="modal_client_name" name="client_name" class="form-control"
                           placeholder="e.g. Rajagiri Conference App" required autofocus>
                    <span style="font-size: 0.78rem; color: var(--text-gray); margin-top: 4px; display: block;">
                        The official name or project title representing this merchant integration.
                    </span>
                </div>

                <div class="form-group" style="margin-bottom: 6px;">
                    <label for="modal_client_email" style="font-weight: 600; font-size: 0.85rem; color: var(--text-dark); margin-bottom: 6px; display: block;">
                        Contact / Notifications Email <span style="font-size: 0.75rem; color: var(--text-gray); font-weight: normal;">(Optional)</span>
                    </label>
                    <input type="email" id="modal_client_email" name="email" class="form-control"
                           placeholder="merchant@rajagiri.edu">
                    <span style="font-size: 0.78rem; color: var(--text-gray); margin-top: 4px; display: block;">
                        Used for security alerts, key rotation notifications, and transactional receipts.
                    </span>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" id="cancelCreateClientModal">Cancel</button>
                <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 20px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    <span>Register Client &amp; Generate Keys</span>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<style>
.client-overview-row {
    cursor: pointer;
    transition: background-color 0.15s ease;
}
.client-overview-row:hover,
.client-overview-row:focus {
    background-color: var(--primary-light) !important;
    outline: none;
}

/* Modal Tab Nav / Fragment Switcher */
.client-modal-tabnav {
    display: flex;
    background: var(--bg-hover);
    border-bottom: 1px solid var(--border-color);
    padding: 0 16px;
    gap: 6px;
    flex-shrink: 0;
}
.client-tab-btn {
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
    transition: all 0.2s ease;
    text-decoration: none;
    position: relative;
    bottom: -1px;
}
.client-tab-btn:hover {
    color: var(--text-dark);
}
.client-tab-btn.active {
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

/* Fixed Consistent Modal Height & Smooth Internal Scroll */
#clientOverviewModal .modal-box {
    height: 575px !important;
    max-height: 88vh !important;
    min-height: 420px !important;
    display: flex !important;
    flex-direction: column !important;
}
#clientOverviewModal .modal-body {
    flex: 1 1 0 !important;
    min-height: 0 !important;
    overflow-y: auto !important;
    overscroll-behavior: contain;
}
#clientOverviewModal .modal-body::-webkit-scrollbar {
    width: 6px;
}
#clientOverviewModal .modal-body::-webkit-scrollbar-track {
    background: transparent;
}
#clientOverviewModal .modal-body::-webkit-scrollbar-thumb {
    background: rgba(150, 150, 150, 0.35);
    border-radius: 4px;
}
#clientOverviewModal .modal-body::-webkit-scrollbar-thumb:hover {
    background: rgba(150, 150, 150, 0.6);
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
    width: 35%;
    padding: 10px 18px;
    font-size: 0.83rem;
    font-weight: 600;
    color: var(--text-gray);
    background: var(--bg-hover);
    border-right: 1px solid var(--border-color);
    vertical-align: middle;
}
.modal-details-table .dt-value {
    width: 65%;
    padding: 10px 18px;
    font-size: 0.86rem;
    color: var(--text-dark);
    word-break: break-word;
    vertical-align: middle;
}

/* Spinner */
@keyframes clientSpin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
.client-spinner {
    width: 26px;
    height: 26px;
    border: 3px solid var(--border-color);
    border-top-color: var(--primary-blue);
    border-radius: 50%;
    animation: clientSpin 0.75s linear infinite;
}

/* Timeline Track */
.client-timeline-track {
    position: relative;
    padding-left: 24px;
    margin-left: 8px;
    border-left: 2px solid var(--border-color);
}
.client-timeline-node {
    position: relative;
    margin-bottom: 18px;
}
.client-timeline-node:last-child {
    margin-bottom: 0;
}
.client-timeline-bullet {
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
.client-timeline-bullet.bullet-success {
    border-color: #16a34a;
    background: #16a34a;
}
.client-timeline-bullet.bullet-danger {
    border-color: #dc2626;
    background: #dc2626;
}
.client-timeline-bullet.bullet-pending {
    border-color: #f59e0b;
    background: #f59e0b;
}
.client-timeline-bullet.bullet-refunded {
    border-color: #9333ea;
    background: #9333ea;
}
.client-timeline-card {
    background: var(--bg-hover);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 10px 14px;
    transition: border-color 0.15s ease;
}
.client-timeline-card:hover {
    border-color: var(--primary-blue);
}

/* Audit Cards */
.client-audit-cards-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.client-sub-card {
    background: var(--bg-hover);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 0.8rem;
}
</style>

<script>
let currentOverviewClientId = 0;

function switchClientModalTab(tab) {
    const tabs = ['details', 'payments', 'timeline'];
    tabs.forEach(t => {
        const btn = document.getElementById('clientTabBtn' + t.charAt(0).toUpperCase() + t.slice(1));
        const frag = document.getElementById('clientFragment' + t.charAt(0).toUpperCase() + t.slice(1));
        if (btn) {
            if (t === tab) {
                btn.classList.add('active');
                btn.setAttribute('aria-selected', 'true');
            } else {
                btn.classList.remove('active');
                btn.setAttribute('aria-selected', 'false');
            }
        }
        if (frag) {
            frag.style.display = (t === tab) ? 'block' : 'none';
        }
    });

    const modalBody = document.querySelector('#clientOverviewModal .modal-body');
    if (modalBody) {
        modalBody.scrollTop = 0;
    }
}

function toggleEditClientName(show) {
    const container = document.getElementById('clientRenameFormContainer');
    const btn = document.getElementById('btnEditClientName');
    const input = document.getElementById('inputEditClientName');
    const feedback = document.getElementById('clientRenameFeedback');
    if (!container) return;

    if (show) {
        container.style.display = 'block';
        if (btn) btn.style.display = 'none';
        if (feedback) feedback.style.display = 'none';
        const currentName = (document.getElementById('dtModalClientName')?.textContent || '').trim();
        if (input) {
            input.value = currentName === '—' ? '' : currentName;
            input.focus();
            input.select();
        }
    } else {
        container.style.display = 'none';
        if (btn) btn.style.display = 'inline-flex';
        if (feedback) feedback.style.display = 'none';
    }
}

function saveClientName() {
    const input = document.getElementById('inputEditClientName');
    const feedback = document.getElementById('clientRenameFeedback');
    const saveBtn = document.getElementById('btnSaveClientName');
    if (!input || !currentOverviewClientId) return;

    const newName = input.value.trim();
    if (!newName) {
        if (feedback) {
            feedback.style.display = 'block';
            feedback.style.color = '#dc2626';
            feedback.textContent = 'Merchant / Client name cannot be empty.';
        }
        input.focus();
        return;
    }

    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';
    }

    const formData = new FormData();
    formData.append('action', 'rename_client');
    formData.append('client_id', currentOverviewClientId);
    formData.append('client_name', newName);

    fetch('index.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save';
        }
        if (!data.success) {
            if (feedback) {
                feedback.style.display = 'block';
                feedback.style.color = '#dc2626';
                feedback.textContent = data.message || 'Failed to rename client.';
            }
            return;
        }

        // Update modal texts
        const nameEl = document.getElementById('dtModalClientName');
        if (nameEl) nameEl.textContent = newName;
        const subEl = document.getElementById('clientModalSubtitle');
        if (subEl) subEl.textContent = newName + ' • ID: #' + currentOverviewClientId;

        // Update main table row if present
        const tableRow = document.getElementById('client-row-' + currentOverviewClientId);
        if (tableRow) {
            const nameCell = tableRow.querySelector('.client-name-cell-text');
            if (nameCell) nameCell.textContent = newName;
            try {
                let clientData = JSON.parse(tableRow.getAttribute('data-client') || '{}');
                clientData.client_name = newName;
                tableRow.setAttribute('data-client', JSON.stringify(clientData));
            } catch(e) {}
        }

        toggleEditClientName(false);
    })
    .catch(err => {
        console.error('Rename error:', err);
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save';
        }
        if (feedback) {
            feedback.style.display = 'block';
            feedback.style.color = '#dc2626';
            feedback.textContent = 'Network error while updating name.';
        }
    });
}

function openClientOverviewModal(row) {
    if (!row) return;
    const rawData = row.getAttribute('data-client');
    if (!rawData) return;

    let client;
    try {
        client = JSON.parse(rawData);
    } catch(e) {
        console.error('Invalid client json', e);
        return;
    }

    const modal = document.getElementById('clientOverviewModal');
    if (!modal) return;

    // Reset to Details fragment & reset rename state
    switchClientModalTab('details');
    toggleEditClientName(false);

    const clientId = parseInt(client.id, 10) || 0;
    currentOverviewClientId = clientId;

    // 1. Header & Subtitle
    document.getElementById('dtModalClientId').textContent = '#' + clientId;
    document.getElementById('clientModalSubtitle').textContent = (client.client_name || 'Client') + ' • ID: #' + clientId;
    document.getElementById('dtModalClientName').textContent = client.client_name || '—';

    // 2. Email & Phone
    document.getElementById('dtModalClientEmail').textContent = client.email || 'Not Provided';
    document.getElementById('dtModalClientPhone').textContent = client.phone || '—';

    // 3. Status Badge
    const isActive = parseInt(client.is_active, 10) === 1;
    const badgeClass = isActive ? 'badge-success' : 'badge-danger';
    const statusText = isActive ? 'Active' : 'Disabled';

    const headerBadge = document.getElementById('clientModalBadge');
    if (headerBadge) {
        headerBadge.className = 'badge ' + badgeClass;
        headerBadge.textContent = statusText;
    }

    const statusContainer = document.getElementById('dtModalClientStatus');
    if (statusContainer) {
        statusContainer.innerHTML = '<span class="badge ' + badgeClass + '">' + statusText + '</span>';
    }

    // 4. API Keys & Secrets
    document.getElementById('dtModalClientApiKey').textContent = client.api_key || '—';
    document.getElementById('dtModalClientTestKey').textContent = client.test_api_key || '—';

    const apiSec = client.api_secret || '';
    const testSec = client.test_api_secret || '';
    const isMasked = apiSec === '••••••••••••';

    document.getElementById('dtModalClientApiSecret').textContent = apiSec || '—';
    document.getElementById('dtModalClientTestSecret').textContent = testSec || '—';

    const btnCopyLiveSec = document.getElementById('btnCopyClientApiSecret');
    const btnCopyTestSec = document.getElementById('btnCopyClientTestSecret');
    if (btnCopyLiveSec) btnCopyLiveSec.style.display = (!isMasked && apiSec) ? 'inline-block' : 'none';
    if (btnCopyTestSec) btnCopyTestSec.style.display = (!isMasked && testSec) ? 'inline-block' : 'none';

    // 5. Notifications
    let notifs = [];
    if (parseInt(client.notify_email_payment || 0, 10) === 1) notifs.push('Email: Payments');
    if (parseInt(client.notify_email_refund || 0, 10) === 1) notifs.push('Email: Refunds');
    if (parseInt(client.notify_email_dispute || 0, 10) === 1) notifs.push('Email: Disputes');
    if (parseInt(client.notify_sms_payment || 0, 10) === 1) notifs.push('SMS: Payments');
    if (parseInt(client.notify_sms_refund || 0, 10) === 1) notifs.push('SMS: Refunds');
    if (parseInt(client.notify_sms_dispute || 0, 10) === 1) notifs.push('SMS: Disputes');

    const notifElem = document.getElementById('dtModalClientNotifications');
    if (notifElem) {
        if (notifs.length > 0) {
            notifElem.innerHTML = notifs.map(n => '<span class="badge" style="background:var(--bg-hover); border:1px solid var(--border-color); margin-right:4px; margin-bottom:4px; font-size:0.72rem; display:inline-block;">' + escapeHtml(n) + '</span>').join('');
        } else {
            notifElem.innerHTML = '<span style="color:var(--text-gray); font-style:italic;">Default system alerts</span>';
        }
    }

    // 6. Volume & Stats placeholder
    document.getElementById('dtModalClientVolume').innerHTML = '<span style="color:var(--text-gray); font-style:italic;">Loading metrics...</span>';

    // 7. Onboarding date
    document.getElementById('dtModalClientCreatedAt').textContent = client.created_at || '—';

    // 8. Footer Action Buttons (Status Toggle)
    const statusIdInput = document.getElementById('clientModalStatusId');
    const statusActionInput = document.getElementById('clientModalStatusAction');
    const statusBtn = document.getElementById('clientModalStatusBtn');

    if (statusIdInput && statusActionInput && statusBtn) {
        statusIdInput.value = clientId;
        if (isActive) {
            statusActionInput.value = 'deactivate_client';
            statusBtn.className = 'btn btn-secondary btn-sm';
            statusBtn.style.color = '#dc2626';
            statusBtn.textContent = 'Deactivate Merchant';
            statusBtn.setAttribute('onclick', 'return confirm("Are you sure you want to deactivate merchant #' + clientId + '?");');
        } else {
            statusActionInput.value = 'activate_client';
            statusBtn.className = 'btn btn-primary btn-sm';
            statusBtn.style.color = '';
            statusBtn.textContent = 'Activate Merchant';
            statusBtn.removeAttribute('onclick');
        }
    }

    // Open modal
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';

    // Asynchronously load Timeline & Activity
    loadClientTimelineData(clientId);
}

function loadClientTimelineData(clientId) {
    const loadingElem = document.getElementById('clientTimelineLoading');
    const contentElem = document.getElementById('clientTimelineContent');
    const badgeElem = document.getElementById('clientTabTimelineBadge');
    const activityList = document.getElementById('clientActivityList');
    const countText = document.getElementById('clientActivityCountText');
    const auditContainer = document.getElementById('clientAuditRecordsContainer');
    const auditList = document.getElementById('clientAuditRecordsList');
    const volumeElem = document.getElementById('dtModalClientVolume');

    if (loadingElem) loadingElem.style.display = 'block';
    if (contentElem) contentElem.style.display = 'none';
    if (badgeElem) badgeElem.style.display = 'none';

    const paymentsLoading = document.getElementById('clientPaymentsLoading');
    const paymentsEmpty = document.getElementById('clientPaymentsEmpty');
    const paymentsContent = document.getElementById('clientPaymentsContent');
    const tabPaymentsBadge = document.getElementById('clientTabPaymentsBadge');

    if (paymentsLoading) {
        paymentsLoading.style.display = 'block';
        paymentsLoading.innerHTML = '<div class="client-spinner" style="margin: 0 auto 12px auto;"></div><div style="font-size: 0.85rem; font-weight: 500;">Loading payment transactions...</div>';
    }
    if (paymentsEmpty) paymentsEmpty.style.display = 'none';
    if (paymentsContent) paymentsContent.style.display = 'none';
    if (tabPaymentsBadge) tabPaymentsBadge.style.display = 'none';

    fetch('client_timeline_ajax.php?client_id=' + encodeURIComponent(clientId))
        .then(res => res.json())
        .then(data => {
            if (currentOverviewClientId !== clientId) return; // Stale request guard
            if (!data.success) {
                if (loadingElem) loadingElem.innerHTML = '<div style="color:var(--danger-text); padding:20px;">' + (data.message || 'Failed to load timeline.') + '</div>';
                if (paymentsLoading) paymentsLoading.innerHTML = '<div style="color:var(--danger-text); padding:20px;">' + (data.message || 'Failed to load payments.') + '</div>';
                return;
            }

            // Update Volume / Revenue Stats in Details tab
            if (volumeElem && data.stats) {
                const rev = parseFloat(data.stats.total_revenue || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                volumeElem.innerHTML = `
                    <div style="font-weight: 700; color: var(--text-dark); font-size: 0.95rem;">₹${rev}</div>
                    <div style="font-size: 0.76rem; color: var(--text-gray); margin-top: 2px;">
                        <strong>${data.stats.total_count}</strong> total payment(s) &bull; 
                        <strong style="color: #16a34a;">${data.stats.success_count}</strong> successful &bull; 
                        <strong style="color: #dc2626;">${data.stats.failed_count}</strong> failed
                    </div>
                `;
            }

            // 1. Tab Badges
            const paymentsList = data.recent_payments || [];
            const auditLogsList = data.audit_logs || [];

            if (tabPaymentsBadge) {
                tabPaymentsBadge.textContent = paymentsList.length;
                tabPaymentsBadge.style.display = paymentsList.length > 0 ? 'inline-block' : 'none';
            }

            const tabTimelineBadge = document.getElementById('clientTabTimelineBadge');
            if (tabTimelineBadge) {
                tabTimelineBadge.textContent = auditLogsList.length;
                tabTimelineBadge.style.display = auditLogsList.length > 0 ? 'inline-block' : 'none';
            }

            // 2. Render Fragment 2: Tabular Payments View
            const paymentsTableBody = document.getElementById('clientPaymentsTableBody');
            const paymentsHeaderCount = document.getElementById('clientPaymentsHeaderCount');
            const paymentsHeaderStats = document.getElementById('clientPaymentsHeaderStats');

            if (paymentsLoading) paymentsLoading.style.display = 'none';

            if (paymentsList.length === 0) {
                if (paymentsEmpty) paymentsEmpty.style.display = 'block';
                if (paymentsContent) paymentsContent.style.display = 'none';
            } else {
                if (paymentsEmpty) paymentsEmpty.style.display = 'none';
                if (paymentsContent) paymentsContent.style.display = 'block';

                if (paymentsHeaderCount) {
                    paymentsHeaderCount.textContent = 'Payments (' + paymentsList.length + ')';
                }

                if (paymentsHeaderStats && data.stats) {
                    const rev = parseFloat(data.stats.total_revenue || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    paymentsHeaderStats.innerHTML = `
                        <span>Total: <strong style="color:var(--text-dark);">₹${rev}</strong></span>
                        <span style="color:#16a34a; font-weight:600; margin-left:6px;">✓ ${data.stats.success_count}</span>
                        ${data.stats.failed_count > 0 ? `<span style="color:#dc2626; font-weight:600; margin-left:4px;">✗ ${data.stats.failed_count}</span>` : ''}
                        ${data.stats.refunded_count > 0 ? `<span style="color:#b45309; font-weight:600; margin-left:4px;">↺ ${data.stats.refunded_count}</span>` : ''}
                    `;
                }

                if (paymentsTableBody) {
                    paymentsTableBody.innerHTML = paymentsList.map(p => {
                        const st = (p.status || '').toUpperCase();
                        let bClass = 'badge-pending';
                        if (st === 'SUCCESS') bClass = 'badge-success';
                        else if (st === 'FAILED') bClass = 'badge-danger';
                        else if (st === 'REFUNDED') bClass = 'badge-refunded';

                        const amt = parseFloat(p.amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        const cust = escapeHtml(p.customer_email ? (p.customer_email + (p.customer_mobile ? ' • ' + p.customer_mobile : '')) : (p.customer_mobile || '—'));
                        const evt = escapeHtml(p.event_name || 'Direct Gateway Charge');
                        const txId = escapeHtml(p.vortex_transaction_id || ('#' + p.id));
                        const dateStr = escapeHtml(p.created_at || '—');

                        return `
                            <tr>
                                <td style="padding: 10px 12px;">
                                    <span class="font-mono" style="color: var(--primary-blue); font-weight: 700; font-size: 0.78rem;">
                                        ${txId}
                                    </span>
                                </td>
                                <td style="padding: 10px 12px; font-weight: 500; color: var(--text-dark);">
                                    ${evt}
                                </td>
                                <td style="padding: 10px 12px; color: var(--text-gray); font-size: 0.78rem;">
                                    ${cust}
                                </td>
                                <td style="padding: 10px 12px; font-weight: 700; color: var(--text-dark); white-space: nowrap;">
                                    ₹${amt}
                                </td>
                                <td style="padding: 10px 12px; text-align: center;">
                                    <span class="badge ${bClass}" style="font-size: 0.7rem; padding: 2px 7px;">
                                        ${escapeHtml(st === 'PENDING' ? 'UNPAID' : st)}
                                    </span>
                                </td>
                                <td style="padding: 10px 12px; text-align: right; color: var(--text-gray); font-size: 0.74rem; white-space: nowrap;">
                                    ${dateStr}
                                </td>
                            </tr>
                        `;
                    }).join('');
                }
            }

            // 3. Render Fragment 3: Timeline & Audit Trail
            if (countText) {
                countText.textContent = paymentsList.length + ' milestone(s)';
            }

            if (activityList) {
                if (paymentsList.length === 0) {
                    activityList.innerHTML = '<div style="color:var(--text-gray); font-size:0.85rem; padding:12px 0;">No transaction activity recorded yet for this merchant.</div>';
                } else {
                    activityList.innerHTML = paymentsList.map((p, idx) => {
                        let bulletClass = 'bullet-pending';
                        let bClass = 'badge-pending';
                        const st = (p.status || '').toUpperCase();
                        if (st === 'SUCCESS') { bulletClass = 'bullet-success'; bClass = 'badge-success'; }
                        else if (st === 'FAILED') { bulletClass = 'bullet-danger'; bClass = 'badge-danger'; }
                        else if (st === 'REFUNDED') { bulletClass = 'bullet-refunded'; bClass = 'badge-refunded'; }

                        const amt = parseFloat(p.amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                        return `
                            <div class="client-timeline-node">
                                <div class="client-timeline-bullet ${bulletClass}"></div>
                                <div class="client-timeline-card"${idx === 0 ? ' style="border-left: 3px solid var(--primary-blue);"' : ''}>
                                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px; margin-bottom:6px;">
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <span class="badge ${bClass}" style="font-size:0.72rem; padding:2px 6px;">${escapeHtml(st === 'PENDING' ? 'UNPAID' : st)}</span>
                                            ${idx === 0 ? '<span class="badge" style="background:var(--primary-light); color:var(--primary-blue); font-size:0.68rem; padding:1px 5px; font-weight:700;">LATEST</span>' : ''}
                                            <span style="font-weight:700; color:var(--text-dark); font-size:0.88rem;">₹${amt}</span>
                                        </div>
                                        <div style="font-size:0.74rem; color:var(--text-gray);">${escapeHtml(p.created_at || '')}</div>
                                    </div>
                                    <div style="font-size:0.82rem; color:var(--text-dark); margin-bottom:4px;">
                                        ${escapeHtml(p.event_name || 'Direct Gateway Charge')}
                                    </div>
                                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px; font-size:0.74rem; color:var(--text-gray);">
                                        <div class="font-mono" style="color:var(--primary-blue); font-weight:600;">
                                            ${escapeHtml(p.vortex_transaction_id || '')}
                                        </div>
                                        <div>${escapeHtml(p.customer_email || 'No email')}</div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('');
                }
            }

            // Render Governance & Audit Logs
            if (auditContainer && auditList) {
                if (auditLogsList.length > 0) {
                    auditContainer.style.display = 'block';
                    auditList.innerHTML = auditLogsList.map(log => `
                        <div class="client-sub-card">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                                <div>
                                    <strong style="color:var(--text-dark);">${escapeHtml(log.action || 'Action')}</strong>
                                    <span style="color:var(--text-gray); font-size:0.74rem; margin-left:6px;">by <strong>${escapeHtml(log.actor_username || 'Admin')}</strong> (${escapeHtml(log.actor_role || 'Role')})</span>
                                </div>
                                <span style="font-size:0.72rem; color:var(--text-gray);">${escapeHtml(log.created_at || '')}</span>
                            </div>
                            <div style="color:var(--text-dark); font-size:0.78rem;">${escapeHtml(log.description || '')}</div>
                            ${log.ip_address ? `<div style="font-size:0.7rem; color:var(--text-gray); margin-top:2px;">IP: ${escapeHtml(log.ip_address)}</div>` : ''}
                        </div>
                    `).join('');
                } else {
                    auditContainer.style.display = 'none';
                }
            }

            if (loadingElem) loadingElem.style.display = 'none';
            if (contentElem) contentElem.style.display = 'block';
        })
        .catch(err => {
            console.error('Failed to fetch client timeline', err);
            if (loadingElem) loadingElem.innerHTML = '<div style="color:var(--danger-text); padding:20px; font-size:0.85rem;">Failed to load timeline. Please check your network connection.</div>';
        });
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

function closeClientOverviewModal() {
    const modal = document.getElementById('clientOverviewModal');
    if (modal) {
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }
}

function copyClientModalField(elemId, btn) {
    const elem = document.getElementById(elemId);
    if (!elem) return;
    const text = elem.textContent.trim();
    if (!text || text === '—' || text === '••••••••••••') return;

    if (typeof copyToClipboard === 'function') {
        copyToClipboard(text, btn);
    } else {
        navigator.clipboard.writeText(text).then(() => {
            const orig = btn.textContent;
            btn.textContent = '✓ Copied!';
            setTimeout(() => { btn.textContent = orig; }, 1500);
        }).catch(err => {
            console.error('Copy failed', err);
        });
    }
}

function openCreateClientModal(e) {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();
    const modal = document.getElementById('createClientModal');
    if (modal) {
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
        const input = document.getElementById('modal_client_name');
        if (input) setTimeout(() => input.focus(), 100);
    }
}

function closeCreateClientModal(e) {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();
    const modal = document.getElementById('createClientModal');
    if (modal) {
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }
}

// Close on backdrop click & Escape key
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('clientOverviewModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeClientOverviewModal();
            }
        });
    }

    const createModal = document.getElementById('createClientModal');
    const closeCreateBtn = document.getElementById('closeCreateClientModal');
    const cancelCreateBtn = document.getElementById('cancelCreateClientModal');

    if (closeCreateBtn) closeCreateBtn.addEventListener('click', closeCreateClientModal);
    if (cancelCreateBtn) cancelCreateBtn.addEventListener('click', closeCreateClientModal);

    if (createModal) {
        createModal.addEventListener('click', function(e) {
            if (e.target === createModal) {
                closeCreateClientModal();
            }
        });
    }

    <?php if (isset($flashError) && $flashError && (str_contains(strtolower($flashError), 'client name') || str_contains(strtolower($flashError), 'api client'))): ?>
    openCreateClientModal();
    <?php endif; ?>
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('clientOverviewModal');
        if (modal && modal.classList.contains('open')) {
            closeClientOverviewModal();
        }
        const createModal = document.getElementById('createClientModal');
        if (createModal && createModal.classList.contains('open')) {
            closeCreateClientModal();
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

