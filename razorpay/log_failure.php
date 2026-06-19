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

$registration_id = isset($data['registration_id']) ? (string)$data['registration_id'] : '';
$razorpay_order_id = isset($data['razorpay_order_id']) ? (string)$data['razorpay_order_id'] : '';
$razorpay_payment_id = isset($data['razorpay_payment_id']) ? (string)$data['razorpay_payment_id'] : '';
$error_description = isset($data['error_description']) ? (string)$data['error_description'] : '';

if ($razorpay_order_id === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing order ID.']);
    exit;
}

$order_id_db = mysqli_real_escape_string($db_conn, $razorpay_order_id);
$payment_id_db = mysqli_real_escape_string($db_conn, $razorpay_payment_id);
$error_desc_db = mysqli_real_escape_string($db_conn, $error_description);
$reg_id_db = mysqli_real_escape_string($db_conn, $registration_id);

try {
    // 1. Log the failed attempt
    $insert_attempt_sql = "INSERT INTO payment_attempts (razorpay_order_id, razorpay_payment_id, status, error_description) 
        VALUES ('$order_id_db', '$payment_id_db', 'failed', '$error_desc_db')";
    mysqli_query($db_conn, $insert_attempt_sql);

    // 2. Update tracking order only if not already paid
    $update_order_sql = "UPDATE payment_orders SET status = 'failed' WHERE razorpay_order_id = '$order_id_db' AND status != 'paid' LIMIT 1";
    mysqli_query($db_conn, $update_order_sql);

    // 3. Mark as Failed in main table, ONLY IF it is not already Completed
    if ($reg_id_db !== '') {
        $select_sql = "SELECT payment_status FROM user_registrations WHERE registration_id = '$reg_id_db' LIMIT 1";
        $res = mysqli_query($db_conn, $select_sql);
        
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            if ($row['payment_status'] !== 'Completed') {
                $status_db = mysqli_real_escape_string($db_conn, 'Failed');
                $update_sql = "UPDATE user_registrations
                    SET payment_status = '$status_db'
                    WHERE registration_id = '$reg_id_db'
                    LIMIT 1";
                mysqli_query($db_conn, $update_sql);
            }
        }
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit;
}

echo json_encode(['success' => true]);
