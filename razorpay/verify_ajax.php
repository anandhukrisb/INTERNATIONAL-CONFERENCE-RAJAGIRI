<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/config.php';

if (RAZORPAY_KEY_ID === '' || RAZORPAY_KEY_SECRET === '') {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Razorpay keys are not configured.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// Read JSON payload
$json = file_get_contents('php://input');
$data = json_decode($json, true);

$registration_id = isset($data['registration_id']) ? (string)$data['registration_id'] : '';
$razorpay_payment_id = isset($data['razorpay_payment_id']) ? (string)$data['razorpay_payment_id'] : '';
$razorpay_order_id = isset($data['razorpay_order_id']) ? (string)$data['razorpay_order_id'] : '';
$razorpay_signature = isset($data['razorpay_signature']) ? (string)$data['razorpay_signature'] : '';

if ($registration_id === '' || $razorpay_order_id === '' || $razorpay_payment_id === '' || $razorpay_signature === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required payment details.']);
    exit;
}

$signature_verified = false;
try {
    $api = new Razorpay\Api\Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
    $api->utility->verifyPaymentSignature([
        'razorpay_order_id'   => $razorpay_order_id,
        'razorpay_payment_id' => $razorpay_payment_id,
        'razorpay_signature'  => $razorpay_signature,
    ]);
    $signature_verified = true;
} catch (Throwable $e) {
    // Signature verification failed
    $signature_verified = false;
}

if (!$signature_verified) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Signature verification failed.']);
    exit;
}

// Signature is valid. Update database.
$reg_id_db = mysqli_real_escape_string($db_conn, $registration_id);
$payment_id_db = mysqli_real_escape_string($db_conn, $razorpay_payment_id);
$order_id_db = mysqli_real_escape_string($db_conn, $razorpay_order_id);
$status_db = mysqli_real_escape_string($db_conn, 'Completed');

try {
    // 1. Log the attempt
    $insert_attempt_sql = "INSERT INTO payment_attempts (razorpay_order_id, razorpay_payment_id, status) 
        VALUES ('$order_id_db', '$payment_id_db', 'captured')";
    mysqli_query($db_conn, $insert_attempt_sql);

    // 2. Update the tracking order
    $update_order_sql = "UPDATE payment_orders SET status = 'paid' WHERE razorpay_order_id = '$order_id_db' LIMIT 1";
    mysqli_query($db_conn, $update_order_sql);

    // 3. Update the main registration row
    $update_sql = "UPDATE user_registrations
    SET transaction_id = '$payment_id_db',
        payment_status = '$status_db'
    WHERE registration_id = '$reg_id_db'
    LIMIT 1";

    $update_res = mysqli_query($db_conn, $update_sql);

    if (!$update_res) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update payment status in database.']);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Payment verified successfully.',
    'transaction_id' => $razorpay_payment_id
]);
