<?php

/**
 * ================================================================
 * BILLDESK — TRANSACTION RETRIEVE (STATUS CHECK)
 * ================================================================
 *
 * Used to manually query BillDesk for the current status of a
 * specific order.  Called:
 *   • From response.php "Check Status" button (pending state)
 *   • From a scheduled cron job for long-pending orders
 *
 * Flow:
 *   1. Validate orderid input
 *   2. Build retrieve payload
 *   3. Encrypt → Sign
 *   4. Call BillDesk /transactions/get
 *   5. Verify → Decrypt response
 *   6. Update DB (TODO stubs)
 *   7. Render status page
 *
 * NOTE: Does NOT require pay.php.
 *       All crypto comes from billdesk_helper.php.
 * ================================================================
 */

require_once 'config.php';
require_once 'billdesk_helper.php';

// ── Initialise status variables ────────────────────────────────
$status    = 'error';
$order_id  = '';
$txn_id    = '';
$amount    = '';
$error_msg = 'Could not retrieve transaction status.';
$txn       = [];

try {

    // ── 1. Validate orderid ───────────────────────────────────
    bd_validate_config($merchant_id, $client_id, $enc_key, $sign_key);

    $order_id = trim($_GET['orderid'] ?? '');

    if (empty($order_id)) {
        throw new RuntimeException('Missing order ID. Please return to the registration page.');
    }

    // Sanitise: BillDesk order IDs are alphanumeric, 10–35 chars
    if (!preg_match('/^[A-Za-z0-9]{10,35}$/', $order_id)) {
        throw new RuntimeException('Invalid order ID format.');
    }

    $order_id_safe = htmlspecialchars($order_id, ENT_QUOTES, 'UTF-8');

    // ── 2. Build retrieve payload ─────────────────────────────
    $payload = [
        'mercid'  => $merchant_id,
        'orderid' => $order_id,
    ];

    // ── 3. Encrypt → Sign ─────────────────────────────────────
    $trace_id  = 'TRC' . strtoupper(bin2hex(random_bytes(8)));
    $timestamp = date('YmdHis');

    $jws_body = bd_sign(
        bd_encrypt($payload, $enc_key, $enc_key_id, $client_id),
        $sign_key,
        $sign_key_id,
        $client_id
    );

    bd_log('retrieve', 'QUERY_SENT', [
        'order_id' => $order_id,
        'trace_id' => $trace_id,
    ]);

    // ── 4. Call BillDesk Retrieve API ────────────────────────
    $ch = curl_init($retrieve_url);

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jws_body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/jose',
            'Accept: application/jose',
            'BD-Traceid: '   . $trace_id,
            'BD-Timestamp: ' . $timestamp,
        ],
    ]);

    $raw_response = curl_exec($ch);
    $http_code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error   = curl_error($ch);
    curl_close($ch);

    // ── 4a. Handle cURL / HTTP errors ─────────────────────────
    if (!empty($curl_error)) {
        bd_log('retrieve', 'CURL_ERROR', ['error' => $curl_error, 'order_id' => $order_id]);
        throw new RuntimeException('Network error while contacting BillDesk. Please try again shortly.');
    }

    if ($http_code === 400) {
        bd_log('retrieve', 'HTTP_400', ['order_id' => $order_id]);
        throw new RuntimeException('BillDesk could not find this order (HTTP 400).');
    }

    if ($http_code === 401) {
        bd_log('retrieve', 'HTTP_401', ['order_id' => $order_id]);
        throw new RuntimeException('BillDesk authorisation failed (HTTP 401). Contact support.');
    }

    if ($http_code !== 200) {
        bd_log('retrieve', 'HTTP_ERROR', ['http_code' => $http_code, 'order_id' => $order_id]);
        throw new RuntimeException("BillDesk returned unexpected HTTP {$http_code}.");
    }

    // ── 5. Verify → Decrypt response ─────────────────────────
    $jwe = bd_verify($raw_response, $sign_key);
    $txn = bd_decrypt($jwe, $enc_key);

    $txn_id      = $txn['transactionid'] ?? '';
    $auth_status = $txn['auth_status']   ?? '';
    $amount      = $txn['amount']        ?? '';

    bd_log('retrieve', 'RESPONSE_RECEIVED', [
        'order_id'    => $order_id,
        'txn_id'      => $txn_id,
        'auth_status' => $auth_status,
        'amount'      => $amount,
    ]);

    // ── 6. Process status & update DB ────────────────────────
    switch ($auth_status) {

        // ✅ SUCCESS ───────────────────────────────────────────
        case '0300':

            $status = 'success';

            // ── TODO: Update payment table ────────────────────
            // $pdo->prepare("
            //     UPDATE payments
            //     SET    status         = 'SUCCESS',
            //            transaction_id = :txn_id,
            //            amount         = :amount,
            //            paid_at        = NOW(),
            //            updated_at     = NOW()
            //     WHERE  order_id       = :order_id
            //       AND  status        != 'SUCCESS'
            // ")->execute([
            //     ':txn_id'   => $txn_id,
            //     ':amount'   => $amount,
            //     ':order_id' => $order_id,
            // ]);

            // ── TODO: Update registration table ──────────────
            // $pdo->prepare("
            //     UPDATE registrations
            //     SET    payment_status      = 'PAID',
            //            registration_status = 'COMPLETED',
            //            updated_at          = NOW()
            //     WHERE  order_id = :order_id
            // ")->execute([':order_id' => $order_id]);

            bd_log('retrieve', 'PAYMENT_SUCCESS', [
                'order_id' => $order_id,
                'txn_id'   => $txn_id,
                'amount'   => $amount,
            ]);
            break;

        // ❌ FAILED ────────────────────────────────────────────
        case '0399':

            $status    = 'failed';
            $error_msg = $txn['transaction_error_desc'] ?? 'Payment was declined.';

            // ── TODO: Update payment table ────────────────────
            // $pdo->prepare("
            //     UPDATE payments
            //     SET    status         = 'FAILED',
            //            failure_reason = :reason,
            //            updated_at     = NOW()
            //     WHERE  order_id       = :order_id
            // ")->execute([
            //     ':reason'   => $error_msg,
            //     ':order_id' => $order_id,
            // ]);

            bd_log('retrieve', 'PAYMENT_FAILED', [
                'order_id' => $order_id,
                'reason'   => $error_msg,
            ]);
            break;

        // ⏳ PENDING ───────────────────────────────────────────
        case '0002':

            $status = 'pending';

            // ── TODO: Keep status as PENDING_VERIFICATION ─────
            // If this endpoint keeps returning 0002 after 24 hours,
            // escalate to BillDesk via the merchant portal.

            bd_log('retrieve', 'STILL_PENDING', [
                'order_id' => $order_id,
            ]);
            break;

        default:

            $status    = 'error';
            $error_msg = 'Received unknown status code: ' . htmlspecialchars($auth_status, ENT_QUOTES, 'UTF-8');
            bd_log('retrieve', 'UNKNOWN_STATUS', [
                'order_id'    => $order_id,
                'auth_status' => $auth_status,
            ]);
            break;
    }

} catch (RuntimeException $e) {

    $status    = 'error';
    $error_msg = $e->getMessage();
    bd_log('retrieve', 'ERROR', ['message' => $e->getMessage()]);

} catch (Exception $e) {

    $status    = 'error';
    $error_msg = 'A technical error occurred. Please contact support.';
    bd_log('retrieve', 'UNEXPECTED_ERROR', [
        'message' => $e->getMessage(),
        'trace'   => $e->getTraceAsString(),
    ]);
}

// ── Safe display values ────────────────────────────────────────
$order_id_safe = htmlspecialchars($order_id, ENT_QUOTES, 'UTF-8');
$txn_id_safe   = htmlspecialchars($txn_id,   ENT_QUOTES, 'UTF-8');
$amount_safe   = htmlspecialchars($amount,   ENT_QUOTES, 'UTF-8');

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Verification — ICSWHMH 2027 Conference</title>
    <meta name="description" content="Payment verification status for your ICSWHMH 2027 conference registration.">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/x-icon" href="https://res.cloudinary.com/dswfp5fwx/image/upload/v1778131826/Favicon-192_hdltam.ico">

    <!-- Real site navbar & footer web components -->
    <script src="../navbar.js" defer></script>
    <script src="../footer.js" defer></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="payment-status.css">
</head>
<body class="status-page" data-status="<?= $status ?>">

    <!-- Real site navbar -->
    <floating-navbar></floating-navbar>

    <!-- Background orbs -->
    <div class="bg-layer" aria-hidden="true">
        <div class="bg-orb bg-orb--1"></div>
        <div class="bg-orb bg-orb--2"></div>
    </div>

    <main class="status-main" id="main-content" role="main">

        <div class="status-card status-card--<?= $status ?>" role="dialog" aria-modal="true" aria-live="polite" aria-labelledby="status-title" aria-describedby="status-message">

            <div class="status-icon-ring" aria-hidden="true">
                <?php if ($status === 'success'): ?>
                    <div class="status-icon status-icon--success">
                        <svg viewBox="0 0 52 52" fill="none" aria-hidden="true">
                            <circle class="icon-circle" cx="26" cy="26" r="24" stroke-width="2"/>
                            <path class="icon-check" d="M14 26l8 8 16-16" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                <?php elseif ($status === 'failed'): ?>
                    <div class="status-icon status-icon--failed">
                        <svg viewBox="0 0 52 52" fill="none" aria-hidden="true">
                            <circle class="icon-circle" cx="26" cy="26" r="24" stroke-width="2"/>
                            <path class="icon-cross" d="M18 18l16 16M34 18L18 34" stroke-width="3" stroke-linecap="round"/>
                        </svg>
                    </div>
                <?php elseif ($status === 'pending'): ?>
                    <div class="status-icon status-icon--pending">
                        <svg viewBox="0 0 52 52" fill="none" aria-hidden="true">
                            <circle class="icon-circle" cx="26" cy="26" r="24" stroke-width="2"/>
                            <path class="icon-clock" d="M26 16v10l6 6" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                <?php else: ?>
                    <div class="status-icon status-icon--error">
                        <svg viewBox="0 0 52 52" fill="none" aria-hidden="true">
                            <circle class="icon-circle" cx="26" cy="26" r="24" stroke-width="2"/>
                            <path class="icon-exclaim" d="M26 17v12M26 35v2" stroke-width="3" stroke-linecap="round"/>
                        </svg>
                    </div>
                <?php endif; ?>
            </div>

            <div class="status-badge status-badge--<?= $status ?>" role="status">
                <?php
                echo [
                    'success' => 'Registration Completed',
                    'failed'  => 'Registration Incomplete',
                    'pending' => 'Pending Verification',
                    'error'   => 'Verification Error',
                ][$status] ?? 'Unknown';
                ?>
            </div>

            <h1 class="status-title" id="status-title">
                <?php echo [
                    'success' => 'Payment Verified',
                    'failed'  => 'Payment Failed',
                    'pending' => 'Still Processing',
                    'error'   => 'Verification Failed',
                ][$status] ?? 'Unknown'; ?>
            </h1>

            <p class="status-message" id="status-message">
                <?php if ($status === 'success'): ?>
                    Your payment has been verified successfully. Your registration is now complete.
                <?php elseif ($status === 'failed'): ?>
                    Payment verification failed: <strong><?= htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8') ?></strong>
                <?php elseif ($status === 'pending'): ?>
                    Your payment is still being processed. Please check back after <strong>60 minutes</strong>. If the issue persists, contact support.
                <?php else: ?>
                    <?= htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
            </p>

            <?php if (in_array($status, ['success', 'pending'])): ?>
            <dl class="txn-details" aria-label="Transaction details">
                <?php if (!empty($order_id_safe)): ?>
                <div class="txn-row">
                    <dt>Order ID</dt>
                    <dd><?= $order_id_safe ?></dd>
                </div>
                <?php endif; ?>
                <?php if (!empty($txn_id_safe)): ?>
                <div class="txn-row">
                    <dt>Transaction ID</dt>
                    <dd><?= $txn_id_safe ?></dd>
                </div>
                <?php endif; ?>
                <?php if (!empty($amount_safe)): ?>
                <div class="txn-row">
                    <dt>Amount</dt>
                    <dd>₹<?= $amount_safe ?></dd>
                </div>
                <?php endif; ?>
            </dl>
            <?php endif; ?>

            <div class="status-actions" role="group" aria-label="Next steps">
                <?php if ($status === 'success'): ?>
                    <a href="registration-details.php?orderid=<?= urlencode($order_id) ?>" class="btn btn--primary" id="btn-retrieve-view">
                        View Registration Details
                    </a>
                <?php elseif ($status === 'failed'): ?>
                    <a href="../registration.html" class="btn btn--primary" id="btn-retrieve-retry">
                        Retry Payment
                    </a>
                    <a href="../registration.html" class="btn btn--outline" id="btn-retrieve-back">
                        Back to Registration
                    </a>
                <?php elseif ($status === 'pending'): ?>
                    <a href="retrieve.php?orderid=<?= urlencode($order_id) ?>" class="btn btn--primary" id="btn-retrieve-recheck">
                        Check Again
                    </a>
                    <a href="../index.html" class="btn btn--outline" id="btn-retrieve-home">
                        Back to Home
                    </a>
                <?php else: ?>
                    <a href="../registration.html" class="btn btn--outline" id="btn-retrieve-error-back">
                        Back to Registration
                    </a>
                <?php endif; ?>
            </div>

            <div class="secure-badge" aria-label="Secured by BillDesk">
                <svg width="14" height="17" viewBox="0 0 14 17" fill="none" aria-hidden="true">
                    <path d="M7 0L0 3v5c0 4.4 3 8.5 7 9.5C11 16.5 14 12.4 14 8V3L7 0z" fill="currentColor" opacity=".2"/>
                    <path d="M7 1.5L1.5 4v4c0 3.6 2.4 6.9 5.5 7.8 3.1-.9 5.5-4.2 5.5-7.8V4L7 1.5z" fill="currentColor"/>
                    <path d="M5 8l1.5 1.5L9.5 6" stroke="white" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Secured by BillDesk
            </div>

        </div>

    </main>

    <!-- Real site footer -->
    <main-footer></main-footer>

    <script src="payment-status.js"></script>

    <?php if ($status === 'success'): ?>
    <script>
        // Auto redirect to registration details in 5 seconds
        (function () {
            var target  = 'registration-details.php?orderid=<?= urlencode($order_id) ?>';
            setTimeout(function () { window.location.href = target; }, 5000);
        }());
    </script>
    <?php endif; ?>

</body>
</html>