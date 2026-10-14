<?php
// backend/verify_vortex_payment.php
session_start();

require_once __DIR__ . '/db.php';

$isLocal = (!empty($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false));
$rawApiUrl = $env['VORTEX_API_URL'] ?? getenv('VORTEX_API_URL') ?: '';

if ($isLocal) {
    // When running locally on dev server, route to local Vortex instance
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443;
    $protocol = $isHttps ? 'https://' : 'http://';
    $rawApiUrl = $protocol . $_SERVER['HTTP_HOST'] . '/vortex/api';
} elseif (empty($rawApiUrl)) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443;
    $protocol = $isHttps ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'icswhmh.com';
    $rawApiUrl = $protocol . $host . '/vortex/api';
}

$vortexApiUrl = rtrim($rawApiUrl, '/') . '/get_payment_status.php';
if (!preg_match('#^https?://#i', $vortexApiUrl)) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443;
    $protocol = $isHttps ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'icswhmh.com';
    $vortexApiUrl = $protocol . $host . '/' . ltrim($vortexApiUrl, '/');
}

$vortexApiKey = $env['VORTEX_API_KEY'] ?? getenv('VORTEX_API_KEY') ?: 'test_ea1b4a6263fc0b0fb0ad8680be4d4087';
$vortexApiSecret = $env['VORTEX_API_SECRET'] ?? getenv('VORTEX_API_SECRET') ?: 'e415495af5e85622c1ab65cf45199165';

$regId = $_GET['reg_id'] ?? '';
$vortexTxnId = $_GET['vortex_transaction_id'] ?? '';
$statusCode = $_GET['status_code'] ?? '';

// Determine Base URL to return to process_payment.php
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443;
$protocol = $isHttps ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = dirname(dirname($_SERVER['SCRIPT_NAME']));
$frontendUrl = $protocol . $host . rtrim($basePath, '/\\') . '/process_payment.php?reg_id=' . urlencode($regId);

if (empty($regId) || empty($vortexTxnId)) {
    // Missing required parameters, redirect back to payment page to show failure
    header("Location: " . $frontendUrl);
    exit;
}

try {
    
    // 1. We must verify the transaction with Vortex API securely
    $payload = [
        'api_key' => $vortexApiKey,
        'api_secret' => $vortexApiSecret,
        'vortex_transaction_id' => $vortexTxnId
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
    
    if ($httpCode === 200 && isset($result['status']) && $result['status'] === 'success' && $result['data']['payment_status'] === 'SUCCESS') {
        // Payment is verified as SUCCESS
        $stmtUpdate = $pdo->prepare("UPDATE user_registrations SET payment_status = 'Completed' WHERE registration_id = :reg_id");
        $stmtUpdate->execute([':reg_id' => $regId]);
        
        // Redirect back to frontend
        header("Location: " . $frontendUrl . "&status=success");
        exit;
    } else {
        // Payment failed or is pending
        $stmtUpdate = $pdo->prepare("UPDATE user_registrations SET payment_status = 'Failed' WHERE registration_id = :reg_id");
        $stmtUpdate->execute([':reg_id' => $regId]);
        
        header("Location: " . $frontendUrl . "&status=failed");
        exit;
    }

} catch (Exception $e) {
    // In case of DB error or script error, return to frontend
    header("Location: " . $frontendUrl . "&status=error");
    exit;
}
