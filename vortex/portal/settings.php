<?php
/**
 * ------------------------------------------------------------
 * settings.php – Merchant Account & Security Settings
 * ------------------------------------------------------------
 * Project : PayVortex Unified Payment Gateway
 * ------------------------------------------------------------
 */

$pageTitle = 'Merchant Settings';
$activeNav  = 'settings';

require_once __DIR__ . '/includes/header.php';

$feedbackMessage = '';
$feedbackType    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $clientName = trim($_POST['client_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');

        $result = $clientModel->updateClientProfile($clientId, $clientName, $email);
        if ($result['success']) {
            $feedbackMessage = 'Merchant profile updated successfully.';
            $feedbackType = 'success';
            $currentClient = $clientModel->getClientById($clientId);
            $_SESSION['vortex_client_name'] = $currentClient['client_name'];
        } else {
            $feedbackMessage = $result['message'];
            $feedbackType = 'error';
        }
    } elseif ($action === 'update_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $hasExistingPassword = !empty($currentClient['password_hash']);

        if ($hasExistingPassword) {
            if (!password_verify($currentPassword, $currentClient['password_hash'])) {
                $feedbackMessage = 'Incorrect current password.';
                $feedbackType = 'error';
            }
        }

        if (empty($feedbackMessage)) {
            if (strlen($newPassword) < 6) {
                $feedbackMessage = 'New password must be at least 6 characters.';
                $feedbackType = 'error';
            } elseif ($newPassword !== $confirmPassword) {
                $feedbackMessage = 'New passwords do not match.';
                $feedbackType = 'error';
            } else {
                $result = $clientModel->updateClientPassword($clientId, $newPassword);
                if ($result['success']) {
                    $feedbackMessage = 'Merchant password updated successfully.';
                    $feedbackType = 'success';
                    $currentClient = $clientModel->getClientById($clientId);
                } else {
                    $feedbackMessage = $result['message'];
                    $feedbackType = 'error';
                }
            }
        }
    } elseif ($action === 'update_notifications') {
        require_once __DIR__ . '/../classes/NotificationService.php';
        $notifData = [
            'email'                => trim($_POST['email'] ?? ''),
            'phone'                => trim($_POST['phone'] ?? ''),
            'notify_email_payment' => !empty($_POST['notify_email_payment']) ? 1 : 0,
            'notify_email_refund'  => !empty($_POST['notify_email_refund']) ? 1 : 0,
            'notify_email_dispute' => !empty($_POST['notify_email_dispute']) ? 1 : 0,
            'notify_sms_payment'   => !empty($_POST['notify_sms_payment']) ? 1 : 0,
            'notify_sms_refund'    => !empty($_POST['notify_sms_refund']) ? 1 : 0,
            'notify_sms_dispute'   => !empty($_POST['notify_sms_dispute']) ? 1 : 0,
        ];
        $result = NotificationService::updateClientNotificationSettings($clientId, $notifData);
        if ($result['success']) {
            $feedbackMessage = 'Notification channels and alert preferences saved successfully.';
            $feedbackType = 'success';
            $currentClient = $clientModel->getClientById($clientId);
        } else {
            $feedbackMessage = $result['message'];
            $feedbackType = 'error';
        }
    } elseif ($action === 'send_test_alert') {
        require_once __DIR__ . '/../classes/NotificationService.php';
        $result = NotificationService::sendTestNotification($clientId);
        if ($result['success']) {
            $feedbackMessage = 'Test notification alert successfully dispatched to your configured channels!';
            $feedbackType = 'success';
        } else {
            $feedbackMessage = 'Failed to dispatch test alert: ' . ($result['message'] ?? 'Please ensure an email is configured.');
            $feedbackType = 'error';
        }
    }
}

$hasExistingPassword = !empty($currentClient['password_hash']);
?>

<div style="margin-bottom: 24px;">
    <h1 style="font-size: 1.35rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">Merchant Profile &amp; Security</h1>
    <p style="font-size: 0.84rem; color: var(--text-muted); margin: 0;">
        Manage your business contact information and merchant portal credentials.
    </p>
</div>

<?php if (!empty($feedbackMessage)): ?>
    <div class="alert <?= $feedbackType === 'success' ? 'alert-success' : 'alert-error' ?>">
        <?= htmlspecialchars($feedbackMessage) ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; align-items: start;">
    <!-- Profile Card -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <h3 class="card-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                Business Profile
            </h3>
        </div>
        <div class="card-body" style="padding: 24px;">
            <form method="POST" action="settings.php">
                <input type="hidden" name="action" value="update_profile">

                <div class="form-group">
                    <label class="form-label">Client ID</label>
                    <input type="text" class="form-control font-mono" value="#<?= (int)$clientId ?>" readonly style="background: #f8fafc; font-weight: 700;">
                </div>

                <div class="form-group">
                    <label class="form-label">Merchant / Business Name</label>
                    <input type="text" name="client_name" class="form-control" value="<?= htmlspecialchars($currentClient['client_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Contact / Notifications Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($currentClient['email'] ?? '') ?>" placeholder="merchant@business.com">
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                        Used for critical gateway notifications and password sign-in.
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 24px;">
                    <label class="form-label">Account Status</label>
                    <div>
                        <?php if ((int)$currentClient['is_active'] === 1): ?>
                            <span class="badge badge-success">ACTIVE &amp; APPROVED</span>
                        <?php else: ?>
                            <span class="badge badge-failed">SUSPENDED</span>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Save Profile Changes</button>
            </form>
        </div>
    </div>

    <!-- Security & Password Card -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <h3 class="card-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                Portal Password
            </h3>
        </div>
        <div class="card-body" style="padding: 24px;">
            <form method="POST" action="settings.php">
                <input type="hidden" name="action" value="update_password">

                <?php if ($hasExistingPassword): ?>
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required placeholder="••••••••">
                    </div>
                <?php else: ?>
                    <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: var(--radius-sm); padding: 12px; margin-bottom: 18px; font-size: 0.82rem; color: #1e40af;">
                        💡 You currently log in using your API credentials. Set a password below to enable quick email/password login.
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control" minlength="6" required placeholder="Minimum 6 characters">
                </div>

                <div class="form-group" style="margin-bottom: 24px;">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" minlength="6" required placeholder="Re-type new password">
                </div>

                <button type="submit" class="btn btn-primary"><?= $hasExistingPassword ? 'Update Password' : 'Set Password' ?></button>
            </form>
        </div>
    </div>
</div>

<!-- Real-time Alerts & Notification Channels Card -->
<div class="card" style="margin-top: 24px; margin-bottom: 0;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
            <h3 class="card-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                Real-Time Alerts &amp; Notification Channels
            </h3>
            <div class="card-subtitle">Receive instant Email and SMS alerts when payments succeed, refunds are issued, or disputes occur</div>
        </div>
        <form method="POST" action="settings.php" style="margin: 0;">
            <input type="hidden" name="action" value="send_test_alert">
            <button type="submit" class="btn btn-secondary" style="font-size: 0.78rem; padding: 7px 14px; display: inline-flex; align-items: center; gap: 6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                Send Test Alert
            </button>
        </form>
    </div>
    <div class="card-body" style="padding: 24px;">
        <form method="POST" action="settings.php">
            <input type="hidden" name="action" value="update_notifications">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 24px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 4px;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        Notification Destination Email
                    </label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($currentClient['email'] ?? '') ?>" placeholder="alerts@merchant.com">
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">Dispatched via Google Accounts SMTP Mailer</div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 4px;"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
                        SMS Alert Mobile Phone
                    </label>
                    <input type="text" name="phone" class="form-control font-mono" value="<?= htmlspecialchars($currentClient['phone'] ?? '') ?>" placeholder="+919876543210">
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">Include international prefix (e.g. +91 for India)</div>
                </div>
            </div>

            <div style="margin-bottom: 24px;">
                <label class="form-label" style="margin-bottom: 10px;">Event Alert Subscriptions</label>
                <div style="border: 1px solid var(--border); border-radius: var(--radius-sm); overflow: hidden;">
                    <table class="portal-table" style="margin: 0;">
                        <thead>
                            <tr>
                                <th style="width: 50%;">Event / Trigger</th>
                                <th style="text-align: center; width: 25%;">Email Notification</th>
                                <th style="text-align: center; width: 25%;">SMS Notification</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: var(--text-main); font-size: 0.85rem;">Payment Success / Captured</div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">Real-time alert when a checkout payment is authorized and captured</div>
                                </td>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="notify_email_payment" value="1" <?= (int)($currentClient['notify_email_payment'] ?? 1) === 1 ? 'checked' : '' ?> style="width: 17px; height: 17px; accent-color: var(--primary); cursor: pointer;">
                                </td>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="notify_sms_payment" value="1" <?= (int)($currentClient['notify_sms_payment'] ?? 0) === 1 ? 'checked' : '' ?> style="width: 17px; height: 17px; accent-color: var(--primary); cursor: pointer;">
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: var(--text-main); font-size: 0.85rem;">Refund Processed</div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">Alert when a full or partial refund is successfully initiated</div>
                                </td>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="notify_email_refund" value="1" <?= (int)($currentClient['notify_email_refund'] ?? 1) === 1 ? 'checked' : '' ?> style="width: 17px; height: 17px; accent-color: var(--primary); cursor: pointer;">
                                </td>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="notify_sms_refund" value="1" <?= (int)($currentClient['notify_sms_refund'] ?? 1) === 1 ? 'checked' : '' ?> style="width: 17px; height: 17px; accent-color: var(--primary); cursor: pointer;">
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: var(--text-main); font-size: 0.85rem;">Payment Failed / Chargeback Dispute</div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">Urgent alert when a bank dispute or customer payment drop-off occurs</div>
                                </td>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="notify_email_dispute" value="1" <?= (int)($currentClient['notify_email_dispute'] ?? 1) === 1 ? 'checked' : '' ?> style="width: 17px; height: 17px; accent-color: var(--primary); cursor: pointer;">
                                </td>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="notify_sms_dispute" value="1" <?= (int)($currentClient['notify_sms_dispute'] ?? 1) === 1 ? 'checked' : '' ?> style="width: 17px; height: 17px; accent-color: var(--primary); cursor: pointer;">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Save Alert Preferences</button>
        </form>
    </div>
</div>

<?php
$clientNotificationLogs = NotificationService::getClientNotificationLogs($clientId, 10);
?>
<!-- Recent Dispatched Notifications Log -->
<div class="card" style="margin-top: 24px; margin-bottom: 0;">
    <div class="card-header">
        <h3 class="card-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3zm-8.27 4a2 2 0 0 1-3.46 0"></path></svg>
            Recent Dispatched Alerts
        </h3>
        <div class="card-subtitle">Real-time log of Email and SMS notifications dispatched to your endpoints</div>
    </div>
    <div class="table-responsive">
        <table class="portal-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Channel</th>
                    <th>Event Type</th>
                    <th>Recipient</th>
                    <th>Subject / Content</th>
                    <th>Delivery Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clientNotificationLogs)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 28px 16px; color: var(--text-muted);">
                            No notification alerts dispatched yet. Click "Send Test Alert" to verify your delivery channels.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($clientNotificationLogs as $notif): ?>
                        <tr>
                            <td style="font-size: 0.78rem; color: var(--text-muted); white-space: nowrap;">
                                <?= date('Y-m-d H:i:s', strtotime($notif['created_at'])) ?>
                            </td>
                            <td>
                                <?php if ($notif['channel'] === 'email'): ?>
                                    <span class="badge" style="background: #e0f2fe; color: #0369a1; font-weight: 700;">EMAIL</span>
                                <?php else: ?>
                                    <span class="badge" style="background: #fef3c7; color: #92400e; font-weight: 700;">SMS</span>
                                <?php endif; ?>
                            </td>
                            <td class="font-mono" style="font-size: 0.78rem;">
                                <?= htmlspecialchars($notif['event_type']) ?>
                            </td>
                            <td style="font-size: 0.82rem; font-weight: 500;">
                                <?= htmlspecialchars($notif['recipient']) ?>
                            </td>
                            <td style="font-size: 0.82rem; color: var(--text-main); max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= htmlspecialchars($notif['subject'] ?: $notif['content']) ?>
                            </td>
                            <td>
                                <?php if ($notif['status'] === 'SENT'): ?>
                                    <span class="badge badge-success">DELIVERED</span>
                                <?php elseif ($notif['status'] === 'SIMULATED'): ?>
                                    <span class="badge" style="background: #e2e8f0; color: #334155;">SIMULATED</span>
                                <?php else: ?>
                                    <span class="badge badge-failed" title="<?= htmlspecialchars($notif['error_message'] ?? '') ?>">FAILED</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$clientAuditLogs = AuditLogger::getClientLogs($clientId, 15);
?>
<!-- Account Security & Activity Audit Log -->
<div class="card" style="margin-top: 24px; margin-bottom: 0;">
    <div class="card-header">
        <h3 class="card-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
            Security &amp; Activity Audit Log
        </h3>
        <div class="card-subtitle">Transparent record of administrative actions, credential updates, and webhook mutations affecting your account</div>
    </div>
    <div class="table-responsive">
        <table class="portal-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Action</th>
                    <th>Actor (Who)</th>
                    <th>Summary</th>
                    <th>Network Context</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clientAuditLogs)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 28px 16px; color: var(--text-muted);">
                            No governance or mutation events recorded for this account yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($clientAuditLogs as $log): ?>
                        <tr>
                            <td style="font-size: 0.78rem; color: var(--text-muted); white-space: nowrap;">
                                <?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?>
                            </td>
                            <td>
                                <?= AuditLogger::getActionBadge($log['action']) ?>
                            </td>
                            <td>
                                <span style="font-weight: 600; font-size: 0.82rem;"><?= htmlspecialchars($log['actor_username'] ?: 'System') ?></span>
                                <span class="badge" style="font-size: 0.68rem; margin-left: 4px; background: #f1f5f9; color: #475569;">
                                    <?= htmlspecialchars(strtoupper($log['actor_role'] ?: $log['actor_type'])) ?>
                                </span>
                            </td>
                            <td style="font-size: 0.82rem; color: var(--text-main);">
                                <?= htmlspecialchars($log['description'] ?: '–') ?>
                            </td>
                            <td class="font-mono" style="font-size: 0.76rem; color: var(--text-muted); white-space: nowrap;">
                                <?= htmlspecialchars($log['ip_address'] ?: '127.0.0.1') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
