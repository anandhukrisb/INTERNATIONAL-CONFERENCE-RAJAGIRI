<?php
/**
 * ------------------------------------------------------------
 * dashboard.php – Merchant Portal Dashboard
 * ------------------------------------------------------------
 * Project : PayVortex Unified Payment Gateway
 * ------------------------------------------------------------
 */

$pageTitle = 'Merchant Dashboard';
$activeNav  = 'dashboard';

require_once __DIR__ . '/includes/header.php';

// Fetch client overview metrics
$metrics = $clientModel->getClientMetrics($clientId);

// Fetch recent 8 transactions
$recentTxResult = $clientModel->getClientTransactions($clientId, ['limit' => 8]);
$recentTransactions = $recentTxResult['rows'] ?? [];

$grossVolume = (float)($metrics['gross_volume'] ?? 0);
$volume30d   = (float)($metrics['volume_30d'] ?? 0);
$totalTx     = (int)($metrics['total_transactions'] ?? 0);
$successRate = (float)($metrics['success_rate'] ?? 0);
$avgTicket   = (float)($metrics['avg_ticket_size'] ?? 0);
$successCount = (int)($metrics['success_count'] ?? 0);
$pendingCount = (int)($metrics['pending_count'] ?? 0);
$failedCount  = (int)($metrics['failed_count'] ?? 0);
$refundedCount = (int)($metrics['refunded_count'] ?? 0);

$hasWebhook = !empty($currentClient['webhook_url']);
?>

<!-- Welcome Banner -->
<div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-radius: var(--radius-md); padding: 24px 28px; margin-bottom: 24px; color: #ffffff; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; border: 1px solid rgba(255,255,255,0.08); box-shadow: var(--shadow-sm);">
    <div>
        <div style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.8px; color: #94a3b8; font-weight: 700; margin-bottom: 4px;">Merchant Portal</div>
        <h1 style="font-size: 1.45rem; font-weight: 800; margin-bottom: 4px;"><?= htmlspecialchars($currentClient['client_name']) ?></h1>
        <p style="color: #cbd5e1; font-size: 0.88rem; margin: 0;">
            Account ID: <span class="font-mono" style="color: #93c5fd;">#<?= (int)$clientId ?></span> &bull; 
            Partner since <?= date('M Y', strtotime($currentClient['created_at'] ?? 'now')) ?>
        </p>
    </div>
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <a href="export_report.php" class="btn btn-secondary btn-sm" style="background: rgba(255,255,255,0.1); color: #ffffff; border-color: rgba(255,255,255,0.2);">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            Export All Data
        </a>
        <a href="api_keys.php" class="btn btn-primary btn-sm">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-6 6l7-7-2-2-7 7v4h4z"></path><circle cx="7.5" cy="16.5" r="5.5"></circle></svg>
            Integration Keys
        </a>
    </div>
</div>

<!-- Metrics Overview Grid -->
<div class="metrics-grid">
    <div class="metric-card success">
        <div class="metric-label">Gross Processed Volume</div>
        <div class="metric-value">₹<?= number_format($grossVolume, 2) ?></div>
        <div class="metric-meta">
            <span style="color: var(--success); font-weight: 600;">All-time captured</span>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-label">Last 30 Days Volume</div>
        <div class="metric-value">₹<?= number_format($volume30d, 2) ?></div>
        <div class="metric-meta">
            <span>Rolling 30 days</span>
        </div>
    </div>

    <div class="metric-card purple">
        <div class="metric-label">Success Rate</div>
        <div class="metric-value"><?= number_format($successRate, 1) ?>%</div>
        <div class="metric-meta">
            <span><?= $successCount ?> of <?= $totalTx ?> transactions</span>
        </div>
    </div>

    <div class="metric-card warning">
        <div class="metric-label">Average Ticket Size</div>
        <div class="metric-value">₹<?= number_format($avgTicket, 2) ?></div>
        <div class="metric-meta">
            <span>Per completed transaction</span>
        </div>
    </div>
</div>

<!-- Transaction Status Summary Strip -->
<div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 14px 20px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-around; flex-wrap: wrap; gap: 12px; font-size: 0.86rem;">
    <div style="display: flex; align-items: center; gap: 8px;">
        <span class="badge badge-success"><?= $successCount ?></span>
        <span style="color: var(--text-muted); font-weight: 500;">Successful</span>
    </div>
    <div style="height: 16px; width: 1px; background: var(--border);"></div>
    <div style="display: flex; align-items: center; gap: 8px;">
        <span class="badge badge-pending"><?= $pendingCount ?></span>
        <span style="color: var(--text-muted); font-weight: 500;">Pending</span>
    </div>
    <div style="height: 16px; width: 1px; background: var(--border);"></div>
    <div style="display: flex; align-items: center; gap: 8px;">
        <span class="badge badge-failed"><?= $failedCount ?></span>
        <span style="color: var(--text-muted); font-weight: 500;">Failed</span>
    </div>
    <div style="height: 16px; width: 1px; background: var(--border);"></div>
    <div style="display: flex; align-items: center; gap: 8px;">
        <span class="badge badge-refunded"><?= $refundedCount ?></span>
        <span style="color: var(--text-muted); font-weight: 500;">Refunded</span>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2.2fr 1fr; gap: 24px; align-items: start;">
    <!-- Recent Transactions Card -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div>
                <h3 class="card-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                    Recent Transactions
                </h3>
                <div class="card-subtitle">Latest payment activity processed under your API credentials</div>
            </div>
            <a href="transactions.php" class="btn btn-secondary btn-sm">View All (<?= $totalTx ?>)</a>
        </div>

        <div class="table-responsive">
            <table class="portal-table">
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Customer</th>
                        <th>Mode</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentTransactions)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 48px 16px; color: var(--text-muted);">
                                <div style="font-size: 1.1rem; font-weight: 600; margin-bottom: 6px; color: var(--text-main);">No transactions found yet</div>
                                <p style="font-size: 0.85rem; max-width: 360px; margin: 0 auto 16px;">Initiate a test payment or connect your store using your API keys to see live payments here.</p>
                                <a href="api_keys.php" class="btn btn-primary btn-sm">Get Integration Code</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentTransactions as $tx): ?>
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
                                    <div class="font-mono" style="font-size: 0.82rem; font-weight: 600; color: var(--primary);">
                                        <a href="transaction_details.php?id=<?= urlencode($tx['vortex_transaction_id']) ?>" style="text-decoration: none; color: inherit;">
                                            <?= htmlspecialchars(substr($tx['vortex_transaction_id'], 0, 18)) ?>...
                                        </a>
                                    </div>
                                    <div style="font-size: 0.72rem; color: var(--text-muted); font-family: monospace;">
                                        <?= htmlspecialchars($tx['razorpay_order_id'] ?? '–') ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; font-size: 0.84rem;"><?= htmlspecialchars($tx['customer_name'] ?? '–') ?></div>
                                    <div style="font-size: 0.74rem; color: var(--text-muted);"><?= htmlspecialchars($tx['customer_email'] ?? '') ?></div>
                                </td>
                                <td>
                                    <?php if ($isTest): ?>
                                        <span class="badge" style="background: #fef3c7; color: #b45309;">TEST</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: #dcfce7; color: #15803d;">LIVE</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight: 700;">
                                    ₹<?= number_format((float)($tx['amount'] ?? 0), 2) ?>
                                </td>
                                <td>
                                    <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span>
                                </td>
                                <td style="font-size: 0.78rem; color: var(--text-muted); white-space: nowrap;">
                                    <?= date('M d, H:i', strtotime($tx['created_at'])) ?>
                                </td>
                                <td style="text-align: right;">
                                    <a href="transaction_details.php?id=<?= urlencode($tx['vortex_transaction_id']) ?>" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 0.75rem;">
                                        Details &rarr;
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Side: Integration Snapshot -->
    <div style="display: flex; flex-direction: column; gap: 20px;">
        <!-- Integration Snapshot Card -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <h3 class="card-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                    Integration Snapshot
                </h3>
            </div>
            <div class="card-body" style="padding: 18px 20px; font-size: 0.85rem;">
                <div style="margin-bottom: 14px;">
                    <div style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: 700; margin-bottom: 4px;">Live API Key</div>
                    <div style="display: flex; align-items: center; justify-content: space-between; background: #f8fafc; padding: 6px 10px; border-radius: var(--radius-sm); border: 1px solid var(--border);">
                        <span class="font-mono" style="font-size: 0.8rem;"><?= htmlspecialchars(substr($currentClient['api_key'] ?? '', 0, 14)) ?>••••</span>
                        <button type="button" class="btn-copy" onclick="copyToClipboard('<?= htmlspecialchars($currentClient['api_key'] ?? '') ?>', this)">Copy</button>
                    </div>
                </div>

                <div style="margin-bottom: 14px;">
                    <div style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: 700; margin-bottom: 4px;">Webhook Endpoint</div>
                    <?php if ($hasWebhook): ?>
                        <div style="display: flex; align-items: center; gap: 6px; color: var(--success); font-weight: 600; font-size: 0.8rem;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            Active &amp; Configured
                        </div>
                        <div class="font-mono text-truncate" style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;" title="<?= htmlspecialchars($currentClient['webhook_url']) ?>">
                            <?= htmlspecialchars($currentClient['webhook_url']) ?>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; align-items: center; gap: 6px; color: var(--warning); font-weight: 600; font-size: 0.8rem;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                            No Webhook Configured
                        </div>
                        <p style="font-size: 0.76rem; color: var(--text-muted); margin: 4px 0 8px;">Configure webhooks to receive real-time server callbacks.</p>
                        <a href="webhooks.php" class="btn btn-secondary btn-sm" style="width: 100%;">Setup Webhook</a>
                    <?php endif; ?>
                </div>

                <div style="border-top: 1px solid var(--border-light); padding-top: 14px;">
                    <a href="api_keys.php" class="btn btn-secondary btn-sm" style="width: 100%; margin-bottom: 8px;">
                        Manage API Keys &amp; Secrets
                    </a>
                    <a href="webhooks.php" class="btn btn-secondary btn-sm" style="width: 100%;">
                        Webhook Settings &amp; Test Dispatch
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Developer Guide Card -->
        <div class="card" style="margin-bottom: 0; background: #0f172a; color: #ffffff; border-color: #334155;">
            <div class="card-header" style="border-color: rgba(255,255,255,0.08);">
                <h3 class="card-title" style="color: #ffffff; font-size: 0.95rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#60a5fa" stroke-width="2"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
                    API Quickstart
                </h3>
            </div>
            <div class="card-body" style="padding: 16px 20px; font-size: 0.8rem; color: #94a3b8;">
                <p style="margin-bottom: 10px;">Post payment requests to PayVortex unified endpoint:</p>
                <div class="font-mono" style="background: rgba(0,0,0,0.4); padding: 8px; border-radius: 4px; color: #93c5fd; font-size: 0.74rem; word-break: break-all;">
                    POST /vortex/api/v1/create_order.php
                </div>
                <div style="margin-top: 12px;">
                    <a href="api_keys.php#docs" style="color: #60a5fa; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                        View cURL &amp; PHP samples &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
