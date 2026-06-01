<?php

/**
 * ================================================================
 * BILLDESK — ASYNCHRONOUS WEBHOOK HANDLER
 * ================================================================
 *
 * BillDesk sends a server-to-server POST here for every terminal
 * transaction event, regardless of whether the customer returned
 * to response.php.  This is the source of truth.
 *
 * Flow:
 *   1. Read POST[transaction_response]
 *   2. Verify HMAC signature   — bd_verify()
 *   3. Decrypt JWE payload     — bd_decrypt()
 *   4. Idempotency check       — skip if already processed
 *   5. Update DB by auth_status
 *   6. Write structured JSON log
 *   7. Respond HTTP 200 OK
 * ================================================================
 */

require_once 'config.php';
require_once 'billdesk_helper.php';

// ── Only accept POST requests from BillDesk ────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// ── Read raw webhook payload ───────────────────────────────────
$raw = trim($_POST['transaction_response'] ?? '');

if (empty($raw)) {
    bd_log('webhook', 'EMPTY_PAYLOAD', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    http_response_code(400);
    exit('Bad Request: Empty payload');
}

try {

    // ── 2. Verify signature ────────────────────────────────────
    // bd_verify() throws if signature is invalid — no raw crypto here.
    $jwe = bd_verify($raw, $sign_key);

    // ── 3. Decrypt payload ─────────────────────────────────────
    $txn = bd_decrypt($jwe, $enc_key);

    // ── Validate mandatory transaction fields ──────────────────
    $order_id    = $txn['orderid']        ?? '';
    $txn_id      = $txn['transactionid']  ?? '';
    $auth_status = $txn['auth_status']    ?? '';
    $amount      = $txn['amount']         ?? '';

    if (empty($order_id)) {
        throw new RuntimeException('Webhook payload missing orderid.');
    }

    if (empty($auth_status)) {
        throw new RuntimeException('Webhook payload missing auth_status.');
    }

    // ── 4. Idempotency guard ───────────────────────────────────
    // Prevent double-processing if BillDesk retries the webhook.
    // Uncomment and adapt once you have a DB connection.

    // $stmt = $pdo->prepare("SELECT id FROM payments WHERE order_id = :oid AND status = 'SUCCESS'");
    // $stmt->execute([':oid' => $order_id]);
    //
    // if ($stmt->fetchColumn()) {
    //     bd_log('webhook', 'DUPLICATE_SKIPPED', ['order_id' => $order_id]);
    //     http_response_code(200);
    //     echo 'OK';
    //     exit;
    // }

    bd_log('webhook', 'RECEIVED', [
        'order_id'    => $order_id,
        'txn_id'      => $txn_id,
        'auth_status' => $auth_status,
        'amount'      => $amount,
        'full_payload'=> $txn,
    ]);

    // ── 5. Process auth_status ────────────────────────────────
    switch ($auth_status) {

        // ✅ SUCCESS ───────────────────────────────────────────
        case '0300':

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

            // ── TODO: Store transaction log ───────────────────
            // $pdo->prepare("
            //     INSERT IGNORE INTO transaction_logs
            //         (order_id, transaction_id, auth_status, amount, full_payload, created_at)
            //     VALUES
            //         (:order_id, :txn_id, '0300', :amount, :payload, NOW())
            // ")->execute([
            //     ':order_id' => $order_id,
            //     ':txn_id'   => $txn_id,
            //     ':amount'   => $amount,
            //     ':payload'  => json_encode($txn),
            // ]);

            // ── TODO: Dispatch registration confirmation e-mail
            // send_registration_email($order_id);

            bd_log('webhook', 'PAYMENT_SUCCESS', [
                'order_id' => $order_id,
                'txn_id'   => $txn_id,
                'amount'   => $amount,
            ]);
            break;

        // ❌ FAILED ────────────────────────────────────────────
        case '0399':

            $failure_reason = $txn['transaction_error_desc'] ?? 'Unknown failure';

            // ── TODO: Update payment table ────────────────────
            // $pdo->prepare("
            //     UPDATE payments
            //     SET    status         = 'FAILED',
            //            failure_reason = :reason,
            //            updated_at     = NOW()
            //     WHERE  order_id       = :order_id
            // ")->execute([
            //     ':reason'   => $failure_reason,
            //     ':order_id' => $order_id,
            // ]);

            // ── TODO: Update registration table ──────────────
            // $pdo->prepare("
            //     UPDATE registrations
            //     SET    registration_status = 'PAYMENT_FAILED',
            //            updated_at          = NOW()
            //     WHERE  order_id = :order_id
            // ")->execute([':order_id' => $order_id]);

            bd_log('webhook', 'PAYMENT_FAILED', [
                'order_id' => $order_id,
                'reason'   => $failure_reason,
            ]);
            break;

        // ⏳ PENDING ───────────────────────────────────────────
        case '0002':

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
            //     SET    registration_status = 'PENDING_VERIFICATION',
            //            updated_at          = NOW()
            //     WHERE  order_id = :order_id
            // ")->execute([':order_id' => $order_id]);

            // ── TODO: Queue a retrieve job for 60 minutes later
            // enqueue_retrieve_job($order_id, time() + 3600);

            bd_log('webhook', 'PAYMENT_PENDING', [
                'order_id' => $order_id,
                'txn_id'   => $txn_id,
            ]);
            break;

        default:

            bd_log('webhook', 'UNKNOWN_STATUS', [
                'order_id'    => $order_id,
                'auth_status' => $auth_status,
                'full_payload'=> $txn,
            ]);
            break;
    }

    // ── 7. Acknowledge webhook ────────────────────────────────
    // BillDesk expects HTTP 200 + body "OK" within the timeout.
    // Any other code or timeout causes a retry.
    http_response_code(200);
    echo 'OK';

} catch (RuntimeException $e) {

    bd_log('webhook', 'ERROR', [
        'message' => $e->getMessage(),
        'ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();

} catch (Exception $e) {

    bd_log('webhook', 'UNEXPECTED_ERROR', [
        'message' => $e->getMessage(),
        'trace'   => $e->getTraceAsString(),
    ]);
    http_response_code(500);
    echo 'Internal server error';
}