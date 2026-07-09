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

// Signature is valid. Fetch payment details directly from Razorpay to ensure it is actually 'captured'.
try {
    $payment = $api->payment->fetch($razorpay_payment_id);
    $api_status = $payment->status;
    
    $order_id_db = mysqli_real_escape_string($db_conn, $razorpay_order_id);
    $payment_id_db = mysqli_real_escape_string($db_conn, $razorpay_payment_id);
    $reg_id_db = mysqli_real_escape_string($db_conn, $registration_id);

    if ($api_status === 'failed') {
        $error_desc = $payment->error_description ?? 'Payment failed on verification';
        $error_desc_db = mysqli_real_escape_string($db_conn, $error_desc);
        mysqli_query($db_conn, "INSERT INTO payment_attempts (razorpay_order_id, razorpay_payment_id, status, error_description) VALUES ('$order_id_db', '$payment_id_db', 'failed', '$error_desc_db')");
        mysqli_query($db_conn, "UPDATE payment_orders SET status = 'failed' WHERE razorpay_order_id = '$order_id_db' AND status != 'paid' LIMIT 1");
        mysqli_query($db_conn, "UPDATE user_registrations SET payment_status = 'Failed' WHERE registration_id = '$reg_id_db' AND payment_status != 'Completed' LIMIT 1");

        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Payment is marked as failed by Razorpay: ' . $error_desc]);
        exit;
    } 
    
    if ($api_status === 'authorized') {
        mysqli_query($db_conn, "INSERT INTO payment_attempts (razorpay_order_id, razorpay_payment_id, status) VALUES ('$order_id_db', '$payment_id_db', 'authorized')");
        echo json_encode(['success' => true, 'message' => 'Payment authorized but not yet captured.', 'transaction_id' => $razorpay_payment_id]);
        exit;
    }
    
    if ($api_status !== 'captured') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Payment not captured. Status: ' . $api_status]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not fetch payment status from Razorpay.']);
    exit;
}

// Payment is captured. Update database.
$reg_id_db = mysqli_real_escape_string($db_conn, $registration_id);
$payment_id_db = mysqli_real_escape_string($db_conn, $razorpay_payment_id);
$order_id_db = mysqli_real_escape_string($db_conn, $razorpay_order_id);
$status_db = mysqli_real_escape_string($db_conn, 'Completed');
$currency_db = mysqli_real_escape_string($db_conn, $payment->currency ?? '');

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
        payment_status = '$status_db',
        currency = '$currency_db'
    WHERE registration_id = '$reg_id_db'
    LIMIT 1";

    $update_res = mysqli_query($db_conn, $update_sql);

    if (!$update_res) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update payment status in database.']);
        exit;
    }

    // --- EMAIL INVOICE LOGIC START ---
    // Fetch user details for the email invoice
    $select_sql = "SELECT first_name, last_name, email, package, base_amount FROM user_registrations WHERE registration_id = '$reg_id_db' LIMIT 1";
    $res = mysqli_query($db_conn, $select_sql);
    if ($res && mysqli_num_rows($res) > 0) {
        $user = mysqli_fetch_assoc($res);
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
                        <td style='padding: 8px 0; text-align: right;'>$razorpay_payment_id</td>
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
            // Provide a plain-text fallback
            $mail->AltBody = "Payment Receipt\n\nDear $firstName $lastName,\n\nThank you! Your payment has been successfully processed.\n\nRegistration ID: $registration_id\nTransaction ID: $razorpay_payment_id\nPackage: $package\nPayment Status: COMPLETED\nTotal Paid: $baseAmount\n\nIf you have any questions, please reply to this email.";

            $mail->send();
        } catch (Exception $e) {
            error_log("verify_ajax.php: Failed to send payment receipt email to $email. Mailer Error: {$mail->ErrorInfo}");
        }
    }
    // --- EMAIL INVOICE LOGIC END ---
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
