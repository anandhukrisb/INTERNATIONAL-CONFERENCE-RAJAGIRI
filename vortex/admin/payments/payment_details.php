<?php
/**
 * ------------------------------------------------------------
 * payment_details.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 * Purpose : Comprehensive Search & Inspection for every detail of a Vortex Transaction by vortexId.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Logger.php';

$pageTitle = "Vortex Transaction Details & Search";
$activeNav = "payments";

$txnParam = trim((string)($_GET['vortex_transaction_id'] ?? $_GET['id'] ?? $_GET['txn_id'] ?? $_GET['q'] ?? ''));

$transaction     = null;
$apiClient       = null;
$event           = null;
$webhookLogs     = [];
$refundLogs      = [];
$sessionLogs     = [];
$errorMessage    = null;
$allSearchResults= [];

if ($txnParam !== '') {
    try {
        $db = Database::getInstance()->getConnection();

        // 1. Fetch Primary Transaction Record
        $numericId = is_numeric($txnParam) ? (int)$txnParam : 0;
        $queryTx = "SELECT t.*, e.event_name, e.department, c.client_name, c.api_key, c.webhook_url
                    FROM transactions t
                    LEFT JOIN events e ON t.event_id = e.event_id
                    LEFT JOIN api_clients c ON t.api_client_id = c.id
                    WHERE t.vortex_transaction_id = :param
                       OR t.id = :num
                       OR t.razorpay_order_id = :param
                       OR t.razorpay_payment_id = :param
                       OR t.customer_email = :param
                    ORDER BY t.id DESC";

        $stmtTx = $db->prepare($queryTx);
        $stmtTx->execute(['param' => $txnParam, 'num' => $numericId]);
        $allSearchResults = $stmtTx->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($allSearchResults)) {
            // Take the exact or most relevant match
            $transaction = $allSearchResults[0];
            $vortexTxId  = $transaction['vortex_transaction_id'];
            $txDbId      = $transaction['id'];
            $rzpOrder    = $transaction['razorpay_order_id'] ?? '';
            $rzpPayment  = $transaction['razorpay_payment_id'] ?? '';

            // 2. Fetch Full API Client Details
            if (!empty($transaction['api_client_id'])) {
                $stmtC = $db->prepare("SELECT id, client_name, api_key, webhook_url, is_active, created_at FROM api_clients WHERE id = ?");
                $stmtC->execute([$transaction['api_client_id']]);
                $apiClient = $stmtC->fetch(PDO::FETCH_ASSOC);
            }

            // 3. Fetch Full Event Details
            if (!empty($transaction['event_id'])) {
                $stmtE = $db->prepare("SELECT * FROM events WHERE event_id = ? OR id = ?");
                $stmtE->execute([$transaction['event_id'], is_numeric($transaction['event_id']) ? (int)$transaction['event_id'] : 0]);
                $event = $stmtE->fetch(PDO::FETCH_ASSOC);
            }

            // 4. Fetch Webhook Logs for this transaction/order/payment
            try {
                $stmtWh = $db->prepare("SELECT * FROM webhooks 
                                        WHERE (order_id IS NOT NULL AND order_id != '' AND order_id = :order_id)
                                           OR (payment_id IS NOT NULL AND payment_id != '' AND payment_id = :payment_id)
                                           OR payload LIKE :like_vortex
                                        ORDER BY id DESC");
                $stmtWh->execute([
                    'order_id'   => $rzpOrder,
                    'payment_id' => $rzpPayment,
                    'like_vortex'=> "%" . $vortexTxId . "%"
                ]);
                $webhookLogs = $stmtWh->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                $webhookLogs = [];
            }

            // 5. Fetch Refund History
            try {
                $stmtRef = $db->prepare("SELECT * FROM refunds WHERE transaction_id = :vtx_id OR transaction_id = :db_id ORDER BY id DESC");
                $stmtRef->execute([
                    'vtx_id' => $vortexTxId,
                    'db_id'  => (string)$txDbId
                ]);
                $refundLogs = $stmtRef->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                $refundLogs = [];
            }

            // 6. Fetch Checkout Sessions
            try {
                $stmtSess = $db->prepare("SELECT * FROM checkout_sessions WHERE transaction_id = :vtx_id OR transaction_id = :db_id ORDER BY id DESC");
                $stmtSess->execute([
                    'vtx_id' => $vortexTxId,
                    'db_id'  => (string)$txDbId
                ]);
                $sessionLogs = $stmtSess->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                $sessionLogs = [];
            }

        } else {
            $errorMessage = "No record found matching identifier: \"" . htmlspecialchars($txnParam) . "\"";
        }
    } catch (\Throwable $e) {
        Logger::error("Search payment details error: " . $e->getMessage());
        $errorMessage = "A database query error occurred: " . htmlspecialchars($e->getMessage());
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Search Bar Header Banner -->
<div class="section-card">
    <div class="section-header">
        <h2>🔍 Search Transaction by Vortex ID</h2>
        <a href="../dashboard.php" class="btn btn-secondary btn-sm">← Back to Dashboard</a>
    </div>
    <div class="section-body">
        <form action="payment_details.php" method="GET" style="display: flex; gap: 12px; max-width: 700px;">
            <input type="text" name="vortex_transaction_id" class="form-control" style="flex: 1; font-family: var(--font-mono);" 
                   placeholder="Enter Vortex Transaction ID (e.g. VTX_SIM_123, VTX_66E3...)" 
                   value="<?= htmlspecialchars($txnParam) ?>" required>
            <button type="submit" class="btn btn-primary">Search Details</button>
        </form>
        <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 8px;">
            * You can search by Vortex Transaction ID (`VTX_...`), Database ID, Razorpay Order ID (`order_...`), Razorpay Payment ID (`pay_...`), or Customer Email.
        </p>
    </div>
</div>

<?php if ($errorMessage): ?>
    <div class="alert alert-danger">
        <strong>✕ Search Result:</strong> <?= $errorMessage ?>
    </div>
<?php elseif ($transaction): ?>

    <!-- Multiple Results Picker (if search returned multiple rows) -->
    <?php if (count($allSearchResults) > 1): ?>
        <div class="alert alert-warning">
            <strong>Multiple Matches Found (<?= count($allSearchResults) ?>):</strong> Select the specific transaction below to inspect.
            <div style="margin-top: 8px; display: flex; gap: 8px; flex-wrap: wrap;">
                <?php foreach ($allSearchResults as $sr): ?>
                    <a href="payment_details.php?vortex_transaction_id=<?= urlencode($sr['vortex_transaction_id']) ?>" class="btn btn-secondary btn-sm">
                        <?= htmlspecialchars($sr['vortex_transaction_id']) ?> (<?= htmlspecialchars($sr['status']) ?> - ₹<?= number_format($sr['amount'], 2) ?>)
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- 1. Primary Transaction Overview -->
    <div class="section-card">
        <div class="section-header">
            <h2>Transaction Master Overview: <span class="font-mono"><?= htmlspecialchars($transaction['vortex_transaction_id']) ?></span></h2>
            <div>
                <?php
                    $st = strtoupper((string)$transaction['status']);
                    if ($st === 'SUCCESS') echo '<span class="badge badge-success">✓ SUCCESS</span>';
                    elseif ($st === 'FAILED') echo '<span class="badge badge-danger">✕ FAILED</span>';
                    elseif ($st === 'REFUNDED') echo '<span class="badge badge-refunded">↩ REFUNDED</span>';
                    else echo '<span class="badge badge-pending">⏳ ' . htmlspecialchars($st) . '</span>';
                ?>
            </div>
        </div>
        <div class="section-body">
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Vortex Transaction ID</span>
                    <div class="copy-wrapper" style="margin-top: 2px;">
                        <span class="detail-value font-mono" style="color: var(--primary); font-size: 1.05rem;"><?= htmlspecialchars($transaction['vortex_transaction_id']) ?></span>
                        <button type="button" class="btn-copy btn-copy-sm" onclick="copyToClipboard('<?= htmlspecialchars($transaction['vortex_transaction_id'], ENT_QUOTES) ?>', this)" title="Copy Vortex Transaction ID">📋 Copy</button>
                    </div>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Database Record ID</span>
                    <span class="detail-value font-mono">#<?= (int)$transaction['id'] ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Transaction Amount</span>
                    <span class="detail-value" style="font-size: 1.2rem; font-weight: 700; color: var(--success-text);">
                        <?= htmlspecialchars($transaction['currency'] ?? 'INR') ?> ₹<?= number_format((float)$transaction['amount'], 2) ?>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Payment Status</span>
                    <span class="detail-value"><strong><?= htmlspecialchars($transaction['status']) ?></strong></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Customer Email</span>
                    <span class="detail-value"><?= htmlspecialchars($transaction['customer_email'] ?: 'N/A') ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Customer Mobile</span>
                    <span class="detail-value"><?= htmlspecialchars($transaction['customer_mobile'] ?: 'N/A') ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Date Created</span>
                    <span class="detail-value"><?= htmlspecialchars($transaction['created_at']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Last Updated</span>
                    <span class="detail-value"><?= htmlspecialchars($transaction['updated_at'] ?? $transaction['created_at']) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Gateway Integration & Processor Details -->
    <div class="section-card">
        <div class="section-header">
            <h2>💳 Payment Processor (Razorpay) Details</h2>
        </div>
        <div class="section-body">
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Razorpay Order ID</span>
                    <div class="copy-wrapper" style="margin-top: 2px;">
                        <span class="detail-value font-mono"><?= htmlspecialchars($transaction['razorpay_order_id'] ?? 'N/A') ?></span>
                        <?php if (!empty($transaction['razorpay_order_id'])): ?>
                            <button type="button" class="btn-copy btn-copy-sm" onclick="copyToClipboard('<?= htmlspecialchars($transaction['razorpay_order_id'], ENT_QUOTES) ?>', this)" title="Copy Order ID">📋 Copy</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Razorpay Payment ID</span>
                    <div class="copy-wrapper" style="margin-top: 2px;">
                        <span class="detail-value font-mono"><?= htmlspecialchars($transaction['razorpay_payment_id'] ?? 'N/A') ?></span>
                        <?php if (!empty($transaction['razorpay_payment_id'])): ?>
                            <button type="button" class="btn-copy btn-copy-sm" onclick="copyToClipboard('<?= htmlspecialchars($transaction['razorpay_payment_id'], ENT_QUOTES) ?>', this)" title="Copy Payment ID">📋 Copy</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="detail-item" style="grid-column: span 2;">
                    <span class="detail-label">Razorpay Signature</span>
                    <span class="detail-value font-mono" style="word-break: break-all; font-size: 0.8rem; background: #f8fafc;">
                        <?= htmlspecialchars($transaction['razorpay_signature'] ?? 'N/A') ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Merchant & Event Association Details -->
    <div class="section-card">
        <div class="section-header">
            <h2>🏢 Merchant & Event Context</h2>
        </div>
        <div class="section-body">
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">API Client (Merchant Name)</span>
                    <span class="detail-value"><strong><?= htmlspecialchars($transaction['client_name'] ?? 'Default Client') ?></strong> (ID: #<?= (int)$transaction['api_client_id'] ?>)</span>
                    <?php if (!empty($transaction['api_key'])): ?>
                        <div class="copy-wrapper" style="margin-top: 4px;">
                            <span style="font-size:0.75rem; color:var(--text-muted);">Key:</span>
                            <span class="detail-value font-mono" style="font-size:0.82rem;"><?= htmlspecialchars($transaction['api_key']) ?></span>
                            <button type="button" class="btn-copy btn-copy-sm" onclick="copyToClipboard('<?= htmlspecialchars($transaction['api_key'], ENT_QUOTES) ?>', this)" title="Copy API Key">📋 Copy</button>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Client Webhook URL</span>
                    <span class="detail-value font-mono" style="font-size: 0.82rem;"><?= htmlspecialchars($transaction['webhook_url'] ?? 'Not Configured') ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Event Name</span>
                    <span class="detail-value"><?= htmlspecialchars($transaction['event_name'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Event Code / ID</span>
                    <div class="copy-wrapper" style="margin-top: 2px;">
                        <span class="detail-value font-mono"><?= htmlspecialchars($transaction['event_id']) ?></span>
                        <button type="button" class="btn-copy btn-copy-sm" onclick="copyToClipboard('<?= htmlspecialchars($transaction['event_id'], ENT_QUOTES) ?>', this)" title="Copy Event ID">📋 Copy</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Webhook Payload Log Details -->
    <div class="section-card">
        <div class="section-header">
            <h2>📡 Associated Webhook Callbacks (<?= count($webhookLogs) ?>)</h2>
        </div>
        <div class="section-body">
            <?php if (empty($webhookLogs)): ?>
                <p style="color: var(--text-muted);">No recorded webhook events for this transaction.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Event Type</th>
                                <th>Payment / Order ID</th>
                                <th>Status</th>
                                <th>Received At</th>
                                <th>Raw Payload</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($webhookLogs as $wh): ?>
                                <tr>
                                    <td>#<?= $wh['id'] ?></td>
                                    <td><span class="font-mono"><?= htmlspecialchars($wh['event_type']) ?></span></td>
                                    <td>
                                        <span class="font-mono"><?= htmlspecialchars($wh['payment_id'] ?? $wh['order_id'] ?? 'N/A') ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $wh['status'] === 'PROCESSED' ? 'badge-success' : 'badge-pending' ?>">
                                            <?= htmlspecialchars($wh['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($wh['created_at']) ?></td>
                                    <td>
                                        <details>
                                            <summary style="cursor: pointer; color: var(--primary); font-weight: 600;">View JSON</summary>
                                            <pre class="json-viewer" style="margin-top: 8px;"><?= htmlspecialchars($wh['payload']) ?></pre>
                                        </details>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 5. Refund History -->
    <?php if (!empty($refundLogs)): ?>
        <div class="section-card">
            <div class="section-header">
                <h2>↩ Refund Records (<?= count($refundLogs) ?>)</h2>
            </div>
            <div class="section-body">
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Refund ID</th>
                                <th>Razorpay Refund ID</th>
                                <th>Amount</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($refundLogs as $rf): ?>
                                <tr>
                                    <td>#<?= $rf['id'] ?></td>
                                    <td><span class="font-mono"><?= htmlspecialchars($rf['razorpay_refund_id'] ?? 'N/A') ?></span></td>
                                    <td style="font-weight: 700; color: var(--danger-text);">₹<?= number_format($rf['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($rf['reason'] ?? 'N/A') ?></td>
                                    <td><span class="badge badge-refunded"><?= htmlspecialchars($rf['status']) ?></span></td>
                                    <td><?= htmlspecialchars($rf['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- 6. Raw JSON Data Copy Option -->
    <div class="section-card">
        <div class="section-header">
            <h2>📄 Raw Transaction Diagnostic JSON</h2>
            <button class="btn btn-secondary btn-sm" onclick="copyToClipboard('raw-json-box')">📋 Copy JSON</button>
        </div>
        <div class="section-body">
            <pre class="json-viewer" id="raw-json-box"><?= htmlspecialchars(json_encode([
                'transaction' => $transaction,
                'api_client'  => $apiClient,
                'event'       => $event,
                'webhooks'    => $webhookLogs,
                'refunds'     => $refundLogs,
                'sessions'    => $sessionLogs
            ], JSON_PRETTY_PRINT)) ?></pre>
        </div>
    </div>

<?php else: ?>
    <div class="section-card">
        <div class="section-body" style="text-align: center; padding: 48px 24px;">
            <h3 style="margin-bottom: 8px;">Enter a Vortex ID to Inspect All Details</h3>
            <p style="color: var(--text-muted); max-width: 500px; margin: 0 auto 24px auto;">
                Use the search box above or type a Vortex Transaction ID (`VTX_...`) to retrieve all associated transaction details, Razorpay signatures, API client keys, and webhook logs.
            </p>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
