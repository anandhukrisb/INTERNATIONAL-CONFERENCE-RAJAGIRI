<?php
/**
 * ------------------------------------------------------------
 * payment_details.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 * Purpose : Comprehensive Search & Inspection for every detail of a Vortex Transaction by vortexId.
 * ------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['vortex_admin_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Logger.php';
require_once __DIR__ . '/../../classes/TransactionTimeline.php';

$pageTitle = "Rpay Transaction Details & Search";
$activeNav = "payments";

$txnParam = trim((string)($_GET['vortex_transaction_id'] ?? $_GET['id'] ?? $_GET['txn_id'] ?? $_GET['q'] ?? $_GET['search'] ?? $_GET['query'] ?? ''));

// Fetch top recent transactions for Quick Switcher
$recentSwitcherTx = [];
try {
    $db = Database::getInstance()->getConnection();
    $recentSwitcherTx = $db->query("SELECT id, vortex_transaction_id, amount, status, razorpay_payment_id FROM transactions ORDER BY id DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);

    // If no search parameter provided, default to the most recent transaction so the page is never blank!
    if ($txnParam === '' && !empty($recentSwitcherTx)) {
        $txnParam = $recentSwitcherTx[0]['vortex_transaction_id'];
    }
} catch (\Throwable $e) {}

$transaction     = null;
$apiClient       = null;
$event           = null;
$webhookLogs     = [];
$refundLogs      = [];
$sessionLogs     = [];
$timelineEvents  = [];
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
                    WHERE t.vortex_transaction_id = :param1
                       OR t.id = :num
                       OR t.razorpay_order_id = :param2
                       OR t.razorpay_payment_id = :param3
                       OR t.customer_email = :param4
                    ORDER BY t.id DESC";

        $stmtTx = $db->prepare($queryTx);
        $stmtTx->execute([
            'param1' => $txnParam,
            'param2' => $txnParam,
            'param3' => $txnParam,
            'param4' => $txnParam,
            'num'    => $numericId,
        ]);
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

            // 7. Fetch Transaction Timeline (Ordered Audit Trail)
            try {
                $timelineEvents = TransactionTimeline::getTimeline($vortexTxId, $txDbId);
            } catch (\Throwable $e) {
                $timelineEvents = [];
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
        <h2>🔍 Search Transaction by Rpay ID</h2>
        <a href="../dashboard.php" class="btn btn-secondary btn-sm">← Back to Dashboard</a>
    </div>
    <div class="section-body">
        <form action="payment_details.php" method="GET" style="display: flex; gap: 12px; max-width: 700px;">
            <input type="text" name="vortex_transaction_id" class="form-control" style="flex: 1; font-family: var(--font-mono);" 
                   placeholder="Enter Rpay Transaction ID (e.g. VTX_SIM_123, VTX_66E3...)" 
                   value="<?= htmlspecialchars($txnParam) ?>" required>
            <button type="submit" class="btn btn-primary">Search Details</button>
        </form>
        <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 8px;">
            * You can search by Rpay Transaction ID (`VTX_...`), Database ID, Razorpay Order ID (`order_...`), Razorpay Payment ID (`pay_...`), or Customer Email.
        </p>

        <!-- Quick Switcher Bar -->
        <?php if (!empty($recentSwitcherTx)): ?>
            <div style="margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--border-color); display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <span style="font-size: 0.78rem; font-weight: 700; color: var(--text-gray); text-transform: uppercase;">Quick Switch:</span>
                <?php foreach ($recentSwitcherTx as $rtx): ?>
                    <?php 
                        $isActive = ($transaction && $transaction['vortex_transaction_id'] === $rtx['vortex_transaction_id']);
                        $st = strtoupper((string)$rtx['status']);
                        $badgeStyle = 'background:#f1f5f9; color:#475569;';
                        if ($st === 'SUCCESS') $badgeStyle = 'background:var(--success-bg); color:var(--success-text);';
                        elseif ($st === 'FAILED') $badgeStyle = 'background:var(--danger-bg); color:var(--danger-text);';
                        elseif ($st === 'REFUNDED') $badgeStyle = 'background:var(--purple-bg); color:var(--purple-text);';
                        elseif ($st === 'DISPUTED') $badgeStyle = 'background:#fee2e2; color:#b91c1c;';
                        elseif ($st === 'PENDING') $badgeStyle = 'background:var(--warning-bg); color:var(--warning-text);';
                    ?>
                    <a href="payment_details.php?vortex_transaction_id=<?= urlencode($rtx['vortex_transaction_id']) ?>" 
                       class="btn btn-secondary btn-sm" 
                       style="<?= $isActive ? 'border-color: var(--primary-blue); background: var(--primary-light); font-weight: 700;' : '' ?> font-size: 0.78rem; padding: 3px 8px;">
                        <span class="font-mono" style="font-size: 0.75rem;"><?= htmlspecialchars(substr($rtx['vortex_transaction_id'], 0, 15)) ?>...</span>
                        <span style="display:inline-block; padding: 1px 5px; border-radius: 4px; font-size: 0.7rem; font-weight: 600; <?= $badgeStyle ?>">
                            <?= htmlspecialchars($st === 'PENDING' ? 'UNPAID' : $st) ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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
            <div style="display: flex; align-items: center; gap: 8px;">
                <?php
                    $st = strtoupper((string)$transaction['status']);
                    if ($st === 'SUCCESS') echo '<span class="badge badge-success">✓ SUCCESS</span>';
                    elseif ($st === 'FAILED') echo '<span class="badge badge-danger">✕ FAILED</span>';
                    elseif ($st === 'REFUNDED') echo '<span class="badge badge-refunded">↩ REFUNDED</span>';
                    elseif ($st === 'DISPUTED') echo '<span class="badge badge-disputed">⚠️ DISPUTED</span>';
                    else {
                        $displaySt = ($st === 'PENDING') ? 'UNPAID' : $st;
                        echo '<span class="badge badge-pending">⏳ ' . htmlspecialchars($displaySt) . '</span>';
                    }

                    if (!empty($webhookLogs[0]['event_type'])) {
                        echo ' <span class="badge badge-event" title="Latest Razorpay Webhook Event">📡 ' . htmlspecialchars($webhookLogs[0]['event_type']) . '</span>';
                    }
                ?>
                <?php if ($st === 'SUCCESS' && RBAC::can('payments.refund')): ?>
                    <button type="button" class="btn btn-sm"
                            onclick="openRefundModal('<?= htmlspecialchars($transaction['vortex_transaction_id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($transaction['amount'], ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($transaction['customer_email'] ?? ''), ENT_QUOTES) ?>')"
                            style="background-color: #dc2626; color: white; border: none; font-weight: 700; padding: 6px 14px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; font-size: 0.82rem; box-shadow: 0 2px 4px rgba(220, 38, 38, 0.2);">
                        💸 Process Refund
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="section-body">
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Rpay Transaction ID</span>
                    <div class="copy-wrapper" style="margin-top: 2px;">
                        <span class="detail-value font-mono" style="color: var(--primary); font-size: 1.05rem;"><?= htmlspecialchars($transaction['vortex_transaction_id']) ?></span>
                        <button type="button" class="btn-copy btn-copy-sm" onclick="copyToClipboard('<?= htmlspecialchars($transaction['vortex_transaction_id'], ENT_QUOTES) ?>', this)" title="Copy Rpay Transaction ID">📋 Copy</button>
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
                    <span class="detail-value">
                        <strong><?= htmlspecialchars($transaction['status'] === 'PENDING' ? 'UNPAID' : $transaction['status']) ?></strong>
                        <?php if (!empty($webhookLogs[0]['event_type'])): ?>
                            <span class="badge badge-event" style="margin-left: 6px;">📡 <?= htmlspecialchars($webhookLogs[0]['event_type']) ?></span>
                        <?php endif; ?>
                    </span>
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

    <!-- 2. Chronological Status Lifecycle & Timeline History -->
    <div class="section-card">
        <div class="section-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
            <h2>📜 Status Lifecycle History & Audit Trail (<?= count($timelineEvents) ?>)</h2>
            <span style="font-size: 0.85rem; color: var(--text-muted);">Sequential order of customer attempts, failures, chargebacks, and refunds</span>
        </div>
        <div class="section-body" style="padding: 0;">
            <?php if (empty($timelineEvents)): ?>
                <div style="padding: 24px; color: var(--text-muted);">
                    No lifecycle events recorded yet for this transaction. When a customer attempts, fails, chargebacks, or is refunded, all events are logged here in chronological order.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Lifecycle Event</th>
                                <th>Resulting Status</th>
                                <th>Payment / Reference ID</th>
                                <th>Amount</th>
                                <th>Reason / Explanation</th>
                                <th>Source</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($timelineEvents as $idx => $item): ?>
                                <tr>
                                    <td style="font-weight: 700; color: var(--text-muted);"><?= $idx + 1 ?></td>
                                    <td>
                                        <span class="badge badge-event" style="font-size: 0.76rem; font-weight: 600;">
                                            <?= htmlspecialchars($item['event_type']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $itSt = strtoupper((string)$item['status']);
                                        if ($itSt === 'SUCCESS') echo '<span class="badge badge-success">✓ SUCCESS</span>';
                                        elseif ($itSt === 'FAILED') echo '<span class="badge badge-failed">✕ FAILED</span>';
                                        elseif ($itSt === 'REFUNDED') echo '<span class="badge badge-refunded">↩ REFUNDED</span>';
                                        elseif ($itSt === 'DISPUTED') echo '<span class="badge badge-disputed">⚠️ DISPUTED</span>';
                                        else echo '<span class="badge badge-pending">⏳ ' . htmlspecialchars($itSt === 'PENDING' ? 'UNPAID' : $itSt) . '</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <span class="font-mono" style="font-size: 0.82rem;">
                                            <?= htmlspecialchars($item['payment_id'] ?: ($item['order_id'] ?: 'N/A')) ?>
                                        </span>
                                    </td>
                                    <td style="font-weight: 600;">
                                        <?= !empty($item['amount']) ? (htmlspecialchars($item['currency'] ?? 'INR') . ' ₹' . number_format((float)$item['amount'], 2)) : '—' ?>
                                    </td>
                                    <td style="max-width: 320px; font-size: 0.88rem; color: var(--text-main);">
                                        <?= htmlspecialchars($item['reason'] ?? 'Status updated') ?>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.75rem; color: var(--text-muted); background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">
                                            <?= htmlspecialchars($item['source']) ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 0.82rem; color: var(--text-muted); white-space: nowrap;">
                                        <?= htmlspecialchars($item['created_at']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 3. Gateway Integration & Processor Details -->
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
            <h3 style="margin-bottom: 8px;">Enter an Rpay ID to Inspect All Details</h3>
            <p style="color: var(--text-muted); max-width: 500px; margin: 0 auto 24px auto;">
                Use the search box above or type an Rpay Transaction ID (`VTX_...`) to retrieve all associated transaction details, Razorpay signatures, API client keys, and webhook logs.
            </p>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/refund_modal.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
