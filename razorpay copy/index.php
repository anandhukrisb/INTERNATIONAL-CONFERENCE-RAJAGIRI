<?php
declare(strict_types=1);

session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$status_message = '';
$status_kind = '';
$status_details = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (($_POST['action'] ?? '') === 'status')) {
    $posted_token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
    if ($posted_token === '' || !hash_equals((string)$_SESSION['csrf_token'], $posted_token)) {
        $status_kind = 'bad';
        $status_message = 'Invalid request.';
    } else {
        $tx_id_raw = isset($_POST['transaction_id']) ? trim((string)$_POST['transaction_id']) : '';
        $order_id_raw = isset($_POST['razorpay_order_id']) ? trim((string)$_POST['razorpay_order_id']) : '';

        if ($tx_id_raw === '' && $order_id_raw === '') {
            $status_kind = 'bad';
            $status_message = 'Enter Transaction ID or Razorpay Order ID.';
        } else {
            require_once __DIR__ . '/config.php';

            $tx = null;
            $tx_id = 0;
            if ($tx_id_raw !== '' && preg_match('/^\d+$/', $tx_id_raw)) {
                $tx_id = (int)$tx_id_raw;
                if ($tx_id > 0) {
                    $res = mysqli_query($db_conn, "SELECT * FROM transactions WHERE id = $tx_id LIMIT 1");
                    if ($res !== false) {
                        $row = mysqli_fetch_assoc($res);
                        if (is_array($row)) {
                            $tx = $row;
                        }
                    }
                }
            }

            $order_id = $order_id_raw;
            if ($order_id === '' && is_array($tx) && !empty($tx['razorpay_order_id'])) {
                $order_id = (string)$tx['razorpay_order_id'];
            }

            if ($order_id === '') {
                $status_kind = 'muted';
                $status_message = 'Payment status: PENDING';
                if ($tx_id > 0 && !is_array($tx)) {
                    $status_kind = 'bad';
                    $status_message = 'Transaction not found.';
                }
            } else {
                try {
                    $api = new Razorpay\Api\Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
                    $order = $api->order->fetch($order_id);
                    $order_status = isset($order['status']) ? (string)$order['status'] : '';

                    $final_status = 'PENDING';
                    $payment_id = '';
                    $failure_reason = '';

                    if ($order_status === 'paid') {
                        $final_status = 'SUCCESS';
                        $payments = $api->order->fetch($order_id)->payments();
                        if (isset($payments['items'][0]['id'])) {
                            $payment_id = (string)$payments['items'][0]['id'];
                        }
                    } else {
                        if (is_array($tx) && !empty($tx['created_at'])) {
                            $created_ts = strtotime((string)$tx['created_at']);
                            if ($created_ts !== false) {
                                $age_seconds = time() - $created_ts;
                                if ($age_seconds >= 3600) {
                                    $final_status = 'FAILED';
                                    $failure_reason = 'Not paid within 60 minutes.';
                                }
                            }
                        }
                    }

                    if (is_array($tx) || $tx_id > 0) {
                        $order_id_db = mysqli_real_escape_string($db_conn, $order_id);
                        $payment_id_db = mysqli_real_escape_string($db_conn, $payment_id);
                        $final_status_db = mysqli_real_escape_string($db_conn, $final_status);

                        $where = '';
                        if (is_array($tx) && !empty($tx['id'])) {
                            $where = "id = " . (int)$tx['id'];
                        } else {
                            $where = "razorpay_order_id = '$order_id_db'";
                        }

                        mysqli_query(
                            $db_conn,
                            "UPDATE transactions
                             SET payment_status = '$final_status_db',
                                 razorpay_payment_id = CASE WHEN '$payment_id_db' <> '' THEN '$payment_id_db' ELSE razorpay_payment_id END
                             WHERE $where
                             LIMIT 1"
                        );

                        $res2 = mysqli_query($db_conn, "SELECT * FROM transactions WHERE $where LIMIT 1");
                        if ($res2 !== false) {
                            $row2 = mysqli_fetch_assoc($res2);
                            if (is_array($row2)) {
                                $tx = $row2;
                            }
                        }
                    }

                    $status_kind = $final_status === 'SUCCESS' ? 'ok' : ($final_status === 'FAILED' ? 'bad' : 'muted');
                    $status_message = 'Payment status: ' . $final_status;

                    $status_details = [
                        'Razorpay Order ID' => $order_id,
                        'Razorpay Order Status' => $order_status !== '' ? $order_status : 'unknown',
                    ];
                    if ($payment_id !== '') {
                        $status_details['Razorpay Payment ID'] = $payment_id;
                    }
                    if ($failure_reason !== '') {
                        $status_details['Reason'] = $failure_reason;
                    }
                    if (is_array($tx)) {
                        $status_details['Transaction ID'] = (string)$tx['id'];
                        $status_details['Amount (INR)'] = (string)$tx['amount'];
                    }
                } catch (Throwable $e) {
                    $status_kind = 'bad';
                    $status_message = 'Unable to fetch status from Razorpay.';
                }
            }
        }
    }
}

$old_name = isset($_GET['name']) ? (string)$_GET['name'] : '';
$old_email = isset($_GET['email']) ? (string)$_GET['email'] : '';
$old_amount = isset($_GET['amount']) ? (string)$_GET['amount'] : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Razorpay Checkout</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f7f7f7; }
        .wrap { max-width: 520px; margin: 50px auto; background: #fff; padding: 24px; border-radius: 10px; box-shadow: 0 2px 18px rgba(0,0,0,0.06); }
        h1 { margin: 0 0 16px; font-size: 20px; }
        h2 { margin: 18px 0 10px; font-size: 16px; }
        label { display: block; margin: 12px 0 6px; font-weight: 600; }
        input { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        button { width: 100%; margin-top: 18px; background: #0b74de; color: #fff; border: 0; padding: 12px 14px; border-radius: 8px; font-size: 15px; cursor: pointer; }
        button:hover { background: #095fb6; }
        .hint { margin-top: 10px; font-size: 12px; color: #666; }
        hr { border: 0; border-top: 1px solid #eee; margin: 18px 0; }
        .status { margin-top: 12px; padding: 12px 12px; border-radius: 8px; background: #f7f7f7; }
        .ok { color: #137333; font-weight: 700; }
        .bad { color: #c5221f; font-weight: 700; }
        .muted { color: #555; font-weight: 700; }
        .kv { font-size: 13px; color: #333; margin-top: 8px; }
        .kv div { margin: 6px 0; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Standard Checkout</h1>
        <form method="post" action="modal.php" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">

            <label for="payer_name">Name</label>
            <input id="payer_name" name="payer_name" type="text" required maxlength="255" value="<?php echo h($old_name); ?>">

            <label for="payer_email">Email</label>
            <input id="payer_email" name="payer_email" type="email" required maxlength="255" value="<?php echo h($old_email); ?>">

            <label for="amount">Amount (INR)</label>
            <input id="amount" name="amount" type="text" required inputmode="decimal" placeholder="e.g. 499.00" value="<?php echo h($old_amount); ?>">

            <button type="submit">Pay Now</button>
            <div class="hint">Amount supports up to 2 decimal places (INR).</div>
        </form>

        <hr>

        <h2>Check Payment Status</h2>
        <form method="post" action="index.php" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
            <input type="hidden" name="action" value="status">

            <label for="transaction_id">Transaction ID (Local)</label>
            <input id="transaction_id" name="transaction_id" type="text" inputmode="numeric" placeholder="e.g. 123">

            <div class="hint">OR</div>

            <label for="razorpay_order_id">Razorpay Order ID</label>
            <input id="razorpay_order_id" name="razorpay_order_id" type="text" placeholder="e.g. order_XXXXXXXXXXXXXX">

            <button type="submit">Check Status</button>
        </form>

        <?php if ($status_message !== ''): ?>
            <div class="status">
                <div class="<?php echo h($status_kind !== '' ? $status_kind : 'muted'); ?>"><?php echo h($status_message); ?></div>
                <?php if (!empty($status_details)): ?>
                    <div class="kv">
                        <?php foreach ($status_details as $k => $v): ?>
                            <div><strong><?php echo h((string)$k); ?>:</strong> <?php echo h((string)$v); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
