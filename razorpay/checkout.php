<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function amount_to_paise(string $amount_inr): ?int
{
    $amount_inr = trim(str_replace([',', ' '], '', $amount_inr));
    if ($amount_inr === '' || !preg_match('/^\d+(\.\d{1,2})?$/', $amount_inr)) {
        return null;
    }

    $parts = explode('.', $amount_inr, 2);
    $rupees = $parts[0] === '' ? '0' : $parts[0];
    $paise_part = $parts[1] ?? '0';
    $paise_part = str_pad($paise_part, 2, '0', STR_PAD_RIGHT);
    $paise_part = substr($paise_part, 0, 2);

    $rupees_int = (int)$rupees;
    $paise_int = (int)$paise_part;

    $total = ($rupees_int * 100) + $paise_int;
    if ($total <= 0) {
        return null;
    }
    return $total;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$posted_token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
$session_token = isset($_SESSION['csrf_token']) ? (string)$_SESSION['csrf_token'] : '';
if ($posted_token === '' || !hash_equals($session_token, $posted_token)) {
    http_response_code(400);
    echo 'Invalid request.';
    exit;
}

if (RAZORPAY_KEY_ID === '' || RAZORPAY_KEY_SECRET === '') {
    http_response_code(500);
    echo 'Razorpay keys are not configured.';
    exit;
}

$payer_name_raw = isset($_POST['payer_name']) ? trim((string)$_POST['payer_name']) : '';
$payer_email_raw = isset($_POST['payer_email']) ? trim((string)$_POST['payer_email']) : '';
$amount_raw = isset($_POST['amount']) ? (string)$_POST['amount'] : '';

if ($payer_name_raw === '' || $payer_email_raw === '' || !filter_var($payer_email_raw, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo 'Please provide a valid name and email.';
    exit;
}

$amount_paise = amount_to_paise($amount_raw);
if ($amount_paise === null) {
    http_response_code(400);
    echo 'Please provide a valid amount.';
    exit;
}

$amount_inr = number_format($amount_paise / 100, 2, '.', '');

$payer_name = mysqli_real_escape_string($db_conn, $payer_name_raw);
$payer_email = mysqli_real_escape_string($db_conn, $payer_email_raw);
$amount_db = mysqli_real_escape_string($db_conn, $amount_inr);

$insert_sql = "INSERT INTO transactions (payer_name, payer_email, amount, payment_status, created_at)
VALUES ('$payer_name', '$payer_email', '$amount_db', 'PENDING', NOW())";

$insert_ok = mysqli_query($db_conn, $insert_sql);
if ($insert_ok === false) {
    http_response_code(500);
    echo 'Failed to create transaction.';
    exit;
}

$transaction_id = (int)mysqli_insert_id($db_conn);
if ($transaction_id <= 0) {
    http_response_code(500);
    echo 'Failed to create transaction reference.';
    exit;
}

try {
    $api = new Razorpay\Api\Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
    $order = $api->order->create([
        'receipt' => (string)$transaction_id,
        'amount' => $amount_paise,
        'currency' => 'INR',
        'payment_capture' => 1,
    ]);
} catch (Throwable $e) {
    http_response_code(502);
    echo 'Failed to create Razorpay order.';
    exit;
}

$razorpay_order_id = isset($order['id']) ? (string)$order['id'] : '';
if ($razorpay_order_id === '') {
    http_response_code(502);
    echo 'Invalid Razorpay order response.';
    exit;
}

$razorpay_order_id_db = mysqli_real_escape_string($db_conn, $razorpay_order_id);
$update_sql = "UPDATE transactions
SET razorpay_order_id = '$razorpay_order_id_db'
WHERE id = $transaction_id
LIMIT 1";

$update_ok = mysqli_query($db_conn, $update_sql);
if ($update_ok === false) {
    http_response_code(500);
    echo 'Failed to update transaction.';
    exit;
}

$display_name = $payer_name_raw;
$display_email = $payer_email_raw;

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = isset($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : 'localhost';
$dir = isset($_SERVER['SCRIPT_NAME']) ? rtrim(str_replace('\\', '/', dirname((string)$_SERVER['SCRIPT_NAME'])), '/') : '';
$base_url = $scheme . '://' . $host . ($dir !== '' && $dir !== '/' ? $dir : '');
$callback_url = $base_url . '/verify.php';

$options = [
    'key' => RAZORPAY_KEY_ID,
    'amount' => $amount_paise,
    'currency' => 'INR',
    'name' => 'Checkout',
    'description' => 'Payment',
    'order_id' => $razorpay_order_id,
    'callback_url' => $callback_url,
    'redirect' => true,
    'prefill' => [
        'name' => $display_name,
        'email' => $display_email,
    ],
    'notes' => [
        'transaction_id' => (string)$transaction_id,
    ],
    'theme' => [
        'color' => '#0b74de',
    ],
];

$options_json = json_encode($options, JSON_UNESCAPED_SLASHES);
if ($options_json === false) {
    http_response_code(500);
    echo 'Failed to start checkout.';
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Redirecting to Razorpay</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f7f7f7; margin: 0; padding: 0; }
        .wrap { max-width: 520px; margin: 50px auto; background: #fff; padding: 24px; border-radius: 10px; box-shadow: 0 2px 18px rgba(0,0,0,0.06); }
        .muted { color: #666; font-size: 13px; }
        button { width: 100%; margin-top: 18px; background: #0b74de; color: #fff; border: 0; padding: 12px 14px; border-radius: 8px; font-size: 15px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="wrap">
        <h2>Opening payment window…</h2>
        <div class="muted">If the Razorpay window does not open, click the button below.</div>
        <button id="payBtn" type="button">Pay Now</button>
    </div>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        (function () {
            var options = <?php echo $options_json; ?>;
            var rzp = new Razorpay(options);

            function openCheckout() {
                try { rzp.open(); } catch (e) {}
            }

            var btn = document.getElementById('payBtn');
            if (btn) btn.addEventListener('click', openCheckout);

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', openCheckout);
            } else {
                openCheckout();
            }
        })();
    </script>
</body>
</html>
