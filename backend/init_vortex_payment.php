<?php
// backend/init_vortex_payment.php
header('Content-Type: application/json');

// 1. Load database & environment configuration
require_once __DIR__ . '/db.php';

// Vortex Gateway Configuration
$isLocal = (!empty($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false));
$rawApiUrl = $env['VORTEX_API_URL'] ?? getenv('VORTEX_API_URL') ?: '';

if (empty($rawApiUrl)) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443;
    $protocol = $isHttps ? 'https://' : 'http://';
    if ($isLocal) {
        $rawApiUrl = $protocol . $_SERVER['HTTP_HOST'] . '/vortex/api';
    } else {
        $host = $_SERVER['HTTP_HOST'] ?? 'rpay.rajagiri.edu';
        $rawApiUrl = $protocol . $host . '/api';
    }
}

$vortexApiUrl = rtrim($rawApiUrl, '/') . '/create_payment_order.php';
// Safety check: ensure URL has protocol and host
if (!preg_match('#^https?://#i', $vortexApiUrl)) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443;
    $protocol = $isHttps ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'rpay.rajagiri.edu';
    $vortexApiUrl = $protocol . $host . '/' . ltrim($vortexApiUrl, '/');
}

$vortexApiKey = $env['VORTEX_API_KEY'] ?? getenv('VORTEX_API_KEY') ?: '839109936a140f77e293b0c17c50173f';
$vortexApiSecret = $env['VORTEX_API_SECRET'] ?? getenv('VORTEX_API_SECRET') ?: '0b48b7bd0f581246fc3f80130ba8c2e9';
$vortexEventId = $env['VORTEX_EVENT_ID'] ?? getenv('VORTEX_EVENT_ID') ?: 'ICSW20261011MV3SALAS2FM';

try {
    // 2. Get raw input data
    $json = file_get_contents('php://input');
    if (($json === '' || $json === false) && php_sapi_name() === 'cli') {
        $json = @file_get_contents('php://stdin');
    }
    $data = json_decode($json, true);
    
    $regId = $data['registration_id'] ?? '';
    if (empty($regId)) {
        throw new Exception("Registration ID is missing.");
    }
    
    // 3. Fetch user data from database
    $stmt = $pdo->prepare("SELECT * FROM user_registrations WHERE registration_id = :reg_id LIMIT 1");
    $stmt->execute([':reg_id' => $regId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("Invalid Registration ID.");
    }
    
    $currency = (strpos(strtolower($user['country_category']), 'india') !== false || strtolower($user['country_category']) === 'national') ? 'INR' : 'USD';
    
    // Determine the return URL for the user after checkout finishes
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443;
    $protocol = $isHttps ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = dirname(dirname($_SERVER['SCRIPT_NAME'])); // Back out of backend/
    
    $returnUrl = $protocol . $host . rtrim($basePath, '/\\') . '/backend/verify_vortex_payment.php?reg_id=' . urlencode($regId);
    
    // 3. Prepare Vortex API Request
    $payload = [
        'api_key' => $vortexApiKey,
        'api_secret' => $vortexApiSecret,
        'event_id' => $vortexEventId,
        'email' => $user['email'],
        'mobile' => $user['phone'] ?? '9999999999',
        'amount' => $user['base_amount'],
        'currency' => $currency,
        'redirect_url' => $returnUrl
    ];
    
    // 4. Send Request via cURL
    $ch = curl_init($vortexApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    // If you don't have local SSL set up, you might need to bypass verification (for dev only)
    if (strpos($vortexApiUrl, 'localhost') !== false) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        throw new Exception("cURL Error: " . $err);
    }
    
    $result = json_decode($response, true);
    
    if ($httpCode !== 200 || !isset($result['status']) || $result['status'] !== 'success') {
        $apiError = $result['message'] ?? "Failed to create Vortex payment order. HTTP: {$httpCode}. Response: " . strip_tags($response);
        throw new Exception($apiError);
    }
    
    // 5. Update user_registrations with transaction ID (optional but good practice)
    $vortexTxnId = $result['data']['vortex_transaction_id'];
    $stmtUpdate = $pdo->prepare("UPDATE user_registrations SET transaction_id = :txn_id WHERE registration_id = :reg_id");
    $stmtUpdate->execute([':txn_id' => $vortexTxnId, ':reg_id' => $regId]);
    
    // 6. Return Success Response
    $paymentUrl = $result['data']['payment_url'] ?? '';

    // Align checkout URL host with VORTEX_API_URL only if the gateway returned a mismatched host
    $gatewayHost = parse_url($vortexApiUrl, PHP_URL_HOST);
    $gatewayPath = parse_url($vortexApiUrl, PHP_URL_PATH) ?: '';
    $gatewayBasePath = preg_replace('#/api/?.*$#i', '', $gatewayPath); // e.g. "/vortex" or ""
    $gatewayScheme = parse_url($vortexApiUrl, PHP_URL_SCHEME) ?: 'https';

    $paymentUrlHost = parse_url($paymentUrl, PHP_URL_HOST);
    if ($gatewayHost && $paymentUrlHost && strcasecmp($gatewayHost, $paymentUrlHost) !== 0) {
        $token = '';
        if (preg_match('/[?&]token=([^&]+)/', $paymentUrl, $m)) {
            $token = $m[1];
        }
        $paymentUrl = $gatewayScheme . '://' . $gatewayHost . rtrim($gatewayBasePath, '/') . '/checkout.php?token=' . $token;
    }

    echo json_encode([
        'success' => true,
        'payment_url' => $paymentUrl,
        'vortex_transaction_id' => $vortexTxnId
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
