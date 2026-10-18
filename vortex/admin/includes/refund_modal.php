<?php
/**
 * ------------------------------------------------------------
 * refund_modal.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway - Admin Panel
 * Purpose : Reusable modal component for initiating full or partial
 *           refunds across Vortex Admin pages.
 * ------------------------------------------------------------
 */
$adminBaseForModal = isset($adminBase) ? $adminBase : '';
?>

<!-- Vortex Refund Modal -->
<div class="modal-overlay" id="vortexRefundModal" role="dialog" aria-modal="true" aria-labelledby="vortexRefundModalTitle" style="z-index: 9999;">
    <div class="modal-box" style="max-width: 500px; border-radius: 12px; box-shadow: 0 20px 40px rgba(0,0,0,0.22); overflow: hidden;">
        
        <div class="modal-header" style="background: #fef2f2; border-bottom: 1px solid #fee2e2; padding: 18px 24px; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 38px; height: 38px; border-radius: 50%; background: #fee2e2; display: flex; align-items: center; justify-content: center;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#991b1b" stroke-width="2.5"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                </div>
                <div>
                    <h2 id="vortexRefundModalTitle" style="font-size: 1.15rem; font-weight: 700; color: #991b1b; margin: 0;">Issue Payment Refund</h2>
                    <p style="font-size: 0.78rem; color: #b91c1c; margin: 0;">Direct via Razorpay Gateway</p>
                </div>
            </div>
            <button type="button" class="modal-close" onclick="closeRefundModal()" style="font-size: 1.5rem; color: #991b1b; background: none; border: none; cursor: pointer;">&times;</button>
        </div>

        <form id="vortexRefundForm" onsubmit="handleRefundFormSubmit(event)">
            <input type="hidden" id="rfTxnId" name="vortex_transaction_id" value="">
            
            <div class="modal-body" style="padding: 20px 24px;">
                <!-- Error Banner in Modal -->
                <div id="rfErrorAlert" style="display: none; background: #fef2f2; border: 1px solid #f87171; border-radius: 6px; padding: 10px 14px; margin-bottom: 16px; font-size: 0.85rem; color: #b91c1c;"></div>
                
                <!-- Success Banner in Modal -->
                <div id="rfSuccessAlert" style="display: none; background: #ecfdf5; border: 1px solid #34d399; border-radius: 6px; padding: 10px 14px; margin-bottom: 16px; font-size: 0.85rem; color: #065f46;"></div>

                <!-- Transaction Summary Card -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; margin-bottom: 18px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; color: #64748b;">Transaction ID</span>
                        <span id="rfTxnIdDisplay" class="font-mono" style="font-weight: 700; color: #1e293b; font-size: 0.85rem;"></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; color: #64748b;">Customer / User</span>
                        <span id="rfCustomerDisplay" style="color: #334155; font-size: 0.85rem; font-weight: 500;"></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; color: #64748b;">Total Paid</span>
                        <span id="rfTotalPaidDisplay" style="color: #0f172a; font-weight: 800; font-size: 1.05rem;"></span>
                    </div>
                </div>

                <!-- Refund Amount Field -->
                <div class="form-group" style="margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label for="rfAmountInput" style="font-weight: 600; font-size: 0.88rem; color: #1e293b; margin: 0;">Refund Amount (₹)</label>
                        <button type="button" id="rfFullAmountBtn" onclick="setFullRefundAmount()" style="background: none; border: none; color: #2563eb; font-size: 0.75rem; font-weight: 600; cursor: pointer; text-decoration: underline;">
                            Reset to Full
                        </button>
                    </div>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-weight: 700; color: #64748b;">₹</span>
                        <input type="number" step="0.01" min="0.01" id="rfAmountInput" name="amount" class="form-control" required style="padding-left: 28px; font-size: 1.05rem; font-weight: 700; color: #0f172a;" placeholder="0.00">
                    </div>
                    <span style="font-size: 0.75rem; color: #64748b; margin-top: 4px; display: block;">Partial refunds are fully supported by entering a lower amount.</span>
                </div>

                <!-- Reason Selector -->
                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="rfReasonSelect" style="font-weight: 600; font-size: 0.88rem; color: #1e293b; margin-bottom: 6px; display: block;">Reason for Refund</label>
                    <select id="rfReasonSelect" class="form-control" onchange="toggleCustomReason(this.value)" style="width: 100%;">
                        <option value="Customer requested cancellation">Customer requested cancellation</option>
                        <option value="Duplicate transaction / order">Duplicate transaction / order</option>
                        <option value="Event rescheduled or cancelled">Event rescheduled or cancelled</option>
                        <option value="Billing discrepancy resolved">Billing discrepancy resolved</option>
                        <option value="Custom">Other (Specify below)...</option>
                    </select>
                </div>

                <!-- Custom Reason Notes -->
                <div class="form-group" id="rfCustomReasonGroup" style="display: none; margin-bottom: 8px;">
                    <label for="rfCustomReasonInput" style="font-weight: 600; font-size: 0.82rem; color: #475569; margin-bottom: 4px; display: block;">Custom Reason Details</label>
                    <input type="text" id="rfCustomReasonInput" class="form-control" placeholder="Enter reason for audit logs...">
                </div>

                <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 10px 12px; display: flex; gap: 8px; align-items: flex-start; margin-top: 14px;">
                    <span style="font-size: 0.95rem; line-height: 1;">⚠️</span>
                    <span style="font-size: 0.76rem; color: #92400e; line-height: 1.35;">
                        This action will immediately initiate funds reversal via Razorpay back to the customer's original payment method. The client webhook will be dispatched automatically.
                    </span>
                </div>
            </div>

            <div class="modal-footer" style="padding: 16px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" onclick="closeRefundModal()" style="padding: 8px 16px; font-size: 0.88rem; font-weight: 600;">Cancel</button>
                <button type="submit" id="rfSubmitBtn" class="btn" style="background: #dc2626; color: white; border: none; padding: 8px 20px; font-size: 0.88rem; font-weight: 700; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(220, 38, 38, 0.25);">
                    <span id="rfBtnSpinner" style="display: none;">⏳</span>
                    <span id="rfBtnText">Confirm Refund</span>
                </button>
            </div>
        </form>

    </div>
</div>

<script>
let currentRefundMaxAmount = 0;

function openRefundModal(vortexTxnId, amount, customerEmail) {
    currentRefundMaxAmount = parseFloat(amount) || 0;
    
    document.getElementById('rfTxnId').value = vortexTxnId;
    document.getElementById('rfTxnIdDisplay').textContent = vortexTxnId;
    document.getElementById('rfCustomerDisplay').textContent = customerEmail ? customerEmail : 'Registered User';
    document.getElementById('rfTotalPaidDisplay').textContent = '₹' + currentRefundMaxAmount.toFixed(2);
    
    const amountInput = document.getElementById('rfAmountInput');
    amountInput.value = currentRefundMaxAmount.toFixed(2);
    amountInput.max = currentRefundMaxAmount;

    // Reset alerts
    const errAlert = document.getElementById('rfErrorAlert');
    const succAlert = document.getElementById('rfSuccessAlert');
    errAlert.style.display = 'none';
    errAlert.textContent = '';
    succAlert.style.display = 'none';
    succAlert.textContent = '';

    // Reset button
    const btn = document.getElementById('rfSubmitBtn');
    btn.disabled = false;
    document.getElementById('rfBtnSpinner').style.display = 'none';
    document.getElementById('rfBtnText').textContent = 'Confirm Refund';

    // Show modal
    const modal = document.getElementById('vortexRefundModal');
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
    
    setTimeout(() => {
        amountInput.focus();
        amountInput.select();
    }, 100);
}

function closeRefundModal() {
    const modal = document.getElementById('vortexRefundModal');
    modal.classList.remove('open');
    document.body.style.overflow = '';
}

function setFullRefundAmount() {
    const amountInput = document.getElementById('rfAmountInput');
    amountInput.value = currentRefundMaxAmount.toFixed(2);
}

function toggleCustomReason(val) {
    const customGroup = document.getElementById('rfCustomReasonGroup');
    if (val === 'Custom') {
        customGroup.style.display = 'block';
        document.getElementById('rfCustomReasonInput').focus();
    } else {
        customGroup.style.display = 'none';
    }
}

function handleRefundFormSubmit(e) {
    e.preventDefault();
    
    const txnId = document.getElementById('rfTxnId').value;
    const amount = parseFloat(document.getElementById('rfAmountInput').value);
    const reasonSelect = document.getElementById('rfReasonSelect').value;
    let reason = reasonSelect;
    if (reasonSelect === 'Custom') {
        const customText = document.getElementById('rfCustomReasonInput').value.trim();
        reason = customText ? customText : 'Admin requested custom refund';
    }

    const errAlert = document.getElementById('rfErrorAlert');
    const succAlert = document.getElementById('rfSuccessAlert');
    errAlert.style.display = 'none';
    succAlert.style.display = 'none';

    if (!amount || amount <= 0) {
        errAlert.textContent = 'Please enter a valid refund amount greater than 0.';
        errAlert.style.display = 'block';
        return;
    }

    if (amount > currentRefundMaxAmount) {
        errAlert.textContent = 'Refund amount cannot exceed original total (₹' + currentRefundMaxAmount.toFixed(2) + ').';
        errAlert.style.display = 'block';
        return;
    }

    // Set loading state
    const btn = document.getElementById('rfSubmitBtn');
    btn.disabled = true;
    document.getElementById('rfBtnSpinner').style.display = 'inline-block';
    document.getElementById('rfBtnText').textContent = 'Processing with Razorpay...';

    // Determine correct endpoint path
    let endpoint = 'payments/process_refund.php';
    if (window.location.pathname.includes('/payments/')) {
        endpoint = 'process_refund.php';
    }

    fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            vortex_transaction_id: txnId,
            amount: amount,
            reason: reason
        })
    })
    .then(async response => {
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Refund processing failed.');
        }
        return data;
    })
    .then(data => {
        succAlert.textContent = data.message || 'Refund processed successfully!';
        succAlert.style.display = 'block';
        document.getElementById('rfBtnSpinner').style.display = 'none';
        document.getElementById('rfBtnText').textContent = '✓ Refunded';
        
        // Reload after 1.2s to show updated table status and badges
        setTimeout(() => {
            window.location.reload();
        }, 1200);
    })
    .catch(err => {
        errAlert.textContent = err.message || 'An unexpected error occurred.';
        errAlert.style.display = 'block';
        btn.disabled = false;
        document.getElementById('rfBtnSpinner').style.display = 'none';
        document.getElementById('rfBtnText').textContent = 'Try Again';
    });
}

// Close on ESC key or clicking outside box
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeRefundModal();
    }
});
document.addEventListener('click', function(event) {
    const modal = document.getElementById('vortexRefundModal');
    if (event.target === modal) {
        closeRefundModal();
    }
});
</script>
