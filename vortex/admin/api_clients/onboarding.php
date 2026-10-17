<?php
/**
 * ------------------------------------------------------------
 * onboarding.php – Client Onboarding Wizard
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway - Admin Panel
 * Purpose : Guided 4-step enterprise onboarding wizard for new
 *           merchants, featuring live webhook diagnostic testing,
 *           cryptographic key generation, and credentials handoff.
 * ------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/ApiClient.php';
require_once __DIR__ . '/../../classes/RBAC.php';
require_once __DIR__ . '/../../classes/AuditLogger.php';
require_once __DIR__ . '/../../classes/NotificationService.php';
require_once __DIR__ . '/../../classes/Logger.php';

// Auth Guard
if (empty($_SESSION['vortex_admin_id'])) {
    header('Location: ../login.php');
    exit;
}

// RBAC Guard - onboarding creates a client
RBAC::require('clients.create', null, 'index.php');

$apiClientModel = new ApiClient();
$error = null;
$success = null;
$onboardedClient = null;

// Handle Onboarding Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete_onboarding') {
    $clientName   = trim($_POST['client_name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $isActive     = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

    $nep = isset($_POST['notify_email_payment']) ? 1 : 0;
    $ner = isset($_POST['notify_email_refund'])  ? 1 : 0;
    $ned = isset($_POST['notify_email_dispute']) ? 1 : 0;
    $nsp = isset($_POST['notify_sms_payment'])   ? 1 : 0;
    $nsr = isset($_POST['notify_sms_refund'])    ? 1 : 0;
    $nsd = isset($_POST['notify_sms_dispute'])   ? 1 : 0;

    $sendWelcome  = isset($_POST['send_welcome_email']) ? 1 : 0;

    if (empty($clientName)) {
        $error = "Merchant / Organization name is required.";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide a valid contact email address.";
    } else {
        $result = $apiClientModel->onboardClient([
            'client_name'          => $clientName,
            'email'                => $email,
            'phone'                => $phone,
            'is_active'            => $isActive,
            'notify_email_payment' => $nep,
            'notify_email_refund'  => $ner,
            'notify_email_dispute' => $ned,
            'notify_sms_payment'   => $nsp,
            'notify_sms_refund'    => $nsr,
            'notify_sms_dispute'   => $nsd,
        ]);

        if ($result['success']) {
            $onboardedClient = $result['data'];
            $success = $result['message'];

            // Optional welcome email dispatch
            if ($sendWelcome && !empty($email)) {
                try {
                    NotificationService::sendWelcomeEmail($onboardedClient);
                } catch (\Throwable $e) {
                    Logger::error("Welcome email failed during onboarding: " . $e->getMessage());
                }
            }
        } else {
            $error = $result['message'];
        }
    }
}

// Generate an initial random webhook secret for the UI
$initialWebhookSecret = bin2hex(random_bytes(24));

$pageTitle = "Client Onboarding Wizard - Rpay Admin";
$activeNav = "clients";
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Wizard Layout & Theme */
.wizard-container {
    max-width: 920px;
    margin: 0 auto 40px auto;
}

.wizard-header {
    margin-bottom: 24px;
}

.wizard-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 10px;
}

.wizard-desc {
    color: var(--text-gray);
    font-size: 0.9rem;
    margin-top: 4px;
}

/* Stepper Progress Bar */
.wizard-stepper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    margin-bottom: 32px;
    padding: 0 10px;
}

.wizard-stepper::before {
    content: '';
    position: absolute;
    top: 20px;
    left: 40px;
    right: 40px;
    height: 3px;
    background: #e2e8f0;
    z-index: 1;
}

.wizard-progress-bar {
    position: absolute;
    top: 20px;
    left: 40px;
    height: 3px;
    background: var(--primary-blue);
    z-index: 2;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    width: 0%;
}

.step-node {
    position: relative;
    z-index: 3;
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    background: transparent;
    border: none;
    padding: 0;
}

.step-circle {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: #ffffff;
    border: 2px solid #cbd5e1;
    color: #64748b;
    font-weight: 700;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.step-node.active .step-circle {
    background: var(--primary-blue);
    border-color: var(--primary-blue);
    color: #ffffff;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.18);
}

.step-node.completed .step-circle {
    background: #10b981;
    border-color: #10b981;
    color: #ffffff;
}

.step-label {
    margin-top: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #64748b;
    white-space: nowrap;
}

.step-node.active .step-label {
    color: var(--primary-blue);
}

.step-node.completed .step-label {
    color: #10b981;
}

/* Card & Steps */
.wizard-card {
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
    overflow: hidden;
}

.wizard-card-header {
    padding: 20px 28px;
    border-bottom: 1px solid var(--border-color);
    background: #fafbfc;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.wizard-card-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0;
}

.wizard-card-subtitle {
    font-size: 0.82rem;
    color: var(--text-gray);
    margin-top: 3px;
}

.wizard-card-body {
    padding: 28px;
}

.wizard-card-footer {
    padding: 18px 28px;
    background: #fafbfc;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.wizard-step-pane {
    display: none;
}

.wizard-step-pane.active {
    display: block;
    animation: fadeInStep 0.25s ease forwards;
}

@keyframes fadeInStep {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Form Styling */
.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

@media (max-width: 640px) {
    .form-grid-2 { grid-template-columns: 1fr; }
    .wizard-stepper::before, .wizard-progress-bar { display: none; }
}

.pref-box {
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 16px;
    background: #f8fafc;
    margin-bottom: 14px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.pref-box:hover {
    border-color: #cbd5e1;
    background: #f1f5f9;
}

.pref-box input[type="checkbox"] {
    margin-top: 3px;
    width: 18px;
    height: 18px;
    accent-color: var(--primary-blue);
    cursor: pointer;
}

.pref-box-content {
    flex: 1;
}

.pref-box-title {
    font-size: 0.92rem;
    font-weight: 600;
    color: var(--text-dark);
}

.pref-box-desc {
    font-size: 0.8rem;
    color: var(--text-gray);
    margin-top: 2px;
}

/* Diagnostic Box */
.diagnostic-panel {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 20px;
    margin-top: 18px;
}

.ping-result-box {
    margin-top: 16px;
    border-radius: 8px;
    padding: 14px 18px;
    display: none;
}

.ping-result-box.success {
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #065f46;
}

.ping-result-box.error {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
}

/* Handoff Success Box */
.handoff-banner {
    background: linear-gradient(135deg, #0f172a, #1e293b);
    border-radius: 12px;
    padding: 28px;
    color: #ffffff;
    margin-bottom: 24px;
}

.credential-field {
    background: #0f172a;
    border: 1px solid #334155;
    border-radius: 8px;
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.credential-field .val {
    font-family: monospace;
    font-size: 0.95rem;
    color: #38bdf8;
    word-break: break-all;
}
</style>

<div class="wizard-container">
    <!-- Top Back Link & Header -->
    <div style="margin-bottom: 16px;">
        <a href="index.php" style="color: var(--primary-blue); text-decoration: none; font-size: 0.88rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
            &larr; Return to API Clients
        </a>
    </div>

    <div class="wizard-header">
        <h1 class="wizard-title">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary-blue);"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>
            Merchant Onboarding Wizard
        </h1>
        <p class="wizard-desc">
            Register a new client, configure multi-channel alerts, validate real-time webhook endpoints, and generate cryptographic API keys.
        </p>
    </div>

    <!-- Global Alerts -->
    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom: 20px;">
            <strong>Onboarding Error:</strong> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($onboardedClient): ?>
        <!-- STEP 4: SUCCESS & HANDOFF SCREEN -->
        <div class="wizard-card">
            <div class="wizard-card-header" style="background: #ecfdf5; border-color: #a7f3d0;">
                <div>
                    <h3 class="wizard-card-title" style="color: #065f46; display: flex; align-items: center; gap: 8px;">
                        <span>🎉</span> Merchant Successfully Onboarded!
                    </h3>
                    <div class="wizard-card-subtitle" style="color: #047857;">
                        Account has been created and cryptographic access credentials have been provisioned.
                    </div>
                </div>
                <span class="badge badge-success" style="font-size: 0.85rem; padding: 6px 12px;">Active &amp; Ready</span>
            </div>

            <div class="wizard-card-body">
                <div class="handoff-banner">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
                        <div>
                            <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; font-weight: 700;">Client Profile</span>
                            <h2 style="margin: 2px 0 0 0; font-size: 1.4rem; color: #f8fafc;"><?= htmlspecialchars($onboardedClient['client_name']) ?></h2>
                            <div style="font-size: 0.82rem; color: #cbd5e1; margin-top: 3px;">
                                Client ID: #<?= (int)$onboardedClient['id'] ?>
                                <?php if (!empty($onboardedClient['email'])): ?> &bull; <?= htmlspecialchars($onboardedClient['email']) ?><?php endif; ?>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="downloadCredentialsJson()" style="background: #334155; color: #f8fafc; border: 1px solid #475569;">
                                📥 Download JSON
                            </button>
                            <button type="button" class="btn btn-primary btn-sm" onclick="copyAllCredentials()">
                                📋 Copy All Keys
                            </button>
                        </div>
                    </div>

                    <!-- Live Credentials -->
                    <div style="margin-top: 20px;">
                        <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: #38bdf8; font-weight: 700;">Live Production Keys</span>
                        <div class="credential-field" style="margin-top: 6px;">
                            <div>
                                <span style="font-size: 0.75rem; color: #94a3b8; display: block;">Live API Key</span>
                                <span class="val" id="disp_live_key"><?= htmlspecialchars($onboardedClient['api_key']) ?></span>
                            </div>
                            <button type="button" class="btn-copy btn-copy-sm" onclick="copyToClipboard('<?= htmlspecialchars($onboardedClient['api_key'], ENT_QUOTES) ?>', this)">📋 Copy</button>
                        </div>
                        <div class="credential-field">
                            <div>
                                <span style="font-size: 0.75rem; color: #f87171; display: block;">Live API Secret (Keep Secret!)</span>
                                <span class="val" style="color: #fca5a5;" id="disp_live_secret"><?= htmlspecialchars($onboardedClient['api_secret']) ?></span>
                            </div>
                            <button type="button" class="btn-copy btn-copy-sm" onclick="copyToClipboard('<?= htmlspecialchars($onboardedClient['api_secret'], ENT_QUOTES) ?>', this)">📋 Copy</button>
                        </div>
                    </div>

                    <!-- Sandbox / Test Credentials -->
                    <div style="margin-top: 16px;">
                        <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: #a78bfa; font-weight: 700;">Sandbox / Test Keys</span>
                        <div class="credential-field" style="margin-top: 6px;">
                            <div>
                                <span style="font-size: 0.75rem; color: #94a3b8; display: block;">Test API Key</span>
                                <span class="val" style="color: #c4b5fd;" id="disp_test_key"><?= htmlspecialchars($onboardedClient['test_api_key']) ?></span>
                            </div>
                            <button type="button" class="btn-copy btn-copy-sm" onclick="copyToClipboard('<?= htmlspecialchars($onboardedClient['test_api_key'], ENT_QUOTES) ?>', this)">📋 Copy</button>
                        </div>
                        <div class="credential-field">
                            <div>
                                <span style="font-size: 0.75rem; color: #94a3b8; display: block;">Test API Secret</span>
                                <span class="val" style="color: #c4b5fd;" id="disp_test_secret"><?= htmlspecialchars($onboardedClient['test_api_secret']) ?></span>
                            </div>
                            <button type="button" class="btn-copy btn-copy-sm" onclick="copyToClipboard('<?= htmlspecialchars($onboardedClient['test_api_secret'], ENT_QUOTES) ?>', this)">📋 Copy</button>
                        </div>
                    </div>

                    <!-- Event Webhooks Notice -->
                    <div style="margin-top: 16px; background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 6px; padding: 12px 14px;">
                        <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: #93c5fd; font-weight: 700; display: block; margin-bottom: 4px;">Event Webhooks</span>
                        <div style="font-size: 0.8rem; color: #cbd5e1;">
                            Webhooks are configured per Event in the <a href="../events/index.php" style="color: #60a5fa; text-decoration: underline; font-weight: 600;">Events Management</a> panel.
                        </div>
                    </div>
                </div>

                <!-- Integration Quickstart Preview -->
                <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 18px;">
                    <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-dark); margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
                        API Integration Quickstart (PHP)
                    </div>
                    <pre style="background: #1e293b; color: #f8fafc; padding: 14px; border-radius: 6px; font-size: 0.8rem; overflow-x: auto; margin: 0;"><?= htmlspecialchars('$ch = curl_init("https://gateway.rajagiri.edu/vortex/api/initiate_payment.php");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        "event_id" => 1,
        "amount" => 500.00,
        "currency" => "INR",
        "customer_email" => "attendee@example.com"
    ]),
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "X-Vortex-API-Key: ' . $onboardedClient['api_key'] . '",
        "X-Vortex-API-Secret: ' . $onboardedClient['api_secret'] . '"
    ],
    CURLOPT_RETURNTRANSFER => true
]);
$response = json_decode(curl_exec($ch), true);') ?></pre>
                </div>
            </div>

            <div class="wizard-card-footer">
                <a href="onboarding.php" class="btn btn-secondary">
                    + Onboard Another Merchant
                </a>
                <div style="display: flex; gap: 10px;">
                    <a href="../../portal/login.php" target="_blank" class="btn btn-secondary">
                        Open Merchant Portal &#8599;
                    </a>
                    <a href="index.php" class="btn btn-primary">
                        Done &amp; View All Clients
                    </a>
                </div>
            </div>
        </div>

        <script>
        const clientBundle = <?= json_encode($onboardedClient) ?>;

        function downloadCredentialsJson() {
            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(clientBundle, null, 2));
            const downloadAnchor = document.createElement('a');
            downloadAnchor.setAttribute("href", dataStr);
            downloadAnchor.setAttribute("download", `payvortex_credentials_${clientBundle.client_name.toLowerCase().replace(/[^a-z0-9]/g, '_')}.json`);
            document.body.appendChild(downloadAnchor);
            downloadAnchor.click();
            downloadAnchor.remove();
        }

        function copyAllCredentials() {
            const text = `Rpay Merchant Credentials
-------------------------------------------
Client Name:   ${clientBundle.client_name}
Client ID:     #${clientBundle.id}
Live API Key:  ${clientBundle.api_key}
Live Secret:   ${clientBundle.api_secret}
Test API Key:  ${clientBundle.test_api_key}
Test Secret:   ${clientBundle.test_api_secret}
Webhook URL:   ${clientBundle.webhook_url || 'N/A'}
Webhook Secret:${clientBundle.webhook_secret || 'N/A'}
-------------------------------------------`;
            navigator.clipboard.writeText(text).then(() => {
                alert('All credentials copied to clipboard!');
            });
        }
        </script>

    <?php else: ?>
        <!-- MULTI-STEP WIZARD FORM -->

        <!-- Stepper Navigation -->
        <div class="wizard-stepper">
            <div class="wizard-progress-bar" id="stepperProgressBar"></div>

            <button type="button" class="step-node active" id="stepNode1" onclick="jumpToStep(1)">
                <div class="step-circle">1</div>
                <span class="step-label">Merchant Details</span>
            </button>

            <button type="button" class="step-node" id="stepNode2" onclick="jumpToStep(2)">
                <div class="step-circle">2</div>
                <span class="step-label">Notifications</span>
            </button>

            <button type="button" class="step-node" id="stepNode3" onclick="jumpToStep(3)">
                <div class="step-circle">3</div>
                <span class="step-label">Review &amp; Deploy</span>
            </button>
        </div>

        <form action="onboarding.php" method="POST" id="onboardingForm" onsubmit="return validateBeforeSubmit()">
            <input type="hidden" name="action" value="complete_onboarding">

            <div class="wizard-card">
                <!-- STEP 1: MERCHANT DETAILS -->
                <div class="wizard-step-pane active" id="paneStep1">
                    <div class="wizard-card-header">
                        <div>
                            <h3 class="wizard-card-title">1. Merchant &amp; Business Information</h3>
                            <div class="wizard-card-subtitle">Enter the merchant's business entity and primary contact information.</div>
                        </div>
                        <span class="badge badge-info" style="font-size: 0.78rem;">Step 1 of 3</span>
                    </div>
                    <div class="wizard-card-body">
                        <div class="form-group">
                            <label for="client_name">Client / Merchant Name <span style="color: #ef4444;">*</span></label>
                            <input type="text" id="client_name" name="client_name" class="form-control" placeholder="e.g. Rajagiri Conference &amp; Cultural Fest" required autofocus>
                            <small style="color: var(--text-gray); font-size: 0.78rem;">The official entity name representing this merchant or internal department.</small>
                        </div>

                        <div class="form-grid-2" style="margin-top: 16px;">
                            <div class="form-group">
                                <label for="email">Contact / Billing Email</label>
                                <input type="email" id="email" name="email" class="form-control" placeholder="merchant@rajagiri.edu">
                                <small style="color: var(--text-gray); font-size: 0.78rem;">Receives credentials and transaction alerts.</small>
                            </div>

                            <div class="form-group">
                                <label for="phone">Contact Phone / Mobile</label>
                                <input type="tel" id="phone" name="phone" class="form-control" placeholder="+91 9876543210">
                                <small style="color: var(--text-gray); font-size: 0.78rem;">For urgent dispute alerts and SMS notifications.</small>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top: 16px;">
                            <label for="is_active">Initial Account Status</label>
                            <select id="is_active" name="is_active" class="form-control" style="max-width: 280px;">
                                <option value="1" selected>Active (Can process live payments immediately)</option>
                                <option value="0">Suspended / Draft (Credentials inactive)</option>
                            </select>
                        </div>
                    </div>
                    <div class="wizard-card-footer">
                        <div></div>
                        <button type="button" class="btn btn-primary" onclick="nextStep(2)">
                            Next: Notification Channels &rarr;
                        </button>
                    </div>
                </div>

                <!-- STEP 2: NOTIFICATION PREFERENCES -->
                <div class="wizard-step-pane" id="paneStep2">
                    <div class="wizard-card-header">
                        <div>
                            <h3 class="wizard-card-title">2. Notification Channels &amp; Event Subscriptions</h3>
                            <div class="wizard-card-subtitle">Choose which events trigger real-time Email and SMS alerts for this merchant.</div>
                        </div>
                        <span class="badge badge-info" style="font-size: 0.78rem;">Step 2 of 4</span>
                    </div>
                    <div class="wizard-card-body">
                        <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--text-dark); margin: 0 0 12px 0;">
                            ✉️ Email Notification Channels
                        </h4>

                        <label class="pref-box">
                            <input type="checkbox" name="notify_email_payment" value="1" checked>
                            <div class="pref-box-content">
                                <div class="pref-box-title">Payment Captured Alerts</div>
                                <div class="pref-box-desc">Sends branded email notifications when an attendee completes a successful transaction.</div>
                            </div>
                        </label>

                        <label class="pref-box">
                            <input type="checkbox" name="notify_email_refund" value="1" checked>
                            <div class="pref-box-content">
                                <div class="pref-box-title">Refund Execution Notices</div>
                                <div class="pref-box-desc">Sends email notices when an admin or automated policy issues a full/partial refund.</div>
                            </div>
                        </label>

                        <label class="pref-box">
                            <input type="checkbox" name="notify_email_dispute" value="1" checked>
                            <div class="pref-box-content">
                                <div class="pref-box-title">Payment Failure &amp; Dispute Warnings</div>
                                <div class="pref-box-desc">Instant alerts for dropped bank handshakes, fraud flags, or chargebacks.</div>
                            </div>
                        </label>

                        <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--text-dark); margin: 24px 0 12px 0;">
                            📱 SMS Alert Channels (Optional)
                        </h4>

                        <label class="pref-box">
                            <input type="checkbox" name="notify_sms_payment" value="1">
                            <div class="pref-box-content">
                                <div class="pref-box-title">High-Volume Payment SMS</div>
                                <div class="pref-box-desc">Send immediate SMS ping on every captured payment (carrier charges may apply).</div>
                            </div>
                        </label>

                        <label class="pref-box">
                            <input type="checkbox" name="notify_sms_refund" value="1" checked>
                            <div class="pref-box-content">
                                <div class="pref-box-title">Refund Processed SMS</div>
                                <div class="pref-box-desc">Critical alert SMS when funds are debited back for a refund.</div>
                            </div>
                        </label>

                        <label class="pref-box">
                            <input type="checkbox" name="notify_sms_dispute" value="1" checked>
                            <div class="pref-box-content">
                                <div class="pref-box-title">Urgent Dispute &amp; Chargeback SMS</div>
                                <div class="pref-box-desc">Critical SMS ping to merchant contact for timely dispute representation.</div>
                            </div>
                        </label>
                    </div>
                    <div class="wizard-card-footer">
                        <button type="button" class="btn btn-secondary" onclick="prevStep(1)">
                            &larr; Back
                        </button>
                        <button type="button" class="btn btn-primary" onclick="nextStep(3)">
                            Next: Review &amp; Deploy &rarr;
                        </button>
                    </div>
                </div>

                <!-- STEP 3: REVIEW & PROVISION -->
                <div class="wizard-step-pane" id="paneStep3">
                    <div class="wizard-card-header">
                        <div>
                            <h3 class="wizard-card-title">3. Review &amp; Deploy Merchant Account</h3>
                            <div class="wizard-card-subtitle">Review summary before generating live and sandbox cryptographic keys.</div>
                        </div>
                        <span class="badge badge-info" style="font-size: 0.78rem;">Step 3 of 3</span>
                    </div>
                    <div class="wizard-card-body">
                        <!-- Summary Table -->
                        <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; margin-bottom: 20px;">
                            <div style="padding: 12px 16px; background: #fafbfc; border-bottom: 1px solid var(--border-color); font-weight: 700; font-size: 0.88rem;">
                                Onboarding Summary
                            </div>
                            <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                                <tr>
                                    <td style="padding: 10px 16px; color: var(--text-gray); width: 200px; border-bottom: 1px solid #edf2f7;">Merchant Name</td>
                                    <td style="padding: 10px 16px; font-weight: 600; color: var(--text-dark); border-bottom: 1px solid #edf2f7;" id="rev_client_name">–</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 16px; color: var(--text-gray); border-bottom: 1px solid #edf2f7;">Contact Email</td>
                                    <td style="padding: 10px 16px; color: var(--text-dark); border-bottom: 1px solid #edf2f7;" id="rev_email">–</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 16px; color: var(--text-gray); border-bottom: 1px solid #edf2f7;">Contact Phone</td>
                                    <td style="padding: 10px 16px; color: var(--text-dark); border-bottom: 1px solid #edf2f7;" id="rev_phone">–</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 16px; color: var(--text-gray); border-bottom: 1px solid #edf2f7;">Event Webhooks</td>
                                    <td style="padding: 10px 16px; color: var(--text-dark); border-bottom: 1px solid #edf2f7;">Configured per Event under Events Management</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 16px; color: var(--text-gray); border-bottom: 1px solid #edf2f7;">Account Status</td>
                                    <td style="padding: 10px 16px; font-weight: 600; color: #16a34a; border-bottom: 1px solid #edf2f7;" id="rev_status">Active</td>
                                </tr>
                            </table>
                        </div>

                        <!-- Welcome Email Checkbox -->
                        <label class="pref-box" style="margin-bottom: 0;">
                            <input type="checkbox" name="send_welcome_email" id="send_welcome_email" value="1" checked>
                            <div class="pref-box-content">
                                <div class="pref-box-title">✉️ Dispatch Welcome Credentials Email</div>
                                <div class="pref-box-desc">Automatically email the generated live/test API keys, portal quickstart, and login link to the contact email.</div>
                            </div>
                        </label>
                    </div>
                    <div class="wizard-card-footer">
                        <button type="button" class="btn btn-secondary" onclick="prevStep(2)">
                            &larr; Back
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnSubmitOnboarding" style="padding: 10px 24px; font-weight: 700;">
                            ✨ Complete Onboarding &amp; Generate Keys
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <script>
        let currentStep = 1;

        function updateStepUI(step) {
            currentStep = step;

            // Update Progress Bar & Nodes
            const progressPct = ((step - 1) / 2) * 100;
            document.getElementById('stepperProgressBar').style.width = progressPct + '%';

            for (let i = 1; i <= 3; i++) {
                const node = document.getElementById('stepNode' + i);
                const pane = document.getElementById('paneStep' + i);

                node.classList.remove('active', 'completed');
                pane.classList.remove('active');

                if (i === step) {
                    node.classList.add('active');
                    pane.classList.add('active');
                } else if (i < step) {
                    node.classList.add('completed');
                }
            }

            // Sync step 3 review summary if reached
            if (step === 3) {
                document.getElementById('rev_client_name').textContent = document.getElementById('client_name').value.trim() || '–';
                document.getElementById('rev_email').textContent       = document.getElementById('email').value.trim() || '–';
                document.getElementById('rev_phone').textContent       = document.getElementById('phone').value.trim() || '–';
                document.getElementById('rev_status').textContent      = document.getElementById('is_active').value === '1' ? 'Active' : 'Suspended';
            }
        }

        function nextStep(target) {
            // Validate current step
            if (currentStep === 1) {
                const name = document.getElementById('client_name').value.trim();
                if (!name) {
                    alert('Please enter the Client / Merchant name.');
                    document.getElementById('client_name').focus();
                    return;
                }
            }

            updateStepUI(target);
        }

        function prevStep(target) {
            updateStepUI(target);
        }

        function jumpToStep(target) {
            if (target > currentStep) {
                nextStep(target);
            } else {
                updateStepUI(target);
            }
        }

        function validateBeforeSubmit() {
            const name = document.getElementById('client_name').value.trim();
            if (!name) {
                alert('Client Name is required.');
                jumpToStep(1);
                return false;
            }
            return true;
        }
        </script>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
