<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Enable logging for debugging
$log_file = __DIR__ . '/webhook_log.txt';
file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] Webhook ping received!\n", FILE_APPEND);

$webhook_secret = $env['RAZORPAY_WEBHOOK_SECRET'] ?? getenv('RAZORPAY_WEBHOOK_SECRET') ?: '';

if ($webhook_secret === '') {
    // If webhook secret isn't configured, we can't securely verify the payload.
    // We return 500 so Razorpay retries until the server is configured.
    http_response_code(500);
    exit('Webhook secret not configured.');
}

$webhook_body = file_get_contents('php://input');
$webhook_signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

file_put_contents($log_file, "Signature header: $webhook_signature\n", FILE_APPEND);

if ($webhook_signature === '') {
    file_put_contents($log_file, "Error: Missing signature\n", FILE_APPEND);
    http_response_code(400);
    exit('Missing signature.');
}

try {
    $api = new Razorpay\Api\Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
    $api->utility->verifyWebhookSignature($webhook_body, $webhook_signature, $webhook_secret);
    file_put_contents($log_file, "Signature VERIFIED.\n", FILE_APPEND);
} catch (Throwable $e) {
    file_put_contents($log_file, "Error: Invalid signature. " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(400);
    exit('Invalid signature.');
}

// Signature is valid. Parse payload.
$payload = json_decode($webhook_body, true);

if (!isset($payload['event']) || !isset($payload['payload']['payment']['entity'])) {
    file_put_contents($log_file, "Error: Malformed payload.\n", FILE_APPEND);
    http_response_code(400);
    exit('Malformed payload.');
}

$event = $payload['event'];
$payment_entity = $payload['payload']['payment']['entity'];
file_put_contents($log_file, "Event type: $event\n", FILE_APPEND);

$payment_id = $payment_entity['id'] ?? '';
$notes = $payment_entity['notes'] ?? [];

// In create_order.php, we passed registration_id in the order notes, which Razorpay copies to the payment notes.
$registration_id = $notes['registration_id'] ?? '';
file_put_contents($log_file, "Registration ID from notes: $registration_id\n", FILE_APPEND);

$order_id_db = mysqli_real_escape_string($db_conn, $payment_entity['order_id'] ?? '');

// Fallback: If notes don't have registration_id, look it up using the order_id
if ($registration_id === '' && $order_id_db !== '') {
    $lookup_sql = "SELECT registration_id FROM payment_orders WHERE razorpay_order_id = '$order_id_db' LIMIT 1";
    $lookup_res = mysqli_query($db_conn, $lookup_sql);
    if ($lookup_res && mysqli_num_rows($lookup_res) > 0) {
        $lookup_row = mysqli_fetch_assoc($lookup_res);
        $registration_id = $lookup_row['registration_id'];
        file_put_contents($log_file, "Registration ID found via fallback: $registration_id\n", FILE_APPEND);
    }
}

if ($registration_id === '') {
    // We cannot identify the registration. We acknowledge receipt to avoid retries.
    file_put_contents($log_file, "Error: Registration ID not found in notes or database.\n", FILE_APPEND);
    http_response_code(200);
    exit('Registration ID not found.');
}

$reg_id_db = mysqli_real_escape_string($db_conn, $registration_id);
$payment_id_db = mysqli_real_escape_string($db_conn, $payment_id);
$error_desc_db = mysqli_real_escape_string($db_conn, $payment_entity['error_description'] ?? '');
$currency_db = mysqli_real_escape_string($db_conn, $payment_entity['currency'] ?? '');

file_put_contents($log_file, "Proceeding to update DB for Reg ID: $registration_id, Order: $order_id_db, Status: $event\n", FILE_APPEND);

if ($event === 'payment.captured' || $event === 'payment.authorized' || $event === 'order.paid') {
    $current_status = ($event === 'payment.captured' || $event === 'order.paid') ? 'captured' : 'authorized';

    // 1. Log the attempt with the accurate status
    $insert_attempt_sql = "INSERT INTO payment_attempts (razorpay_order_id, razorpay_payment_id, status) 
        VALUES ('$order_id_db', '$payment_id_db', '$current_status')";
    mysqli_query($db_conn, $insert_attempt_sql);

    // If it is only authorized, stop here. We only fulfill on capture.
    if ($event === 'payment.authorized') {
        echo json_encode(['success' => true, 'message' => 'Payment authorized logged.']);
        exit;
    }

    // 2. Update tracking order
    $update_order_sql = "UPDATE payment_orders SET status = 'paid' WHERE razorpay_order_id = '$order_id_db' LIMIT 1";
    mysqli_query($db_conn, $update_order_sql);

    // 3. Check if already completed to prevent duplicate emails
    $select_sql = "SELECT payment_status, first_name, last_name, email, package, base_amount FROM user_registrations WHERE registration_id = '$reg_id_db' LIMIT 1";
    $res = mysqli_query($db_conn, $select_sql);
    
    if ($res && mysqli_num_rows($res) > 0) {
        $user = mysqli_fetch_assoc($res);
        if ($user['payment_status'] !== 'Completed') {
            // Mark as Completed in main table
            $status_db = mysqli_real_escape_string($db_conn, 'Completed');
            $update_sql = "UPDATE user_registrations
                SET transaction_id = '$payment_id_db',
                    payment_status = '$status_db',
                    currency = '$currency_db'
                WHERE registration_id = '$reg_id_db'
                LIMIT 1";
            mysqli_query($db_conn, $update_sql);

            // --- EMAIL INVOICE LOGIC START ---
            $email = $user['email'];
            $firstName = $user['first_name'];
            $lastName = $user['last_name'];
            $package = $user['package'];
            $baseAmount = $user['base_amount'];
            
            require_once __DIR__ . '/../backend/PHPMailer/src/Exception.php';
            require_once __DIR__ . '/../backend/PHPMailer/src/PHPMailer.php';
            require_once __DIR__ . '/../backend/PHPMailer/src/SMTP.php';

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = $env['SMTP_USER'] ?? '';
                $mail->Password   = $env['SMTP_PASS'] ?? '';
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom($env['SMTP_USER'] ?? 'icswhmh2027@rajagiri.edu', 'ICSWHMH Organizing Team');
                $mail->addAddress($email, "$firstName $lastName");
                $mail->addReplyTo($env['SMTP_USER'] ?? 'icswhmh2027@rajagiri.edu', 'ICSWHMH Support');

                $mail->isHTML(true);
                $mail->Subject = 'Payment Receipt - ICSWHMH';
                
                $htmlBody = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px;'>
                    <h2 style='color: #4A148C; text-align: center;'>Payment Receipt</h2>
                    <p>Dear <strong>$firstName $lastName</strong>,</p>
                    <p>Thank you! Your payment has been successfully processed.</p>
                    <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 8px 0;'><strong>Registration ID:</strong></td>
                            <td style='padding: 8px 0; text-align: right;'>$registration_id</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0;'><strong>Transaction ID:</strong></td>
                            <td style='padding: 8px 0; text-align: right;'>$payment_id</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0;'><strong>Package:</strong></td>
                            <td style='padding: 8px 0; text-align: right;'>$package</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0;'><strong>Payment Status:</strong></td>
                            <td style='padding: 8px 0; text-align: right; color: green;'><strong>COMPLETED</strong></td>
                        </tr>
                        <tr>
                            <td style='padding: 15px 0; border-top: 2px solid #333;'><strong>Total Paid:</strong></td>
                            <td style='padding: 15px 0; text-align: right; border-top: 2px solid #333;'><strong>$baseAmount</strong></td>
                        </tr>
                    </table>
                    <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #777; text-align: center;'>If you have any questions, please reply to this email.</p>
                </div>";
                
                $mail->Body = $htmlBody;
                $mail->AltBody = "Payment Receipt\n\nDear $firstName $lastName,\n\nThank you! Your payment has been successfully processed.\n\nRegistration ID: $registration_id\nTransaction ID: $payment_id\nPackage: $package\nPayment Status: COMPLETED\nTotal Paid: $baseAmount\n\nIf you have any questions, please reply to this email.";

                $mail->send();
            } catch (Exception $e) {
                error_log("webhook.php: Failed to send payment receipt email to $email. Mailer Error: {$mail->ErrorInfo}");
            }
            // --- EMAIL INVOICE LOGIC END ---
        }
    }

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
