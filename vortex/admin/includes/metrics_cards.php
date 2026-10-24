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
    <div class="metric-card" id="metricCardTotalRevenue" title="Total Gross Revenue" style="position: relative;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
            <div>
                <a href="<?= $base ?>payments/revenue.php" style="text-decoration: none; color: inherit;" title="View Event Revenue Breakdown">
                    <span class="metric-label" style="display: flex; align-items: center; gap: 4px;">
                        Total Revenue
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                    </span>
                </a>
                <div style="display: inline-flex; background: var(--bg-hover, #f1f5f9); border: 1px solid var(--border-color, #e2e8f0); border-radius: 6px; overflow: hidden; margin-top: 6px;">
                    <button type="button" id="revBtnAll" onclick="switchRevCurr('ALL')" style="padding: 2px 7px; font-size: 0.7rem; font-weight: 700; border: none; cursor: pointer; transition: all 0.15s ease;">All</button>
                    <button type="button" id="revBtnINR" onclick="switchRevCurr('INR')" style="padding: 2px 7px; font-size: 0.7rem; font-weight: 700; border: none; border-left: 1px solid var(--border-color, #e2e8f0); cursor: pointer; transition: all 0.15s ease;">₹ INR</button>
                    <button type="button" id="revBtnUSD" onclick="switchRevCurr('USD')" style="padding: 2px 7px; font-size: 0.7rem; font-weight: 700; border: none; border-left: 1px solid var(--border-color, #e2e8f0); cursor: pointer; transition: all 0.15s ease;">$ USD</button>
                </div>
            </div>
            <a href="<?= $base ?>payments/revenue.php" style="width: 32px; height: 32px; border-radius: 8px; background: var(--primary-light); display: flex; align-items: center; justify-content: center; text-decoration: none; flex-shrink: 0;" title="Open Revenue Analytics">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary-blue);"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            </a>
        </div>
        <div style="margin-top: 10px;">
            <span class="metric-value amount" id="revAmountDisplay" style="font-size: 1.15rem; line-height: 1.3;"></span>
        </div>
    </div>
    <script>
    (function(){
        const inrAmt = <?= json_encode(number_format($totalRevenueINR ?? 0, 2)) ?>;
        const usdAmt = <?= json_encode(number_format($totalRevenueUSD ?? 0, 2)) ?>;
        const hasUSD = <?= json_encode(($totalRevenueUSD ?? 0) > 0) ?>;

        window.switchRevCurr = function(curr) {
            const d = document.getElementById("revAmountDisplay");
            const bA = document.getElementById("revBtnAll");
            const bI = document.getElementById("revBtnINR");
            const bU = document.getElementById("revBtnUSD");
            if (!d) return;

            const activeStyle = "background: var(--primary-blue, #1a73e8); color: #ffffff;";
            const inactiveStyle = "background: transparent; color: var(--text-gray, #64748b);";

            if (bA) bA.style.cssText = "padding:2px 7px; font-size:0.7rem; font-weight:700; border:none; cursor:pointer; " + (curr === 'ALL' ? activeStyle : inactiveStyle);
            if (bI) bI.style.cssText = "padding:2px 7px; font-size:0.7rem; font-weight:700; border:none; border-left:1px solid var(--border-color, #e2e8f0); cursor:pointer; " + (curr === 'INR' ? activeStyle : inactiveStyle);
            if (bU) bU.style.cssText = "padding:2px 7px; font-size:0.7rem; font-weight:700; border:none; border-left:1px solid var(--border-color, #e2e8f0); cursor:pointer; " + (curr === 'USD' ? activeStyle : inactiveStyle);

            if (curr === "INR") {
                d.innerHTML = '₹ ' + inrAmt + ' <span style="font-size:0.7rem; font-weight:600; color:var(--text-gray);">INR</span>';
            } else if (curr === "USD") {
                d.innerHTML = '<span style="color:#0284c7;">$ ' + usdAmt + '</span> <span style="font-size:0.7rem; font-weight:600; color:var(--text-gray);">USD</span>';
            } else {
                if (hasUSD) {
                    d.innerHTML = '<div style="display:flex; flex-direction:column; gap:2px;">' +
                        '<span>₹ ' + inrAmt + ' <span style="font-size:0.65rem; color:var(--text-gray); font-weight:500;">INR</span></span>' +
                        '<span style="font-size:0.92rem; color:#0284c7;">$ ' + usdAmt + ' <span style="font-size:0.65rem; color:var(--text-gray); font-weight:500;">USD</span></span>' +
                        '</div>';
                } else {
                    d.innerHTML = '₹ ' + inrAmt + ' <span style="font-size:0.7rem; font-weight:600; color:var(--text-gray);">INR</span>';
                }
            }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() { switchRevCurr('ALL'); });
        } else {
            switchRevCurr('ALL');
        }
    })();
    </script>
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
