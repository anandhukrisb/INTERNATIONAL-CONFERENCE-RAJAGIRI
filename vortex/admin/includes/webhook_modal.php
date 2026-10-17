<?php
/**
 * ------------------------------------------------------------
 * webhook_modal.php
 * ------------------------------------------------------------
 * Reusable modal component for configuring Client Webhook URLs
 * and cryptographic HMAC secrets.
 * ------------------------------------------------------------
 */

$formTarget = $webhookFormAction ?? 'index.php';
?>
<!-- Webhook Configure Modal -->
<div class="modal-overlay" id="webhookModal" role="dialog" aria-modal="true" aria-labelledby="webhookModalTitle">
    <div class="modal-box" style="max-width: 520px;">
        <div class="modal-header">
            <h2 id="webhookModalTitle">🔗 Configure Webhook Endpoint</h2>
            <button class="modal-close" onclick="closeWebhookModal()" title="Close">&times;</button>
        </div>
        <form action="<?= htmlspecialchars($formTarget) ?>" method="POST" id="webhookForm">
            <input type="hidden" name="action" value="update_webhook">
            <input type="hidden" name="client_id" id="webhookClientId">
            <div class="modal-body">
                <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 18px; line-height: 1.45;">
                    Set the HTTPS URL where Rpay will POST real-time payment notifications. The webhook secret is used by the merchant to verify HMAC-SHA256 signatures.
                </p>
                <div class="form-group">
                    <label for="webhookUrlInput">Webhook URL</label>
                    <input type="url" id="webhookUrlInput" name="webhook_url" class="form-control font-mono"
                           placeholder="https://merchant.example.com/api/rpay_webhook">
                </div>
                <div class="form-group" style="margin-top: 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label for="webhookSecretInput" style="margin: 0;">Webhook Secret</label>
                        <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 0.75rem;" onclick="generateRandomWebhookSecret()">🎲 Generate New</button>
                    </div>
                    <div class="copy-wrapper">
                        <input type="text" id="webhookSecretInput" name="webhook_secret" class="form-control font-mono" style="width: 100%; font-size: 0.82rem;"
                               placeholder="Leave as-is or click Generate New">
                        <button type="button" class="btn-copy btn-copy-sm" onclick="copyToClipboard(document.getElementById('webhookSecretInput').value, this)" title="Copy Secret">📋</button>
                    </div>
                </div>
                <div id="modalPingResult" style="display: none; margin-top: 12px; font-size: 0.82rem; padding: 10px 14px; border-radius: 6px; border: 1px solid transparent;"></div>

                <p style="font-size: 0.82rem; color: var(--text-muted); margin-top: 12px;">
                    💡 Leave the URL blank and save to <strong>remove</strong> webhook configuration.
                </p>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
                <button type="button" class="btn btn-secondary btn-sm" id="btnModalPing" onclick="testModalWebhook()">⚡ Test Ping</button>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn-cancel" onclick="closeWebhookModal()">Cancel</button>
                    <button type="submit" class="btn-submit-event">💾 Save Webhook</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openWebhookModal(clientId, currentUrl, currentSecret) {
    document.getElementById('webhookClientId').value    = clientId;
    document.getElementById('webhookUrlInput').value    = currentUrl || '';
    document.getElementById('webhookSecretInput').value = currentSecret || '';
    const resBox = document.getElementById('modalPingResult');
    if (resBox) {
        resBox.style.display = 'none';
        resBox.innerHTML = '';
    }
    document.getElementById('webhookModal').classList.add('open');
    document.body.style.overflow = 'hidden';
    document.getElementById('webhookUrlInput').focus();
}

function testModalWebhook() {
    const url = document.getElementById('webhookUrlInput').value.trim();
    const secret = document.getElementById('webhookSecretInput').value.trim();
    const resBox = document.getElementById('modalPingResult');
    const btn = document.getElementById('btnModalPing');

    if (!url) {
        alert('Please enter a Webhook URL to test.');
        document.getElementById('webhookUrlInput').focus();
        return;
    }

    btn.disabled = true;
    btn.textContent = '⏳ Testing...';
    resBox.style.display = 'block';
    resBox.style.background = '#f8fafc';
    resBox.style.borderColor = '#cbd5e1';
    resBox.style.color = '#475569';
    resBox.textContent = 'Sending signed test ping to endpoint...';

    const endpoint = window.location.pathname.includes('/api_clients/') ? 'test_webhook_ajax.php' : 'api_clients/test_webhook_ajax.php';

    fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ webhook_url: url, webhook_secret: secret })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = '⚡ Test Ping';
        if (data.success) {
            resBox.style.background = '#ecfdf5';
            resBox.style.borderColor = '#a7f3d0';
            resBox.style.color = '#065f46';
            resBox.innerHTML = `<strong>✅ Verified:</strong> HTTP ${data.http_code} (${data.response_time_ms}ms) &bull; ${data.primary_ip || ''}`;
        } else {
            resBox.style.background = '#fef2f2';
            resBox.style.borderColor = '#fecaca';
            resBox.style.color = '#991b1b';
            resBox.innerHTML = `<strong>❌ Failed:</strong> ${data.message} ${data.http_code ? '(HTTP ' + data.http_code + ')' : ''}`;
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.textContent = '⚡ Test Ping';
        resBox.style.background = '#fef2f2';
        resBox.style.borderColor = '#fecaca';
        resBox.style.color = '#991b1b';
        resBox.textContent = 'Diagnostic request error: ' + err.message;
    });
}

function generateRandomWebhookSecret() {
    const arr = new Uint8Array(24);
    window.crypto.getRandomValues(arr);
    const hex = Array.from(arr, b => b.toString(16).padStart(2, '0')).join('');
    document.getElementById('webhookSecretInput').value = hex;
}

function closeWebhookModal() {
    const modal = document.getElementById('webhookModal');
    if (modal) {
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }
}

(function() {
    const modal = document.getElementById('webhookModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) closeWebhookModal();
        });
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('open')) closeWebhookModal();
    });
})();
</script>
