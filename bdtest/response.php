<?php

/**
 * ================================================================
 * BILLDESK — PAYMENT RESPONSE HANDLER
 * ================================================================
 *
 * BillDesk POSTs `transaction_response` here after the customer
 * completes (or abandons) payment on the BillDesk checkout page.
 *
 * Flow:
 *   1. Read POST[transaction_response]
 *   2. Verify HMAC signature   — bd_verify()
 *   3. Decrypt JWE payload     — bd_decrypt()
 *   4. Parse auth_status
 *   5. Update DB  (TODO stubs)
 *   6. Render premium status popup page
 * ================================================================
 */

require_once 'config.php';
require_once 'billdesk_helper.php';

// ── Initialise variables shown in the page ─────────────────────
$status      = 'error';        // success | failed | pending | error
$order_id    = '';
$txn_id      = '';
$amount      = '';
$error_msg   = 'An unexpected error occurred.';
$txn         = [];

try {

    // ── 1. Read raw response ───────────────────────────────────
    $raw = trim($_POST['transaction_response'] ?? '');

    if (empty($raw)) {
        throw new RuntimeException(
            'No transaction response received. Please do not refresh this page.'
        );
    }

    // ── 2. Verify signature → 3. Decrypt ──────────────────────
    // bd_verify() checks HMAC-SHA256 and returns the JWE string.
    // bd_decrypt() AES-256-GCM decrypts the JWE and returns array.
    $jwe = bd_verify($raw, $sign_key);
    $txn = bd_decrypt($jwe, $enc_key);

    // ── Validate mandatory fields ──────────────────────────────
    if (empty($txn['orderid'])) {
        throw new RuntimeException('Transaction payload missing orderid.');
    }

    if (empty($txn['transactionid'])) {
        throw new RuntimeException('Transaction payload missing transactionid.');
    }

    $order_id   = htmlspecialchars($txn['orderid']       ?? '', ENT_QUOTES, 'UTF-8');
    $txn_id     = htmlspecialchars($txn['transactionid'] ?? '', ENT_QUOTES, 'UTF-8');
    $amount     = htmlspecialchars($txn['amount']        ?? '', ENT_QUOTES, 'UTF-8');
    $auth_status = $txn['auth_status'] ?? '';

    bd_log('response', 'CALLBACK_RECEIVED', [
        'order_id'    => $order_id,
        'txn_id'      => $txn_id,
        'auth_status' => $auth_status,
        'amount'      => $amount,
    ]);

    // ── 4. Process auth_status ────────────────────────────────
    switch ($auth_status) {

        // ✅ SUCCESS ───────────────────────────────────────────
        case '0300':

            $status = 'success';

            // ── TODO: Update payment table ────────────────────
            // $pdo->prepare("
            //     UPDATE payments
            //     SET    status         = 'SUCCESS',
            //            transaction_id = :txn_id,
            //            paid_at        = NOW(),
            //            amount         = :amount
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
            //     SET    payment_status       = 'PAID',
            //            registration_status  = 'COMPLETED',
            //            updated_at           = NOW()
            //     WHERE  order_id = :order_id
            // ")->execute([':order_id' => $order_id]);

            // ── TODO: Store transaction log ───────────────────
            // $pdo->prepare("
            //     INSERT INTO transaction_logs
            //         (order_id, transaction_id, auth_status, amount, full_payload, created_at)
            //     VALUES
            //         (:order_id, :txn_id, '0300', :amount, :payload, NOW())
            // ")->execute([
            //     ':order_id' => $order_id,
            //     ':txn_id'   => $txn_id,
            //     ':amount'   => $amount,
            //     ':payload'  => json_encode($txn),
            // ]);

            bd_log('response', 'PAYMENT_SUCCESS', [
                'order_id' => $order_id,
                'txn_id'   => $txn_id,
                'amount'   => $amount,
            ]);
            break;

        // ❌ FAILED ────────────────────────────────────────────
        case '0399':

            $status    = 'failed';
            $error_msg = htmlspecialchars(
                $txn['transaction_error_desc'] ?? 'Payment was declined.',
                ENT_QUOTES, 'UTF-8'
            );

            // ── TODO: Update payment table ────────────────────
            // $pdo->prepare("
            //     UPDATE payments
            //     SET    status        = 'FAILED',
            //            failure_reason = :reason,
            //            updated_at    = NOW()
            //     WHERE  order_id = :order_id
            // ")->execute([
            //     ':reason'   => $txn['transaction_error_desc'] ?? 'Unknown',
            //     ':order_id' => $order_id,
            // ]);

            // ── TODO: Update registration table ──────────────
            // $pdo->prepare("
            //     UPDATE registrations
            //     SET registration_status = 'PAYMENT_FAILED',
            //         updated_at          = NOW()
            //     WHERE order_id = :order_id
            // ")->execute([':order_id' => $order_id]);

            bd_log('response', 'PAYMENT_FAILED', [
                'order_id' => $order_id,
                'reason'   => $txn['transaction_error_desc'] ?? 'Unknown',
            ]);
            break;

        // ⏳ PENDING ───────────────────────────────────────────
        case '0002':

            $status = 'pending';

            // ── TODO: Update payment table ────────────────────
            // $pdo->prepare("
            //     UPDATE payments
            //     SET    status     = 'PENDING',
            //            updated_at = NOW()
            //     WHERE  order_id   = :order_id
            // ")->execute([':order_id' => $order_id]);

            // ── TODO: Update registration table ──────────────
            // $pdo->prepare("
            //     UPDATE registrations
            //     SET registration_status = 'PENDING_VERIFICATION',
            //         updated_at          = NOW()
            //     WHERE order_id = :order_id
            // ")->execute([':order_id' => $order_id]);

            bd_log('response', 'PAYMENT_PENDING', [
                'order_id' => $order_id,
                'txn_id'   => $txn_id,
            ]);
            break;

        default:

            $status    = 'error';
            $error_msg = 'Received unknown payment status: ' . htmlspecialchars($auth_status, ENT_QUOTES, 'UTF-8');

            bd_log('response', 'UNKNOWN_STATUS', [
                'order_id'    => $order_id,
                'auth_status' => $auth_status,
            ]);
            break;
    }

} catch (RuntimeException $e) {

    $status    = 'error';
    $error_msg = $e->getMessage();
    bd_log('response', 'ERROR', ['message' => $e->getMessage()]);

} catch (Exception $e) {

    $status    = 'error';
    $error_msg = 'A technical error occurred. Please contact support.';
    bd_log('response', 'UNEXPECTED_ERROR', [
        'message' => $e->getMessage(),
        'trace'   => $e->getTraceAsString(),
    ]);
}

// ──────────────────────────────────────────────────────────────
// Retrieve URL for the "Check Status" button (pending state)
// ──────────────────────────────────────────────────────────────
$check_status_url = 'retrieve.php?orderid=' . urlencode($order_id);

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status — ICSWHMH 2027 Conference</title>
    <meta name="description" content="Payment status for your ICSWHMH 2027 conference registration.">
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

    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- REAL SITE NAVBAR                                        -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <floating-navbar></floating-navbar>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- BACKGROUND LAYER                                        -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <div class="bg-layer" aria-hidden="true">
        <div class="bg-orb bg-orb--1"></div>
        <div class="bg-orb bg-orb--2"></div>
    </div>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- MAIN CONTENT (padded below navbar)                      -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <main class="status-main" id="main-content" role="main">

        <!-- ── STATUS CARD ─────────────────────────────────── -->
        <div class="status-card status-card--<?= $status ?>" role="dialog" aria-modal="true" aria-live="polite" aria-labelledby="status-title" aria-describedby="status-message">

            <!-- Icon ring -->
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

            <!-- Status badge -->
            <div class="status-badge status-badge--<?= $status ?>" role="status">
                <?php
                $badge_labels = [
                    'success' => 'Registration Completed',
                    'failed'  => 'Registration Incomplete',
                    'pending' => 'Pending Verification',
                    'error'   => 'Error',
                ];
                echo $badge_labels[$status] ?? 'Unknown';
                ?>
            </div>

            <!-- Title -->
            <h1 class="status-title" id="status-title">
                <?php
                $titles = [
                    'success' => 'Payment Successful',
                    'failed'  => 'Payment Failed',
                    'pending' => 'Payment Under Verification',
                    'error'   => 'Something Went Wrong',
                ];
                echo $titles[$status] ?? 'Unknown Status';
                ?>
            </h1>

            <!-- Message -->
            <p class="status-message" id="status-message">
                <?php if ($status === 'success'): ?>
                    Your conference registration has been completed successfully. A confirmation e-mail will be sent shortly.
                <?php elseif ($status === 'failed'): ?>
                    Your payment could not be completed. <strong><?= $error_msg ?></strong> You may retry or contact our support team.
                <?php elseif ($status === 'pending'): ?>
                    Your payment is currently being processed by the bank. Please do <strong>not</strong> attempt another payment. We will verify and update your status within 60 minutes.
                <?php else: ?>
                    <?= $error_msg ?>
                <?php endif; ?>
            </p>

            <!-- Transaction details (shown for success/pending) -->
            <?php if ($status === 'success' || $status === 'pending'): ?>
            <dl class="txn-details" aria-label="Transaction details">
                <?php if (!empty($order_id)): ?>
                <div class="txn-row">
                    <dt>Order ID</dt>
                    <dd><?= $order_id ?></dd>
                </div>
                <?php endif; ?>
                <?php if (!empty($txn_id)): ?>
                <div class="txn-row">
                    <dt>Transaction ID</dt>
                    <dd><?= $txn_id ?></dd>
                </div>
                <?php endif; ?>
                <?php if (!empty($amount)): ?>
                <div class="txn-row">
                    <dt>Amount</dt>
                    <dd>₹<?= $amount ?></dd>
                </div>
                <?php endif; ?>
            </dl>
            <?php endif; ?>

            <!-- Auto-redirect notice (success only) -->
            <?php if ($status === 'success'): ?>
            <p class="redirect-notice" role="timer" aria-live="polite" id="redirect-notice">
                Redirecting to your registration details in <strong id="countdown">5</strong>s&hellip;
            </p>
            <?php endif; ?>

            <!-- Action buttons -->
            <div class="status-actions" role="group" aria-label="Next steps">
                <?php if ($status === 'success'): ?>
                    <a href="../registration.html"
                       id="btn-view-details"
                       class="btn btn--primary"
                       aria-label="View your registration details">
                        Go to Registration
                    </a>
                <?php elseif ($status === 'failed'): ?>
                    <a href="../registration.html"
                       id="btn-retry"
                       class="btn btn--primary"
                       aria-label="Retry payment">
                        Retry Payment
                    </a>
                    <a href="../registration.html"
                       id="btn-back"
                       class="btn btn--outline"
                       aria-label="Return to registration form">
                        Back to Registration
                    </a>
                <?php elseif ($status === 'pending'): ?>
                    <a href="<?= $check_status_url ?>"
                       id="btn-check-status"
                       class="btn btn--primary"
                       aria-label="Manually check payment status">
                        Check Status
                    </a>
                <?php else: ?>
                    <a href="../registration.html"
                       id="btn-back-error"
                       class="btn btn--outline"
                       aria-label="Return to registration form">
                        Back to Registration
                    </a>
                <?php endif; ?>
            </div>

            <!-- Billdesk secure badge -->
            <div class="secure-badge" aria-label="Secured by BillDesk">
                <svg width="14" height="17" viewBox="0 0 14 17" fill="none" aria-hidden="true">
                    <path d="M7 0L0 3v5c0 4.4 3 8.5 7 9.5C11 16.5 14 12.4 14 8V3L7 0z" fill="currentColor" opacity=".2"/>
                    <path d="M7 1.5L1.5 4v4c0 3.6 2.4 6.9 5.5 7.8 3.1-.9 5.5-4.2 5.5-7.8V4L7 1.5z" fill="currentColor"/>
                    <path d="M5 8l1.5 1.5L9.5 6" stroke="white" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Secured by BillDesk
            </div>

        </div><!-- /.status-card -->

    </main>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- REAL SITE FOOTER                                        -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <main-footer></main-footer>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- SCRIPTS                                                 -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <script src="payment-status.js"></script>

    <?php if ($status === 'success'): ?>
    <script>
        // Auto-redirect to registration details after 5 seconds
        (function () {
            var target  = '../registration.html?payment=success';
            var seconds = 5;
            var el      = document.getElementById('countdown');
            var timer   = setInterval(function () {
                seconds--;
                if (el) el.textContent = seconds;
                if (seconds <= 0) {
                    clearInterval(timer);
                    window.location.href = target;
                }
            }, 1000);
        }());
    </script>
    <?php endif; ?>

</body>
</html>