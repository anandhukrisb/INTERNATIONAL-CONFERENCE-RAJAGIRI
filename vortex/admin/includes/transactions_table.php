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
                        <th>Rpay Transaction ID</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Razorpay Payment ID</th>
                        <th>Event</th>
                        <th>Merchant</th>
                        <th>Timestamp</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentTx)): ?>
                        <tr><td colspan="8" style="text-align: center; padding: 28px; color: var(--text-gray);">No transactions recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentTx as $tx): ?>
                            <tr>
                                <td>
                                    <a href="<?= $base ?>payments/payment_details.php?vortex_transaction_id=<?= urlencode($tx['vortex_transaction_id']) ?>" class="font-mono" style="color: var(--primary-blue); font-weight: 700; text-decoration: underline;">
                                        <?= htmlspecialchars($tx['vortex_transaction_id']) ?>
                                    </a>
                                    <?php if (!empty($tx['customer_email'])): ?>
                                        <div style="font-size: 0.76rem; color: var(--text-gray); margin-top: 3px; display: flex; align-items: center; gap: 4px;" title="Customer / User">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.7;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                            <span style="max-width: 175px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($tx['customer_email']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight: 700; color: var(--text-dark);">₹<?= number_format($tx['amount'], 2) ?></td>
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
                                            <span class="badge badge-event" title="Latest Razorpay Event"><?= htmlspecialchars($tx['latest_event']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($tx['latest_reason']) && $tx['status'] !== 'SUCCESS'): ?>
                                        <div style="margin-top: 4px; font-size: 0.74rem; color: var(--danger-text); max-width: 220px; line-height: 1.25;">
                                            <?= htmlspecialchars($tx['latest_reason']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="font-mono"><?= htmlspecialchars($tx['razorpay_payment_id'] ?? 'N/A') ?></span></td>
                                <td><?= htmlspecialchars($tx['event_name'] ?? $tx['event_id'] ?? '–') ?></td>
                                <td><?= htmlspecialchars($tx['client_name'] ?? 'Default Client') ?></td>
                                <td style="font-size: 0.82rem; color: var(--text-gray); white-space: nowrap;"><?= htmlspecialchars($tx['created_at']) ?></td>
                                <td>
                                    <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                         <a href="<?= $base ?>payments/payment_details.php?vortex_transaction_id=<?= urlencode($tx['vortex_transaction_id']) ?>" class="btn btn-secondary btn-sm" title="View Full Status History & Audit Trail" style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.76rem; padding: 4px 8px;">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                            <span>Timeline (<?= (int)($tx['timeline_count'] ?? 0) ?>)</span>
                                        </a>
                                        <?php if ($tx['status'] === 'SUCCESS'): ?>
                                            <button type="button" class="btn btn-sm"
                                                    onclick="openRefundModal('<?= htmlspecialchars($tx['vortex_transaction_id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($tx['amount'], ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($tx['customer_email'] ?? ''), ENT_QUOTES) ?>')"
                                                    title="Process Refund via Razorpay"
                                                    style="background-color: #dc2626; color: white; border: none; font-weight: 600; padding: 4px 9px; border-radius: 4px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; font-size: 0.76rem;">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                                                <span>Refund</span>
                                            </button>
                                        <?php elseif ($tx['status'] === 'REFUNDED'): ?>
                                            <span class="badge badge-refunded" style="font-size: 0.74rem; padding: 4px 8px;">Refunded</span>
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
