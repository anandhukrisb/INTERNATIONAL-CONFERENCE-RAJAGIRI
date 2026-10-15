<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$reg_id = $data['registration_id'] ?? '';

if (empty($reg_id)) {
    echo json_encode(['success' => false, 'error' => 'Registration ID is required']);
    exit;
}

try {
    require_once __DIR__ . '/../backend/db.php';
    
    // Ensure the registration is 'Completed'
    $stmt = $pdo->prepare("SELECT payment_status, transaction_id, base_amount FROM user_registrations WHERE registration_id = :reg_id");
    $stmt->execute([':reg_id' => $reg_id]);
    $reg = $stmt->fetch();
    
    if (!$reg || $reg['payment_status'] !== 'Completed') {
        echo json_encode(['success' => false, 'error' => 'Registration is not in Completed state.']);
        exit;
    }
    
    if (empty($reg['transaction_id'])) {
        echo json_encode(['success' => false, 'error' => 'No Vortex transaction ID found for this registration.']);
        exit;
    }
    
    $vortexTxnId = $reg['transaction_id'];
    
    // Call Vortex API to create refund
    $rawApiUrl = $env['VORTEX_API_URL'] ?? getenv('VORTEX_API_URL') ?: '';
    if (empty($rawApiUrl)) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443;
        $protocol = $isHttps ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'rpay.rajagiri.edu';
        $rawApiUrl = $protocol . $host . '/api';
    }

    $vortexApiUrl = rtrim($rawApiUrl, '/') . '/create_refund.php';
    if (!preg_match('#^https?://#i', $vortexApiUrl)) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443;
        $protocol = $isHttps ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'rpay.rajagiri.edu';
        $vortexApiUrl = $protocol . $host . '/' . ltrim($vortexApiUrl, '/');
    }
    $vortexApiKey = $env['VORTEX_API_KEY'] ?? $env['KEY'] ?? getenv('VORTEX_API_KEY') ?: '839109936a140f77e293b0c17c50173f';
    $vortexApiSecret = $env['VORTEX_API_SECRET'] ?? $env['SECRET'] ?? getenv('VORTEX_API_SECRET') ?: '0b48b7bd0f581246fc3f80130ba8c2e9';
    
    $payload = [
        'api_key' => $vortexApiKey,
        'api_secret' => $vortexApiSecret,
        'vortex_transaction_id' => $vortexTxnId,
        'amount' => $reg['base_amount'] // Full refund by default
    ];
    
    $ch = curl_init($vortexApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    if (strpos($vortexApiUrl, 'localhost') !== false) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode === 200 && isset($result['status']) && $result['status'] === 'success') {
        // Update the status to 'Refund Approved'
        $stmt = $pdo->prepare("UPDATE user_registrations SET payment_status = 'Refund Approved' WHERE registration_id = :reg_id");
        $stmt->execute([':reg_id' => $reg_id]);
        
        echo json_encode(['success' => true]);
    } else {
        $errorMsg = $result['message'] ?? 'Unknown error from Vortex Gateway';
        echo json_encode(['success' => false, 'error' => 'Refund failed: ' . $errorMsg]);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
