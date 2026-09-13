<?php
/**
 * ------------------------------------------------------------
 * callback_example.php
 * ------------------------------------------------------------
 * Example Developer Callback / Return Page
 *
 * Demonstrates:
 * 1. Receiving customer redirect from Vortex Gateway.
 * 2. Parsing status_code (200 = SUCCESS, 400 = FAILED).
 * 3. Displaying an animated, state-of-the-art Success / Failure modal.
 * 4. Cleaning query parameters with window.history.replaceState.
 * ------------------------------------------------------------
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer Callback & Order Status - Vortex Gateway</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-base: #0a0d14;
            --bg-card: rgba(17, 24, 39, 0.85);
            --border-subtle: rgba(255, 255, 255, 0.08);
            --border-accent: rgba(99, 102, 241, 0.4);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --emerald: #10b981;
            --emerald-glow: rgba(16, 185, 129, 0.25);
            --rose: #f43f5e;
            --rose-glow: rgba(244, 63, 94, 0.25);
            --indigo: #6366f1;
            --indigo-hover: #4f46e5;
            --font-main: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-code: 'JetBrains Mono', monospace;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-main);
            background: var(--bg-base);
            background-image: 
                radial-gradient(circle at 15% 20%, rgba(99, 102, 241, 0.12) 0%, transparent 40%),
                radial-gradient(circle at 85% 80%, rgba(6, 182, 212, 0.1) 0%, transparent 40%);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .container {
            width: 100%;
            max-width: 580px;
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 20px;
            padding: 2.5rem;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
            text-align: center;
        }

        .logo-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--indigo);
            background: rgba(99, 102, 241, 0.12);
            padding: 6px 14px;
            border-radius: 9999px;
            border: 1px solid rgba(99, 102, 241, 0.25);
            margin-bottom: 1.25rem;
            font-weight: 600;
        }

        h1 {
            font-size: 1.85rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 0.75rem;
        }

        p.desc {
            color: var(--text-secondary);
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .test-controls {
            display: flex;
            flex-direction: column;
            gap: 12px;
            background: rgba(0, 0, 0, 0.25);
            padding: 1.5rem;
            border-radius: 14px;
            border: 1px solid var(--border-subtle);
            margin-top: 1rem;
        }

        .test-title {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }

        .btn-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .btn {
            padding: 0.85rem 1.25rem;
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: var(--font-main);
        }

        .btn-success {
            background: #059669;
            color: #ffffff;
        }
        .btn-success:hover {
            background: #10b981;
            box-shadow: 0 0 20px var(--emerald-glow);
        }

        .btn-danger {
            background: #e11d48;
            color: #ffffff;
        }
        .btn-danger:hover {
            background: #f43f5e;
            box-shadow: 0 0 20px var(--rose-glow);
        }

        .btn-primary {
            background: var(--indigo);
            color: #ffffff;
            width: 100%;
            margin-top: 1.25rem;
        }
        .btn-primary:hover {
            background: var(--indigo-hover);
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.35);
        }

        /* --- Modal Overlay & Dialog --- */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(5, 8, 16, 0.78);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
            z-index: 9999;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .modal-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }

        .modal-dialog {
            background: #131b2e;
            border: 1px solid var(--border-subtle);
            width: 100%;
            max-width: 440px;
            border-radius: 24px;
            padding: 2.25rem 2rem;
            text-align: center;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.7);
            transform: scale(0.92) translateY(10px);
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
        }

        .modal-overlay.active .modal-dialog {
            transform: scale(1) translateY(0);
        }

        .status-icon-wrapper {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem auto;
            position: relative;
        }

        .status-icon-wrapper.success {
            background: rgba(16, 185, 129, 0.15);
            border: 2px solid var(--emerald);
            color: var(--emerald);
            box-shadow: 0 0 35px var(--emerald-glow);
        }

        .status-icon-wrapper.failed {
            background: rgba(244, 63, 94, 0.15);
            border: 2px solid var(--rose);
            color: var(--rose);
            box-shadow: 0 0 35px var(--rose-glow);
        }

        .status-icon-wrapper svg {
            width: 38px;
            height: 38px;
            stroke-width: 2.5;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .modal-subtitle {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 1.5rem;
        }

        .details-box {
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            font-family: var(--font-code);
            font-size: 0.85rem;
            text-align: left;
            margin-bottom: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .details-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .details-label {
            color: var(--text-secondary);
        }

        .details-value {
            color: var(--text-primary);
            font-weight: 600;
        }

        .code-badge {
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 600;
        }

        .code-badge.success {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .code-badge.failed {
            background: rgba(244, 63, 94, 0.2);
            color: #fb7185;
            border: 1px solid rgba(244, 63, 94, 0.3);
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="logo-badge">
            <span>●</span> Developer Callback Endpoint
        </div>
        <h1>Unified Payment Return Page</h1>
        <p class="desc">
            This page receives the redirect from Vortex Gateway. When Vortex redirects back with <code>?status_code=200</code> or <code>?status_code=400</code>, the appropriate modal activates automatically.
        </p>

        <div class="test-controls">
            <div class="test-title">Quick Simulation / Local Test</div>
            <div class="btn-group">
                <button class="btn btn-success" onclick="triggerTest(200)">
                    Simulate Status 200
                </button>
                <button class="btn btn-danger" onclick="triggerTest(400)">
                    Simulate Status 400
                </button>
            </div>
        </div>
    </div>

    <!-- Status Modal Dialog -->
    <div id="status-modal" class="modal-overlay">
        <div class="modal-dialog">
            <div id="status-icon" class="status-icon-wrapper">
                <!-- SVG Icon inserted dynamically -->
            </div>
            <h2 id="modal-title" class="modal-title">Payment Status</h2>
            <p id="modal-subtitle" class="modal-subtitle">Processing transaction response...</p>

            <div class="details-box">
                <div class="details-row">
                    <span class="details-label">Status Code</span>
                    <span id="detail-code" class="code-badge">--</span>
                </div>
                <div class="details-row">
                    <span class="details-label">Transaction ID</span>
                    <span id="detail-tx" class="details-value">--</span>
                </div>
                <div id="detail-amount-row" class="details-row" style="display: none;">
                    <span class="details-label">Amount</span>
                    <span id="detail-amount" class="details-value">--</span>
                </div>
                <div id="detail-datetime-row" class="details-row" style="display: none;">
                    <span class="details-label">Date & Time</span>
                    <span id="detail-datetime" class="details-value" style="font-size: 0.8rem;">--</span>
                </div>
                <div id="detail-msg-row" class="details-row" style="display: none;">
                    <span class="details-label">Reason</span>
                    <span id="detail-msg" class="details-value" style="font-size: 0.8rem; color: #fca5a5;">--</span>
                </div>
            </div>

            <button id="modal-action-btn" class="btn btn-primary" onclick="dismissModal()">
                Continue
            </button>
        </div>
    </div>

    <script>
        // Icons
        const ICON_SUCCESS = `
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
        `;

        const ICON_FAILED = `
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        `;

        function checkParamsAndDisplay() {
            const params = new URLSearchParams(window.location.search);
            const statusCodeRaw = params.get('status_code');
            const txId = params.get('vortex_transaction_id') || 'VTX_DEMO_0000';
            const amount = params.get('amount');
            const currency = params.get('currency') || 'INR';
            const dateTime = params.get('date_time');
            const message = params.get('message');

            if (!statusCodeRaw) {
                return; // No status code in URL; regular landing state
            }

            const statusCode = parseInt(statusCodeRaw, 10);
            renderModal(statusCode, txId, amount, currency, dateTime, message);
        }

        function renderModal(statusCode, txId, amount, currency, dateTime, message) {
            const modal = document.getElementById('status-modal');
            const iconWrapper = document.getElementById('status-icon');
            const title = document.getElementById('modal-title');
            const subtitle = document.getElementById('modal-subtitle');
            const detailCode = document.getElementById('detail-code');
            const detailTx = document.getElementById('detail-tx');
            const detailAmountRow = document.getElementById('detail-amount-row');
            const detailAmount = document.getElementById('detail-amount');
            const detailDatetimeRow = document.getElementById('detail-datetime-row');
            const detailDatetime = document.getElementById('detail-datetime');
            const detailMsgRow = document.getElementById('detail-msg-row');
            const detailMsg = document.getElementById('detail-msg');
            const actionBtn = document.getElementById('modal-action-btn');

            detailTx.textContent = txId;

            if (dateTime) {
                detailDatetimeRow.style.display = 'flex';
                detailDatetime.textContent = dateTime;
            } else {
                detailDatetimeRow.style.display = 'none';
            }

            if (statusCode === 200) {
                iconWrapper.className = 'status-icon-wrapper success';
                iconWrapper.innerHTML = ICON_SUCCESS;
                title.textContent = 'Payment Completed!';
                subtitle.textContent = 'Your payment was successfully verified and captured.';
                detailCode.className = 'code-badge success';
                detailCode.textContent = '200 OK';

                if (amount) {
                    detailAmountRow.style.display = 'flex';
                    detailAmount.textContent = `${currency} ${parseFloat(amount).toFixed(2)}`;
                } else {
                    detailAmountRow.style.display = 'none';
                }
                detailMsgRow.style.display = 'none';
                actionBtn.textContent = 'View Order / Continue';
                actionBtn.className = 'btn btn-primary';
            } else {
                iconWrapper.className = 'status-icon-wrapper failed';
                iconWrapper.innerHTML = ICON_FAILED;
                title.textContent = 'Payment Failed';
                subtitle.textContent = 'We were unable to complete your transaction.';
                detailCode.className = 'code-badge failed';
                detailCode.textContent = `${statusCode} Failed`;

                detailAmountRow.style.display = 'none';
                detailMsgRow.style.display = 'flex';
                detailMsg.textContent = message || 'Payment verification failed or was declined.';
                actionBtn.textContent = 'Try Again';
                actionBtn.className = 'btn btn-danger';
            }

            modal.classList.add('active');
        }

        function dismissModal() {
            const modal = document.getElementById('status-modal');
            modal.classList.remove('active');

            // Clean query parameters from URL without reloading page
            const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
        }

        function triggerTest(code) {
            const dummyTx = 'VTX' + Date.now() + Math.random().toString(36).substring(2, 6).toUpperCase();
            const nowIso = new Date().toISOString().replace('T', ' ').substring(0, 19);
            if (code === 200) {
                renderModal(200, dummyTx, '500.00', 'INR', nowIso, null);
            } else {
                renderModal(400, dummyTx, null, null, nowIso, 'Signature verification failed.');
            }
        }

        // Run on load
        window.addEventListener('DOMContentLoaded', checkParamsAndDisplay);
    </script>
</body>
</html>
