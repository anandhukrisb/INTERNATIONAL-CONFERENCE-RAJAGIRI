<?php
/**
 * ------------------------------------------------------------
 * transaction_details.php – Detailed Single Transaction View
 * ------------------------------------------------------------
 * Project : PayVortex Unified Payment Gateway
 * ------------------------------------------------------------
 */

$pageTitle = 'Transaction Details';
$activeNav  = 'transactions';

require_once __DIR__ . '/includes/header.php';

$txId = trim($_GET['id'] ?? $_GET['vortex_transaction_id'] ?? '');

if (empty($txId)) {
    header('Location: transactions.php');
    exit;
}

// Strict tenant isolation: only fetch if owned by this client
$txn = $clientModel->getClientTransactionDetails($clientId, $txId);

if (!$txn) {
    ?>
    <div style="max-width: 600px; margin: 40px auto; text-align: center;">
        <div class="card">
            <div class="card-body" style="padding: 40px 24px;">
                <div style="font-size: 2.5rem; margin-bottom: 12px;">🔒</div>
                <h2 style="font-size: 1.3rem; font-weight: 700; margin-bottom: 8px;">Transaction Not Found</h2>
                <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 24px;">
                    The requested transaction does not exist or does not belong to your merchant account.
                </p>
                <a href="transactions.php" class="btn btn-primary btn-sm">&larr; Return to Transactions</a>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$status = strtoupper($txn['status'] ?? 'PENDING');
$badgeClass = 'badge-default';
if ($status === 'SUCCESS') $badgeClass = 'badge-success';
elseif ($status === 'FAILED') $badgeClass = 'badge-failed';
elseif ($status === 'PENDING') $badgeClass = 'badge-pending';
elseif ($status === 'REFUNDED') $badgeClass = 'badge-refunded';

$isTest = (int)($txn['is_test_mode'] ?? 0) === 1;
$refunds = $txn['refunds'] ?? [];
$timeline = $txn['timeline'] ?? [];
?>

<!-- Back Navigation -->
<div style="margin-bottom: 16px;">
    <a href="transactions.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.84rem; display: inline-flex; align-items: center; gap: 6px; font-weight: 500;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
        Back to Transactions
    </a>
</div>

<!-- Header Title Bar -->
<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 24px;">
    <div>
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <h1 class="font-mono" style="font-size: 1.4rem; font-weight: 800; color: var(--text-main); margin: 0;">
                <?= htmlspecialchars($txn['vortex_transaction_id']) ?>
            </h1>
            <button type="button" class="btn-copy" onclick="copyToClipboard('<?= htmlspecialchars($txn['vortex_transaction_id']) ?>', this)">Copy ID</button>
            <span class="badge <?= $badgeClass ?>" style="font-size: 0.8rem; padding: 4px 10px;"><?= htmlspecialchars($status) ?></span>
            <?php if ($isTest): ?>
                <span class="badge" style="background: #fef3c7; color: #b45309;">TEST ENVIRONMENT</span>
            <?php else: ?>
                <span class="badge" style="background: #dcfce7; color: #15803d;">LIVE ENVIRONMENT</span>
            <?php endif; ?>
        </div>
        <p style="font-size: 0.82rem; color: var(--text-muted); margin: 4px 0 0;">
            Created on <?= date('F j, Y \a\t g:i A', strtotime($txn['created_at'])) ?>
        </p>
    </div>

    <div>
        <span style="font-size: 1.6rem; font-weight: 800; color: var(--text-main);">
            ₹<?= number_format((float)($txn['amount'] ?? 0), 2) ?>
        </span>
        <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;"> <?= htmlspecialchars($txn['currency'] ?? 'INR') ?></span>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1.9fr 1.1fr; gap: 24px; align-items: start;">
    <!-- Left Column: Payment & Gateway & Timeline -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        <!-- Payment & Gateway Info Card -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <h3 class="card-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                    Payment &amp; Gateway Identifiers
                </h3>
            </div>
            <div class="card-body" style="padding: 20px 24px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <div style="font-size: 0.74rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Vortex Internal ID</div>
                        <div class="font-mono" style="font-size: 0.86rem; color: var(--text-main); margin-top: 4px;">#<?= (int)$txn['id'] ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.74rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Payment Gateway</div>
                        <div style="font-size: 0.86rem; color: var(--text-main); font-weight: 600; margin-top: 4px;">Razorpay Unified</div>
                    </div>
                    <div>
                        <div style="font-size: 0.74rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Razorpay Order ID</div>
                        <div class="font-mono" style="font-size: 0.84rem; color: var(--text-main); margin-top: 4px; display: flex; align-items: center; gap: 6px;">
                            <span><?= htmlspecialchars($txn['razorpay_order_id'] ?: '–') ?></span>
                            <?php if (!empty($txn['razorpay_order_id'])): ?>
                                <button type="button" class="btn-copy" onclick="copyToClipboard('<?= htmlspecialchars($txn['razorpay_order_id']) ?>', this)">Copy</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 0.74rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Razorpay Payment ID</div>
                        <div class="font-mono" style="font-size: 0.84rem; color: var(--text-main); margin-top: 4px; display: flex; align-items: center; gap: 6px;">
                            <span><?= htmlspecialchars($txn['razorpay_payment_id'] ?: '–') ?></span>
                            <?php if (!empty($txn['razorpay_payment_id'])): ?>
                                <button type="button" class="btn-copy" onclick="copyToClipboard('<?= htmlspecialchars($txn['razorpay_payment_id']) ?>', this)">Copy</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($txn['error_message']) || !empty($txn['error_code'])): ?>
                    <div class="alert alert-error" style="margin-top: 18px; margin-bottom: 0;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        <div>
                            <strong>Gateway Failure Notice:</strong>
                            <?= htmlspecialchars($txn['error_message'] ?: 'Unknown error') ?>
                            <?php if (!empty($txn['error_code'])): ?>
                                <span class="font-mono" style="font-size: 0.75rem;">(Code: <?= htmlspecialchars($txn['error_code']) ?>)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Lifecycle Timeline Card -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <h3 class="card-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    Audit &amp; Lifecycle Timeline
                </h3>
            </div>
            <div class="card-body" style="padding: 24px;">
                <?php if (empty($timeline)): ?>
                    <!-- Fallback synthetic events based on current record -->
                    <div style="display: flex; gap: 14px; position: relative;">
                        <div style="display: flex; flex-direction: column; align-items: center;">
                            <div style="width: 12px; height: 12px; border-radius: 50%; background: var(--success); flex-shrink: 0;"></div>
                            <div style="width: 2px; flex: 1; background: var(--border); min-height: 30px;"></div>
                        </div>
                        <div style="padding-bottom: 20px;">
                            <div style="font-weight: 600; font-size: 0.85rem;">Order Initiated</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);"><?= date('Y-m-d H:i:s', strtotime($txn['created_at'])) ?></div>
                            <div style="font-size: 0.8rem; color: #475569; margin-top: 2px;">Vortex transaction created with status <?= htmlspecialchars($status) ?></div>
                        </div>
                    </div>
                    <?php if ($status === 'SUCCESS'): ?>
                        <div style="display: flex; gap: 14px;">
                            <div style="width: 12px; height: 12px; border-radius: 50%; background: var(--success); flex-shrink: 0; margin-top: 2px;"></div>
                            <div>
                                <div style="font-weight: 600; font-size: 0.85rem;">Payment Verified &amp; Captured</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= date('Y-m-d H:i:s', strtotime($txn['updated_at'] ?? $txn['created_at'])) ?></div>
                                <div style="font-size: 0.8rem; color: #475569; margin-top: 2px;">Signature verified. Payment captured via Razorpay.</div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 0;">
                        <?php foreach ($timeline as $idx => $step): ?>
                            <?php $isLast = ($idx === count($timeline) - 1); ?>
                            <div style="display: flex; gap: 14px; position: relative;">
                                <div style="display: flex; flex-direction: column; align-items: center;">
                                    <div style="width: 12px; height: 12px; border-radius: 50%; background: <?= strtolower($step['status'] ?? '') === 'failed' ? 'var(--danger)' : 'var(--success)' ?>; flex-shrink: 0; margin-top: 4px;"></div>
                                    <?php if (!$isLast): ?>
                                        <div style="width: 2px; flex: 1; background: var(--border); min-height: 30px;"></div>
                                    <?php endif; ?>
                                </div>
                                <div style="padding-bottom: <?= $isLast ? '0' : '20px' ?>;">
                                    <div style="font-weight: 600; font-size: 0.85rem; text-transform: capitalize;">
                                        <?= htmlspecialchars(str_replace('_', ' ', $step['event_name'] ?? $step['step_name'] ?? 'Timeline Event')) ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                                        <?= date('Y-m-d H:i:s', strtotime($step['created_at'])) ?>
                                    </div>
                                    <?php if (!empty($step['notes']) || !empty($step['description'])): ?>
                                        <div style="font-size: 0.8rem; color: #475569; margin-top: 3px;">
                                            <?= htmlspecialchars($step['notes'] ?? $step['description']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Customer & Event & Refunds -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        <!-- Customer Details Card -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <h3 class="card-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    Customer Details
                </h3>
            </div>
            <div class="card-body" style="padding: 20px 24px;">
                <div style="margin-bottom: 12px;">
                    <div style="font-size: 0.74rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Full Name</div>
                    <div style="font-size: 0.9rem; font-weight: 600; color: var(--text-main); margin-top: 2px;">
                        <?= htmlspecialchars($txn['customer_name'] ?: '–') ?>
                    </div>
                </div>

                <div style="margin-bottom: 12px;">
                    <div style="font-size: 0.74rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Email Address</div>
                    <div style="font-size: 0.85rem; color: var(--primary); margin-top: 2px;">
                        <?php if (!empty($txn['customer_email'])): ?>
                            <a href="mailto:<?= htmlspecialchars($txn['customer_email']) ?>" style="color: inherit; text-decoration: none;">
                                <?= htmlspecialchars($txn['customer_email']) ?>
                            </a>
                        <?php else: ?>
                            <span style="color: var(--text-muted);">–</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <div style="font-size: 0.74rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Mobile Phone</div>
                    <div style="font-size: 0.85rem; color: var(--text-main); margin-top: 2px;">
                        <?= htmlspecialchars($txn['customer_mobile'] ?: '–') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Event / Associated Service Card -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <h3 class="card-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    Event / Purpose
                </h3>
            </div>
            <div class="card-body" style="padding: 20px 24px;">
                <div style="margin-bottom: 12px;">
                    <div style="font-size: 0.74rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Event Title</div>
                    <div style="font-size: 0.9rem; font-weight: 600; color: var(--text-main); margin-top: 2px;">
                        <?= htmlspecialchars($txn['event_name'] ?: ('Event #' . ($txn['event_id'] ?? '–'))) ?>
                    </div>
                </div>

                <?php if (!empty($txn['department'])): ?>
                    <div style="margin-bottom: 12px;">
                        <div style="font-size: 0.74rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Department</div>
                        <div style="font-size: 0.85rem; color: var(--text-main); margin-top: 2px;">
                            <?= htmlspecialchars($txn['department']) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($txn['event_start_date'])): ?>
                    <div>
                        <div style="font-size: 0.74rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Event Schedule</div>
                        <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 2px;">
                            <?= date('M d, Y', strtotime($txn['event_start_date'])) ?>
                            <?php if (!empty($txn['event_end_date'])): ?>
                                &ndash; <?= date('M d, Y', strtotime($txn['event_end_date'])) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Refunds Card -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <h3 class="card-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"></polyline><polyline points="23 20 23 14 17 14"></polyline><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"></path></svg>
                    Refunds (<?= count($refunds) ?>)
                </h3>
            </div>
            <div class="card-body" style="padding: 20px 24px;">
                <?php if (empty($refunds)): ?>
                    <div style="font-size: 0.82rem; color: var(--text-muted); text-align: center; padding: 12px 0;">
                        No refunds have been processed for this transaction.
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php foreach ($refunds as $ref): ?>
                            <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px 14px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                    <span class="font-mono" style="font-size: 0.78rem; font-weight: 700;"><?= htmlspecialchars($ref['refund_id'] ?? ('REF#' . $ref['id'])) ?></span>
                                    <span class="badge badge-refunded" style="font-size: 0.7rem;"><?= htmlspecialchars($ref['status'] ?? 'PROCESSED') ?></span>
                                </div>
                                <div style="font-weight: 700; font-size: 0.88rem; color: var(--purple);">
                                    ₹<?= number_format((float)($ref['amount'] ?? 0), 2) ?>
                                </div>
                                <?php if (!empty($ref['reason'])): ?>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                                        Reason: <?= htmlspecialchars($ref['reason']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
