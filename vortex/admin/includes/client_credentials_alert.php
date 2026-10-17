<?php
/**
 * ------------------------------------------------------------
 * client_credentials_alert.php
 * ------------------------------------------------------------
 * Reusable component for displaying newly generated API client
 * credentials or rotated webhook secrets with one-click copy.
 * ------------------------------------------------------------
 */
?>

<?php if (!empty($createdClientDetails)): ?>
    <div class="alert alert-warning" style="border: 2px dashed #b45309; margin-bottom: 24px;">
        <h3 style="margin-bottom: 8px; color: #78350f;">🔑 New API Client Generated!</h3>
        <p style="margin-bottom: 12px; font-size: 0.9rem; color: #92400e;">
            Please copy and store these API credentials securely in a password manager. <strong>The API secrets will not be shown again.</strong>
        </p>
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Client Name</span>
                <span class="detail-value" style="font-weight: 700;"><?= htmlspecialchars($createdClientDetails['client_name']) ?></span>
            </div>
            <div class="detail-item"></div>
            <div class="detail-item">
                <span class="detail-label">LIVE API Key</span>
                <div class="copy-wrapper" style="margin-top: 4px;">
                    <span class="detail-value font-mono"><?= htmlspecialchars($createdClientDetails['api_key']) ?></span>
                    <button type="button" class="btn-copy" onclick="copyToClipboard('<?= htmlspecialchars($createdClientDetails['api_key'], ENT_QUOTES) ?>', this)" title="Copy LIVE API Key">📋 Copy</button>
                </div>
            </div>
            <div class="detail-item">
                <span class="detail-label">TEST API Key</span>
                <div class="copy-wrapper" style="margin-top: 4px;">
                    <span class="detail-value font-mono"><?= htmlspecialchars($createdClientDetails['test_api_key'] ?? 'N/A') ?></span>
                    <button type="button" class="btn-copy" onclick="copyToClipboard('<?= htmlspecialchars($createdClientDetails['test_api_key'] ?? '', ENT_QUOTES) ?>', this)" title="Copy TEST API Key">📋 Copy</button>
                </div>
            </div>
            <div class="detail-item">
                <span class="detail-label">LIVE API Secret</span>
                <div class="copy-wrapper" style="margin-top: 4px;">
                    <span class="detail-value font-mono" style="color: var(--danger-text); font-weight: 700;"><?= htmlspecialchars($createdClientDetails['api_secret']) ?></span>
                    <button type="button" class="btn-copy" onclick="copyToClipboard('<?= htmlspecialchars($createdClientDetails['api_secret'], ENT_QUOTES) ?>', this)" title="Copy LIVE API Secret">📋 Copy</button>
                </div>
            </div>
            <div class="detail-item">
                <span class="detail-label">TEST API Secret</span>
                <div class="copy-wrapper" style="margin-top: 4px;">
                    <span class="detail-value font-mono" style="color: var(--danger-text); font-weight: 700;"><?= htmlspecialchars($createdClientDetails['test_api_secret'] ?? 'N/A') ?></span>
                    <button type="button" class="btn-copy" onclick="copyToClipboard('<?= htmlspecialchars($createdClientDetails['test_api_secret'] ?? '', ENT_QUOTES) ?>', this)" title="Copy TEST API Secret">📋 Copy</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($newWebhookSecret)): ?>
    <div class="alert alert-warning" style="border: 2px dashed #b45309; margin-bottom: 24px;">
        <h3 style="margin-bottom: 8px; color: #78350f;">🔐 New Webhook Secret Generated for Client #<?= (int)$newWebhookSecret['client_id'] ?>!</h3>
        <p style="margin-bottom: 10px; font-size: 0.9rem; color: #92400e;">
            Share this secret with the client to verify incoming webhook signatures.
            <strong>It will not be displayed again.</strong>
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
