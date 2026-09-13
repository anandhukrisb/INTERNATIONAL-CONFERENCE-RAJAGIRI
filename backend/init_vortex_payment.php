<?php
// backend/init_vortex_payment.php
header('Content-Type: application/json');

$env = parse_ini_file(__DIR__ . '/../.env');

// Vortex Gateway Configuration
$vortexApiUrl = rtrim($env['VORTEX_API_URL'], '/') . '/create_payment_order.php';
$vortexApiKey = $env['VORTEX_API_KEY'];
$vortexApiSecret = $env['VORTEX_API_SECRET'];
$vortexEventId = $env['VORTEX_EVENT_ID'];

try {
    // 1. Get raw input data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    $regId = $data['registration_id'] ?? '';
    if (empty($regId)) {
        throw new Exception("Registration ID is missing.");
    }
    
    // 2. Fetch user data from database
    require_once __DIR__ . '/db.php';
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
    
    if ($httpCode !== 200 || !($result['success'] ?? false)) {
        throw new Exception($result['error'] ?? "Failed to create Vortex payment order.");
    }
    
    // 5. Update user_registrations with transaction ID (optional but good practice)
    $vortexTxnId = $result['data']['vortex_transaction_id'];
    $stmtUpdate = $pdo->prepare("UPDATE user_registrations SET transaction_id = :txn_id WHERE registration_id = :reg_id");
    $stmtUpdate->execute([':txn_id' => $vortexTxnId, ':reg_id' => $regId]);
    
    // 6. Return Success Response
    echo json_encode([
        'success' => true,
        'payment_url' => $result['data']['payment_url'],
        'vortex_transaction_id' => $vortexTxnId
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
