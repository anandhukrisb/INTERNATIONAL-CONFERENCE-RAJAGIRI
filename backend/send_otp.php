<?php

header('Content-Type: application/json');


ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();

try {
    
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    $email = $data['email'] ?? '';

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Valid email is required.']);
        exit;
    }

    
    $sessionKey = 'verification_otp_' . $email;
    $lastTimeKey = 'last_otp_time_' . $email;

    if (isset($_SESSION[$lastTimeKey]) && (time() - $_SESSION[$lastTimeKey]) < 60) {
        $waitTime = 60 - (time() - $_SESSION[$lastTimeKey]);
        echo json_encode(['success' => false, 'error' => "Please wait {$waitTime} seconds before requesting a new OTP."]);
        exit;
    }

    $otp = random_int(100000, 999999);
    $_SESSION[$sessionKey] = [
        'otp' => $otp,
        'expires' => time() + (10 * 60),
        'attempts' => 0
    ];
    $_SESSION[$lastTimeKey] = time();

    
    require_once __DIR__ . '/db.php';
    
    
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $env['SMTP_USER'] ?? '';
        $mail->Password   = $env['SMTP_PASS'] ?? '';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom($env['SMTP_USER'] ?? 'icswhmh2027@rajagiri.edu', 'ICSWHMH Verification');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Your Registration OTP - ICSWHMH';
        
        $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;'>
            <h2 style='color: #1D0A3F; border-bottom: 2px solid #C9A227; padding-bottom: 10px;'>Email Verification</h2>
            <p>Hello,</p>
            <p>You requested to verify your email for registration. Your One-Time Password (OTP) is:</p>
            <div style='background-color: #f4f4f4; text-align: center; padding: 15px; font-size: 24px; font-weight: bold; letter-spacing: 5px; color: #1D0A3F; border-radius: 4px; margin: 20px 0;'>
                {$otp}
            </div>
            <p>This code will expire in 10 minutes. If you did not request this, please ignore this email.</p>
            <br>
            <p style='color: #777; font-size: 12px;'>Best regards,<br>ICSWHMH Organizing Team</p>
        </div>";

        $mail->Body = $message;
        $mail->send();

        echo json_encode(['success' => true, 'message' => 'OTP sent successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to send OTP email. Please check your email configuration.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An unexpected server error occurred.']);
}
?>
