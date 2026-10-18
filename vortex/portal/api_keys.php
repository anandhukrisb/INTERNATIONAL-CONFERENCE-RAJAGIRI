<?php
/**
 * ------------------------------------------------------------
 * api_keys.php – Merchant Portal API Credentials & Developer Hub
 * ------------------------------------------------------------
 * Project : PayVortex Unified Payment Gateway
 * ------------------------------------------------------------
 */

$pageTitle = 'API Keys & Integration';
$activeNav  = 'api_keys';

require_once __DIR__ . '/includes/header.php';

$apiKey      = $currentClient['api_key'] ?? '';
$apiSecret   = $currentClient['api_secret'] ?? '';
$testApiKey  = $currentClient['test_api_key'] ?? '';
$testApiSecret = $currentClient['test_api_secret'] ?? '';

// Determine server base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
$baseUrl = $protocol . $host . '/vortex';
?>

<div style="margin-bottom: 24px;">
    <h1 style="font-size: 1.35rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">Developer Credentials &amp; Quickstart</h1>
    <p style="font-size: 0.84rem; color: var(--text-muted); margin: 0;">
        Access your cryptographic API keys and ready-to-run code snippets to integrate PayVortex into your application.
    </p>
</div>

<!-- API Keys Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; margin-bottom: 28px;">
    <!-- Live Mode Credentials -->
    <div class="card" style="margin-bottom: 0; border-top: 3px solid var(--success);">
        <div class="card-header">
            <div>
                <h3 class="card-title">
                    <span class="badge" style="background: #dcfce7; color: #15803d;">LIVE ENVIRONMENT</span>
                    Production Credentials
                </h3>
                <div class="card-subtitle">Use these credentials to process real payments</div>
            </div>
        </div>
        <div class="card-body" style="padding: 20px 24px;">
            <!-- Live API Key -->
            <div class="form-group">
                <label class="form-label" style="display: flex; justify-content: space-between;">
                    <span>Live API Key (Public)</span>
                    <span style="font-size: 0.72rem; color: var(--text-muted);">Required in HTTP Header / Payload</span>
                </label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" class="form-control font-mono" value="<?= htmlspecialchars($apiKey) ?>" readonly id="liveApiKeyInput">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="copyToClipboard('<?= htmlspecialchars($apiKey) ?>', this)">Copy</button>
                </div>
            </div>

            <!-- Live API Secret -->
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: flex; justify-content: space-between;">
                    <span>Live API Secret (Private)</span>
                    <span style="font-size: 0.72rem; color: var(--danger); font-weight: 600;">Keep strictly confidential</span>
                </label>
                <div style="display: flex; gap: 8px;">
                    <input type="password" class="form-control font-mono" value="<?= htmlspecialchars($apiSecret) ?>" readonly id="liveApiSecretInput">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="toggleSecretVisibility('liveApiSecretInput', this)">Reveal</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="copyToClipboard('<?= htmlspecialchars($apiSecret) ?>', this)">Copy</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Test / Sandbox Credentials -->
    <div class="card" style="margin-bottom: 0; border-top: 3px solid var(--warning);">
        <div class="card-header">
            <div>
                <h3 class="card-title">
                    <span class="badge" style="background: #fef3c7; color: #b45309;">TEST ENVIRONMENT</span>
                    Sandbox / Test Credentials
                </h3>
                <div class="card-subtitle">Use in local development &amp; automated test suites</div>
            </div>
        </div>
        <div class="card-body" style="padding: 20px 24px;">
            <!-- Test API Key -->
            <div class="form-group">
                <label class="form-label" style="display: flex; justify-content: space-between;">
                    <span>Test API Key</span>
                    <span style="font-size: 0.72rem; color: var(--text-muted);">Simulates payment flows safely</span>
                </label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" class="form-control font-mono" value="<?= htmlspecialchars($testApiKey) ?>" readonly id="testApiKeyInput">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="copyToClipboard('<?= htmlspecialchars($testApiKey) ?>', this)">Copy</button>
                </div>
            </div>

            <!-- Test API Secret -->
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: flex; justify-content: space-between;">
                    <span>Test API Secret</span>
                    <span style="font-size: 0.72rem; color: var(--text-muted);">For local signature verification</span>
                </label>
                <div style="display: flex; gap: 8px;">
                    <input type="password" class="form-control font-mono" value="<?= htmlspecialchars($testApiSecret) ?>" readonly id="testApiSecretInput">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="toggleSecretVisibility('testApiSecretInput', this)">Reveal</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="copyToClipboard('<?= htmlspecialchars($testApiSecret) ?>', this)">Copy</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Integration Code Quickstart Section -->
<div class="card" id="docs">
    <div class="card-header">
        <h3 class="card-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
            Integration Quickstart
        </h3>
        <div class="card-subtitle">Ready-to-use code snippets with your active merchant credentials</div>
    </div>
    <div class="card-body" style="padding: 24px;">
        <!-- Step 1: Create Order -->
        <div style="margin-bottom: 28px;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                <span style="background: var(--primary); color: white; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 0.78rem; font-weight: 700;">1</span>
                <h4 style="font-size: 0.96rem; font-weight: 700; color: var(--text-main); margin: 0;">Create a Payment Order (Server-to-Server)</h4>
            </div>
            <p style="font-size: 0.84rem; color: var(--text-muted); margin-bottom: 12px;">
                Call <code class="font-mono" style="color: var(--primary); background: #eff6ff; padding: 2px 6px; border-radius: 4px;"><?= $baseUrl ?>/api/v1/create_order.php</code> from your server backend:
            </p>

            <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                <button type="button" class="btn btn-secondary btn-sm tab-btn active" onclick="switchSnippetTab('curl', this)">cURL</button>
                <button type="button" class="btn btn-secondary btn-sm tab-btn" onclick="switchSnippetTab('php', this)">PHP</button>
                <button type="button" class="btn btn-secondary btn-sm tab-btn" onclick="switchSnippetTab('node', this)">Node.js</button>
            </div>

            <!-- cURL snippet -->
            <div id="tab-curl" class="snippet-content">
                <div class="code-box">
<pre>curl -X POST "<?= $baseUrl ?>/api/v1/create_order.php" \
  -H "Content-Type: application/json" \
  -H "X-Api-Key: <?= htmlspecialchars($apiKey ?: 'YOUR_API_KEY') ?>" \
  -H "X-Api-Secret: <?= htmlspecialchars($apiSecret ?: 'YOUR_API_SECRET') ?>" \
  -d '{
    "amount": 500.00,
    "currency": "INR",
    "customer_name": "Jane Doe",
    "customer_email": "jane@example.com",
    "customer_mobile": "9876543210",
    "is_test_mode": 1
  }'</pre>
                </div>
            </div>

            <!-- PHP snippet -->
            <div id="tab-php" class="snippet-content" style="display: none;">
                <div class="code-box">
<pre>&lt;?php
$payload = [
    'amount'          => 500.00,
    'currency'        => 'INR',
    'customer_name'   => 'Jane Doe',
    'customer_email'  => 'jane@example.com',
    'customer_mobile' => '9876543210',
    'is_test_mode'    => 1 // 1 for Test, 0 for Live
];

$ch = curl_init('<?= $baseUrl ?>/api/v1/create_order.php');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-Api-Key: <?= htmlspecialchars($apiKey ?: 'YOUR_API_KEY') ?>',
        'X-Api-Secret: <?= htmlspecialchars($apiSecret ?: 'YOUR_API_SECRET') ?>'
    ]
]);

$response = curl_exec($ch);
$order = json_decode($response, true);
// $order['data']['razorpay_order_id']
// $order['data']['vortex_transaction_id']
?&gt;</pre>
                </div>
            </div>

            <!-- Node.js snippet -->
            <div id="tab-node" class="snippet-content" style="display: none;">
                <div class="code-box">
<pre>const response = await fetch('<?= $baseUrl ?>/api/v1/create_order.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-Api-Key': '<?= htmlspecialchars($apiKey ?: 'YOUR_API_KEY') ?>',
    'X-Api-Secret': '<?= htmlspecialchars($apiSecret ?: 'YOUR_API_SECRET') ?>'
  },
  body: JSON.stringify({
    amount: 500.00,
    currency: 'INR',
    customer_name: 'Jane Doe',
    customer_email: 'jane@example.com',
    customer_mobile: '9876543210',
    is_test_mode: 1
  })
});

const data = await response.json();
console.log('Vortex Order:', data);</pre>
                </div>
            </div>
        </div>

        <!-- Step 2: Signature Verification -->
        <div>
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                <span style="background: var(--primary); color: white; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 0.78rem; font-weight: 700;">2</span>
                <h4 style="font-size: 0.96rem; font-weight: 700; color: var(--text-main); margin: 0;">Verify Payment Callback Signature</h4>
            </div>
            <p style="font-size: 0.84rem; color: var(--text-muted); margin-bottom: 12px;">
                When Razorpay completes the checkout, verify the cryptographic HMAC SHA-256 signature on your backend before delivering goods:
            </p>
            <div class="code-box">
<pre>&lt;?php
$expectedSignature = hash_hmac(
    'sha256',
    $razorpayOrderId . '|' . $razorpayPaymentId,
    '<?= htmlspecialchars($apiSecret ?: 'YOUR_API_SECRET') ?>'
);

if (hash_equals($expectedSignature, $razorpaySignature)) {
    // Payment verified and authentic!
} else {
    // Signature mismatch - possible tampering
}
?&gt;</pre>
            </div>
        </div>
    </div>
</div>

<script>
function toggleSecretVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerText = 'Hide';
    } else {
        input.type = 'password';
        btn.innerText = 'Reveal';
    }
}

function switchSnippetTab(tabName, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.snippet-content').forEach(c => c.style.display = 'none');
    
    btn.classList.add('active');
    const target = document.getElementById('tab-' + tabName);
    if (target) target.style.display = 'block';
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
