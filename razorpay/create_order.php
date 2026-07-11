<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}


$json = file_get_contents('php://input');
$data = json_decode($json, true);
$registration_id = isset($data['registration_id']) ? trim((string)$data['registration_id']) : '';

if ($registration_id === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing registration ID.']);
    exit;
}

$reg_id_db = mysqli_real_escape_string($db_conn, $registration_id);

$select_sql = "SELECT id, first_name, last_name, email, phone, base_amount, country_category FROM user_registrations WHERE registration_id = '$reg_id_db' LIMIT 1";
$res = mysqli_query($db_conn, $select_sql);

if ($res === false || mysqli_num_rows($res) === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Registration not found.']);
    exit;
}

$row = mysqli_fetch_assoc($res);
$amount_raw = $row['base_amount'];

$country_category = strtolower($row['country_category'] ?? '');
$currency = (strpos($country_category, 'india') !== false || $country_category === 'national') ? 'INR' : 'USD';


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
    $total = ((int)$rupees * 100) + (int)$paise_part;
    return $total > 0 ? $total : null;
}

$amount_paise = amount_to_paise((string)$amount_raw);
if ($amount_paise === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid base amount.']);
    exit;
}

$transaction_id = (int)$row['id'];

try {
    $api = new Razorpay\Api\Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
    $order = $api->order->create([
        'receipt' => (string)$transaction_id,
        'amount' => $amount_paise,
        'currency' => $currency, 
        'payment_capture' => 1,
        'notes' => [
            'registration_id' => $registration_id
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'Failed to create Razorpay order.', 'details' => $e->getMessage()]);
    exit;
}

$razorpay_order_id = isset($order['id']) ? (string)$order['id'] : '';
if ($razorpay_order_id === '') {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'Invalid Razorpay order response.']);
    exit;
}

try {
    
    $order_id_db = mysqli_real_escape_string($db_conn, $razorpay_order_id);
    $amount_db = (float)($amount_paise / 100);
    $currency_db = mysqli_real_escape_string($db_conn, $currency);

    $insert_order_sql = "INSERT INTO payment_orders (registration_id, razorpay_order_id, amount, currency, status) 
        VALUES ('$reg_id_db', '$order_id_db', $amount_db, '$currency_db', 'created')";
    mysqli_query($db_conn, $insert_order_sql);
} catch (Throwable $e) {
    
}

echo json_encode([
    'success' => true,
    'order_id' => $razorpay_order_id,
    'amount' => $amount_paise,
    'currency' => $currency,
    'key' => RAZORPAY_KEY_ID,
    'name' => trim($row['first_name'] . ' ' . $row['last_name']),
    'email' => $row['email'],
    'phone' => $row['phone'] ?? ''
]);
