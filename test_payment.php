<?php
/**
 * ------------------------------------------------------------
 * test_payment.php - Vortex Unified Payment Gateway Test Lab
 * ------------------------------------------------------------
 * Project : ICSWHMH 2027 / Vortex Unified Payments
 * Purpose : Standalone test payment page to verify 1 Rupee (INR)
 *           and 1 Dollar (USD) transactions via PayVortex.
 * ------------------------------------------------------------
 */

// Enable session for CSRF & return tracking
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Locate and load environment configuration
$rootEnvPath = __DIR__ . '/.env';
$vortexEnvPath = __DIR__ . '/vortex/.env';

$env = [];
foreach ([$rootEnvPath, $vortexEnvPath] as $path) {
    if (file_exists($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '#') === 0 || strpos($line, ';') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($k, $v) = explode('=', $line, 2);
                    $env[trim($k)] = trim(trim($v), '"\'');
                }
            }
        }
    }
}

// Credentials for Vortex API
$vortexApiKey    = $env['VORTEX_API_KEY'] ?? getenv('VORTEX_API_KEY') ?: '839109936a140f77e293b0c17c50173f';
$vortexApiSecret = $env['VORTEX_API_SECRET'] ?? getenv('VORTEX_API_SECRET') ?: '0b48b7bd0f581246fc3f80130ba8c2e9';
$vortexEventId   = $env['VORTEX_EVENT_ID'] ?? getenv('VORTEX_EVENT_ID') ?: 'ICSW20261011MV3SALAS2FM';

// Determine host & protocol
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443;
$protocol = $isHttps ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$currentScript = $_SERVER['SCRIPT_NAME'] ?? '/test_payment.php';
$baseDir = dirname($currentScript);
if ($baseDir === '/' || $baseDir === '\\') $baseDir = '';

// Determine redirect return URL after checkout
$returnUrl = $protocol . $host . rtrim($baseDir, '/\\') . '/test_payment.php?returned=1';

// -------------------------------------------------------------------------
// AJAX / POST ACTION: Create Vortex Payment Order
// -------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'create_order') {
    header('Content-Type: application/json');

    try {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!$payload) {
            $payload = $_POST;
        }

        $selection = $payload['payment_tier'] ?? '1_INR';
        $email     = trim($payload['email'] ?? 'test.payer@rajagiri.edu');
        $mobile    = trim($payload['mobile'] ?? '9876543210');
        $name      = trim($payload['name'] ?? 'Test Payer');

        // Parse amount & currency based on dropdown selection
        if ($selection === '1_USD') {
            $amount = 1.00;
            $currency = 'USD';
        } else {
            $amount = 1.00;
            $currency = 'INR';
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please provide a valid test email address.");
        }

        if (empty($mobile) || !preg_match('/^[0-9]{10}$/', preg_replace('/[^0-9]/', '', $mobile))) {
            throw new Exception("Please provide a valid 10-digit mobile number.");
        }

        $orderPayload = [
            'api_key'      => $vortexApiKey,
            'api_secret'   => $vortexApiSecret,
            'event_id'     => $vortexEventId,
            'email'        => $email,
            'mobile'       => $mobile,
            'amount'       => $amount,
            'currency'     => $currency,
            'redirect_url' => $returnUrl
        ];

        // 1. Try internal direct class invocation first (failsafe against single-threaded loopback freeze)
        $paymentUrl = null;
        $vortexTxnId = null;

        if (file_exists(__DIR__ . '/vortex/classes/Auth.php') &&
            file_exists(__DIR__ . '/vortex/classes/Payment.php') &&
            file_exists(__DIR__ . '/vortex/classes/CheckoutSession.php')) {
            
            require_once __DIR__ . '/vortex/classes/Auth.php';
            require_once __DIR__ . '/vortex/classes/Payment.php';
            require_once __DIR__ . '/vortex/classes/Event.php';
            require_once __DIR__ . '/vortex/classes/CheckoutSession.php';
            require_once __DIR__ . '/vortex/classes/Validator.php';
            require_once __DIR__ . '/vortex/classes/Response.php';

            try {
                $auth = new Auth();
                $client = $auth->authenticate($vortexApiKey, $vortexApiSecret);
                if ($client === false) {
                    throw new Exception("Vortex API Authentication Failed: Invalid API Key or Secret.");
                }

                $paymentObj = new Payment();
                $payResult = $paymentObj->createPayment(
                    $client['id'],
                    $vortexEventId,
                    $email,
                    $mobile,
                    $amount,
                    $currency,
                    $client['is_test_mode'] ?? 1
                );

                if (is_array($payResult) && ($payResult['success'] ?? false) === true) {
                    $txDbId = $payResult['data']['database_transaction_id'];
                    $vortexTxnId = $payResult['data']['vortex_transaction_id'];

                    $sessionObj = new CheckoutSession();
                    $sessionRes = $sessionObj->createSession($txDbId, $returnUrl, 5);

                    if (($sessionRes['success'] ?? false) === true) {
                        $token = $sessionRes['session_token'];
                        $vortexDir = rtrim($baseDir, '/\\') . '/vortex';
                        $paymentUrl = $protocol . $host . $vortexDir . '/checkout.php?token=' . urlencode($token);
                    } else {
                        throw new Exception("Failed to generate checkout session: " . ($sessionRes['message'] ?? 'Unknown session error'));
                    }
                } else {
                    $errMsg = is_array($payResult) ? ($payResult['message'] ?? 'Unable to create payment order.') : 'Payment engine returned invalid response.';
                    throw new Exception($errMsg);
                }
            } catch (\Throwable $directEx) {
                // If it's a known error from Payment.php (like validation or inactive event), rethrow it directly
                $isLocalServer = (php_sapi_name() === 'cli-server') || in_array($host, ['localhost', '127.0.0.1', 'localhost:8000']);
                if ($isLocalServer || !empty($directEx->getMessage())) {
                    throw $directEx;
                }
                $paymentUrl = null;
            }
        }

        // 2. If direct call wasn't completed, execute via HTTP cURL API
        if (empty($paymentUrl)) {
            $apiUrl = $protocol . $host . rtrim($baseDir, '/\\') . '/vortex/api/create_payment_order.php';
            
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderPayload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                throw new Exception("cURL Gateway Connection Error: " . $curlError);
            }

            $resData = json_decode($response, true);
            if ($httpCode !== 200 || !isset($resData['status']) || $resData['status'] !== 'success') {
                $err = $resData['message'] ?? ("Vortex HTTP {$httpCode}: " . strip_tags($response));
                throw new Exception($err);
            }

            $paymentUrl  = $resData['data']['payment_url'] ?? '';
            $vortexTxnId = $resData['data']['vortex_transaction_id'] ?? '';
        }

        if (empty($paymentUrl)) {
            throw new Exception("Vortex failed to generate a valid checkout URL.");
        }

        echo json_encode([
            'success'               => true,
            'payment_url'           => $paymentUrl,
            'vortex_transaction_id' => $vortexTxnId,
            'amount'                => $amount,
            'currency'              => $currency
        ]);
        exit;

    } catch (\Throwable $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => $e->getMessage()
        ]);
        exit;
    }
}

// -------------------------------------------------------------------------
// Check Returned Transaction Status
// -------------------------------------------------------------------------
$isReturned = isset($_GET['returned']);
$returnedTxnId = $_GET['vortex_transaction_id'] ?? '';
$returnedStatusCode = $_GET['status_code'] ?? '';
$returnedAmount = $_GET['amount'] ?? '';
$returnedCurrency = $_GET['currency'] ?? '';
$txnDetails = null;

if ($isReturned && !empty($returnedTxnId)) {
    // Attempt to read status from Vortex database directly
    try {
        if (file_exists(__DIR__ . '/vortex/classes/Database.php')) {
            require_once __DIR__ . '/vortex/classes/Database.php';
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM transactions WHERE vortex_transaction_id = ? LIMIT 1");
            $stmt->execute([$returnedTxnId]);
            $txnDetails = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (\Throwable $e) {
        // Fallback to query params
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayVortex Payment Testing Lab</title>
    <link rel="icon" type="image/x-icon" href="https://res.cloudinary.com/dswfp5fwx/image/upload/v1778131826/Favicon-192_hdltam.ico">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-purple: #1D0A3F;
            --primary-purple-hover: #291054;
            --accent-gold: #C9A227;
            --accent-gold-hover: #b8921e;
            --bg-page: #0f1422;
            --card-bg: #171d30;
            --card-border: rgba(255, 255, 255, 0.08);
            --card-border-focus: #C9A227;
            --text-light: #f8fafc;
            --text-muted: #94a3b8;
            --text-dark: #1e293b;
            --success-green: #10b981;
            --error-red: #ef4444;
            --badge-bg: rgba(201, 162, 39, 0.15);
            --badge-text: #FAD02C;
            --font-heading: 'Outfit', sans-serif;
            --font-body: 'Inter', sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-page);
            background-image: 
                radial-gradient(circle at 10% 15%, rgba(29, 10, 63, 0.8) 0%, transparent 45%),
                radial-gradient(circle at 90% 85%, rgba(201, 162, 39, 0.15) 0%, transparent 45%);
            color: var(--text-light);
            font-family: var(--font-body);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 24px;
        }

        .container {
            width: 100%;
            max-width: 580px;
        }

        /* Top Header */
        .brand-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--badge-bg);
            color: var(--badge-text);
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
            border: 1px solid rgba(201, 162, 39, 0.3);
        }

        .brand-badge::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--accent-gold);
            box-shadow: 0 0 10px var(--accent-gold);
        }

        .brand-title {
            font-family: var(--font-heading);
            font-size: 2.2rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 6px;
        }

        .brand-title span {
            color: var(--accent-gold);
        }

        .brand-subtitle {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        /* Testing Card */
        .test-card {
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 34px 30px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(12px);
        }

        .section-label {
            font-family: var(--font-heading);
            font-size: 1.1rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .mode-tag {
            font-size: 0.72rem;
            background: rgba(16, 185, 129, 0.15);
            color: var(--success-green);
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 600;
            font-family: var(--font-mono);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-select, .form-input {
            width: 100%;
            background-color: rgba(15, 20, 34, 0.7);
            border: 1.5px solid var(--card-border);
            border-radius: 12px;
            padding: 14px 16px;
            color: #ffffff;
            font-size: 1rem;
            font-family: var(--font-body);
            transition: all 0.2s ease;
            outline: none;
        }

        .form-select {
            cursor: pointer;
            font-weight: 600;
            font-size: 1.05rem;
            color: var(--badge-text);
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23C9A227'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 18px;
            appearance: none;
            -webkit-appearance: none;
        }

        .form-select option {
            background-color: #171d30;
            color: #ffffff;
            padding: 10px;
        }

        .form-select:focus, .form-input:focus {
            border-color: var(--card-border-focus);
            box-shadow: 0 0 0 3px rgba(201, 162, 39, 0.2);
            background-color: rgba(15, 20, 34, 0.9);
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        /* Pricing Preview Banner */
        .price-preview-banner {
            background: linear-gradient(135deg, rgba(29, 10, 63, 0.6) 0%, rgba(201, 162, 39, 0.12) 100%);
            border: 1px solid rgba(201, 162, 39, 0.25);
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .price-preview-title {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 2px;
        }

        .price-preview-val {
            font-family: var(--font-heading);
            font-size: 1.8rem;
            font-weight: 800;
            color: #ffffff;
        }

        .price-preview-val span {
            color: var(--accent-gold);
        }

        .gateway-tag {
            font-size: 0.8rem;
            background: rgba(255, 255, 255, 0.08);
            padding: 6px 12px;
            border-radius: 20px;
            font-family: var(--font-mono);
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .gateway-tag::before {
            content: "⚡";
        }

        /* Payment Button */
        .btn-pay {
            width: 100%;
            background: linear-gradient(135deg, var(--accent-gold) 0%, #E6BF3A 100%);
            color: var(--primary-purple);
            font-family: var(--font-heading);
            font-size: 1.15rem;
            font-weight: 700;
            padding: 16px 24px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            box-shadow: 0 10px 25px -5px rgba(201, 162, 39, 0.4);
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -5px rgba(201, 162, 39, 0.55);
            background: linear-gradient(135deg, #dfb42c 0%, #ffd64f 100%);
        }

        .btn-pay:active {
            transform: translateY(0);
        }

        .btn-pay:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Spinner */
        .spinner {
            width: 20px;
            height: 20px;
            border: 2.5px solid rgba(29, 10, 63, 0.3);
            border-top-color: var(--primary-purple);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Technical Info Box */
        .tech-info-box {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--card-border);
            font-size: 0.78rem;
            color: var(--text-muted);
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .tech-row {
            display: flex;
            justify-content: space-between;
            font-family: var(--font-mono);
        }

        .tech-val {
            color: #ffffff;
            font-weight: 500;
        }

        /* Alert / Status Card */
        .result-card {
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 34px 30px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            margin-bottom: 24px;
            text-align: center;
        }

        .result-icon {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            margin-bottom: 16px;
        }

        .result-success .result-icon {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success-green);
            border: 2px solid rgba(16, 185, 129, 0.4);
        }

        .result-failure .result-icon {
            background: rgba(239, 68, 68, 0.15);
            color: var(--error-red);
            border: 2px solid rgba(239, 68, 68, 0.4);
        }

        .result-title {
            font-family: var(--font-heading);
            font-size: 1.6rem;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .result-desc {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-bottom: 22px;
        }

        .receipt-table {
            width: 100%;
            background: rgba(15, 20, 34, 0.7);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            text-align: left;
            font-size: 0.88rem;
        }

        .receipt-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .receipt-row:last-child {
            border-bottom: none;
        }

        .receipt-label {
            color: var(--text-muted);
        }

        .receipt-val {
            font-family: var(--font-mono);
            color: #ffffff;
            font-weight: 600;
            word-break: break-all;
        }

        .btn-secondary {
            display: inline-block;
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .btn-link {
            color: var(--accent-gold);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            margin-left: 12px;
        }

        .btn-link:hover {
            text-decoration: underline;
        }

        /* Error Notification Banner */
        .toast-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 0.9rem;
            display: none;
        }
    </style>
</head>
<body>

<div class="container">

    <!-- Brand Header -->
    <div class="brand-header">
        <div class="brand-badge">ICSWHMH 2027 • PayVortex Gateway</div>
        <h1 class="brand-title">Testing <span>Payment Page</span></h1>
        <p class="brand-subtitle">Simulate real-time 1 Rupee (INR) and 1 Dollar (USD) transactions via Vortex</p>
    </div>

    <?php if ($isReturned): ?>
        <?php 
            $statusNormalized = strtoupper($txnDetails['status'] ?? ($returnedStatusCode == 200 ? 'SUCCESS' : 'FAILED'));
            $isSuccess = ($statusNormalized === 'SUCCESS');
        ?>
        <!-- Payment Result Receipt Card -->
        <div class="result-card <?= $isSuccess ? 'result-success' : 'result-failure' ?>">
            <div class="result-icon">
                <?= $isSuccess ? '✓' : '✕' ?>
            </div>
            <h2 class="result-title">
                <?= $isSuccess ? 'Test Payment Completed!' : 'Payment Unsuccessful' ?>
            </h2>
            <p class="result-desc">
                <?= $isSuccess ? 'Your test transaction was verified and recorded in the PayVortex engine.' : 'The test transaction was not completed or failed verification.' ?>
            </p>

            <div class="receipt-table">
                <div class="receipt-row">
                    <span class="receipt-label">Vortex Transaction ID</span>
                    <span class="receipt-val"><?= htmlspecialchars($returnedTxnId) ?></span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Amount & Currency</span>
                    <span class="receipt-val">
                        <?= ($txnDetails['currency'] ?? $returnedCurrency) === 'INR' ? '₹' : '$' ?>
                        <?= number_format((float)($txnDetails['amount'] ?? $returnedAmount ?: 1.00), 2) ?> 
                        (<?= htmlspecialchars($txnDetails['currency'] ?? $returnedCurrency ?: 'INR') ?>)
                    </span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Status</span>
                    <span class="receipt-val" style="color: <?= $isSuccess ? 'var(--success-green)' : 'var(--error-red)' ?>;">
                        <?= htmlspecialchars($statusNormalized) ?>
                    </span>
                </div>
                <?php if (!empty($txnDetails['razorpay_payment_id'])): ?>
                <div class="receipt-row">
                    <span class="receipt-label">Gateway Payment ID</span>
                    <span class="receipt-val"><?= htmlspecialchars($txnDetails['razorpay_payment_id']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($txnDetails['razorpay_order_id'])): ?>
                <div class="receipt-row">
                    <span class="receipt-label">Gateway Order ID</span>
                    <span class="receipt-val"><?= htmlspecialchars($txnDetails['razorpay_order_id']) ?></span>
                </div>
                <?php endif; ?>
                <div class="receipt-row">
                    <span class="receipt-label">Date & Time</span>
                    <span class="receipt-val"><?= htmlspecialchars($txnDetails['created_at'] ?? date('Y-m-d H:i:s')) ?></span>
                </div>
            </div>

            <div style="display: flex; justify-content: center; align-items: center; flex-wrap: wrap; gap: 12px;">
                <a href="test_payment.php" class="btn-secondary">← Run Another Test Payment</a>
                <a href="vortex/admin/payments/view.php?id=<?= urlencode($txnDetails['id'] ?? '') ?>" target="_blank" class="btn-link">View in Admin Inspector &rarr;</a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Payment Configuration Form Card -->
    <div class="test-card" id="paymentTestCard" <?= $isReturned ? 'style="display:none;"' : '' ?>>
        <div class="section-label">
            <span>Configure Test Transaction</span>
            <span class="mode-tag">Vortex Sandbox Ready</span>
        </div>

        <div class="toast-error" id="errorToast"></div>

        <form id="testPaymentForm" onsubmit="initiateTestPayment(event)">
            
            <!-- Amount & Currency Dropdown (Required) -->
            <div class="form-group">
                <label for="paymentTier" class="form-label">Select Amount & Currency <span style="color:var(--accent-gold);">*</span></label>
                <select id="paymentTier" name="payment_tier" class="form-select" onchange="updatePriceBanner()">
                    <option value="1_INR" selected>1 Rupee (₹1.00 INR) — Domestic National Test</option>
                    <option value="1_USD">1 Dollar ($1.00 USD) — International Test</option>
                </select>
            </div>

            <!-- Real-time Preview Banner -->
            <div class="price-preview-banner">
                <div>
                    <div class="price-preview-title">Payable Charge</div>
                    <div class="price-preview-val" id="bannerAmountDisplay">₹1.<span>00</span> <small style="font-size: 0.9rem; font-weight: normal; color: var(--text-muted);">INR</small></div>
                </div>
                <div class="gateway-tag">Razorpay Sandbox</div>
            </div>

            <!-- Customer Details (Optional/Configurable with sensible test defaults) -->
            <div class="form-group">
                <label for="customerName" class="form-label">Test Customer Name</label>
                <input type="text" id="customerName" name="name" class="form-input" value="Test Participant" required>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="customerEmail" class="form-label">Customer Email</label>
                    <input type="email" id="customerEmail" name="email" class="form-input" value="test.user@rajagiri.edu" required>
                </div>
                <div class="form-group">
                    <label for="customerMobile" class="form-label">Customer Mobile</label>
                    <input type="tel" id="customerMobile" name="mobile" class="form-input" value="9876543210" required>
                </div>
            </div>

            <!-- Payment Action Button -->
            <button type="submit" id="btnPay" class="btn-pay">
                <span id="btnPayText">Pay ₹1.00 with PayVortex</span>
            </button>
        </form>

        <!-- Technical Metadata -->
        <div class="tech-info-box">
            <div class="tech-row">
                <span>Vortex Event ID:</span>
                <span class="tech-val"><?= htmlspecialchars($vortexEventId) ?></span>
            </div>
            <div class="tech-row">
                <span>Vortex API Key:</span>
                <span class="tech-val"><?= htmlspecialchars(substr($vortexApiKey, 0, 8) . '...' . substr($vortexApiKey, -6)) ?></span>
            </div>
            <div class="tech-row">
                <span>Session Duration:</span>
                <span class="tech-val">5 Minutes (Temporary Token)</span>
            </div>
            <div class="tech-row">
                <span>Target Checkout:</span>
                <span class="tech-val">vortex/checkout.php</span>
            </div>
        </div>
    </div>

</div>

<script>
    function updatePriceBanner() {
        const tier = document.getElementById('paymentTier').value;
        const bannerDisplay = document.getElementById('bannerAmountDisplay');
        const btnText = document.getElementById('btnPayText');

        if (tier === '1_USD') {
            bannerDisplay.innerHTML = '$1.<span>00</span> <small style="font-size: 0.9rem; font-weight: normal; color: var(--text-muted);">USD</small>';
            btnText.textContent = 'Pay $1.00 with PayVortex';
        } else {
            bannerDisplay.innerHTML = '₹1.<span>00</span> <small style="font-size: 0.9rem; font-weight: normal; color: var(--text-muted);">INR</small>';
            btnText.textContent = 'Pay ₹1.00 with PayVortex';
        }
    }

    async function initiateTestPayment(event) {
        event.preventDefault();

        const btn = document.getElementById('btnPay');
        const btnText = document.getElementById('btnPayText');
        const errorToast = document.getElementById('errorToast');
        
        errorToast.style.display = 'none';
        errorToast.textContent = '';

        const tier = document.getElementById('paymentTier').value;
        const name = document.getElementById('customerName').value.trim();
        const email = document.getElementById('customerEmail').value.trim();
        const mobile = document.getElementById('customerMobile').value.trim();

        // Validation
        if (!email || !mobile) {
            showError('Please fill in both email and mobile number.');
            return;
        }

        // Set loading state
        btn.disabled = true;
        const originalText = btnText.textContent;
        btn.innerHTML = '<span class="spinner"></span><span>Connecting to Vortex Gateway...</span>';

        try {
            const response = await fetch('test_payment.php?action=create_order', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    payment_tier: tier,
                    name: name,
                    email: email,
                    mobile: mobile
                })
            });

            const data = await response.json();

            if (!response.ok || !data.success || !data.payment_url) {
                throw new Error(data.error || 'Failed to initialize test payment order.');
            }

            // Redirect user directly to the Vortex Checkout session
            btn.innerHTML = '<span class="spinner"></span><span>Launching Checkout Interface...</span>';
            window.location.href = data.payment_url;

        } catch (err) {
            console.error('Payment Initialization Error:', err);
            showError(err.message || 'An error occurred while connecting to PayVortex.');
            btn.disabled = false;
            btn.innerHTML = `<span id="btnPayText">${originalText}</span>`;
        }
    }

    function showError(msg) {
        const errorToast = document.getElementById('errorToast');
        errorToast.textContent = '⚠ ' + msg;
        errorToast.style.display = 'block';
    }

    // Initialize display state on load
    document.addEventListener('DOMContentLoaded', updatePriceBanner);
</script>

</body>
</html>
