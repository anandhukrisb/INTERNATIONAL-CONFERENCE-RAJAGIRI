<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$webhook_secret = $env['RAZORPAY_WEBHOOK_SECRET'] ?? getenv('RAZORPAY_WEBHOOK_SECRET') ?: '';

if ($webhook_secret === '') {
    // If webhook secret isn't configured, we can't securely verify the payload.
    // We return 500 so Razorpay retries until the server is configured.
    http_response_code(500);
    exit('Webhook secret not configured.');
}

$webhook_body = file_get_contents('php://input');
$webhook_signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

if ($webhook_signature === '') {
    http_response_code(400);
    exit('Missing signature.');
}

try {
    $api = new Razorpay\Api\Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
    $api->utility->verifyWebhookSignature($webhook_body, $webhook_signature, $webhook_secret);
} catch (Throwable $e) {
    http_response_code(400);
    exit('Invalid signature.');
}

// Signature is valid. Parse payload.
$payload = json_decode($webhook_body, true);

if (!isset($payload['event']) || !isset($payload['payload']['payment']['entity'])) {
    http_response_code(400);
    exit('Malformed payload.');
}

$event = $payload['event'];
$payment_entity = $payload['payload']['payment']['entity'];

$payment_id = $payment_entity['id'] ?? '';
$notes = $payment_entity['notes'] ?? [];

// In create_order.php, we passed registration_id in the order notes, which Razorpay copies to the payment notes.
$registration_id = $notes['registration_id'] ?? '';

if ($registration_id === '') {
    // We cannot identify the registration. We acknowledge receipt to avoid retries.
    http_response_code(200);
    exit('Registration ID not found in notes.');
}

$reg_id_db = mysqli_real_escape_string($db_conn, $registration_id);
$payment_id_db = mysqli_real_escape_string($db_conn, $payment_id);
$order_id_db = mysqli_real_escape_string($db_conn, $payment_entity['order_id'] ?? '');
$error_desc_db = mysqli_real_escape_string($db_conn, $payment_entity['error_description'] ?? '');

if ($event === 'payment.captured' || $event === 'payment.authorized') {
    // 1. Log the attempt
    $insert_attempt_sql = "INSERT INTO payment_attempts (razorpay_order_id, razorpay_payment_id, status) 
        VALUES ('$order_id_db', '$payment_id_db', 'captured')";
    mysqli_query($db_conn, $insert_attempt_sql);

    // 2. Update tracking order
    $update_order_sql = "UPDATE payment_orders SET status = 'paid' WHERE razorpay_order_id = '$order_id_db' LIMIT 1";
    mysqli_query($db_conn, $update_order_sql);

    // 3. Mark as Completed in main table
    $status_db = mysqli_real_escape_string($db_conn, 'Completed');
    $update_sql = "UPDATE user_registrations
        SET transaction_id = '$payment_id_db',
            payment_status = '$status_db'
        WHERE registration_id = '$reg_id_db'
        LIMIT 1";
    mysqli_query($db_conn, $update_sql);

} elseif ($event === 'payment.failed') {
    // 1. Log the failed attempt
    $insert_attempt_sql = "INSERT INTO payment_attempts (razorpay_order_id, razorpay_payment_id, status, error_description) 
        VALUES ('$order_id_db', '$payment_id_db', 'failed', '$error_desc_db')";
    mysqli_query($db_conn, $insert_attempt_sql);

    // 2. Update tracking order only if not already paid
    $update_order_sql = "UPDATE payment_orders SET status = 'failed' WHERE razorpay_order_id = '$order_id_db' AND status != 'paid' LIMIT 1";
    mysqli_query($db_conn, $update_order_sql);

    // 3. Mark as Failed in main table, ONLY IF it is not already Completed
    $select_sql = "SELECT payment_status FROM user_registrations WHERE registration_id = '$reg_id_db' LIMIT 1";
    $res = mysqli_query($db_conn, $select_sql);
    
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        if ($row['payment_status'] !== 'Completed') {
            $status_db = mysqli_real_escape_string($db_conn, 'Failed');
            $update_sql = "UPDATE user_registrations
                SET transaction_id = '$payment_id_db',
                    payment_status = '$status_db'
                WHERE registration_id = '$reg_id_db'
                LIMIT 1";
            mysqli_query($db_conn, $update_sql);
        }
    }
}

// Acknowledge receipt of webhook to Razorpay
http_response_code(200);
echo json_encode(['status' => 'ok']);
