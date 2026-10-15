<?php
/**
 * ------------------------------------------------------------
 * list_payments.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 * Purpose : List all transactions with optional filters.
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

$pageTitle = "Transactions List";
$activeNav = "payments";

$statusFilter = $_GET['status'] ?? '';
$eventIdFilter = $_GET['event_id'] ?? '';

try {
    $db = Database::getInstance()->getConnection();
    
    $query = "SELECT t.*, e.event_name, c.client_name,
                     (SELECT w.event_type FROM webhooks w 
                      WHERE (w.order_id IS NOT NULL AND w.order_id != '' AND w.order_id = t.razorpay_order_id)
                         OR (w.payment_id IS NOT NULL AND w.payment_id != '' AND w.payment_id = t.razorpay_payment_id)
                      ORDER BY w.id DESC LIMIT 1) AS latest_event,
                     (SELECT tl.reason FROM transaction_timeline tl 
                      WHERE tl.vortex_transaction_id = t.vortex_transaction_id 
                      ORDER BY tl.id DESC LIMIT 1) AS latest_reason,
                     (SELECT COUNT(*) FROM transaction_timeline tl 
                      WHERE tl.vortex_transaction_id = t.vortex_transaction_id) AS timeline_count
              FROM transactions t 
              LEFT JOIN events e ON t.event_id = e.event_id 
              LEFT JOIN api_clients c ON t.api_client_id = c.id
              WHERE 1=1";
    $params = [];
    
    if ($statusFilter !== '') {
        $query .= " AND t.status = :status";
        $params[':status'] = $statusFilter;
    }
    
    if ($eventIdFilter !== '') {
        $query .= " AND t.event_id = :event_id";
        $params[':event_id'] = $eventIdFilter;
    }
    
    // Count total records for pagination
    $countQuery = "SELECT COUNT(*) FROM transactions t WHERE 1=1";
    if ($statusFilter !== '') {
        $countQuery .= " AND t.status = :status";
    }
    if ($eventIdFilter !== '') {
        $countQuery .= " AND t.event_id = :event_id";
    }
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $totalCount = (int) $countStmt->fetchColumn();

    $perPage = 25;
    $totalPages = max(1, (int) ceil($totalCount / $perPage));
    $page = max(1, (int) ($_GET['page'] ?? $_GET['p'] ?? 1));
    if ($page > $totalPages && $totalCount > 0) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $query .= " ORDER BY t.id DESC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $fromTx = ($totalCount === 0) ? 0 : $offset + 1;
    $toTx = min($offset + count($transactions), $totalCount);
} catch (\Throwable $e) {
    $transactions = [];
    $totalCount = 0;
    $totalPages = 1;
    $page = 1;
    $fromTx = 0;
    $toTx = 0;
    $error = "Failed to fetch transactions: " . $e->getMessage();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="section-card">
    <div class="section-header">
        <h2>💳 Transactions 
            <?php if ($statusFilter) echo "- " . htmlspecialchars($statusFilter); ?>
            <?php if ($eventIdFilter) echo "- Event: " . htmlspecialchars($eventIdFilter); ?>
        </h2>
        <a href="../dashboard.php" class="btn btn-secondary btn-sm">← Back to Dashboard</a>
    </div>
    <div class="section-body" style="padding: 0;">
        <?php if (isset($error)): ?>
            <div style="padding: 24px; color: var(--danger-text);"><?= htmlspecialchars($error) ?></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Vortex Tx ID</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>RZP Payment ID</th>
                            <th>Event</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr><td colspan="8" style="text-align:center; padding: 24px;">No transactions found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $tx): ?>
                                <tr>
                                    <td>
                                        <a href="payment_details.php?vortex_transaction_id=<?= urlencode($tx['vortex_transaction_id']) ?>" class="font-mono" style="color: var(--primary); font-weight: 700; text-decoration: underline;">
                                            <?= htmlspecialchars($tx['vortex_transaction_id']) ?>
                                        </a>
                                        <?php if (!empty($tx['customer_email'])): ?>
                                            <div style="font-size: 0.76rem; color: var(--text-gray); margin-top: 3px; display: flex; align-items: center; gap: 4px;" title="Customer / User">
                                                <span>👤</span> <span style="max-width: 175px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($tx['customer_email']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-weight: 700; color: var(--text-main);">₹<?= number_format($tx['amount'], 2) ?></td>
                                    <td>
                                        <?php
                                        $bClass = 'badge-pending';
                                        $displayStatus = $tx['status'];
                                        if ($tx['status'] === 'PENDING') $displayStatus = 'UNPAID';
                                        if ($tx['status'] === 'SUCCESS') $bClass = 'badge-success';
                                        if ($tx['status'] === 'FAILED') $bClass = 'badge-danger';
                                        if ($tx['status'] === 'REFUNDED') $bClass = 'badge-refunded';
                                        if ($tx['status'] === 'DISPUTED') $bClass = 'badge-disputed';
                                        ?>
                                        <span class="badge <?= $bClass ?>"><?= htmlspecialchars($displayStatus) ?></span>
                                        <?php if (!empty($tx['latest_event'])): ?>
                                            <div style="margin-top: 3px;">
                                                <span class="badge badge-event" title="Latest Razorpay Event">📡 <?= htmlspecialchars($tx['latest_event']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($tx['latest_reason'])): ?>
                                            <div style="margin-top: 4px; font-size: 0.74rem; color: var(--text-muted); max-width: 220px; line-height: 1.25;">
                                                <?= htmlspecialchars($tx['latest_reason']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="font-mono"><?= htmlspecialchars($tx['razorpay_payment_id'] ?? 'N/A') ?></span></td>
                                    <td><?= htmlspecialchars($tx['event_name'] ?? $tx['event_id']) ?></td>
                                    <td><?= htmlspecialchars($tx['client_name'] ?? 'Default Client') ?></td>
                                    <td style="font-size: 0.85rem; color: var(--text-muted);"><?= htmlspecialchars($tx['created_at']) ?></td>
                                    <td>
                                        <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                            <a href="payment_details.php?vortex_transaction_id=<?= urlencode($tx['vortex_transaction_id']) ?>" class="btn btn-secondary btn-sm" title="View Full Status History & Audit Trail">
                                                📜 Timeline (<?= (int)($tx['timeline_count'] ?? 0) ?>)
                                            </a>
                                            <?php if ($tx['status'] === 'SUCCESS'): ?>
                                                <button type="button" class="btn btn-sm"
                                                        onclick="openRefundModal('<?= htmlspecialchars($tx['vortex_transaction_id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($tx['amount'], ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($tx['customer_email'] ?? ''), ENT_QUOTES) ?>')"
                                                        title="Process Refund via Razorpay"
                                                        style="background-color: #dc2626; color: white; border: none; font-weight: 600; padding: 5px 10px; border-radius: 4px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; font-size: 0.78rem;">
                                                    💸 Refund
                                                </button>
                                            <?php elseif ($tx['status'] === 'REFUNDED'): ?>
                                                <span class="badge badge-refunded" style="font-size: 0.74rem; padding: 4px 8px;">↩ Refunded</span>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm" disabled style="opacity: 0.45; cursor: not-allowed; background: #e2e8f0; color: #64748b; border: none; font-size: 0.75rem; padding: 5px 8px; border-radius: 4px;" title="Refund available only for successful payments">
                                                    Refund
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Transactions Pagination Bar -->
            <?php if ($totalCount > 0): ?>
                <?php
                $buildUrl = function($p) {
                    $params = $_GET;
                    $params['page'] = $p;
                    return '?' . http_build_query($params);
                };
                ?>
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        Showing <strong><?= $fromTx ?>–<?= $toTx ?></strong> of <strong><?= number_format($totalCount) ?></strong> transactions
                        <span style="color: var(--border-color); margin: 0 4px;">•</span>
                        Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div class="pagination-controls">
                            <!-- First & Prev -->
                            <?php if ($page > 1): ?>
                                <a href="<?= htmlspecialchars($buildUrl(1)) ?>" class="pagination-btn pagination-nav-btn" title="First Page">« First</a>
                                <a href="<?= htmlspecialchars($buildUrl($page - 1)) ?>" class="pagination-btn pagination-nav-btn" title="Previous Page">‹ Prev</a>
                            <?php else: ?>
                                <span class="pagination-btn pagination-nav-btn disabled" aria-disabled="true">« First</span>
                                <span class="pagination-btn pagination-nav-btn disabled" aria-disabled="true">‹ Prev</span>
                            <?php endif; ?>

                            <!-- Windowed Numbers -->
                            <?php
                            $window = 2;
                            $startPage = max(1, $page - $window);
                            $endPage = min($totalPages, $page + $window);

                            if ($startPage > 1):
                            ?>
                                <a href="<?= htmlspecialchars($buildUrl(1)) ?>" class="pagination-btn">1</a>
                                <?php if ($startPage > 2): ?>
                                    <span class="pagination-ellipsis">…</span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                                <?php if ($p === $page): ?>
                                    <span class="pagination-btn active" aria-current="page"><?= $p ?></span>
                                <?php else: ?>
                                    <a href="<?= htmlspecialchars($buildUrl($p)) ?>" class="pagination-btn"><?= $p ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <span class="pagination-ellipsis">…</span>
                                <?php endif; ?>
                                <a href="<?= htmlspecialchars($buildUrl($totalPages)) ?>" class="pagination-btn"><?= $totalPages ?></a>
                            <?php endif; ?>

                            <!-- Next & Last -->
                            <?php if ($page < $totalPages): ?>
                                <a href="<?= htmlspecialchars($buildUrl($page + 1)) ?>" class="pagination-btn pagination-nav-btn" title="Next Page">Next ›</a>
                                <a href="<?= htmlspecialchars($buildUrl($totalPages)) ?>" class="pagination-btn pagination-nav-btn" title="Last Page">Last »</a>
                            <?php else: ?>
                                <span class="pagination-btn pagination-nav-btn disabled" aria-disabled="true">Next ›</span>
                                <span class="pagination-btn pagination-nav-btn disabled" aria-disabled="true">Last »</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/refund_modal.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
