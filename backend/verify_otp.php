<?php

header('Content-Type: application/json');

ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    $email = $data['email'] ?? '';
    $enteredOtp = $data['otp'] ?? '';

    if (empty($email) || empty($enteredOtp)) {
        echo json_encode(['success' => false, 'error' => 'Email and OTP are required.']);
        exit;
    }

    $sessionKey = 'verification_otp_' . $email;

    if (!isset($_SESSION[$sessionKey])) {
        echo json_encode(['success' => false, 'error' => 'No OTP request found for this email. Please request a new one.']);
        exit;
    }

    $storedData = $_SESSION[$sessionKey];
    $attempts = ($storedData['attempts'] ?? 0) + 1;
    $_SESSION[$sessionKey]['attempts'] = $attempts;

    if ($attempts > 5) {
        unset($_SESSION[$sessionKey]);
        echo json_encode(['success' => false, 'error' => 'Too many failed attempts. Please request a new OTP.']);
        exit;
    }
    
    if (time() > $storedData['expires']) {
        unset($_SESSION[$sessionKey]);
        echo json_encode(['success' => false, 'error' => 'OTP has expired. Please request a new one.']);
        exit;
    }

    if (!hash_equals((string)$storedData['otp'], (string)$enteredOtp)) {
        $remaining = 5 - $attempts;
        echo json_encode(['success' => false, 'error' => "Invalid OTP. You have {$remaining} attempt(s) remaining."]);
        exit;
    }

    unset($_SESSION[$sessionKey]);
    $_SESSION['verified_email'] = $email;
    $_SESSION['verified_email_time'] = time();

    echo json_encode(['success' => true, 'message' => 'Email successfully verified.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An unexpected server error occurred.']);
}
?>
