<?php
/**
 * ------------------------------------------------------------
 * transactions.php – Merchant Portal Transactions Explorer
 * ------------------------------------------------------------
 * Project : PayVortex Unified Payment Gateway
 * ------------------------------------------------------------
 */

$pageTitle = 'Transactions';
$activeNav  = 'transactions';

require_once __DIR__ . '/includes/header.php';

// Sanitize query filters
$statusFilter = strtoupper(trim($_GET['status'] ?? 'ALL'));
$modeFilter   = isset($_GET['mode']) && $_GET['mode'] !== '' ? (int)$_GET['mode'] : '';
$search       = trim($_GET['search'] ?? '');
$fromDate     = trim($_GET['from_date'] ?? '');
$toDate       = trim($_GET['to_date'] ?? '');
$limit        = max(10, min((int)($_GET['limit'] ?? 20), 100));
$page         = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($page - 1) * $limit;

// Build filter array
$filterParams = [
    'status'       => $statusFilter,
    'search'       => $search,
    'from_date'    => $fromDate,
    'to_date'      => $toDate,
    'limit'        => $limit,
    'offset'       => $offset,
];

if ($modeFilter !== '') {
    $filterParams['is_test_mode'] = $modeFilter;
}

// Fetch transactions with strict tenant isolation
$txData = $clientModel->getClientTransactions($clientId, $filterParams);
$totalRecords = $txData['total'];
$transactions = $txData['rows'];
$totalPages   = ceil($totalRecords / $limit);

// Build export query string
$exportQuery = http_build_query([
    'status'       => $statusFilter,
    'mode'         => $modeFilter,
    'search'       => $search,
    'from_date'    => $fromDate,
    'to_date'      => $toDate,
]);
?>

<!-- Action & Header Bar -->
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
    <div>
        <h1 style="font-size: 1.35rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">Transactions History</h1>
        <p style="font-size: 0.84rem; color: var(--text-muted); margin: 0;">
            Browse, search, and audit all payments routed through your merchant account.
        </p>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
        <a href="export_report.php?<?= $exportQuery ?>" class="btn btn-secondary btn-sm" title="Export matching transactions to CSV format">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            Export Filtered CSV
        </a>
    </div>
</div>

<!-- Filter Box -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 18px 20px;">
        <form method="GET" action="transactions.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) 120px; gap: 14px; align-items: end;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.76rem;">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Tx ID, Order, Email, Phone..." value="<?= htmlspecialchars($search) ?>" style="font-size: 0.82rem; padding: 7px 10px;">
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.76rem;">Status</label>
                <select name="status" class="form-control" style="font-size: 0.82rem; padding: 7px 10px;">
                    <option value="ALL" <?= $statusFilter === 'ALL' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="SUCCESS" <?= $statusFilter === 'SUCCESS' ? 'selected' : '' ?>>SUCCESS</option>
                    <option value="PENDING" <?= $statusFilter === 'PENDING' ? 'selected' : '' ?>>PENDING</option>
                    <option value="FAILED" <?= $statusFilter === 'FAILED' ? 'selected' : '' ?>>FAILED</option>
                    <option value="REFUNDED" <?= $statusFilter === 'REFUNDED' ? 'selected' : '' ?>>REFUNDED</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.76rem;">Environment Mode</label>
                <select name="mode" class="form-control" style="font-size: 0.82rem; padding: 7px 10px;">
                    <option value="" <?= $modeFilter === '' ? 'selected' : '' ?>>All Modes</option>
                    <option value="0" <?= $modeFilter === 0 ? 'selected' : '' ?>>Live Mode</option>
                    <option value="1" <?= $modeFilter === 1 ? 'selected' : '' ?>>Test / Sandbox</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.76rem;">From Date</label>
                <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($fromDate) ?>" style="font-size: 0.82rem; padding: 7px 10px;">
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.76rem;">To Date</label>
                <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($toDate) ?>" style="font-size: 0.82rem; padding: 7px 10px;">
            </div>

            <div style="display: flex; gap: 6px;">
                <button type="submit" class="btn btn-primary btn-sm" style="flex: 1; padding: 8px;">Filter</button>
                <a href="transactions.php" class="btn btn-secondary btn-sm" style="padding: 8px 10px;" title="Reset filters">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Transactions Table Card -->
<div class="card">
    <div class="card-header" style="padding: 14px 20px;">
        <div style="font-size: 0.85rem; color: var(--text-muted);">
            Showing <strong style="color: var(--text-main);"><?= $totalRecords > 0 ? ($offset + 1) : 0 ?></strong> to <strong style="color: var(--text-main);"><?= min($offset + $limit, $totalRecords) ?></strong> of <strong style="color: var(--text-main);"><?= number_format($totalRecords) ?></strong> records
        </div>
        <div>
            <span style="font-size: 0.8rem; color: var(--text-muted);">Per Page:</span>
            <a href="?<?= http_build_query(array_merge($_GET, ['limit' => 20, 'page' => 1])) ?>" style="font-size: 0.8rem; margin: 0 4px; <?= $limit == 20 ? 'font-weight:700; color:var(--primary);' : 'color:var(--text-muted);' ?>">20</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['limit' => 50, 'page' => 1])) ?>" style="font-size: 0.8rem; margin: 0 4px; <?= $limit == 50 ? 'font-weight:700; color:var(--primary);' : 'color:var(--text-muted);' ?>">50</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['limit' => 100, 'page' => 1])) ?>" style="font-size: 0.8rem; margin: 0 4px; <?= $limit == 100 ? 'font-weight:700; color:var(--primary);' : 'color:var(--text-muted);' ?>">100</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="portal-table">
            <thead>
                <tr>
                    <th>Vortex TX ID</th>
                    <th>Razorpay IDs</th>
                    <th>Customer</th>
                    <th>Event / Purpose</th>
                    <th>Mode</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 48px 16px; color: var(--text-muted);">
                            <div style="font-size: 1.1rem; font-weight: 600; margin-bottom: 6px; color: var(--text-main);">No matching transactions found</div>
                            <p style="font-size: 0.85rem; max-width: 400px; margin: 0 auto 14px;">Try modifying your search criteria or resetting filters to see your complete transaction list.</p>
                            <a href="transactions.php" class="btn btn-secondary btn-sm">Reset Filters</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $tx): ?>
                        <?php
                        $status = strtoupper($tx['status'] ?? 'PENDING');
                        $badgeClass = 'badge-default';
                        if ($status === 'SUCCESS') $badgeClass = 'badge-success';
                        elseif ($status === 'FAILED') $badgeClass = 'badge-failed';
                        elseif ($status === 'PENDING') $badgeClass = 'badge-pending';
                        elseif ($status === 'REFUNDED') $badgeClass = 'badge-refunded';

                        $isTest = (int)($tx['is_test_mode'] ?? 0) === 1;
                        ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <a href="transaction_details.php?id=<?= urlencode($tx['vortex_transaction_id']) ?>" class="font-mono" style="font-size: 0.82rem; font-weight: 700; color: var(--primary); text-decoration: none;">
                                        <?= htmlspecialchars(substr($tx['vortex_transaction_id'], 0, 18)) ?>...
                                    </a>
                                    <button type="button" class="btn-copy" onclick="copyToClipboard('<?= htmlspecialchars($tx['vortex_transaction_id']) ?>', this)" title="Copy ID">📋</button>
                                </div>
                            </td>
                            <td>
                                <div class="font-mono" style="font-size: 0.74rem; color: #334155;">
                                    <?= htmlspecialchars($tx['razorpay_order_id'] ?: '–') ?>
                                </div>
                                <div class="font-mono" style="font-size: 0.70rem; color: var(--text-muted);">
                                    <?= htmlspecialchars($tx['razorpay_payment_id'] ?: '–') ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600; font-size: 0.84rem;"><?= htmlspecialchars($tx['customer_name'] ?? '–') ?></div>
                                <div style="font-size: 0.74rem; color: var(--text-muted);"><?= htmlspecialchars($tx['customer_email'] ?? '') ?></div>
                                <?php if (!empty($tx['customer_mobile'])): ?>
                                    <div style="font-size: 0.72rem; color: var(--text-light);"><?= htmlspecialchars($tx['customer_mobile']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size: 0.82rem; font-weight: 500;"><?= htmlspecialchars($tx['event_name'] ?? ('Event #' . ($tx['event_id'] ?? '–'))) ?></div>
                                <?php if (!empty($tx['department'])): ?>
                                    <div style="font-size: 0.72rem; color: var(--text-muted);"><?= htmlspecialchars($tx['department']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isTest): ?>
                                    <span class="badge" style="background: #fef3c7; color: #b45309;">TEST</span>
                                <?php else: ?>
                                    <span class="badge" style="background: #dcfce7; color: #15803d;">LIVE</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight: 700; white-space: nowrap;">
                                ₹<?= number_format((float)($tx['amount'] ?? 0), 2) ?>
                            </td>
                            <td>
                                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span>
                            </td>
                            <td style="font-size: 0.78rem; color: var(--text-muted); white-space: nowrap;">
                                <?= date('Y-m-d H:i', strtotime($tx['created_at'])) ?>
                            </td>
                            <td style="text-align: right;">
                                <a href="transaction_details.php?id=<?= urlencode($tx['vortex_transaction_id']) ?>" class="btn btn-secondary btn-sm" style="padding: 4px 10px; font-size: 0.76rem;">
                                    View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div style="padding: 16px 20px; border-top: 1px solid var(--border-light); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
            <div style="font-size: 0.84rem; color: var(--text-muted);">
                Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong>
            </div>
            <div style="display: flex; gap: 6px;">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn btn-secondary btn-sm">&larr; Previous</a>
                <?php else: ?>
                    <button class="btn btn-secondary btn-sm" disabled style="opacity: 0.5; cursor: not-allowed;">&larr; Previous</button>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn btn-secondary btn-sm">Next &rarr;</a>
                <?php else: ?>
                    <button class="btn btn-secondary btn-sm" disabled style="opacity: 0.5; cursor: not-allowed;">Next &rarr;</button>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
