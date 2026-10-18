<?php
/**
 * ------------------------------------------------------------
 * metrics_cards.php
 * ------------------------------------------------------------
 * Reusable KPI metrics grid component for Vortex Admin.
 * Displays revenue, volume, event counts, and connected clients.
 * ------------------------------------------------------------
 */

$base = $adminBase ?? '';
?>
<div class="metrics-grid">
    <a href="<?= $base ?>payments/revenue.php" class="metric-card" id="metricCardTotalRevenue" title="View Event Revenue Breakdown & Payments">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="metric-label">Total Revenue</span>
            <div style="width: 32px; height: 32px; border-radius: 8px; background: var(--primary-light); display: flex; align-items: center; justify-content: center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary-blue);"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            </div>
        </div>
        <span class="metric-value amount">₹ <?= number_format($totalPaymentAmount ?? 0, 2) ?></span>
    </a>
    <a href="<?= $base ?>payments/list_payments.php?status=SUCCESS" class="metric-card" title="View successful payments">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="metric-label">Successful</span>
            <div style="width: 32px; height: 32px; border-radius: 8px; background: var(--success-bg); display: flex; align-items: center; justify-content: center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--success-text);"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            </div>
        </div>
        <span class="metric-value success"><?= number_format($successfulPayments ?? 0) ?></span>
    </a>
    <a href="<?= $base ?>payments/list_payments.php?status=FAILED" class="metric-card" title="View failed payments">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="metric-label">Failed</span>
            <div style="width: 32px; height: 32px; border-radius: 8px; background: var(--danger-bg); display: flex; align-items: center; justify-content: center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--danger-text);"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
            </div>
        </div>
        <span class="metric-value danger"><?= number_format($failedPayments ?? 0) ?></span>
    </a>
    <a href="<?= $base ?>events/index.php" class="metric-card" title="Manage active events">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="metric-label">Active Events</span>
            <div style="width: 32px; height: 32px; border-radius: 8px; background: var(--warning-bg); display: flex; align-items: center; justify-content: center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--warning-text);"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            </div>
        </div>
        <span class="metric-value"><?= number_format($activeEventsCount ?? 0) ?></span>
    </a>
    <a href="<?= $base ?>api_clients/index.php" class="metric-card" title="Manage connected merchants">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="metric-label">Connected Merchants</span>
            <div style="width: 32px; height: 32px; border-radius: 8px; background: var(--purple-bg); display: flex; align-items: center; justify-content: center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--purple-text);"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            </div>
        </div>
        <span class="metric-value"><?= number_format($totalClientsCount ?? 0) ?></span>
    </a>
</div>
