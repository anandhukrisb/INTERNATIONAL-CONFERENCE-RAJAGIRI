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

        $selection = $payload['payment_tier'] ?? '1_INR';
        $email     = trim($payload['email'] ?? 'test.payer@rajagiri.edu');
        $mobile    = trim($payload['mobile'] ?? '9876543210');
        $name      = trim($payload['name'] ?? 'Test Participant');

        if ($selection === '1_USD') {
            $amount = 1.00;
            $currency = 'USD';
        } else {
            $amount = 1.00;
            $currency = 'INR';
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }

        if (empty($mobile) || !preg_match('/^[0-9]{10}$/', preg_replace('/[^0-9]/', '', $mobile))) {
            throw new Exception("Please enter a valid 10-digit mobile number.");
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
                $mobile,
                $amount,
                $currency,
                $client['is_test_mode'] ?? 1
            );

            if (is_array($payResult) && ($payResult['success'] ?? false) === true) {
                $txDbId = $payResult['data']['database_transaction_id'];
                $vortexTxnId = $payResult['data']['vortex_transaction_id'];

                $sessionObj = new CheckoutSession();
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
            max-width: 440px;
        }

        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
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

        select, input {
            width: 100%;
            padding: 10px 14px;
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

        .row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
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
            padding: 12px;
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
            padding: 10px 14px;
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
                <p>Select currency and test payment via PayVortex</p>
            </div>

            <div class="alert-error" id="errorBox"></div>

            <form id="paymentForm" onsubmit="submitPayment(event)">
                
                <!-- Dropdown: 1 Rupee or 1 Dollar -->
                <div class="form-group">
                    <label for="paymentTier">Select Amount</label>
                    <select id="paymentTier" name="payment_tier" onchange="updateAmount()">
                        <option value="1_INR" selected>1 Rupee (₹1.00 INR)</option>
                        <option value="1_USD">1 Dollar ($1.00 USD)</option>
                    </select>
                </div>

                <div class="summary-box">
                    <span class="summary-label">Total to pay</span>
                    <span class="summary-amount" id="displayAmount">₹1.00</span>
                </div>

                <!-- Customer Details -->
                <div class="form-group">
                    <label for="customerName">Name</label>
                    <input type="text" id="customerName" name="name" value="Test Participant" required>
                </div>

                <div class="row-2">
                    <div class="form-group">
                        <label for="customerEmail">Email</label>
                        <input type="email" id="customerEmail" name="email" value="test.payer@rajagiri.edu" required>
                    </div>
                    <div class="form-group">
                        <label for="customerMobile">Mobile</label>
                        <input type="tel" id="customerMobile" name="mobile" value="9876543210" required>
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
    function updateAmount() {
        const tier = document.getElementById('paymentTier').value;
        const displayAmount = document.getElementById('displayAmount');
        const payBtn = document.getElementById('payBtn');

        if (tier === '1_USD') {
            displayAmount.textContent = '$1.00';
            payBtn.textContent = 'Pay $1.00';
        } else {
            displayAmount.textContent = '₹1.00';
            payBtn.textContent = 'Pay ₹1.00';
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
        const mobile = document.getElementById('customerMobile').value.trim();

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
</script>

</body>
</html>
