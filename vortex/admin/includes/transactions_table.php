<?php
/**
 * ------------------------------------------------------------
 * transactions_table.php
 * ------------------------------------------------------------
 * Reusable transactions list and pagination component.
 * Supports status badges, customer attribution, Razorpay events,
 * transaction timeline modal/page links, and refund triggers.
 * ------------------------------------------------------------
 */

$base = $adminBase ?? '';
$buildTxUrl = function($p) {
    $params = $_GET;
    $params['page'] = $p;
    return '?' . http_build_query($params);
};
?>

<div class="section-card" id="transactions-component">
    <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; border-bottom: 1px solid var(--border-color); background: #fafbfc;">
        <h2 style="font-size: 0.95rem; font-weight: 700; color: var(--text-dark); margin: 0; display: flex; align-items: center; gap: 8px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary-blue);"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
            <?= htmlspecialchars($tableTitle ?? 'Recent Transactions') ?>
        </h2>
        <div style="display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 0.82rem; color: var(--text-gray);">
                <?php if (!empty($txTotalCount) && $txTotalCount > 0): ?>
                    Showing <strong><?= (int)$fromTx ?>–<?= (int)$toTx ?></strong> of <strong><?= number_format($txTotalCount) ?></strong>
                <?php else: ?>
                    0 transactions
                <?php endif; ?>
            </span>
            <a href="<?= $base ?>payments/list_payments.php" class="btn btn-secondary btn-sm" style="font-size: 0.76rem; padding: 4px 10px;">
                View All Payments
            </a>
        </div>
    </div>
    <div class="section-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Event Name</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentTx)): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 28px; color: var(--text-gray);">
                            <?= !empty($isFiltered) ? 'No transactions matching the selected filter criteria found. <a href="dashboard.php" style="color:var(--primary-blue); font-weight:600; text-decoration:underline; margin-left:6px;">Clear filters</a>' : 'No transactions recorded yet.' ?>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($recentTx as $tx): ?>
                            <?php
                            $txJson = htmlspecialchars(json_encode($tx, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr class="tx-overview-row"
                                tabindex="0"
                                role="button"
                                aria-label="View details for transaction <?= htmlspecialchars($tx['vortex_transaction_id']) ?>"
                                title="Click / tap to view transaction details & actions"
                                data-transaction-id="<?= htmlspecialchars($tx['vortex_transaction_id']) ?>"
                                data-tx='<?= $txJson ?>'
                                onclick="openTxOverviewModal(this)"
                                onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openTxOverviewModal(this);}">
                                
                                <!-- 1. Email -->
                                <td>
                                    <?= htmlspecialchars(!empty($tx['customer_email']) ? $tx['customer_email'] : '—') ?>
                                </td>

                                <!-- 2. Event Name -->
                                <td>
                                    <span style="font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars($tx['event_name'] ?? $tx['event_id'] ?? '–') ?></span>
                                    <?php if (!empty($tx['department'])): ?>
                                        <div style="font-size: 0.74rem; color: var(--text-gray); margin-top: 2px;">
                                            <?= htmlspecialchars($tx['department']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- 3. Amount -->
                                <td style="font-weight: 700; color: var(--text-dark); font-size: 0.95rem; white-space: nowrap;">
                                    <?php 
                                    $curr = strtoupper($tx['currency'] ?? 'INR');
                                    $currSymbol = ($curr === 'USD') ? '$' : '₹';
                                    ?>
                                    <span><?= $currSymbol ?><?= number_format((float)$tx['amount'], 2) ?></span>
                                    <?php if ($curr === 'USD'): ?>
                                        <span class="badge" style="font-size: 0.65rem; padding: 2px 5px; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; margin-left: 3px;" title="International USD Payment">USD</span>
                                    <?php endif; ?>
                                </td>

                                <!-- 4. Status -->
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
                                            <span class="badge badge-event" style="font-size: 0.68rem; padding: 2px 5px;" title="Latest Gateway Event"><?= htmlspecialchars($tx['latest_event']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($tx['latest_reason']) && $tx['status'] !== 'SUCCESS'): ?>
                                        <div style="margin-top: 4px; font-size: 0.72rem; color: var(--danger-text); max-width: 220px; line-height: 1.25;">
                                            <?= htmlspecialchars($tx['latest_reason']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- 5. Timestamp -->
                                <td style="font-size: 0.82rem; color: var(--text-gray); white-space: nowrap;">
                                    <div><?= date('M d, Y', strtotime($tx['created_at'])) ?></div>
                                    <div style="font-size: 0.74rem; color: var(--text-gray);"><?= date('H:i:s', strtotime($tx['created_at'])) ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Transactions Pagination Bar -->
        <?php if (!empty($txTotalCount) && $txTotalCount > 0): ?>
            <div class="pagination-wrapper" style="padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); background: #fafbfc; flex-wrap: wrap; gap: 10px;">
                <div class="pagination-info" style="font-size: 0.82rem; color: var(--text-gray);">
                    Showing <strong><?= (int)$fromTx ?>–<?= (int)$toTx ?></strong> of <strong><?= number_format($txTotalCount) ?></strong> transactions
                    <span style="color: var(--border-color); margin: 0 4px;">•</span>
                    Page <strong><?= (int)$txPage ?></strong> of <strong><?= (int)$txTotalPages ?></strong>
                </div>

                <?php if ($txTotalPages > 1): ?>
                    <div class="pagination-controls" style="display: flex; gap: 4px;">
                        <?php if ($txPage > 1): ?>
                            <a href="<?= htmlspecialchars($buildTxUrl(1)) ?>" class="pagination-btn pagination-nav-btn" title="First Page">« First</a>
                            <a href="<?= htmlspecialchars($buildTxUrl($txPage - 1)) ?>" class="pagination-btn pagination-nav-btn" title="Previous Page">‹ Prev</a>
                        <?php else: ?>
                            <span class="pagination-btn pagination-nav-btn disabled" aria-disabled="true">« First</span>
                            <span class="pagination-btn pagination-nav-btn disabled" aria-disabled="true">‹ Prev</span>
                        <?php endif; ?>

                        <?php
                        $window = 2;
                        $startPage = max(1, $txPage - $window);
                        $endPage = min($txTotalPages, $txPage + $window);

                        if ($startPage > 1): ?>
                            <a href="<?= htmlspecialchars($buildTxUrl(1)) ?>" class="pagination-btn">1</a>
                            <?php if ($startPage > 2): ?>
                                <span class="pagination-ellipsis">…</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                            <?php if ($p === $txPage): ?>
                                <span class="pagination-btn active" aria-current="page"><?= $p ?></span>
                            <?php else: ?>
                                <a href="<?= htmlspecialchars($buildTxUrl($p)) ?>" class="pagination-btn"><?= $p ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($endPage < $txTotalPages): ?>
                            <?php if ($endPage < $txTotalPages - 1): ?>
                                <span class="pagination-ellipsis">…</span>
                            <?php endif; ?>
                            <a href="<?= htmlspecialchars($buildTxUrl($txTotalPages)) ?>" class="pagination-btn"><?= $txTotalPages ?></a>
                        <?php endif; ?>

                        <?php if ($txPage < $txTotalPages): ?>
                            <a href="<?= htmlspecialchars($buildTxUrl($txPage + 1)) ?>" class="pagination-btn pagination-nav-btn" title="Next Page">Next ›</a>
                            <a href="<?= htmlspecialchars($buildTxUrl($txTotalPages)) ?>" class="pagination-btn pagination-nav-btn" title="Last Page">Last »</a>
                        <?php else: ?>
                            <span class="pagination-btn pagination-nav-btn disabled" aria-disabled="true">Next ›</span>
                            <span class="pagination-btn pagination-nav-btn disabled" aria-disabled="true">Last »</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/tx_overview_modal.php'; ?>

