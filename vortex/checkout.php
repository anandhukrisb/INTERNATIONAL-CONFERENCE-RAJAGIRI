<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

/**
 * ------------------------------------------------------------
 * checkout.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 *
 * Purpose:
 * Customer-facing payment checkout page for Vortex transactions.
 * Validates the 5-minute temporary session token and launches Razorpay.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

require_once __DIR__ . '/classes/CheckoutSession.php';
require_once __DIR__ . '/classes/Logger.php';

$token = $_GET['token'] ?? '';
$checkoutSession = new CheckoutSession();
$sessionResult = $checkoutSession->getValidSession($token);

$isValid = $sessionResult['valid'] ?? false;
$sessionData = $sessionResult['data'] ?? null;
$errorMessage = $sessionResult['message'] ?? 'Invalid or expired checkout session.';
$errorCode = $sessionResult['error_code'] ?? 400;

$razorpayKeyId = trim($_ENV['RAZORPAY_KEY_ID'] ?? '');

// Check if transaction is already completed
$isAlreadyPaid = false;
if ($isValid && $sessionData && ($sessionData['transaction_status'] ?? '') === 'SUCCESS') {
    $isAlreadyPaid = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vortex Payment Checkout</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-page: #eef2f9;
            --card-navy: #0e1643;
            --card-blue: #2c54f5;
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --text-dark: #1e293b;
            --accent-purple: #6366f1;
            --accent-cyan: #06b6d4;
            --accent-emerald: #10b981;
            --accent-rose: #f43f5e;
            --font-main: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-page);
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(200, 220, 255, 0.6) 0%, transparent 50%),
                radial-gradient(circle at 90% 80%, rgba(220, 230, 255, 0.6) 0%, transparent 50%);
            color: var(--text-dark);
            font-family: var(--font-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .checkout-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            max-width: 480px;
            gap: 1.5rem;
        }

        .credit-card {
            width: 100%;
            max-width: 420px;
            height: 250px;
            background-color: var(--card-navy);
            border-radius: 20px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(14, 23, 62, 0.12);
            padding: 1.75rem 2rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: white;
            z-index: 10;
        }

        .card-bg-shape {
            position: absolute;
            top: -10%;
            right: -10%;
            width: 60%;
            height: 120%;
            background: linear-gradient(135deg, #1e3ccb, var(--card-blue));
            border-top-left-radius: 120px 250px;
            border-bottom-left-radius: 80px 200px;
            z-index: 1;
        }

        .card-content {
            position: relative;
            z-index: 2;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .card-brand {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .brand-logo {
            width: 24px;
            height: 24px;
            background: white;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--card-navy);
            font-weight: 800;
            font-size: 0.8rem;
            transform: rotate(-10deg);
            margin-right: 0.25rem;
        }

        .brand-name {
            font-size: 1.05rem;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .card-type-icon {
            display: flex;
        }

        .circle-1, .circle-2 {
            width: 32px;
            height: 32px;
            border-radius: 50%;
        }

        .circle-1 {
            background-color: rgba(255, 255, 255, 0.9);
            position: relative;
            z-index: 2;
        }

        .circle-2 {
            background-color: rgba(255, 255, 255, 0.4);
            margin-left: -14px;
            position: relative;
            z-index: 1;
        }

        .card-body {
            margin-top: 1rem;
        }

        .balance-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 0.25rem;
        }

        .balance-amount {
            font-size: 2.25rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            letter-spacing: -0.02em;
        }

        .eye-icon {
            font-size: 1.2rem;
            opacity: 0.8;
            cursor: pointer;
            background: rgba(255,255,255,0.15);
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-footer {
            font-family: var(--font-mono);
            font-size: 1.15rem;
            letter-spacing: 0.1em;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 0.25rem;
        }

        .checkout-panel {
            background: white;
            border-radius: 20px;
            padding: 2.25rem 2rem;
            width: 100%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            position: relative;
        }

        .timer-badge {
            position: absolute;
            top: -15px;
            right: 24px;
            background: #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            border-radius: 20px;
            padding: 0.45rem 0.85rem;
            font-family: var(--font-mono);
            font-size: 0.85rem;
            color: var(--accent-rose);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            border: 1px solid #f1f5f9;
        }
        
        .timer-badge.safe {
            color: var(--accent-emerald);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.85rem 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.92rem;
        }

        .detail-row:last-of-type {
            border-bottom: none;
            margin-bottom: 1.5rem;
        }

        .detail-label {
            color: var(--text-muted);
        }

        .detail-value {
            font-weight: 600;
            color: var(--text-dark);
        }

        .btn-pay {
            width: 100%;
            background: var(--card-blue);
            color: #fff;
            border: none;
            border-radius: 14px;
            padding: 1rem 1.5rem;
            font-family: var(--font-main);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.25s ease;
            box-shadow: 0 8px 20px rgba(44, 84, 245, 0.25);
        }

        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(44, 84, 245, 0.35);
            background: #1f42d6;
        }

        .btn-pay:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .error-card, .success-card {
            background: white;
            border-radius: 20px;
            text-align: center;
            padding: 3rem 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            width: 100%;
        }

        .status-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1.5rem;
        }

        .status-error {
            background: rgba(244, 63, 94, 0.1);
            color: var(--accent-rose);
        }

        .status-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-emerald);
        }

        .status-title {
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: var(--text-dark);
        }

        .status-desc {
            font-size: 0.95rem;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .alert-box {
            padding: 1rem;
            border-radius: 12px;
            font-size: 0.9rem;
            margin-top: 1.5rem;
            display: none;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #ef4444;
        }

        .alert-info {
            background: #f0fdfa;
            border: 1px solid #ccfbf1;
            color: #0d9488;
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
        }

        .spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s linear infinite;
            vertical-align: middle;
            margin-right: 0.4rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .footer-secure {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.85rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
    </style>
</head>
<body>

<div class="checkout-wrapper">

    <?php if (!$isValid): ?>
        <!-- Invalid or Expired Session -->
        <div class="error-card">
            <div class="status-icon status-error">✕</div>
            <div class="status-title">Unable to Process Payment</div>
            <div class="status-desc"><?= htmlspecialchars($errorMessage) ?></div>
        </div>

    <?php elseif ($isAlreadyPaid): ?>
        <!-- Already Paid -->
        <?php
            $nowFormatted = date('Y-m-d H:i:s');
            $amountFormatted = number_format((float)$sessionData['amount'], 2, '.', '');
            $delimiter = strpos($sessionData['redirect_url'], '?') !== false ? '&' : '?';
            $alreadyPaidRedirectUrl = $sessionData['redirect_url'] . $delimiter . http_build_query([
                'vortex_transaction_id' => $sessionData['vortex_transaction_id'],
                'status_code'           => 200,
                'date_time'             => $nowFormatted,
                'amount'                => $amountFormatted,
                'currency'              => $sessionData['currency']
            ]);
        ?>
        <div class="success-card">
            <div class="status-icon status-success">✓</div>
            <div class="status-title">Payment Already Completed</div>
            <div class="status-desc">This transaction (<?= htmlspecialchars($sessionData['vortex_transaction_id']) ?>) has already been processed successfully.</div>
            <div style="margin-top: 2rem;">
                <a href="<?= htmlspecialchars($alreadyPaidRedirectUrl) ?>" style="color: var(--card-blue); text-decoration: none; font-size: 1rem; font-weight: 600;">Return to Merchant →</a>
            </div>
        </div>

    <?php else: ?>
        <!-- Valid Active Checkout Session -->
        
        <!-- The Credit Card -->
        <div class="credit-card">
            <div class="card-bg-shape"></div>
            
            <div class="card-content">
                <div class="card-header">
                    <div class="card-brand">
                        <div class="brand-logo">⚡</div>
                        <div class="brand-name">Vortex Card</div>
                    </div>
                    <div class="card-type-icon">
                        <div class="circle-1"></div>
                        <div class="circle-2"></div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="balance-amount">
                        <?= $sessionData['currency'] === 'INR' ? '₹' : '$' ?><?= number_format((float)$sessionData['amount'], 2) ?>
                        <span class="eye-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </span>
                    </div>
                </div>

                <div class="card-footer">
                    <span>****</span>
                    <span><?= htmlspecialchars(substr($sessionData['customer_mobile'], -4) ?: rand(1000, 9999)) ?></span>
                </div>
            </div>
        </div>

        <!-- Checkout Details Panel -->
        <div class="checkout-panel">
            <div class="timer-badge" id="timer-display">
                ⏱ 5:00
            </div>

            <div class="detail-row">
                <span class="detail-label">Event</span>
                <span class="detail-value"><?= htmlspecialchars($sessionData['event_name'] ?: $sessionData['event_id']) ?></span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Email</span>
                <span class="detail-value"><?= htmlspecialchars($sessionData['customer_email']) ?></span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Mobile</span>
                <span class="detail-value"><?= htmlspecialchars($sessionData['customer_mobile']) ?></span>
            </div>

            <button class="btn-pay" id="pay-btn" onclick="launchRazorpayCheckout()">
                <span>Pay <?= $sessionData['currency'] === 'INR' ? '₹' : '$' ?><?= number_format((float)$sessionData['amount'], 2) ?></span>
            </button>

            <div class="alert-box alert-error" id="checkout-alert"></div>
        </div>

        <div class="footer-secure">
            🔒 Secured by Vortex Payment Gateway & Razorpay
        </div>
    <?php endif; ?>
</div>

<?php if ($isValid && !$isAlreadyPaid): ?>
<!-- Razorpay Checkout SDK -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<script>
    const sessionToken = <?= json_encode($token) ?>;
    const razorpayKeyId = <?= json_encode($razorpayKeyId) ?>;
    const razorpayOrderId = <?= json_encode($sessionData['razorpay_order_id']) ?>;
    const vortexTransactionId = <?= json_encode($sessionData['vortex_transaction_id']) ?>;
    const amountInPaise = <?= (int) round(((float)$sessionData['amount']) * 100) ?>;
    const currency = <?= json_encode($sessionData['currency']) ?>;
    const customerEmail = <?= json_encode($sessionData['customer_email']) ?>;
    const customerMobile = <?= json_encode($sessionData['customer_mobile']) ?>;
    const redirectUrl = <?= json_encode($sessionData['redirect_url']) ?>;
    const expiresAtMs = <?= strtotime($sessionData['expires_at']) * 1000 ?>;

    let isRecovering = false;
    let recoveryInterval = null;

    // Countdown Timer
    function startTimer() {
        const timerEl = document.getElementById('timer-display');
        const interval = setInterval(() => {
            const now = Date.now();
            const diff = expiresAtMs - now;

            if (diff <= 0) {
                clearInterval(interval);
                if (recoveryInterval) clearInterval(recoveryInterval);
                timerEl.textContent = '0:00';
                showAlert('Checkout session expired. Please create a new payment.', 'error');
                const btn = document.getElementById('pay-btn');
                if (btn) btn.disabled = true;
                setTimeout(() => window.location.reload(), 2000);
                return;
            }

            const minutes = Math.floor(diff / 60000);
            const seconds = Math.floor((diff % 60000) / 1000);
            if (minutes > 1) {
                timerEl.classList.add('safe');
            } else {
                timerEl.classList.remove('safe');
            }
            timerEl.innerHTML = `⏱ ${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
        }, 1000);
    }
    startTimer();

    function showAlert(message, type = 'error') {
        const alertBox = document.getElementById('checkout-alert');
        if (!alertBox) return;
        alertBox.className = 'alert-box';
        if (type === 'success') {
            alertBox.classList.add('alert-success');
        } else if (type === 'info') {
            alertBox.classList.add('alert-info');
        } else {
            alertBox.classList.add('alert-error');
        }
        alertBox.innerHTML = message;
        alertBox.style.display = 'block';
    }

    function buildRedirectUrl(baseUrl, params) {
        try {
            const url = new URL(baseUrl);
            for (const [key, value] of Object.entries(params)) {
                if (value !== null && value !== undefined && value !== '') {
                    url.searchParams.set(key, value);
                }
            }
            return url.toString();
        } catch (e) {
            const delimiter = baseUrl.includes('?') ? '&' : '?';
            const query = Object.entries(params)
                .filter(([_, v]) => v !== null && v !== undefined && v !== '')
                .map(([k, v]) => encodeURIComponent(k) + '=' + encodeURIComponent(v))
                .join('&');
            return baseUrl + delimiter + query;
        }
    }

    /**
     * ------------------------------------------------------------
     * Payment Status Recovery Polling
     * ------------------------------------------------------------
     * Handles network failure after Razorpay payment authorization.
     * Prevents duplicate payment attempts by keeping the Pay button
     * disabled while continuously verifying payment state.
     * ------------------------------------------------------------
     */
    function startPaymentStatusRecovery(responseContext = {}) {
        if (isRecovering) return;
        isRecovering = true;

        const btn = document.getElementById('pay-btn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span><span>Confirming Payment Status...</span>';
        }

        showAlert('<span class="spinner"></span>We are checking your payment status with the gateway. Please wait...', 'info');

        const pollIntervalMs = 3000;

        const checkStatus = async () => {
            // Check session expiration
            if (Date.now() >= expiresAtMs) {
                if (recoveryInterval) clearInterval(recoveryInterval);
                showAlert('Checkout session expired while confirming payment. Please contact support.', 'error');
                if (btn) btn.innerHTML = '<span>Session Expired</span>';
                return;
            }

            try {
                const res = await fetch('api/get_checkout_payment_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ session_token: sessionToken })
                });

                if (!res.ok) {
                    // Gateway endpoint error or temporary glitch -> keep polling
                    showAlert('<span class="spinner"></span>Payment status is being confirmed. Please do not refresh...', 'info');
                    return;
                }

                const json = await res.json();
                if (json.status !== 'success' || !json.data) {
                    return;
                }

                const paymentStatus = json.data.payment_status;
                const nowIso = new Date().toISOString().replace('T', ' ').substring(0, 19);
                const amountFormatted = (amountInPaise / 100).toFixed(2);
                const targetRedirectUrl = json.data.redirect_url || redirectUrl;

                if (paymentStatus === 'SUCCESS') {
                    if (recoveryInterval) clearInterval(recoveryInterval);
                    showAlert('Payment confirmed! Redirecting to merchant...', 'success');
                    if (btn) btn.innerHTML = '<span>Payment Confirmed ✓</span>';

                    const finalRedirect = buildRedirectUrl(targetRedirectUrl, {
                        vortex_transaction_id: vortexTransactionId,
                        status_code: 200,
                        date_time: nowIso,
                        amount: amountFormatted,
                        currency: currency
                    });

                    setTimeout(() => {
                        window.location.href = finalRedirect;
                    }, 1200);

                } else if (paymentStatus === 'FAILED') {
                    if (recoveryInterval) clearInterval(recoveryInterval);
                    showAlert('Payment failed. Redirecting to merchant...', 'error');
                    if (btn) btn.innerHTML = '<span>Payment Failed ✕</span>';

                    const finalRedirect = buildRedirectUrl(targetRedirectUrl, {
                        vortex_transaction_id: vortexTransactionId,
                        status_code: 400,
                        date_time: nowIso,
                        amount: amountFormatted,
                        currency: currency
                    });

                    setTimeout(() => {
                        window.location.href = finalRedirect;
                    }, 2500);

                } else {
                    // PENDING state
                    showAlert('<span class="spinner"></span>Payment status is being confirmed. Please do not refresh...', 'info');
                }

            } catch (err) {
                // Client network loss during fetch -> do NOT fail or re-enable pay button
                showAlert('<span class="spinner"></span>Network connection interrupted. Reconnecting to verify payment...', 'info');
            }
        };

        // Poll immediately and set recurring interval
        checkStatus();
        recoveryInterval = setInterval(checkStatus, pollIntervalMs);
    }

    function launchRazorpayCheckout() {
        if (isRecovering) return;

        const btn = document.getElementById('pay-btn');
        btn.disabled = true;

        const options = {
            key: razorpayKeyId,
            amount: amountInPaise,
            currency: currency,
            name: 'Vortex Payment Gateway',
            description: 'Payment for ' + <?= json_encode($sessionData['event_name'] ?: $sessionData['event_id']) ?>,
            order_id: razorpayOrderId,
            prefill: {
                email: customerEmail,
                contact: customerMobile
            },
            handler: async function (response) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner"></span><span>Verifying Payment...</span>';
                
                try {
                    const verifyResponse = await fetch('api/verify_payment.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            vortex_transaction_id: vortexTransactionId,
                            razorpay_order_id: response.razorpay_order_id,
                            razorpay_payment_id: response.razorpay_payment_id,
                            razorpay_signature: response.razorpay_signature,
                            session_token: sessionToken
                        })
                    });

                    const verifyData = await verifyResponse.json();
                    const nowIso = new Date().toISOString().replace('T', ' ').substring(0, 19);
                    const amountFormatted = (amountInPaise / 100).toFixed(2);

                    if (verifyResponse.ok && verifyData.status === 'success') {
                        // Payment verified -> Redirect to single redirect_url with status_code 200
                        const redirectBase = verifyData.data?.redirect_url || redirectUrl;
                        const finalRedirect = buildRedirectUrl(redirectBase, {
                            vortex_transaction_id: vortexTransactionId,
                            status_code: 200,
                            date_time: nowIso,
                            amount: amountFormatted,
                            currency: currency
                        });
                        window.location.href = finalRedirect;
                    } else if (verifyResponse.status === 400 && verifyData.message === 'Payment verification failed.') {
                        // Definite signature failure -> status_code 400
                        showAlert('Payment verification failed. Redirecting to merchant...', 'error');
                        const redirectBase = verifyData.data?.redirect_url || redirectUrl;
                        const finalRedirect = buildRedirectUrl(redirectBase, {
                            vortex_transaction_id: vortexTransactionId,
                            status_code: 400,
                            date_time: nowIso,
                            amount: amountFormatted,
                            currency: currency
                        });
                        setTimeout(() => {
                            window.location.href = finalRedirect;
                        }, 2500);
                    } else {
                        // Unconfirmed response: do NOT re-enable Pay button, start recovery polling
                        startPaymentStatusRecovery(response);
                    }

                } catch (error) {
                    // Network failure: do NOT falsely declare payment failed, start recovery polling
                    startPaymentStatusRecovery(response);
                }
            },
            modal: {
                ondismiss: function () {
                    // Only re-enable if payment authorization was not submitted
                    if (isRecovering) return;
                    btn.disabled = false;
                    btn.innerHTML = '<span>Pay <?= $sessionData['currency'] === 'INR' ? '₹' : '$' ?><?= number_format((float)$sessionData['amount'], 2) ?></span>';
                }
            }
        };

        const rzp = new Razorpay(options);
        rzp.open();
    }
</script>
<?php endif; ?>

</body>
</html>