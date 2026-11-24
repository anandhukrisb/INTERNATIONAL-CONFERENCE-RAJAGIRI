<?php
/**
 * ------------------------------------------------------------
 * test_payment.php - Minimal Vortex Test Payment Page
 * ------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load environment configuration
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

// Host & return URL
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443;
$protocol = $isHttps ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$currentScript = $_SERVER['SCRIPT_NAME'] ?? '/test_payment.php';
$baseDir = dirname($currentScript);
if ($baseDir === '/' || $baseDir === '\\') $baseDir = '';
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

        $selection   = trim($payload['payment_tier'] ?? '1_INR');
        $email       = trim($payload['email'] ?? '');
        $countryCode = trim($payload['country_code'] ?? '+91');
        $rawPhone    = trim($payload['mobile'] ?? '');
        $name        = trim($payload['name'] ?? '');

        // Validation: Required fields
        if (empty($name)) {
            throw new Exception("Please enter your full name.");
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }

        if (empty($rawPhone)) {
            throw new Exception("Please enter your phone number.");
        }

        // Clean digits
        $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);
        if (strlen($cleanPhone) < 6 || strlen($cleanPhone) > 15) {
            throw new Exception("Please enter a valid phone number (6 to 15 digits).");
        }

        // Format combined phone number with + country code
        if (!str_starts_with($countryCode, '+')) {
            $countryCode = '+' . $countryCode;
        }
        $fullMobile = $countryCode . $cleanPhone;

        if ($selection === '2_USD') {
            $amount = 2.00;
            $currency = 'USD';
        } elseif ($selection === '3_USD') {
            $amount = 3.00;
            $currency = 'USD';
        } elseif ($selection === '4_USD') {
            $amount = 4.00;
            $currency = 'USD';
        } elseif ($selection === '25_USD') {
            $amount = 25.00;
            $currency = 'USD';
        } elseif ($selection === '50_USD') {
            $amount = 50.00;
            $currency = 'USD';
        } elseif ($selection === '100_USD') {
            $amount = 100.00;
            $currency = 'USD';
        } else {
            $amount = 1.00;
            $currency = 'INR';
        }

        $paymentUrl = null;
        $vortexTxnId = null;

        // Use direct internal OOP instantiation
        if (file_exists(__DIR__ . '/vortex/classes/Auth.php') &&
            file_exists(__DIR__ . '/vortex/classes/Payment.php') &&
            file_exists(__DIR__ . '/vortex/classes/CheckoutSession.php')) {
            
            require_once __DIR__ . '/vortex/classes/Auth.php';
            require_once __DIR__ . '/vortex/classes/Payment.php';
            require_once __DIR__ . '/vortex/classes/Event.php';
            require_once __DIR__ . '/vortex/classes/CheckoutSession.php';
            require_once __DIR__ . '/vortex/classes/Validator.php';
            require_once __DIR__ . '/vortex/classes/Response.php';

            $auth = new Auth();
            $client = $auth->authenticate($vortexApiKey, $vortexApiSecret);
            if ($client === false) {
                throw new Exception("Vortex API Authentication Failed: Invalid credentials.");
            }

            $paymentObj = new Payment();
            $payResult = $paymentObj->createPayment(
                $client['id'],
                $vortexEventId,
                $email,
                $fullMobile,
                $amount,
                $currency,
                $client['is_test_mode'] ?? 1
            );

            if (is_array($payResult) && ($payResult['success'] ?? false) === true) {
                $txDbId = $payResult['data']['database_transaction_id'];
                $vortexTxnId = $payResult['data']['vortex_transaction_id'];

                $sessionObj = new CheckoutSession();
                // 15-minute checkout session duration
                $sessionRes = $sessionObj->createSession($txDbId, $returnUrl, 15);

                if (($sessionRes['success'] ?? false) === true) {
                    $token = $sessionRes['session_token'];
                    $vortexDir = rtrim($baseDir, '/\\') . '/vortex';
                    $paymentUrl = $protocol . $host . $vortexDir . '/checkout.php?token=' . urlencode($token);
                } else {
                    throw new Exception("Failed to generate checkout session: " . ($sessionRes['message'] ?? 'Error'));
                }
            } else {
                $errMsg = is_array($payResult) ? ($payResult['message'] ?? 'Unable to create payment.') : 'Payment engine error.';
                throw new Exception($errMsg);
            }
        }

        if (empty($paymentUrl)) {
            throw new Exception("Could not initialize payment session.");
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
    try {
        if (file_exists(__DIR__ . '/vortex/classes/Database.php')) {
            require_once __DIR__ . '/vortex/classes/Database.php';
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM transactions WHERE vortex_transaction_id = ? LIMIT 1");
            $stmt->execute([$returnedTxnId]);
            $txnDetails = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (\Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Payment - PayVortex</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 460px;
        }

        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 32px 28px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        }

        .header {
            margin-bottom: 24px;
        }

        .header h1 {
            font-size: 1.35rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .header p {
            font-size: 0.875rem;
            color: #64748b;
        }

        .form-group {
            margin-bottom: 18px;
            position: relative;
        }

        label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .required-star {
            color: #ef4444;
            margin-left: 2px;
        }

        select, input[type="text"], input[type="email"], input[type="tel"] {
            width: 100%;
            padding: 11px 14px;
            font-size: 0.95rem;
            color: #0f172a;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            font-family: inherit;
        }

        select:focus, input:focus {
            border-color: #0f172a;
            box-shadow: 0 0 0 1px #0f172a;
        }

        select {
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23475569' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            appearance: none;
            -webkit-appearance: none;
        }

        /* Phone Input with Country Code Selector */
        .phone-input-group {
            display: flex;
            gap: 8px;
            position: relative;
        }

        .country-select-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 11px 12px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            color: #0f172a;
            cursor: pointer;
            white-space: nowrap;
            min-width: 110px;
            justify-content: space-between;
            transition: border-color 0.15s ease;
            user-select: none;
        }

        .country-select-btn:hover, .country-select-btn.active {
            border-color: #0f172a;
        }

        .country-select-btn .flag {
            font-size: 1.1rem;
        }

        .country-select-btn .chevron {
            font-size: 0.65rem;
            color: #64748b;
            margin-left: 2px;
        }

        .phone-input-group input[type="tel"] {
            flex: 1;
        }

        /* Searchable Country Dropdown Menu */
        .country-dropdown-menu {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            width: 100%;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            z-index: 100;
            display: none;
            flex-direction: column;
            overflow: hidden;
            max-height: 280px;
            animation: fadeInMenu 0.15s ease;
        }

        @keyframes fadeInMenu {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .country-dropdown-menu.open {
            display: flex;
        }

        .country-search-box {
            padding: 8px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .country-search-box input {
            width: 100%;
            padding: 8px 10px;
            font-size: 0.85rem;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #ffffff;
        }

        .country-list {
            list-style: none;
            overflow-y: auto;
            max-height: 220px;
            margin: 0;
            padding: 4px 0;
            -webkit-overflow-scrolling: touch;
        }

        .country-list-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 0.875rem;
            color: #1e293b;
            transition: background 0.12s ease;
        }

        .country-list-item:hover, .country-list-item.selected {
            background: #f1f5f9;
        }

        .country-list-item .country-info {
            display: flex;
            align-items: center;
            gap: 8px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .country-list-item .country-code {
            font-weight: 600;
            color: #64748b;
            font-size: 0.8rem;
            margin-left: 8px;
        }

        .no-country-match {
            padding: 12px;
            text-align: center;
            color: #94a3b8;
            font-size: 0.85rem;
            display: none;
        }

        .summary-box {
            background: #f1f5f9;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .summary-label {
            font-size: 0.85rem;
            color: #475569;
            font-weight: 500;
        }

        .summary-amount {
            font-size: 1.15rem;
            font-weight: 700;
            color: #0f172a;
        }

        .btn-pay {
            width: 100%;
            background: #0f172a;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 13px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.15s ease;
            font-family: inherit;
        }

        .btn-pay:hover {
            background: #1e293b;
        }

        .btn-pay:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .footer-note {
            margin-top: 20px;
            text-align: center;
            font-size: 0.75rem;
            color: #94a3b8;
        }

        /* Error alert */
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 11px 14px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 18px;
            display: none;
        }

        /* Receipt Card */
        .receipt {
            text-align: center;
        }

        .status-badge {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-success {
            background: #dcfce7;
            color: #15803d;
        }

        .status-failed {
            background: #fee2e2;
            color: #b91c1c;
        }

        .receipt h2 {
            font-size: 1.25rem;
            color: #0f172a;
            margin-bottom: 18px;
        }

        .receipt-details {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px;
            margin-bottom: 20px;
            text-align: left;
            font-size: 0.85rem;
        }

        .receipt-item {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .receipt-item:last-child {
            border-bottom: none;
        }

        .receipt-item span:first-child {
            color: #64748b;
        }

        .receipt-item span:last-child {
            font-weight: 600;
            color: #0f172a;
            word-break: break-all;
        }

        .btn-link {
            display: inline-block;
            font-size: 0.875rem;
            color: #0f172a;
            text-decoration: underline;
            font-weight: 500;
            cursor: pointer;
        }

        @media (max-width: 480px) {
            body {
                padding: 12px;
            }
            .card {
                padding: 24px 20px;
            }
            .country-dropdown-menu {
                max-height: 250px;
            }
        }
    </style>
</head>
<body>

<div class="container">

    <?php if ($isReturned): ?>
        <?php 
            $statusNormalized = strtoupper($txnDetails['status'] ?? ($returnedStatusCode == 200 ? 'SUCCESS' : 'FAILED'));
            $isSuccess = ($statusNormalized === 'SUCCESS');
        ?>
        <div class="card receipt">
            <span class="status-badge <?= $isSuccess ? 'status-success' : 'status-failed' ?>">
                <?= htmlspecialchars($statusNormalized) ?>
            </span>
            <h2><?= $isSuccess ? 'Payment Successful' : 'Payment Failed' ?></h2>

            <div class="receipt-details">
                <div class="receipt-item">
                    <span>Transaction ID</span>
                    <span><?= htmlspecialchars($returnedTxnId) ?></span>
                </div>
                <div class="receipt-item">
                    <span>Amount</span>
                    <span>
                        <?= ($txnDetails['currency'] ?? $returnedCurrency) === 'INR' ? '₹' : '$' ?><?= number_format((float)($txnDetails['amount'] ?? $returnedAmount ?: 1.00), 2) ?>
                        <?= htmlspecialchars($txnDetails['currency'] ?? $returnedCurrency ?: 'INR') ?>
                    </span>
                </div>
                <?php if (!empty($txnDetails['razorpay_payment_id'])): ?>
                <div class="receipt-item">
                    <span>Payment ID</span>
                    <span><?= htmlspecialchars($txnDetails['razorpay_payment_id']) ?></span>
                </div>
                <?php endif; ?>
                <div class="receipt-item">
                    <span>Date</span>
                    <span><?= htmlspecialchars($txnDetails['created_at'] ?? date('Y-m-d H:i')) ?></span>
                </div>
            </div>

            <a href="test_payment.php" class="btn-link">Make another test payment &rarr;</a>
        </div>

    <?php else: ?>

        <div class="card">
            <div class="header">
                <h1>Test Payment</h1>
                <p>Fill out the required fields to simulate payment via PayVortex</p>
            </div>

            <div class="alert-error" id="errorBox"></div>

            <form id="paymentForm" onsubmit="submitPayment(event)">
                
                <!-- Dropdown: Payment Tiers -->
                <div class="form-group">
                    <label for="paymentTier">Select Amount <span class="required-star">*</span></label>
                    <select id="paymentTier" name="payment_tier" onchange="updateAmount()" required>
                        <option value="1_INR" selected>1 Rupee (₹1.00 INR)</option>
                        <option value="2_USD">2 Dollars ($2.00 USD)</option>
                        <option value="3_USD">3 Dollars ($3.00 USD)</option>
                        <option value="4_USD">4 Dollars ($4.00 USD)</option>
                    </select>
                </div>

                <div class="summary-box">
                    <span class="summary-label">Total to pay</span>
                    <span class="summary-amount" id="displayAmount">₹1.00</span>
                </div>

                <!-- Customer Name -->
                <div class="form-group">
                    <label for="customerName">Full Name <span class="required-star">*</span></label>
                    <input type="text" id="customerName" name="name" placeholder="Enter your full name" required autocomplete="name">
                </div>

                <!-- Customer Email -->
                <div class="form-group">
                    <label for="customerEmail">Email Address <span class="required-star">*</span></label>
                    <input type="email" id="customerEmail" name="email" placeholder="name@example.com" required autocomplete="email">
                </div>

                <!-- Mobile with Searchable Country Code Dropdown -->
                <div class="form-group">
                    <label for="customerMobile">Phone Number <span class="required-star">*</span></label>
                    <input type="hidden" id="selectedCountryCode" name="country_code" value="+91">
                    
                    <div class="phone-input-group">
                        <button type="button" class="country-select-btn" id="countryBtn" onclick="toggleCountryDropdown()">
                            <span class="flag" id="btnFlag">🇮🇳</span>
                            <span id="btnDialCode">+91</span>
                            <span class="chevron">▼</span>
                        </button>
                        
                        <input type="tel" id="customerMobile" name="mobile" placeholder="Mobile / Phone number" required autocomplete="tel-national">

                        <!-- Dropdown Panel (Searchable) -->
                        <div class="country-dropdown-menu" id="countryDropdown">
                            <div class="country-search-box">
                                <input type="text" id="countrySearchInput" placeholder="Search country or code..." oninput="filterCountries()" autocomplete="off">
                            </div>
                            <ul class="country-list" id="countryList">
                                <!-- Populated dynamically by JS -->
                            </ul>
                            <div class="no-country-match" id="noCountryMatch">No matching country found</div>
                        </div>
                    </div>
                </div>

                <!-- Payment Button -->
                <button type="submit" id="payBtn" class="btn-pay">Pay ₹1.00</button>
            </form>

            <div class="footer-note">
                Vortex Unified Payment Gateway Sandbox
            </div>
        </div>

    <?php endif; ?>

</div>

<script>
    // List of major world countries with dial codes and flags
    const countries = [
        { name: "India", code: "+91", iso: "IN", flag: "🇮🇳" },
        { name: "United States", code: "+1", iso: "US", flag: "🇺🇸" },
        { name: "United Kingdom", code: "+44", iso: "GB", flag: "🇬🇧" },
        { name: "United Arab Emirates", code: "+971", iso: "AE", flag: "🇦🇪" },
        { name: "Saudi Arabia", code: "+966", iso: "SA", flag: "🇸🇦" },
        { name: "Canada", code: "+1", iso: "CA", flag: "🇨🇦" },
        { name: "Australia", code: "+61", iso: "AU", flag: "🇦🇺" },
        { name: "Qatar", code: "+974", iso: "QA", flag: "🇶🇦" },
        { name: "Oman", code: "+968", iso: "OM", flag: "🇴🇲" },
        { name: "Kuwait", code: "+965", iso: "KW", flag: "🇰🇼" },
        { name: "Bahrain", code: "+973", iso: "BH", flag: "🇧🇭" },
        { name: "Singapore", code: "+65", iso: "SG", flag: "🇸🇬" },
        { name: "Malaysia", code: "+60", iso: "MY", flag: "🇲🇾" },
        { name: "Germany", code: "+49", iso: "DE", flag: "🇩🇪" },
        { name: "France", code: "+33", iso: "FR", flag: "🇫🇷" },
        { name: "Italy", code: "+39", iso: "IT", flag: "🇮🇹" },
        { name: "Spain", code: "+34", iso: "ES", flag: "🇪🇸" },
        { name: "Netherlands", code: "+31", iso: "NL", flag: "🇳🇱" },
        { name: "Switzerland", code: "+41", iso: "CH", flag: "🇨🇭" },
        { name: "Ireland", code: "+353", iso: "IE", flag: "🇮🇪" },
        { name: "New Zealand", code: "+64", iso: "NZ", flag: "🇳🇿" },
        { name: "Japan", code: "+81", iso: "JP", flag: "🇯🇵" },
        { name: "South Korea", code: "+82", iso: "KR", flag: "🇰🇷" },
        { name: "China", code: "+86", iso: "CN", flag: "🇨🇳" },
        { name: "Hong Kong", code: "+852", iso: "HK", flag: "🇭🇰" },
        { name: "Philippines", code: "+63", iso: "PH", flag: "🇵🇭" },
        { name: "Indonesia", code: "+62", iso: "ID", flag: "🇮🇩" },
        { name: "Thailand", code: "+66", iso: "TH", flag: "🇹🇭" },
        { name: "Vietnam", code: "+84", iso: "VN", flag: "🇻🇳" },
        { name: "Sri Lanka", code: "+94", iso: "LK", flag: "🇱🇰" },
        { name: "Bangladesh", code: "+880", iso: "BD", flag: "🇧🇩" },
        { name: "Nepal", code: "+977", iso: "NP", flag: "🇳🇵" },
        { name: "South Africa", code: "+27", iso: "ZA", flag: "🇿🇦" },
        { name: "Egypt", code: "+20", iso: "EG", flag: "🇪🇬" },
        { name: "Nigeria", code: "+234", iso: "NG", flag: "🇳🇬" },
        { name: "Kenya", code: "+254", iso: "KE", flag: "🇰🇪" },
        { name: "Brazil", code: "+55", iso: "BR", flag: "🇧🇷" },
        { name: "Mexico", code: "+52", iso: "MX", flag: "🇲🇽" },
        { name: "Sweden", code: "+46", iso: "SE", flag: "🇸🇪" },
        { name: "Norway", code: "+47", iso: "NO", flag: "🇳🇴" },
        { name: "Denmark", code: "+45", iso: "DK", flag: "🇩🇰" },
        { name: "Finland", code: "+358", iso: "FI", flag: "🇫🇮" },
        { name: "Belgium", code: "+32", iso: "BE", flag: "🇧🇪" },
        { name: "Austria", code: "+43", iso: "AT", flag: "🇦🇹" },
        { name: "Poland", code: "+48", iso: "PL", flag: "🇵🇱" },
        { name: "Portugal", code: "+351", iso: "PT", flag: "🇵🇹" },
        { name: "Greece", code: "+30", iso: "GR", flag: "🇬🇷" },
        { name: "Turkey", code: "+90", iso: "TR", flag: "🇹🇷" },
        { name: "Maldives", code: "+960", iso: "MV", flag: "🇲🇻" },
        { name: "Bhutan", code: "+975", iso: "BT", flag: "🇧🇹" }
    ];

    let currentSelectedCode = "+91";

    function populateCountryList(filterText = "") {
        const list = document.getElementById('countryList');
        const noMatch = document.getElementById('noCountryMatch');
        if (!list) return;

        list.innerHTML = "";
        const q = filterText.toLowerCase().trim();

        const filtered = countries.filter(c => {
            if (!q) return true;
            return c.name.toLowerCase().includes(q) ||
                   c.code.includes(q) ||
                   c.iso.toLowerCase().includes(q);
        });

        if (filtered.length === 0) {
            noMatch.style.display = "block";
        } else {
            noMatch.style.display = "none";
            filtered.forEach(c => {
                const li = document.createElement('li');
                li.className = 'country-list-item' + (c.code === currentSelectedCode ? ' selected' : '');
                li.onclick = () => selectCountry(c);
                li.innerHTML = `
                    <div class="country-info">
                        <span>${c.flag}</span>
                        <span>${c.name}</span>
                    </div>
                    <span class="country-code">${c.code}</span>
                `;
                list.appendChild(li);
            });
        }
    }

    function toggleCountryDropdown() {
        const dropdown = document.getElementById('countryDropdown');
        const btn = document.getElementById('countryBtn');
        const searchInput = document.getElementById('countrySearchInput');

        if (!dropdown) return;

        const isOpen = dropdown.classList.contains('open');
        if (isOpen) {
            dropdown.classList.remove('open');
            btn.classList.remove('active');
        } else {
            dropdown.classList.add('open');
            btn.classList.add('active');
            populateCountryList();
            setTimeout(() => {
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.focus();
                }
            }, 50);
        }
    }

    function selectCountry(country) {
        currentSelectedCode = country.code;
        document.getElementById('selectedCountryCode').value = country.code;
        document.getElementById('btnFlag').textContent = country.flag;
        document.getElementById('btnDialCode').textContent = country.code;

        const dropdown = document.getElementById('countryDropdown');
        const btn = document.getElementById('countryBtn');
        if (dropdown) dropdown.classList.remove('open');
        if (btn) btn.classList.remove('active');

        // Focus phone input after selecting country
        const phoneInput = document.getElementById('customerMobile');
        if (phoneInput) phoneInput.focus();
    }

    function filterCountries() {
        const q = document.getElementById('countrySearchInput').value;
        populateCountryList(q);
    }

    // Close dropdown on outside click
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('countryDropdown');
        const btn = document.getElementById('countryBtn');
        if (!dropdown || !btn) return;

        if (!btn.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('open');
            btn.classList.remove('active');
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const dropdown = document.getElementById('countryDropdown');
            const btn = document.getElementById('countryBtn');
            if (dropdown) dropdown.classList.remove('open');
            if (btn) btn.classList.remove('active');
        }
    });

    function updateAmount() {
        const tier = document.getElementById('paymentTier').value;
        const displayAmount = document.getElementById('displayAmount');
        const payBtn = document.getElementById('payBtn');

        if (tier.endsWith('_USD')) {
            let amountStr = '2.00';
            if (tier === '2_USD') amountStr = '2.00';
            else if (tier === '3_USD') amountStr = '3.00';
            else if (tier === '4_USD') amountStr = '4.00';
            else if (tier === '25_USD') amountStr = '25.00';
            else if (tier === '50_USD') amountStr = '50.00';
            else if (tier === '100_USD') amountStr = '100.00';

            displayAmount.textContent = '$' + amountStr;
            payBtn.textContent = 'Pay $' + amountStr;

            // If currently India, suggest US as default for convenience
            if (currentSelectedCode === '+91') {
                const us = countries.find(c => c.iso === 'US');
                if (us) selectCountry(us);
            }
        } else {
            displayAmount.textContent = '₹1.00';
            payBtn.textContent = 'Pay ₹1.00';
            if (currentSelectedCode === '+1') {
                const inCountry = countries.find(c => c.iso === 'IN');
                if (inCountry) selectCountry(inCountry);
            }
        }
    }

    async function submitPayment(e) {
        e.preventDefault();

        const btn = document.getElementById('payBtn');
        const errorBox = document.getElementById('errorBox');
        
        errorBox.style.display = 'none';
        errorBox.textContent = '';

        const tier = document.getElementById('paymentTier').value;
        const name = document.getElementById('customerName').value.trim();
        const email = document.getElementById('customerEmail').value.trim();
        const countryCode = document.getElementById('selectedCountryCode').value.trim();
        const mobile = document.getElementById('customerMobile').value.trim();

        if (!name) {
            errorBox.textContent = 'Please enter your full name.';
            errorBox.style.display = 'block';
            return;
        }

        if (!email) {
            errorBox.textContent = 'Please enter your email address.';
            errorBox.style.display = 'block';
            return;
        }

        if (!mobile) {
            errorBox.textContent = 'Please enter your phone number.';
            errorBox.style.display = 'block';
            return;
        }

        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = 'Processing...';

        try {
            const res = await fetch('test_payment.php?action=create_order', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    payment_tier: tier,
                    name: name,
                    email: email,
                    country_code: countryCode,
                    mobile: mobile
                })
            });

            const data = await res.json();

            if (!res.ok || !data.success || !data.payment_url) {
                throw new Error(data.error || 'Failed to start payment session.');
            }

            // Redirect to Vortex checkout
            window.location.href = data.payment_url;

        } catch (err) {
            errorBox.textContent = err.message || 'Payment initiation failed.';
            errorBox.style.display = 'block';
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    // Initialize country list on page load
    document.addEventListener('DOMContentLoaded', () => {
        populateCountryList();
    });
</script>

</body>
</html>
