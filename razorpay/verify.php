<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if (RAZORPAY_KEY_ID === '' || RAZORPAY_KEY_SECRET === '') {
    http_response_code(500);
    echo 'Razorpay keys are not configured.';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$razorpay_payment_id = isset($_POST['razorpay_payment_id']) ? (string)$_POST['razorpay_payment_id'] : '';
$razorpay_order_id = isset($_POST['razorpay_order_id']) ? (string)$_POST['razorpay_order_id'] : '';
$razorpay_signature = isset($_POST['razorpay_signature']) ? (string)$_POST['razorpay_signature'] : '';

$payment_failed = false;
$failure_message = '';

if ($razorpay_order_id === '') {
    $payment_failed = true;
    $failure_message = 'Missing Razorpay order ID.';
}

$signature_verified = false;
if (!$payment_failed) {
    if ($razorpay_payment_id === '' || $razorpay_signature === '') {
        $payment_failed = true;
        $failure_message = 'Payment was not completed.';
    } else {
        try {
            $api = new Razorpay\Api\Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
            $api->utility->verifyPaymentSignature([
                'razorpay_order_id' => $razorpay_order_id,
                'razorpay_payment_id' => $razorpay_payment_id,
                'razorpay_signature' => $razorpay_signature,
            ]);
            $signature_verified = true;
        } catch (Throwable $e) {
            $payment_failed = true;
            $failure_message = 'Signature verification failed.';
        }
    }
}

$status = $payment_failed ? 'FAILED' : 'SUCCESS';

$order_id_db = mysqli_real_escape_string($db_conn, $razorpay_order_id);
$payment_id_db = mysqli_real_escape_string($db_conn, $razorpay_payment_id);
$signature_db = mysqli_real_escape_string($db_conn, $razorpay_signature);
$status_db = mysqli_real_escape_string($db_conn, $status);

$update_sql = "UPDATE transactions
SET razorpay_payment_id = '$payment_id_db',
    razorpay_signature = '$signature_db',
    payment_status = '$status_db'
WHERE razorpay_order_id = '$order_id_db'
LIMIT 1";

mysqli_query($db_conn, $update_sql);

$tx = null;
$select_sql = "SELECT id, payer_name, payer_email, amount, payment_status, razorpay_order_id, razorpay_payment_id
FROM transactions
WHERE razorpay_order_id = '$order_id_db'
LIMIT 1";
$res = mysqli_query($db_conn, $select_sql);
if ($res !== false) {
    $row = mysqli_fetch_assoc($res);
    if (is_array($row)) {
        $tx = $row;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Status</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f7f7f7; margin: 0; padding: 0; }
        .wrap { max-width: 720px; margin: 50px auto; background: #fff; padding: 24px; border-radius: 10px; box-shadow: 0 2px 18px rgba(0,0,0,0.06); }
        .ok { color: #137333; font-weight: 700; }
        .bad { color: #c5221f; font-weight: 700; }
        .meta { margin-top: 14px; font-size: 13px; color: #444; }
        .meta div { margin: 6px 0; }
        a { color: #0b74de; }
    </style>
</head>
<body>
    <div class="wrap">
        <?php if ($status === 'SUCCESS' && $signature_verified): ?>
            <h2 class="ok">Payment Successful</h2>
            <div>Your payment was verified and recorded.</div>
        <?php else: ?>
            <h2 class="bad">Payment Failed</h2>
            <div><?php echo h($failure_message !== '' ? $failure_message : 'Your payment could not be verified.'); ?></div>
        <?php endif; ?>

        <div class="meta">
            <div><strong>Razorpay Order ID:</strong> <?php echo h($razorpay_order_id); ?></div>
            <div><strong>Razorpay Payment ID:</strong> <?php echo h($razorpay_payment_id); ?></div>
            <?php if (is_array($tx)): ?>
                <div><strong>Transaction ID:</strong> <?php echo h((string)$tx['id']); ?></div>
                <div><strong>Amount (INR):</strong> <?php echo h((string)$tx['amount']); ?></div>
                <div><strong>Status:</strong> <?php echo h((string)$tx['payment_status']); ?></div>
            <?php endif; ?>
        </div>

        <div class="meta">
            <a href="index.php">Back to checkout</a>
        </div>
    </div>
</body>
</html>

