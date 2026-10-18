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
    <a href="<?= $base ?>payments/list_payments.php?status=SUCCESS" class="metric-card" title="View all successful transactions">
        <span class="metric-label">Total Revenue (Settled)</span>
        <span class="metric-value amount">₹ <?= number_format($totalPaymentAmount ?? 0, 2) ?></span>
    </a>
    <a href="<?= $base ?>payments/list_payments.php?status=SUCCESS" class="metric-card" title="View successful payments">
        <span class="metric-label">Successful Transactions</span>
        <span class="metric-value success"><?= number_format($successfulPayments ?? 0) ?></span>
    </a>
    <a href="<?= $base ?>payments/list_payments.php?status=FAILED" class="metric-card" title="View failed payments">
        <span class="metric-label">Failed Transactions</span>
        <span class="metric-value danger"><?= number_format($failedPayments ?? 0) ?></span>
    </a>
    <a href="<?= $base ?>events/index.php" class="metric-card" title="Manage active events">
        <span class="metric-label">Active Events</span>
        <span class="metric-value"><?= number_format($activeEventsCount ?? 0) ?></span>
    </a>
    <a href="<?= $base ?>api_clients/index.php" class="metric-card" title="Manage connected merchants">
        <span class="metric-label">Registered API Clients</span>
        <span class="metric-value"><?= number_format($totalClientsCount ?? 0) ?></span>
    </a>
</div>
