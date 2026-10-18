<?php
/**
 * ------------------------------------------------------------
 * fetch_payment.php
 * ------------------------------------------------------------
 * Project : PayVortex Unified Gateway
 * Purpose : CLI tool to fetch payment / order / transaction
 *           details directly as JSON in terminal.
 *
 * Usage:
 *   php fetch_payment.php <payment_id | order_id | vortex_id> [--raw]
 *
 * Examples:
 *   php fetch_payment.php pay_Tk6Moyt74QlagR
 *   php fetch_payment.php order_Tk6MAVN1swYEcw
 *   php fetch_payment.php VTX1791175942335D586008C
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/helpers/env_loader.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/TransactionTimeline.php';

$identifier = $argv[1] ?? null;
$isRaw = in_array('--raw', $argv, true);

if (!$identifier || str_starts_with($identifier, '--')) {
    echo json_encode([
        'error' => 'Missing identifier parameter.',
        'usage' => 'php fetch_payment.php <payment_id | order_id | vortex_id> [--raw]'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}

$liveKeyId     = trim($_ENV['RAZORPAY_LIVE_KEY_ID'] ?? '');
$liveKeySecret = trim($_ENV['RAZORPAY_LIVE_KEY_SECRET'] ?? '');
$testKeyId     = trim($_ENV['RAZORPAY_TEST_KEY_ID'] ?? $_ENV['RAZORPAY_KEY_ID'] ?? '');
$testKeySecret = trim($_ENV['RAZORPAY_TEST_KEY_SECRET'] ?? $_ENV['RAZORPAY_KEY_SECRET'] ?? '');

$dbRecord = null;
$razorpayPayment = null;
$razorpayOrder = null;
$activeMode = null;

// 1. Query PayVortex Database
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $stmt = $pdo->prepare("
        SELECT * FROM transactions 
        WHERE razorpay_payment_id = :val 
           OR razorpay_order_id = :val 
           OR vortex_transaction_id = :val 
           OR id = :db_id
        LIMIT 1
    ");
    $stmt->execute([
        ':val'   => $identifier,
        ':db_id' => is_numeric($identifier) ? (int)$identifier : 0
    ]);
    $dbRecord = $stmt->fetch();
} catch (\Throwable $e) {
    $dbRecord = ['error' => $e->getMessage()];
}

// Determine target payment/order IDs for Razorpay lookup
$paymentIdToFetch = null;
$orderIdToFetch   = null;

if (str_starts_with($identifier, 'pay_')) {
    $paymentIdToFetch = $identifier;
} elseif (str_starts_with($identifier, 'order_')) {
    $orderIdToFetch = $identifier;
} elseif (!empty($dbRecord['razorpay_payment_id'])) {
    $paymentIdToFetch = $dbRecord['razorpay_payment_id'];
    $orderIdToFetch   = $dbRecord['razorpay_order_id'] ?? null;
} elseif (!empty($dbRecord['razorpay_order_id'])) {
    $orderIdToFetch   = $dbRecord['razorpay_order_id'];
}

// 2. Fetch from Razorpay API
$keysToTry = [];
if (!empty($liveKeyId) && !empty($liveKeySecret)) {
    $keysToTry['LIVE'] = [$liveKeyId, $liveKeySecret];
}
if (!empty($testKeyId) && !empty($testKeySecret)) {
    $keysToTry['TEST'] = [$testKeyId, $testKeySecret];
}

foreach ($keysToTry as $mode => [$k, $s]) {
    try {
        $api = new \Razorpay\Api\Api($k, $s);

        if ($paymentIdToFetch && !$razorpayPayment) {
            try {
                $p = $api->payment->fetch($paymentIdToFetch);
                if ($p) {
                    $razorpayPayment = $p->toArray();
                    $activeMode = $mode;
                    if (empty($orderIdToFetch) && !empty($razorpayPayment['order_id'])) {
                        $orderIdToFetch = $razorpayPayment['order_id'];
                    }
                }
            } catch (\Throwable $ignored) {}
        }

        if ($orderIdToFetch && !$razorpayOrder) {
            try {
                $o = $api->order->fetch($orderIdToFetch);
                if ($o) {
                    $razorpayOrder = $o->toArray();
                    $activeMode = $activeMode ?: $mode;

                    // If we didn't have payment before, check order payments
                    if (!$razorpayPayment) {
                        $orderPayments = $o->payments();
                        if (!empty($orderPayments['items'])) {
                            $razorpayPayment = $orderPayments['items'][0]->toArray();
                        }
                    }
                }
            } catch (\Throwable $ignored) {}
        }

        if ($razorpayPayment || $razorpayOrder) {
            break;
        }
    } catch (\Throwable $e) {}
}

// Output Raw Razorpay JSON if requested
if ($isRaw) {
    echo json_encode($razorpayPayment ?: $razorpayOrder ?: ['error' => 'Record not found in Razorpay.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

// Standard Combined Output
$vortexId = $dbRecord['vortex_transaction_id'] ?? $identifier;
$timeline = TransactionTimeline::getTimeline($vortexId, $dbRecord['id'] ?? null);

$response = [
    'query'                => $identifier,
    'fetched_at'           => date('Y-m-d H:i:s'),
    'razorpay_mode'        => $activeMode,
    'database_record'      => $dbRecord ?: null,
    'transaction_timeline' => $timeline,
    'razorpay_payment'     => $razorpayPayment ?: null,
    'razorpay_order'       => $razorpayOrder ?: null
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
