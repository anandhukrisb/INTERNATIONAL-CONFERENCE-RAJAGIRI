<?php
/**
 * ------------------------------------------------------------
 * sync_transactions.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 * Purpose : CLI tool to fetch order & payment status directly
 *           from Razorpay API and update the PayVortex database.
 *           Useful when webhooks fail, are delayed, or need
 *           manual reconciliation.
 *
 * Usage:
 * php sync_transactions.php
 * ------------------------------------------------------------
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Access denied: This reconciliation script can only be run via CLI.\n");
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Logger.php';
require_once __DIR__ . '/classes/TransactionTimeline.php';

$liveKeyId     = trim($_ENV['RAZORPAY_LIVE_KEY_ID'] ?? '');
$liveKeySecret = trim($_ENV['RAZORPAY_LIVE_KEY_SECRET'] ?? '');
$testKeyId     = trim($_ENV['RAZORPAY_TEST_KEY_ID'] ?? $_ENV['RAZORPAY_KEY_ID'] ?? '');
$testKeySecret = trim($_ENV['RAZORPAY_TEST_KEY_SECRET'] ?? $_ENV['RAZORPAY_KEY_SECRET'] ?? '');

echo "============================================================\n";
echo "PayVortex - Razorpay Manual Transaction Sync & Reconciliation\n";
echo "============================================================\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "Database : " . DB_NAME . "\n";
echo "------------------------------------------------------------\n\n";

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Fetch all transactions with a Razorpay Order ID
    $stmt = $pdo->query("SELECT * FROM transactions WHERE razorpay_order_id IS NOT NULL AND razorpay_order_id != '' ORDER BY id DESC");
    $transactions = $stmt->fetchAll();

    echo "Found " . count($transactions) . " transactions with Razorpay Order IDs.\n\n";

    $updatedCount = 0;
    $alreadySyncedCount = 0;
    $errorCount = 0;

    $updateStmt = $pdo->prepare("
        UPDATE transactions 
        SET status = :status, 
            razorpay_payment_id = :payment_id,
            updated_at = NOW() 
        WHERE id = :id
    ");

    foreach ($transactions as $txn) {
        $id          = $txn['id'];
        $vortexId    = $txn['vortex_transaction_id'];
        $orderId     = trim($txn['razorpay_order_id']);
        $currentStat = $txn['status'];
        $currentPay  = trim((string)($txn['razorpay_payment_id'] ?? ''));
        $isTestMode  = (int)($txn['is_test_mode'] ?? 0);

        // Razorpay SDK stores credentials statically on Api::$key.
        // We re-instantiate before each call based on the transaction mode.
        $apiKey    = $isTestMode ? $testKeyId : $liveKeyId;
        $apiSecret = $isTestMode ? $testKeySecret : $liveKeySecret;
        $modeLabel = $isTestMode ? "TEST" : "LIVE";

        if (empty($apiKey) || empty($apiSecret)) {
            echo "[#{$id} | {$vortexId}] ⚠️ Skipped: {$modeLabel} Razorpay credentials missing in .env\n";
            $errorCount++;
            continue;
        }

        try {
            $api = new \Razorpay\Api\Api($apiKey, $apiSecret);
            $order = $api->order->fetch($orderId);
            $orderStatus = $order['status']; // 'created', 'attempted', 'paid'
            $payments = $order->payments();
            $paymentItems = $payments['items'] ?? [];

            $resolvedStatus = $currentStat;
            $resolvedPayId  = $currentPay;

            if ($orderStatus === 'paid') {
                $resolvedStatus = 'SUCCESS';

                // Look for payment items
                foreach ($paymentItems as $p) {
                    if (in_array($p['status'], ['captured', 'refunded', 'authorized'])) {
                        $resolvedPayId = $p['id'];
                        if ($p['status'] === 'refunded' || ($p['refund_status'] ?? '') === 'full') {
                            $resolvedStatus = 'REFUNDED';
                        } else {
                            $resolvedStatus = 'SUCCESS';
                        }
                        break;
                    }
                }
                if (empty($resolvedPayId) && !empty($paymentItems)) {
                    $resolvedPayId = $paymentItems[0]['id'];
                    if ($paymentItems[0]['status'] === 'refunded' || ($paymentItems[0]['refund_status'] ?? '') === 'full') {
                        $resolvedStatus = 'REFUNDED';
                    }
                }
            } elseif ($orderStatus === 'attempted') {
                $hasSuccessfulPayment = false;
                $lastFailedPaymentId  = null;

                foreach ($paymentItems as $p) {
                    if (in_array($p['status'], ['captured', 'refunded', 'authorized'])) {
                        $hasSuccessfulPayment = true;
                        $resolvedPayId = $p['id'];
                        if ($p['status'] === 'refunded' || ($p['refund_status'] ?? '') === 'full') {
                            $resolvedStatus = 'REFUNDED';
                        } else {
                            $resolvedStatus = 'SUCCESS';
                        }
                        break;
                    }
                    if ($p['status'] === 'failed') {
                        $lastFailedPaymentId = $p['id'];
                    }
                }

                if (!$hasSuccessfulPayment && $lastFailedPaymentId) {
                    $resolvedStatus = 'FAILED';
                    if (empty($resolvedPayId)) {
                        $resolvedPayId = $lastFailedPaymentId;
                    }
                }
            }

            // Record payment attempts and status into transaction_timeline
            $checkTimelineStmt = $pdo->prepare("SELECT id FROM transaction_timeline WHERE (payment_id = :pid AND event_type = :etype) OR (order_id = :oid AND event_type = :etype) LIMIT 1");

            foreach ($paymentItems as $p) {
                $pId = $p['id'];
                $pStat = $p['status'];
                $eventType = match ($pStat) {
                    'captured'   => 'PAYMENT_CAPTURED',
                    'failed'     => 'PAYMENT_FAILED',
                    'refunded'   => 'REFUND_PROCESSED',
                    'authorized' => 'PAYMENT_AUTHORIZED',
                    default      => strtoupper($pStat)
                };
                $normStatus = match ($pStat) {
                    'captured'   => 'SUCCESS',
                    'failed'     => 'FAILED',
                    'refunded'   => 'REFUNDED',
                    'authorized' => 'PENDING',
                    default      => strtoupper($pStat)
                };

                $checkTimelineStmt->execute([':pid' => $pId, ':oid' => $orderId, ':etype' => $eventType]);
                if (!$checkTimelineStmt->fetch()) {
                    $pReason = match ($pStat) {
                        'captured'   => 'Payment attempt captured successfully via ' . ($p['method'] ?? 'Razorpay') . (!empty($p['acquirer_data']['rrn']) ? ' (RRN: ' . $p['acquirer_data']['rrn'] . ')' : ''),
                        'failed'     => 'Payment attempt failed: ' . ($p['error_description'] ?? 'Bank/User declined payment'),
                        'refunded'   => 'Full refund credited back to customer (' . ($p['method'] ?? 'UPI') . ')',
                        'authorized' => 'Payment authorized by bank',
                        default      => 'Payment ' . $pStat
                    };

                    TransactionTimeline::record($vortexId, $eventType, $normStatus, [
                        'transaction_id' => $id,
                        'order_id'       => $orderId,
                        'payment_id'     => $pId,
                        'amount'         => ($p['amount'] ?? 0) / 100,
                        'currency'       => $p['currency'] ?? 'INR',
                        'reason'         => $pReason,
                        'source'         => 'MANUAL_SYNC',
                        'metadata'       => [
                            'method'            => $p['method'] ?? null,
                            'vpa'               => $p['vpa'] ?? null,
                            'error_code'        => $p['error_code'] ?? null,
                            'error_description' => $p['error_description'] ?? null
                        ]
                    ]);
                }
            }

            if (empty($paymentItems) && $orderStatus === 'created') {
                $checkTimelineStmt->execute([':pid' => 'NONE', ':oid' => $orderId, ':etype' => 'ORDER_CREATED']);
                if (!$checkTimelineStmt->fetch()) {
                    TransactionTimeline::record($vortexId, 'ORDER_CREATED', 'PENDING', [
                        'transaction_id' => $id,
                        'order_id'       => $orderId,
                        'payment_id'     => null,
                        'amount'         => $tx['amount'] ?? null,
                        'currency'       => $tx['currency'] ?? 'INR',
                        'reason'         => 'Razorpay Order generated; waiting for customer payment attempt',
                        'source'         => 'MANUAL_SYNC'
                    ]);
                }
            }

            // Determine if an update is needed
            $statusChanged = ($resolvedStatus !== $currentStat);
            $payIdChanged  = (!empty($resolvedPayId) && $resolvedPayId !== $currentPay);

            if ($statusChanged || $payIdChanged) {
                $updateStmt->execute([
                    ':status'     => $resolvedStatus,
                    ':payment_id' => $resolvedPayId,
                    ':id'         => $id
                ]);

                // Dispatch webhook notification to client app if status transitioned
                if ($statusChanged && !empty($txn['api_client_id'])) {
                    require_once __DIR__ . '/classes/Webhook.php';
                    $syncWebhook = new Webhook();
                    $syncWebhook->dispatchClientWebhook($vortexId, $resolvedStatus, (int)$txn['api_client_id']);
                }

                echo "✅ [#{$id} | {$vortexId} | {$orderId}]\n";
                echo "   Mode     : {$modeLabel}\n";
                echo "   Status   : [{$currentStat}] -> [{$resolvedStatus}]\n";
                echo "   PaymentID: [" . ($currentPay ?: 'EMPTY') . "] -> [{$resolvedPayId}]\n";
                echo "   Razorpay : Order is '{$orderStatus}' with " . count($paymentItems) . " payment record(s)\n\n";

                Logger::info("Manual sync updated txn ID {$id} ({$vortexId}): Status={$resolvedStatus}, PaymentID={$resolvedPayId}");
                $updatedCount++;
            } else {
                echo "ℹ️  [#{$id} | {$vortexId} | {$orderId}]: Already synchronized (Status: {$currentStat}, PayID: " . ($currentPay ?: 'NONE') . ", Razorpay: {$orderStatus})\n";
                $alreadySyncedCount++;
            }

        } catch (Exception $e) {
            echo "❌ [#{$id} | {$vortexId} | {$orderId}]: Error fetching from Razorpay ({$modeLabel}): " . $e->getMessage() . "\n";
            $errorCount++;
        }
    }

    echo "\n============================================================\n";
    echo "Sync Summary:\n";
    echo "  - Total Checked       : " . count($transactions) . "\n";
    echo "  - Updated Records     : {$updatedCount}\n";
    echo "  - Already in Sync     : {$alreadySyncedCount}\n";
    echo "  - Errors / Unresolved : {$errorCount}\n";
    echo "============================================================\n";

} catch (Exception $e) {
    echo "❌ Database connection error: " . $e->getMessage() . "\n";
    exit(1);
}
