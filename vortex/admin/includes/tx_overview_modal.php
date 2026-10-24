<?php
/**
 * ------------------------------------------------------------
 * tx_overview_modal.php
 * ------------------------------------------------------------
 * Shared Transaction Overview Modal Component.
 * Used on dashboard.php and revenue.php (and any future ledger).
 * Provides two fragments:
 *   1. Details Overview (with copy buttons, metadata, status)
 *   2. Timeline & Audit Logs (asynchronous AJAX lifecycle stream)
 * Includes Issue Refund button trigger and full dark mode support.
 * ------------------------------------------------------------
 */

$modalBase = isset($adminBase) ? $adminBase : '';
?>

<!-- ============================================================ -->
<!-- Transaction Details & Timeline Modal -->
<!-- ============================================================ -->
<div class="modal-overlay" id="txOverviewModal" role="dialog" aria-modal="true" aria-labelledby="txOverviewModalTitle" style="z-index: 9998;">
    <div class="modal-box modal-lg" id="txOverviewModalBox" style="max-width: 680px; width: 100%; height: 575px; max-height: 88vh; min-height: 420px; border-radius: 12px; box-shadow: 0 20px 45px rgba(0,0,0,0.25); overflow: hidden; display: flex; flex-direction: column;">
        
        <!-- Modal Top Header -->
        <div class="modal-header" style="background: var(--bg-hover); border-bottom: 1px solid var(--border-color); padding: 16px 22px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: var(--primary-light); display: flex; align-items: center; justify-content: center; color: var(--primary-blue);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                </div>
                <div>
                    <h2 id="txOverviewModalTitle" style="font-size: 1.05rem; font-weight: 700; color: var(--text-dark); margin: 0;">Transaction Overview</h2>
                    <span id="txModalSubtitle" class="font-mono" style="display: inline-block; font-size: 0.76rem; color: var(--text-gray); background: var(--bg-card); border: 1px solid var(--border-color); padding: 1px 7px; border-radius: 4px; margin-top: 3px;"></span>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <span id="txModalBadge" class="badge"></span>
                <button type="button" class="modal-close" onclick="closeTxOverviewModal()" style="font-size: 1.5rem; background: none; border: none; cursor: pointer; color: var(--text-gray); line-height: 1;">&times;</button>
            </div>
        </div>

        <!-- Section / Fragment Switcher Tabs -->
        <div class="tx-modal-tabnav" role="tablist" aria-label="Transaction Sections">
            <button type="button" role="tab" class="tx-tab-btn active" id="txTabBtnDetails" aria-selected="true" aria-controls="txFragmentDetails" onclick="switchTxModalTab('details')">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                <span>Details Overview</span>
            </button>
            <button type="button" role="tab" class="tx-tab-btn" id="txTabBtnTimeline" aria-selected="false" aria-controls="txFragmentTimeline" onclick="switchTxModalTab('timeline')">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                <span>Timeline &amp; Audit Logs</span>
                <span id="txTabTimelineBadge" class="tab-badge" style="display: none;">0</span>
            </button>
        </div>

        <!-- Modal Body: Fragment 1 & Fragment 2 -->
        <div class="modal-body" style="padding: 0; overflow-y: auto; flex: 1 1 0; min-height: 0;">
            
            <!-- Fragment 1: Two-Column Details -->
            <div id="txFragmentDetails" class="tx-fragment-panel active" role="tabpanel" aria-labelledby="txTabBtnDetails">
                <table class="modal-details-table">
                    <tbody>
                        <tr>
                            <td class="dt-label">Transaction ID</td>
                            <td class="dt-value font-mono">
                                <span id="dtModalTxId" style="font-weight: 700; color: var(--primary-blue);"></span>
                                <button type="button" class="btn-copy btn-copy-sm" id="btnCopyTxId" onclick="copyModalField('dtModalTxId', this)" style="margin-left: 8px; padding: 2px 7px; font-size: 0.72rem;">Copy</button>
                            </td>
                        </tr>
                        <tr>
                            <td class="dt-label">Customer Email</td>
                            <td class="dt-value" id="dtModalEmail" style="font-weight: 600;"></td>
                        </tr>
                        <tr>
                            <td class="dt-label">Customer Mobile</td>
                            <td class="dt-value" id="dtModalMobile"></td>
                        </tr>
                        <tr>
                            <td class="dt-label">Merchant / Client</td>
                            <td class="dt-value" id="dtModalMerchant"></td>
                        </tr>
                        <tr>
                            <td class="dt-label">Event Campaign</td>
                            <td class="dt-value" id="dtModalEvent" style="font-weight: 600;"></td>
                        </tr>
                        <tr>
                            <td class="dt-label">Department</td>
                            <td class="dt-value" id="dtModalDepartment"></td>
                        </tr>
                        <tr>
                            <td class="dt-label">Total Amount</td>
                            <td class="dt-value" id="dtModalAmount" style="font-size: 1.05rem; font-weight: 800; color: var(--text-dark);"></td>
                        </tr>
                        <tr>
                            <td class="dt-label">Payment Status</td>
                            <td class="dt-value" id="dtModalStatusContainer"></td>
                        </tr>
                        <tr>
                            <td class="dt-label">Razorpay Payment ID</td>
                            <td class="dt-value font-mono">
                                <span id="dtModalRzpPayment"></span>
                                <button type="button" class="btn-copy btn-copy-sm" id="btnCopyRzpId" onclick="copyModalField('dtModalRzpPayment', this)" style="margin-left: 8px; padding: 2px 7px; font-size: 0.72rem;">Copy</button>
                            </td>
                        </tr>
                        <tr>
                            <td class="dt-label">Razorpay Order ID</td>
                            <td class="dt-value font-mono">
                                <span id="dtModalRzpOrder"></span>
                                <button type="button" class="btn-copy btn-copy-sm" id="btnCopyRzpOrderId" onclick="copyModalField('dtModalRzpOrder', this)" style="margin-left: 8px; padding: 2px 7px; font-size: 0.72rem;">Copy</button>
                            </td>
                        </tr>
                        <tr>
                            <td class="dt-label">Latest Gateway Event</td>
                            <td class="dt-value" id="dtModalLatestEvent"></td>
                        </tr>
                        <tr>
                            <td class="dt-label">Status Reason / Note</td>
                            <td class="dt-value" id="dtModalReason" style="font-size: 0.82rem;"></td>
                        </tr>
                        <tr>
                            <td class="dt-label">Timestamp</td>
                            <td class="dt-value" id="dtModalTimestamp"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Fragment 2: Timeline & Audit Logs -->
            <div id="txFragmentTimeline" class="tx-fragment-panel" role="tabpanel" aria-labelledby="txTabBtnTimeline" style="display: none; padding: 20px;">
                
                <!-- Loading State -->
                <div id="txTimelineLoading" style="text-align: center; padding: 40px 20px; color: var(--text-gray);">
                    <div class="tx-spinner" style="margin: 0 auto 12px auto;"></div>
                    <div style="font-size: 0.85rem; font-weight: 500;">Loading sequential lifecycle &amp; audit history...</div>
                </div>

                <!-- Timeline Content -->
                <div id="txTimelineContent" style="display: none;">
                    
                    <!-- Section Header -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-dark); display: flex; align-items: center; gap: 7px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary-blue);"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                            <span>Status Lifecycle &amp; Gateway Trail</span>
                        </div>
                        <span id="txTimelineEventsCountText" style="font-size: 0.76rem; color: var(--text-gray);"></span>
                    </div>

                    <!-- Chronological Timeline Nodes -->
                    <div id="txTimelineList" class="tx-timeline-track">
                        <!-- Populated by JS -->
                    </div>

                    <!-- Admin Governance & Audit Logs -->
                    <div id="txAuditRecordsContainer" style="margin-top: 24px; display: none;">
                        <div style="font-weight: 700; font-size: 0.88rem; color: var(--text-dark); margin-bottom: 12px; display: flex; align-items: center; gap: 7px;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #8b5cf6;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                            <span>Governance &amp; Administrative Logs</span>
                        </div>
                        <div id="txAuditRecordsList" class="tx-audit-cards-list">
                            <!-- Populated by JS -->
                        </div>
                    </div>

                    <!-- Gateway Webhooks Callbacks -->
                    <div id="txWebhookRecordsContainer" style="margin-top: 22px; display: none;">
                        <div style="font-weight: 700; font-size: 0.88rem; color: var(--text-dark); margin-bottom: 12px; display: flex; align-items: center; gap: 7px;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #0ea5e9;"><circle cx="12" cy="12" r="2"></circle><path d="M16.2 7.8c2.3 2.3 2.3 6.1 0 8.5"></path><path d="M7.8 16.2c-2.3-2.3-2.3-6.1 0-8.5"></path></svg>
                            <span>Associated Gateway Webhooks</span>
                        </div>
                        <div id="txWebhookRecordsList" class="tx-webhook-cards-list">
                            <!-- Populated by JS -->
                        </div>
                    </div>

                </div>

            </div>

        </div>

        <!-- Modal Footer Actions -->
        <div class="modal-footer" style="padding: 14px 22px; border-top: 1px solid var(--border-color); background: var(--bg-hover); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; flex-shrink: 0;">
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <!-- Refund Action Button -->
                <button type="button" id="dtModalRefundBtn" class="btn btn-sm" style="display: none; background-color: #dc2626; color: white; border: none; font-weight: 600; padding: 6px 12px; border-radius: 4px; cursor: pointer; align-items: center; gap: 5px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                    <span>Issue Refund</span>
                </button>
            </div>

            <button type="button" class="btn btn-secondary btn-sm" onclick="closeTxOverviewModal()">
                Close
            </button>
        </div>

    </div>
</div>

<style>
.tx-overview-row {
    cursor: pointer;
    transition: background-color 0.15s ease;
}
.tx-overview-row:hover,
.tx-overview-row:focus {
    background-color: var(--primary-light) !important;
    outline: none;
}

/* Modal Tab Nav / Fragment Switcher */
.tx-modal-tabnav {
    display: flex;
    background: var(--bg-hover);
    border-bottom: 1px solid var(--border-color);
    padding: 0 16px;
    gap: 6px;
    flex-shrink: 0;
}
.tx-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-gray);
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    position: relative;
    bottom: -1px;
}
.tx-tab-btn:hover {
    color: var(--text-dark);
}
.tx-tab-btn.active {
    color: var(--primary-blue) !important;
    border-bottom: 2px solid var(--primary-blue) !important;
    background: transparent;
    font-weight: 700;
}
.tab-badge {
    background: var(--primary-light);
    color: var(--primary-blue);
    font-size: 0.7rem;
    padding: 1px 7px;
    border-radius: 10px;
    font-weight: 700;
    line-height: 1.4;
}

/* Fixed Consistent Modal Height & Smooth Internal Scroll */
#txOverviewModal .modal-box {
    height: 575px !important;
    max-height: 88vh !important;
    min-height: 420px !important;
    display: flex !important;
    flex-direction: column !important;
}
#txOverviewModal .modal-body {
    flex: 1 1 0 !important;
    min-height: 0 !important;
    overflow-y: auto !important;
    overscroll-behavior: contain;
}
#txOverviewModal .modal-body::-webkit-scrollbar {
    width: 6px;
}
#txOverviewModal .modal-body::-webkit-scrollbar-track {
    background: transparent;
}
#txOverviewModal .modal-body::-webkit-scrollbar-thumb {
    background: rgba(150, 150, 150, 0.35);
    border-radius: 4px;
}
#txOverviewModal .modal-body::-webkit-scrollbar-thumb:hover {
    background: rgba(150, 150, 150, 0.6);
}

/* Details Table */
.modal-details-table {
    width: 100%;
    border-collapse: collapse;
}
.modal-details-table tr {
    border-bottom: 1px solid var(--border-color);
}
.modal-details-table tr:last-child {
    border-bottom: none;
}
.modal-details-table .dt-label {
    width: 35%;
    padding: 10px 18px;
    font-size: 0.83rem;
    font-weight: 600;
    color: var(--text-gray);
    background: var(--bg-hover);
    border-right: 1px solid var(--border-color);
    vertical-align: middle;
}
.modal-details-table .dt-value {
    width: 65%;
    padding: 10px 18px;
    font-size: 0.86rem;
    color: var(--text-dark);
    word-break: break-word;
    vertical-align: middle;
}

/* Dark mode enhancements */
[data-theme="dark"] .modal-details-table .dt-label {
    background: #292a2d !important;
    border-color: #3c4043 !important;
    color: #9aa0a6 !important;
}
[data-theme="dark"] .modal-details-table .dt-value {
    color: #e8eaed !important;
    border-color: #3c4043 !important;
}
[data-theme="dark"] .modal-details-table tr {
    border-color: #3c4043 !important;
}
[data-theme="dark"] .tx-modal-tabnav {
    background: #292a2d !important;
    border-color: #3c4043 !important;
}
[data-theme="dark"] .tx-timeline-card,
[data-theme="dark"] .tx-sub-card {
    background: #292a2d !important;
    border-color: #3c4043 !important;
}

/* Spinner */
@keyframes txSpin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
.tx-spinner {
    width: 26px;
    height: 26px;
    border: 3px solid var(--border-color);
    border-top-color: var(--primary-blue);
    border-radius: 50%;
    animation: txSpin 0.75s linear infinite;
}

/* Timeline Track */
.tx-timeline-track {
    position: relative;
    padding-left: 24px;
    margin-left: 8px;
    border-left: 2px solid var(--border-color);
}
.tx-timeline-node {
    position: relative;
    margin-bottom: 18px;
}
.tx-timeline-node:last-child {
    margin-bottom: 0;
}
.tx-timeline-bullet {
    position: absolute;
    left: -31px;
    top: 4px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--bg-card);
    border: 2px solid var(--primary-blue);
    box-shadow: 0 0 0 2px var(--bg-card);
}
.tx-timeline-bullet.bullet-success {
    border-color: #16a34a;
    background: #16a34a;
}
.tx-timeline-bullet.bullet-danger {
    border-color: #dc2626;
    background: #dc2626;
}
.tx-timeline-bullet.bullet-pending {
    border-color: #f59e0b;
    background: #f59e0b;
}
.tx-timeline-bullet.bullet-refunded {
    border-color: #9333ea;
    background: #9333ea;
}
.tx-timeline-bullet.bullet-disputed {
    border-color: #ef4444;
    background: #ef4444;
}
.tx-timeline-card {
    background: var(--bg-hover);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 10px 14px;
    transition: border-color 0.15s ease;
}
.tx-timeline-card:hover {
    border-color: var(--primary-blue);
}

/* Audit Cards */
.tx-audit-cards-list,
.tx-webhook-cards-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.tx-sub-card {
    background: var(--bg-hover);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 0.8rem;
}
</style>

<script>
let currentOverviewTxId = '';
const txModalBase = <?= json_encode($modalBase) ?>;

function switchTxModalTab(tab) {
    const btnDetails = document.getElementById('txTabBtnDetails');
    const btnTimeline = document.getElementById('txTabBtnTimeline');
    const fragDetails = document.getElementById('txFragmentDetails');
    const fragTimeline = document.getElementById('txFragmentTimeline');

    if (!btnDetails || !btnTimeline || !fragDetails || !fragTimeline) return;

    if (tab === 'timeline') {
        btnDetails.classList.remove('active');
        btnDetails.setAttribute('aria-selected', 'false');
        btnTimeline.classList.add('active');
        btnTimeline.setAttribute('aria-selected', 'true');

        fragDetails.style.display = 'none';
        fragTimeline.style.display = 'block';
    } else {
        btnTimeline.classList.remove('active');
        btnTimeline.setAttribute('aria-selected', 'false');
        btnDetails.classList.add('active');
        btnDetails.setAttribute('aria-selected', 'true');

        fragTimeline.style.display = 'none';
        fragDetails.style.display = 'block';
    }

    const modalBody = document.querySelector('#txOverviewModal .modal-body');
    if (modalBody) {
        modalBody.scrollTop = 0;
    }
}

function openTxOverviewModal(row) {
    if (!row) return;
    const rawData = row.getAttribute('data-tx');
    if (!rawData) return;

    let tx;
    try {
        tx = JSON.parse(rawData);
    } catch(e) {
        console.error('Invalid tx json', e);
        return;
    }

    const modal = document.getElementById('txOverviewModal');
    if (!modal) return;

    // Reset to Details fragment
    switchTxModalTab('details');

    // 1. Transaction ID & Subtitle
    const txId = tx.vortex_transaction_id || '—';
    currentOverviewTxId = txId;
    document.getElementById('dtModalTxId').textContent = txId;
    document.getElementById('txModalSubtitle').textContent = txId;

    // 2. Email & Mobile
    document.getElementById('dtModalEmail').textContent = tx.customer_email || 'Not Provided';
    document.getElementById('dtModalMobile').textContent = tx.customer_mobile || '—';

    // 3. Merchant & Event Info
    document.getElementById('dtModalMerchant').textContent = tx.client_name ? (tx.client_name + (tx.api_client_id ? ' (ID: #' + tx.api_client_id + ')' : '')) : 'Default Client';
    document.getElementById('dtModalEvent').textContent = tx.event_name || tx.event_id || '—';
    document.getElementById('dtModalDepartment').textContent = tx.department || '—';

    // 4. Amount
    const curr = (tx.currency || 'INR').toUpperCase();
    const sym = curr === 'USD' ? '$' : '₹';
    const loc = curr === 'USD' ? 'en-US' : 'en-IN';
    const amt = parseFloat(tx.amount || 0).toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('dtModalAmount').textContent = sym + ' ' + amt + ' ' + curr;

    // 5. Status & Badge
    let bClass = 'badge-pending';
    let displayStatus = tx.status || 'PENDING';
    if (tx.status === 'PENDING') displayStatus = 'UNPAID';
    if (tx.status === 'SUCCESS') bClass = 'badge-success';
    if (tx.status === 'FAILED') bClass = 'badge-danger';
    if (tx.status === 'REFUNDED') bClass = 'badge-refunded';
    if (tx.status === 'DISPUTED') bClass = 'badge-disputed';

    const badgeHeader = document.getElementById('txModalBadge');
    badgeHeader.className = 'badge ' + bClass;
    badgeHeader.textContent = displayStatus;

    const statusContainer = document.getElementById('dtModalStatusContainer');
    statusContainer.innerHTML = '<span class="badge ' + bClass + '">' + displayStatus + '</span>';

    // 6. Razorpay Payment & Order ID
    const rzpPayId = tx.razorpay_payment_id || '';
    document.getElementById('dtModalRzpPayment').textContent = rzpPayId || '—';
    const copyRzpBtn = document.getElementById('btnCopyRzpId');
    if (copyRzpBtn) copyRzpBtn.style.display = (rzpPayId && rzpPayId !== '—') ? 'inline-block' : 'none';

    const rzpOrderId = tx.razorpay_order_id || '';
    document.getElementById('dtModalRzpOrder').textContent = rzpOrderId || '—';
    const copyRzpOrderBtn = document.getElementById('btnCopyRzpOrderId');
    if (copyRzpOrderBtn) copyRzpOrderBtn.style.display = (rzpOrderId && rzpOrderId !== '—') ? 'inline-block' : 'none';

    // 7. Latest Gateway Event & Reason
    document.getElementById('dtModalLatestEvent').innerHTML = tx.latest_event 
        ? '<span class="badge badge-event" style="font-size: 0.72rem; padding: 2px 6px;">' + tx.latest_event + '</span>' 
        : '<span style="color: var(--text-gray); font-style: italic;">None recorded</span>';

    document.getElementById('dtModalReason').textContent = tx.latest_reason || '—';

    // 8. Timestamp
    document.getElementById('dtModalTimestamp').textContent = tx.created_at || '—';

    // 9. Action Buttons Setup
    const refundBtn = document.getElementById('dtModalRefundBtn');
    if (refundBtn) {
        if (tx.status === 'SUCCESS') {
            refundBtn.style.display = 'inline-flex';
            refundBtn.onclick = function() {
                closeTxOverviewModal();
                if (typeof openRefundModal === 'function') {
                    openRefundModal(txId, tx.amount, tx.customer_email || '');
                }
            };
        } else {
            refundBtn.style.display = 'none';
        }
    }

    // Open modal window
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';

    // Asynchronously load Timeline & Audit Logs for Fragment 2
    loadTxTimelineData(txId, txModalBase);
}

function loadTxTimelineData(txId, base) {
    const loadingElem = document.getElementById('txTimelineLoading');
    const contentElem = document.getElementById('txTimelineContent');
    const badgeElem = document.getElementById('txTabTimelineBadge');
    const listElem = document.getElementById('txTimelineList');
    const auditContainer = document.getElementById('txAuditRecordsContainer');
    const auditList = document.getElementById('txAuditRecordsList');
    const webhookContainer = document.getElementById('txWebhookRecordsContainer');
    const webhookList = document.getElementById('txWebhookRecordsList');

    if (loadingElem) loadingElem.style.display = 'block';
    if (contentElem) contentElem.style.display = 'none';
    if (badgeElem) badgeElem.style.display = 'none';

    fetch(base + 'tx_timeline_ajax.php?vortex_transaction_id=' + encodeURIComponent(txId))
        .then(res => res.json())
        .then(data => {
            if (currentOverviewTxId !== txId) return; // stale request guard
            if (!data.success) {
                if (loadingElem) loadingElem.innerHTML = '<div style="color:var(--danger-text); padding:20px;">' + (data.message || 'Failed to load timeline.') + '</div>';
                return;
            }

            // Update tab badge count
            const totalCount = data.total_events || (data.timeline ? data.timeline.length : 0);
            if (badgeElem) {
                badgeElem.textContent = totalCount;
                badgeElem.style.display = 'inline-block';
            }

            const countText = document.getElementById('txTimelineEventsCountText');
            if (countText) {
                countText.textContent = (data.timeline ? data.timeline.length : 0) + ' lifecycle event(s)';
            }

            // Ensure recent milestone is on top (descending order)
            if (data.timeline && Array.isArray(data.timeline)) {
                data.timeline.sort((a, b) => {
                    const tA = new Date(a.created_at || 0).getTime();
                    const tB = new Date(b.created_at || 0).getTime();
                    if (tA === tB) return (b.id || 0) - (a.id || 0);
                    return tB - tA;
                });
            }

            // Render Timeline items
            if (listElem) {
                if (!data.timeline || data.timeline.length === 0) {
                    listElem.innerHTML = '<div style="color:var(--text-gray); font-size:0.85rem; padding:12px 0;">No chronological events recorded yet for this transaction.</div>';
                } else {
                    listElem.innerHTML = data.timeline.map((item, idx) => {
                        let bulletClass = 'bullet-pending';
                        let bClass = 'badge-pending';
                        const st = (item.status || '').toUpperCase();
                        if (st === 'SUCCESS') { bulletClass = 'bullet-success'; bClass = 'badge-success'; }
                        else if (st === 'FAILED') { bulletClass = 'bullet-danger'; bClass = 'badge-danger'; }
                        else if (st === 'REFUNDED') { bulletClass = 'bullet-refunded'; bClass = 'badge-refunded'; }
                        else if (st === 'DISPUTED') { bulletClass = 'bullet-disputed'; bClass = 'badge-disputed'; }

                        const refId = item.payment_id || item.order_id || '';
                        const currSymbol = '₹';

                        return `
                            <div class="tx-timeline-node">
                                <div class="tx-timeline-bullet ${bulletClass}"></div>
                                <div class="tx-timeline-card"${idx === 0 ? ' style="border-left: 3px solid var(--primary-blue);"' : ''}>
                                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px; margin-bottom:6px;">
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <span class="badge badge-event" style="font-size:0.72rem; padding:2px 6px;">${escapeHtml(item.event_type || 'EVENT')}</span>
                                            <span class="badge ${bClass}" style="font-size:0.72rem; padding:2px 6px;">${escapeHtml(st === 'PENDING' ? 'UNPAID' : st)}</span>
                                            ${idx === 0 ? '<span class="badge" style="background:var(--primary-light); color:var(--primary-blue); font-size:0.68rem; padding:1px 5px; font-weight:700;">LATEST</span>' : ''}
                                        </div>
                                        <div style="font-size:0.74rem; color:var(--text-gray);">${escapeHtml(item.created_at || '')}</div>
                                    </div>
                                    <div style="font-size:0.83rem; color:var(--text-dark); margin-bottom:6px; line-height:1.35;">
                                        ${escapeHtml(item.reason || 'Status milestone updated')}
                                    </div>
                                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px; font-size:0.74rem; color:var(--text-gray);">
                                        <div>
                                            ${refId ? `<span class="font-mono" style="color:var(--primary-blue); font-weight:600;">${escapeHtml(refId)}</span>` : ''}
                                            ${item.amount ? `<span style="margin-left:6px; font-weight:700; color:var(--text-dark);">${currSymbol}${parseFloat(item.amount).toFixed(2)}</span>` : ''}
                                        </div>
                                        <span style="background:var(--bg-card); border:1px solid var(--border-color); padding:1px 6px; border-radius:4px; font-size:0.68rem;">
                                            ${escapeHtml(item.source || 'SYSTEM')}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('');
                }
            }

            // Render Audit Logs
            if (auditContainer && auditList) {
                if (data.audit_logs && data.audit_logs.length > 0) {
                    auditContainer.style.display = 'block';
                    auditList.innerHTML = data.audit_logs.map(log => `
                        <div class="tx-sub-card">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                                <div>
                                    <strong style="color:var(--text-dark);">${escapeHtml(log.action || 'Action')}</strong>
                                    <span style="color:var(--text-gray); font-size:0.74rem; margin-left:6px;">by <strong>${escapeHtml(log.actor_username || 'Admin')}</strong> (${escapeHtml(log.actor_role || 'Role')})</span>
                                </div>
                                <span style="font-size:0.72rem; color:var(--text-gray);">${escapeHtml(log.created_at || '')}</span>
                            </div>
                            <div style="color:var(--text-dark); font-size:0.78rem;">${escapeHtml(log.description || '')}</div>
                            ${log.ip_address ? `<div style="font-size:0.7rem; color:var(--text-gray); margin-top:2px;">IP: ${escapeHtml(log.ip_address)}</div>` : ''}
                        </div>
                    `).join('');
                } else {
                    auditContainer.style.display = 'none';
                }
            }

            // Render Webhooks
            if (webhookContainer && webhookList) {
                if (data.webhooks && data.webhooks.length > 0) {
                    webhookContainer.style.display = 'block';
                    webhookList.innerHTML = data.webhooks.map(wh => `
                        <div class="tx-sub-card">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <div style="display:flex; align-items:center; gap:6px;">
                                    <span class="badge badge-event" style="font-size:0.7rem; padding:1px 5px;">${escapeHtml(wh.event_type || '')}</span>
                                    <span class="badge ${wh.status === 'PROCESSED' ? 'badge-success' : 'badge-pending'}" style="font-size:0.7rem; padding:1px 5px;">${escapeHtml(wh.status || '')}</span>
                                    <span class="font-mono" style="font-size:0.74rem; color:var(--text-gray);">${escapeHtml(wh.payment_id || wh.order_id || '')}</span>
                                </div>
                                <span style="font-size:0.72rem; color:var(--text-gray);">${escapeHtml(wh.created_at || '')}</span>
                            </div>
                        </div>
                    `).join('');
                } else {
                    webhookContainer.style.display = 'none';
                }
            }

            if (loadingElem) loadingElem.style.display = 'none';
            if (contentElem) contentElem.style.display = 'block';
        })
        .catch(err => {
            console.error('Failed to fetch timeline', err);
            if (loadingElem) loadingElem.innerHTML = '<div style="color:var(--danger-text); padding:20px; font-size:0.85rem;">Failed to load timeline. Please check your network connection.</div>';
        });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function closeTxOverviewModal() {
    const modal = document.getElementById('txOverviewModal');
    if (modal) {
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }
}

function copyModalField(elemId, btn) {
    const elem = document.getElementById(elemId);
    if (!elem) return;
    const text = elem.textContent.trim();
    if (!text || text === '—') return;

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            const orig = btn.textContent;
            btn.textContent = '✓ Copied!';
            setTimeout(() => { btn.textContent = orig; }, 1500);
        }).catch(() => {
            fallbackCopyText(text, btn);
        });
    } else {
        fallbackCopyText(text, btn);
    }
}

function fallbackCopyText(text, btn) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    try {
        document.execCommand('copy');
        const orig = btn.textContent;
        btn.textContent = '✓ Copied!';
        setTimeout(() => { btn.textContent = orig; }, 1500);
    } catch(e) {
        console.error('Copy fallback failed', e);
    }
    document.body.removeChild(ta);
}

// Close on backdrop click & Escape key
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('txOverviewModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeTxOverviewModal();
            }
        });
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('txOverviewModal');
        if (modal && modal.classList.contains('open')) {
            closeTxOverviewModal();
        }
    }
});
</script>
