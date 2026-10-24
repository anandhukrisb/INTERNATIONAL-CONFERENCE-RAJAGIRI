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

$statusFilter   = trim($_GET['status'] ?? '');
$eventIdFilter  = trim($_GET['event_id'] ?? '');
$currencyFilter = strtoupper(trim($_GET['currency'] ?? ''));
$searchQ        = trim($_GET['q'] ?? '');

$allEvents = [];
try {
    $db = Database::getInstance()->getConnection();

    // Fetch events for filter dropdown
    $evStmt = $db->query("SELECT event_id, event_name FROM events ORDER BY is_active DESC, event_name ASC");
    $allEvents = $evStmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    
    if ($statusFilter !== '' && $statusFilter !== 'ALL') {
        $query .= " AND t.status = :status";
        $params[':status'] = $statusFilter;
    }
    
    if ($eventIdFilter !== '' && $eventIdFilter !== 'ALL') {
        $query .= " AND t.event_id = :event_id";
        $params[':event_id'] = $eventIdFilter;
    }

    if ($currencyFilter !== '' && $currencyFilter !== 'ALL' && in_array($currencyFilter, ['INR', 'USD'])) {
        $query .= " AND t.currency = :currency";
        $params[':currency'] = $currencyFilter;
    }

    if ($searchQ !== '') {
        $query .= " AND (t.vortex_transaction_id LIKE :search OR t.razorpay_order_id LIKE :search OR t.razorpay_payment_id LIKE :search OR t.customer_email LIKE :search)";
        $params[':search'] = '%' . $searchQ . '%';
    }
    
    // Count total records for pagination
    $countQuery = "SELECT COUNT(*) FROM transactions t WHERE 1=1";
    if ($statusFilter !== '' && $statusFilter !== 'ALL') {
        $countQuery .= " AND t.status = :status";
    }
    if ($eventIdFilter !== '' && $eventIdFilter !== 'ALL') {
        $countQuery .= " AND t.event_id = :event_id";
    }
    if ($currencyFilter !== '' && $currencyFilter !== 'ALL' && in_array($currencyFilter, ['INR', 'USD'])) {
        $countQuery .= " AND t.currency = :currency";
    }
    if ($searchQ !== '') {
        $countQuery .= " AND (t.vortex_transaction_id LIKE :search OR t.razorpay_order_id LIKE :search OR t.razorpay_payment_id LIKE :search OR t.customer_email LIKE :search)";
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
    <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; padding: 14px 20px; border-bottom: 1px solid var(--border-color); background: var(--bg-hover);">
        <div style="display: flex; align-items: center; gap: 10px;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary-blue);"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
            <div style="font-size: 1rem; font-weight: 700; color: var(--text-dark);">
                Transactions Ledger (<?= number_format($totalCount) ?>)
            </div>
            <a href="revenue.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.78rem; padding: 4px 8px;" title="Switch to Event Revenue Analytics">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                <span>Revenue by Event</span>
            </a>
        </div>
        
        <!-- Search & Filter Controls -->
        <form method="GET" action="list_payments.php" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <input type="text" name="q" class="form-control" placeholder="Search Tx ID, order, email..." value="<?= htmlspecialchars($searchQ) ?>" style="font-size: 0.82rem; padding: 5px 10px; width: 210px;">
            
            <select name="event_id" class="form-control" style="font-size: 0.82rem; padding: 5px 10px; width: 160px;" onchange="this.form.submit()">
                <option value="">All Events</option>
                <?php foreach ($allEvents as $evt): ?>
                    <option value="<?= htmlspecialchars($evt['event_id']) ?>" <?= $eventIdFilter === $evt['event_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($evt['event_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status" class="form-control" style="font-size: 0.82rem; padding: 5px 10px; width: 120px;" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="SUCCESS" <?= $statusFilter === 'SUCCESS' ? 'selected' : '' ?>>Success</option>
                <option value="PENDING" <?= $statusFilter === 'PENDING' ? 'selected' : '' ?>>Pending</option>
                <option value="FAILED" <?= $statusFilter === 'FAILED' ? 'selected' : '' ?>>Failed</option>
                <option value="REFUNDED" <?= $statusFilter === 'REFUNDED' ? 'selected' : '' ?>>Refunded</option>
            </select>

            <select name="currency" class="form-control" style="font-size: 0.82rem; padding: 5px 10px; width: 140px;" onchange="this.form.submit()">
                <option value="">All Currencies</option>
                <option value="INR" <?= $currencyFilter === 'INR' ? 'selected' : '' ?>>₹ INR (Domestic)</option>
                <option value="USD" <?= $currencyFilter === 'USD' ? 'selected' : '' ?>>$ USD (International)</option>
            </select>

            <button type="submit" class="btn btn-secondary btn-sm" style="padding: 5px 10px;">Filter</button>
            <?php if ($searchQ !== '' || $statusFilter !== '' || $eventIdFilter !== '' || $currencyFilter !== ''): ?>
                <a href="list_payments.php" class="btn btn-secondary btn-sm" style="padding: 5px 10px; color: #dc2626;">Reset</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="section-body" style="padding: 0;">
        <?php if (isset($error)): ?>
            <div style="padding: 24px; color: var(--danger-text);"><?= htmlspecialchars($error) ?></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Rpay Tx ID</th>
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
                            <tr><td colspan="8" style="text-align:center; padding: 28px; color: var(--text-gray);">No transactions matching criteria found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $tx): ?>
                                <tr>
                                    <td>
                                        <a href="payment_details.php?vortex_transaction_id=<?= urlencode($tx['vortex_transaction_id']) ?>" class="font-mono" style="color: var(--primary-blue); font-weight: 700; text-decoration: underline;">
                                            <?= htmlspecialchars($tx['vortex_transaction_id']) ?>
                                        </a>
                                        <?php if (!empty($tx['customer_email'])): ?>
                                            <div style="font-size: 0.76rem; color: var(--text-gray); margin-top: 3px; display: flex; align-items: center; gap: 4px;" title="Customer / User">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                                <span style="max-width: 175px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($tx['customer_email']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-weight: 700; color: var(--text-dark); white-space: nowrap;">
                                        <?php 
                                        $curr = strtoupper($tx['currency'] ?? 'INR');
                                        $currSymbol = ($curr === 'USD') ? '$' : '₹';
                                        ?>
                                        <span><?= $currSymbol ?><?= number_format((float)$tx['amount'], 2) ?></span>
                                        <?php if ($curr === 'USD'): ?>
                                            <span class="badge" style="font-size: 0.65rem; padding: 2px 5px; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; margin-left: 3px;" title="International USD Payment">USD</span>
                                        <?php endif; ?>
                                    </td>
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
                                        <?php if (!empty($tx['latest_event']) && $tx['status'] !== 'SUCCESS'): ?>
                                            <div style="margin-top: 3px;">
                                                <span class="badge badge-event" title="Latest Gateway Event" style="display: inline-flex; align-items: center; gap: 3px; font-size: 0.7rem;">
                                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4.9 19.1C1 15.2 1 8.8 4.9 4.9"></path><path d="M7.8 16.2c-2.3-2.3-2.3-6.1 0-8.5"></path><circle cx="12" cy="12" r="2"></circle><path d="M16.2 7.8c2.3 2.3 2.3 6.1 0 8.5"></path><path d="M19.1 4.9C23 8.8 23 15.2 19.1 19.1"></path></svg>
                                                    <?= htmlspecialchars($tx['latest_event']) ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($tx['latest_reason'])): ?>
                                            <div style="margin-top: 4px; font-size: 0.74rem; color: var(--text-gray); max-width: 220px; line-height: 1.25;">
                                                <?= htmlspecialchars($tx['latest_reason']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="font-mono"><?= htmlspecialchars($tx['razorpay_payment_id'] ?? '—') ?></span></td>
                                    <td><?= htmlspecialchars($tx['event_name'] ?? $tx['event_id']) ?></td>
                                    <td><?= htmlspecialchars($tx['client_name'] ?? 'Default Client') ?></td>
                                    <td style="font-size: 0.82rem; color: var(--text-gray); white-space: nowrap;"><?= date('M d, Y H:i', strtotime($tx['created_at'])) ?></td>
                                    <td>
                                        <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                            <a href="payment_details.php?vortex_transaction_id=<?= urlencode($tx['vortex_transaction_id']) ?>" class="btn btn-secondary btn-sm" title="View Full Status History & Audit Trail" style="display: inline-flex; align-items: center; gap: 4px;">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                                <span>Timeline (<?= (int)($tx['timeline_count'] ?? 0) ?>)</span>
                                            </a>
                                            <?php if ($tx['status'] === 'SUCCESS'): ?>
                                                <button type="button" class="btn btn-sm"
                                                        onclick="openRefundModal('<?= htmlspecialchars($tx['vortex_transaction_id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($tx['amount'], ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($tx['customer_email'] ?? ''), ENT_QUOTES) ?>')"
                                                        title="Process Refund via Razorpay"
                                                        style="background-color: #dc2626; color: white; border: none; font-weight: 600; padding: 4px 8px; border-radius: 4px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; font-size: 0.76rem;">
                                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                                                    <span>Refund</span>
                                                </button>
                                            <?php elseif ($tx['status'] === 'REFUNDED'): ?>
                                                <span class="badge badge-refunded" style="font-size: 0.74rem; padding: 3px 7px; display: inline-flex; align-items: center; gap: 4px;">
                                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                                                    <span>Refunded</span>
                                                </span>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-secondary btn-sm" disabled style="opacity: 0.45; cursor: not-allowed; font-size: 0.74rem; padding: 4px 8px;" title="Refund available only for successful payments">
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
