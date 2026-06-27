<?php
// backend/verify_otp.php
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
    
    if (time() > $storedData['expires']) {
        unset($_SESSION[$sessionKey]);
        echo json_encode(['success' => false, 'error' => 'OTP has expired. Please request a new one.']);
        exit;
    }

    if ((string)$enteredOtp !== (string)$storedData['otp']) {
        echo json_encode(['success' => false, 'error' => 'Invalid OTP. Please try again.']);
        exit;
    }

    // OTP is valid!
    // Clear the OTP from session so it can't be reused
    unset($_SESSION[$sessionKey]);

    echo json_encode(['success' => true, 'message' => 'Email successfully verified.']);

} catch (Exception $e) {
    error_log("verify_otp.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An unexpected server error occurred.']);
}
?>
