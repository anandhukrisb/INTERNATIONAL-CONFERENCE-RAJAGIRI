<?php
/**
 * ------------------------------------------------------------
 * webhooks.php – Merchant Event Webhook Configuration & Dispatcher
 * ------------------------------------------------------------
 * Project : PayVortex Unified Payment Gateway
 * ------------------------------------------------------------
 * Webhooks are configured strictly per-event on the events table.
 * ------------------------------------------------------------
 */

$pageTitle = 'Event Webhooks';
$activeNav  = 'webhooks';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../../classes/Event.php';

$feedbackMessage = '';
$feedbackType    = '';
$testPingResult  = null;

$eventModel = new Event();

// Retrieve events associated with this client (via transactions, matching name, or fallback to all active events)
$clientName = $currentClient['client_name'] ?? '';
$db = $db ?? Database::getInstance()->getConnection();
$eventStmt = $db->prepare("
    SELECT DISTINCT e.id, e.event_id, e.event_name, e.department, e.webhook_url, e.webhook_secret, e.is_active
    FROM events e
    WHERE e.event_id IN (SELECT DISTINCT event_id FROM transactions WHERE api_client_id = ?)
       OR LOWER(e.event_name) = LOWER(?)
    ORDER BY e.id ASC
");
$eventStmt->execute([$clientId, $clientName]);
$events = $eventStmt->fetchAll(\PDO::FETCH_ASSOC);

if (empty($events)) {
    // If no direct association found, load active events so client can manage their target event
    $allEventsStmt = $db->query("SELECT id, event_id, event_name, department, webhook_url, webhook_secret, is_active FROM events WHERE is_active = 1 ORDER BY id ASC");
    $events = $allEventsStmt->fetchAll(\PDO::FETCH_ASSOC);
}

// Determine active selected event
$selectedEventId = trim((string)($_REQUEST['event_id'] ?? ''));
$selectedEvent = null;

if (!empty($selectedEventId)) {
    foreach ($events as $ev) {
        if ($ev['event_id'] === $selectedEventId) {
            $selectedEvent = $ev;
            break;
        }
    }
}

if (!$selectedEvent && !empty($events)) {
    $selectedEvent = $events[0];
    $selectedEventId = $selectedEvent['event_id'];
}

// Handle Form Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selectedEvent) {
    $action = $_POST['action'] ?? '';
    $postEventId = trim((string)($_POST['event_id'] ?? $selectedEventId));

    if ($action === 'save_webhook') {
        $webhookUrl = trim($_POST['webhook_url'] ?? '');
        $webhookSecret = trim($_POST['webhook_secret'] ?? '');

        $result = $eventModel->updateWebhook($postEventId, $webhookUrl, $webhookSecret ?: null);
        if ($result['success']) {
            $feedbackMessage = $result['message'];
            $feedbackType = 'success';
        } else {
            $feedbackMessage = $result['message'];
            $feedbackType = 'error';
        }
    } elseif ($action === 'rotate_secret') {
        $newSecret = bin2hex(random_bytes(24));
        $currentUrl = $selectedEvent['webhook_url'] ?? '';
        $result = $eventModel->updateWebhook($postEventId, $currentUrl, $newSecret);
        if ($result['success']) {
            $feedbackMessage = 'Webhook secret rotated successfully for event: ' . htmlspecialchars($postEventId);
            $feedbackType = 'success';
        } else {
            $feedbackMessage = $result['message'];
            $feedbackType = 'error';
        }
    } elseif ($action === 'test_ping') {
        $destUrl = trim($selectedEvent['webhook_url'] ?? '');
        $sec = trim($selectedEvent['webhook_secret'] ?? '');

        if (empty($destUrl)) {
            $feedbackMessage = 'Please configure and save a valid Webhook URL for this event before testing.';
            $feedbackType = 'error';
        } else {
            $mockPayload = json_encode([
                'event'     => 'payment.captured',
                'timestamp' => time(),
                'client_id' => $clientId,
                'data'      => [
                    'vortex_transaction_id' => 'vtx_sim_' . bin2hex(random_bytes(6)),
                    'event_id'              => $postEventId,
                    'event_name'            => $selectedEvent['event_name'] ?? 'Event',
                    'razorpay_order_id'    => 'order_sim_' . bin2hex(random_bytes(5)),
                    'razorpay_payment_id'  => 'pay_sim_' . bin2hex(random_bytes(6)),
                    'amount'                => 1250.00,
                    'currency'              => 'INR',
                    'status'                => 'SUCCESS',
                    'customer_email'        => 'merchant_test@payvortex.io',
                    'is_test_mode'          => 1
                ]
            ], JSON_PRETTY_PRINT);

            $signature = hash_hmac('sha256', $mockPayload, $sec);

            $startTime = microtime(true);
            $ch = curl_init($destUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $mockPayload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'X-Vortex-Event: payment.captured',
                    'X-Vortex-Event-Id: ' . $postEventId,
                    'X-Vortex-Signature: ' . $signature,
                    'User-Agent: PayVortex-Webhook-Dispatcher/2.0'
                ]
            ]);

            $responseBody = curl_exec($ch);
            $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError    = curl_error($ch);
            $durationMs   = round((microtime(true) - $startTime) * 1000);
            curl_close($ch);

            $testPingResult = [
                'url'         => $destUrl,
                'http_code'   => $httpCode,
                'duration_ms' => $durationMs,
                'payload'     => $mockPayload,
                'response'    => $responseBody,
                'error'       => $curlError,
                'success'     => ($httpCode >= 200 && $httpCode < 300)
            ];
        }
    }

    // Refresh selected event data from DB after updates
    $refStmt = $db->prepare("SELECT id, event_id, event_name, department, webhook_url, webhook_secret, is_active FROM events WHERE event_id = ? LIMIT 1");
    $refStmt->execute([$selectedEventId]);
    $refreshed = $refStmt->fetch(\PDO::FETCH_ASSOC);
    if ($refreshed) {
        $selectedEvent = $refreshed;
    }
}

$webhookUrl    = $selectedEvent['webhook_url'] ?? '';
$webhookSecret = $selectedEvent['webhook_secret'] ?? '';
?>

<div style="margin-bottom: 24px;">
    <h1 style="font-size: 1.35rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">Event Webhook Configuration</h1>
    <p style="font-size: 0.84rem; color: var(--text-muted); margin: 0;">
        Receive automatic HTTP callbacks in real-time when payments succeed, fail, or get refunded for your events.
    </p>
</div>

<?php if (!empty($feedbackMessage)): ?>
    <div class="alert <?= $feedbackType === 'success' ? 'alert-success' : 'alert-error' ?>" style="margin-bottom: 20px;">
        <?= htmlspecialchars($feedbackMessage) ?>
    </div>
<?php endif; ?>

<?php if (empty($events)): ?>
    <div class="card" style="padding: 32px; text-align: center;">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color: var(--text-muted); margin-bottom: 12px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
        <h3 style="font-size: 1.1rem; color: var(--text-main); margin-bottom: 6px;">No Events Found</h3>
        <p style="font-size: 0.84rem; color: var(--text-muted); margin-bottom: 0;">
            No events are currently associated with your merchant account. Create an event via the API or admin panel to configure webhooks.
        </p>
    </div>
<?php else: ?>

<!-- Event Selector Banner -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
        <div>
            <div style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px;">Target Event</div>
            <div style="font-size: 1.05rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                <span><?= htmlspecialchars($selectedEvent['event_name'] ?? 'Event') ?></span>
                <span class="font-mono badge badge-primary" style="font-size: 0.76rem;"><?= htmlspecialchars($selectedEvent['event_id'] ?? '') ?></span>
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">
                Department: <?= htmlspecialchars($selectedEvent['department'] ?? 'General') ?>
            </div>
        </div>
        <?php if (count($events) > 1): ?>
        <div style="display: flex; align-items: center; gap: 8px;">
            <label style="font-size: 0.82rem; font-weight: 600; color: var(--text-muted); margin: 0;">Select Event:</label>
            <select class="form-control" style="width: auto; padding: 6px 12px; font-size: 0.85rem;" onchange="window.location.href='webhooks.php?event_id=' + encodeURIComponent(this.value);">
                <?php foreach ($events as $ev): ?>
                    <option value="<?= htmlspecialchars($ev['event_id']) ?>" <?= $ev['event_id'] === $selectedEventId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ev['event_name']) ?> (<?= htmlspecialchars($ev['event_id']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Test Ping Result Banner -->
<?php if ($testPingResult !== null): ?>
    <div class="card" style="border-left: 4px solid <?= $testPingResult['success'] ? 'var(--success)' : 'var(--danger)' ?>; margin-bottom: 24px;">
        <div class="card-header" style="padding: 14px 20px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="badge <?= $testPingResult['success'] ? 'badge-success' : 'badge-failed' ?>">
                    HTTP <?= $testPingResult['http_code'] ?: '0 (Timeout/Error)' ?>
                </span>
                <span style="font-weight: 700; font-size: 0.9rem;">
                    Test Webhook Dispatch <?= $testPingResult['success'] ? 'Succeeded' : 'Failed' ?>
                </span>
                <span style="font-size: 0.78rem; color: var(--text-muted);">(<?= $testPingResult['duration_ms'] ?> ms)</span>
            </div>
        </div>
        <div class="card-body" style="padding: 18px 20px;">
            <?php if (!empty($testPingResult['error'])): ?>
                <div style="color: var(--danger); font-size: 0.84rem; margin-bottom: 12px; font-weight: 600;">
                    Network / Transport Error: <?= htmlspecialchars($testPingResult['error']) ?>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div>
                    <div style="font-size: 0.76rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px;">Sent Mock Payload</div>
                    <div class="code-box" style="margin-top: 0; max-height: 180px;">
                        <pre><?= htmlspecialchars($testPingResult['payload']) ?></pre>
                    </div>
                </div>
                <div>
                    <div style="font-size: 0.76rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px;">Your Server Response</div>
                    <div class="code-box" style="margin-top: 0; max-height: 180px;">
                        <pre><?= htmlspecialchars($testPingResult['response'] ?: '(No response body)') ?></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 24px; align-items: start;">
    <!-- Settings Form -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <h3 class="card-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                Event Webhook Endpoint Settings
            </h3>
        </div>
        <div class="card-body" style="padding: 24px;">
            <form method="POST" action="webhooks.php">
                <input type="hidden" name="action" value="save_webhook">
                <input type="hidden" name="event_id" value="<?= htmlspecialchars($selectedEventId) ?>">

                <div class="form-group">
                    <label class="form-label">Destination Webhook URL</label>
                    <input type="url" name="webhook_url" class="form-control font-mono" placeholder="https://yourdomain.com/vortex-webhook.php" value="<?= htmlspecialchars($webhookUrl) ?>">
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                        Must be a public HTTPS endpoint reachable by PayVortex servers. Leave blank to clear.
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" style="display: flex; justify-content: space-between;">
                        <span>Webhook Signing Secret</span>
                        <span style="font-size: 0.72rem; color: var(--text-muted);">Used to verify authenticity of events</span>
                    </label>
                    <div style="display: flex; gap: 8px;">
                        <input type="password" id="webhookSecretInput" name="webhook_secret" class="form-control font-mono" value="<?= htmlspecialchars($webhookSecret) ?>" placeholder="Auto-generated if empty">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleSecretVisibility('webhookSecretInput', this)">Reveal</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="copyToClipboard('<?= htmlspecialchars($webhookSecret) ?>', this)">Copy</button>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; align-items: center; margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-light);">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>

            <!-- Rotate Secret Form -->
            <form method="POST" action="webhooks.php" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center;" onsubmit="return confirm('Rotating your webhook secret will immediately invalidate your old secret. Update your listener before rotating. Proceed?');">
                <input type="hidden" name="action" value="rotate_secret">
                <input type="hidden" name="event_id" value="<?= htmlspecialchars($selectedEventId) ?>">
                <div>
                    <div style="font-size: 0.82rem; font-weight: 600; color: var(--text-main);">Rotate Secret</div>
                    <div style="font-size: 0.74rem; color: var(--text-muted);">Generate a brand new cryptographic 48-char hex secret for this event</div>
                </div>
                <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--danger); border-color: #fca5a5;">Rotate</button>
            </form>

            <!-- Test Ping Form -->
            <form method="POST" action="webhooks.php" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center;">
                <input type="hidden" name="action" value="test_ping">
                <input type="hidden" name="event_id" value="<?= htmlspecialchars($selectedEventId) ?>">
                <div>
                    <div style="font-size: 0.82rem; font-weight: 600; color: var(--text-main);">Test Dispatch</div>
                    <div style="font-size: 0.74rem; color: var(--text-muted);">Send a simulated payment.captured event to verify your server</div>
                </div>
                <button type="submit" class="btn btn-secondary btn-sm" <?= empty($webhookUrl) ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?>>
                    Send Test Ping &rarr;
                </button>
            </form>
        </div>
    </div>

    <!-- Right Side: Signature Verification Documentation -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <h3 class="card-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                Signature Verification Guide
            </h3>
        </div>
        <div class="card-body" style="padding: 20px 24px; font-size: 0.84rem;">
            <p style="margin-bottom: 12px; color: var(--text-muted);">
                PayVortex signs every webhook with your event secret in the <code class="font-mono" style="color: var(--primary);">X-Vortex-Signature</code> HTTP header.
            </p>

            <div style="font-size: 0.76rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px;">PHP Webhook Handler Sample</div>
            <div class="code-box" style="margin-top: 0;">
<pre>&lt;?php
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_VORTEX_SIGNATURE'] ?? '';
$secret = '<?= htmlspecialchars($webhookSecret ?: 'YOUR_EVENT_WEBHOOK_SECRET') ?>';

$calculated = hash_hmac('sha256', $payload, $secret);

if (!hash_equals($calculated, $signature)) {
    http_response_code(401);
    exit('Invalid signature');
}

$event = json_decode($payload, true);
// Process event safely
http_response_code(200);
echo 'OK';
?&gt;</pre>
            </div>

            <div style="margin-top: 16px; border-top: 1px solid var(--border-light); padding-top: 14px;">
                <div style="font-weight: 700; font-size: 0.8rem; margin-bottom: 6px;">Supported Webhook Events:</div>
                <ul style="padding-left: 18px; color: var(--text-muted); font-size: 0.78rem; display: flex; flex-direction: column; gap: 4px;">
                    <li><strong style="color: var(--text-main);">payment.captured</strong> – Transaction finalized successfully</li>
                    <li><strong style="color: var(--text-main);">payment.failed</strong> – Transaction aborted or rejected</li>
                    <li><strong style="color: var(--text-main);">refund.processed</strong> – Merchant refund completed</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

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
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
