<?php

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
            --bg-primary: #0b0f19;
            --bg-card: rgba(23, 30, 48, 0.85);
            --border-color: rgba(255, 255, 255, 0.1);
            --border-accent: rgba(99, 102, 241, 0.4);
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
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
            background-color: var(--bg-primary);
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.18) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(6, 182, 212, 0.15) 0px, transparent 50%);
            color: var(--text-main);
            font-family: var(--font-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .checkout-container {
            width: 100%;
            max-width: 480px;
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 2.25rem;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
        }

        .checkout-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-purple), var(--accent-cyan));
        }

        .brand-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.75rem;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }

        .brand-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--accent-purple), var(--accent-cyan));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .brand-title {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            color: #fff;
        }

        .session-timer {
            font-family: var(--font-mono);
            font-size: 0.75rem;
            padding: 0.25rem 0.55rem;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--border-color);
            color: #38bdf8;
        }

        .amount-display {
            background: rgba(11, 15, 25, 0.6);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 1.25rem;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .amount-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }

        .amount-value {
            font-size: 2.25rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: -0.02em;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            font-size: 0.88rem;
        }

        .detail-row:last-of-type {
            border-bottom: none;
            margin-bottom: 1.5rem;
        }

        .detail-label {
            color: var(--text-muted);
        }

        .detail-value {
            font-weight: 500;
            color: #fff;
        }

        .btn-pay {
            width: 100%;
            background: linear-gradient(135deg, var(--accent-purple), #4f46e5);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 0.95rem 1.5rem;
            font-family: var(--font-main);
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        }

        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 22px rgba(99, 102, 241, 0.6);
        }

        .btn-pay:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .error-card {
            text-align: center;
            padding: 2rem 1rem;
        }

        .error-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: rgba(244, 63, 94, 0.15);
            color: var(--accent-rose);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin: 0 auto 1.25rem;
            border: 1px solid rgba(244, 63, 94, 0.3);
        }

        .error-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #fff;
        }

        .error-desc {
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .success-card {
            text-align: center;
            padding: 1.5rem 0.5rem;
        }

        .success-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: rgba(16, 185, 129, 0.15);
            color: var(--accent-emerald);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin: 0 auto 1.25rem;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .alert-box {
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-size: 0.82rem;
            margin-top: 1rem;
            display: none;
        }

        .alert-error {
            background: rgba(244, 63, 94, 0.12);
            border: 1px solid rgba(244, 63, 94, 0.3);
            color: #fca5a5;
        }

        .alert-info {
            background: rgba(6, 182, 212, 0.12);
            border: 1px solid rgba(6, 182, 212, 0.3);
            color: #67e8f9;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #6ee7b7;
        }

        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
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
            margin-top: 1.25rem;
            font-size: 0.78rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }
    </style>
</head>
<body>

<div class="checkout-container">
    <div class="brand-header">
        <div class="brand">
            <div class="brand-icon">⚡</div>
            <div class="brand-title">VORTEX PAYMENT</div>
        </div>
        <?php if ($isValid && !$isAlreadyPaid): ?>
            <div class="session-timer" id="timer-display">5:00</div>
        <?php endif; ?>
    </div>

    <?php if (!$isValid): ?>
        <!-- Invalid or Expired Session -->
        <div class="error-card">
            <div class="error-icon">✕</div>
            <div class="error-title">Unable to Process Payment</div>
            <div class="error-desc"><?= htmlspecialchars($errorMessage) ?></div>
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
            <div class="success-icon">✓</div>
            <div class="error-title">Payment Already Completed</div>
            <div class="error-desc">This transaction (<?= htmlspecialchars($sessionData['vortex_transaction_id']) ?>) has already been processed successfully.</div>
            <div style="margin-top: 1.5rem;">
                <a href="<?= htmlspecialchars($alreadyPaidRedirectUrl) ?>" style="color: var(--accent-cyan); text-decoration: none; font-size: 0.95rem; font-weight: 500;">Return to Merchant →</a>
            </div>
        </div>

    <?php else: ?>
        <!-- Valid Active Checkout Session -->
        <div class="amount-display">
            <div class="amount-label">Total Amount Due</div>
            <div class="amount-value">
                <?= $sessionData['currency'] === 'INR' ? '₹' : '$' ?><?= number_format((float)$sessionData['amount'], 2) ?>
                <span style="font-size: 0.9rem; font-weight: 500; color: var(--text-muted);"><?= htmlspecialchars($sessionData['currency']) ?></span>
            </div>
        </div>

        <div class="detail-row">
            <span class="detail-label">Event</span>
            <span class="detail-value"><?= htmlspecialchars($sessionData['event_name'] ?: $sessionData['event_id']) ?></span>
        </div>

        <div class="detail-row">
            <span class="detail-label">Customer Email</span>
            <span class="detail-value"><?= htmlspecialchars($sessionData['customer_email']) ?></span>
        </div>

        <div class="detail-row">
            <span class="detail-label">Customer Mobile</span>
            <span class="detail-value"><?= htmlspecialchars($sessionData['customer_mobile']) ?></span>
        </div>

        <button class="btn-pay" id="pay-btn" onclick="launchRazorpayCheckout()">
            <span>Pay <?= $sessionData['currency'] === 'INR' ? '₹' : '$' ?><?= number_format((float)$sessionData['amount'], 2) ?></span>
        </button>

        <div class="alert-box alert-error" id="checkout-alert"></div>

        <div class="footer-secure">
            <span>🔒 Secured by Vortex Payment Gateway & Razorpay</span>
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
            timerEl.textContent = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
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