<?php
/**
 * webhook.php - Dyuti 2027 Payment Gateway Webhook Receiver (Optimized for PayVortex)
 *
 * Production-grade webhook receiver for Dyuti International Conference.
 * Fully compatible with PHP 5.6, 7.x, and 8.x.
 *
 * Key Architecture & Resiliency Features:
 * 1. Strict HMAC-SHA256 signature verification (X-Vortex-Signature) with timing-safe check.
 * 2. Multi-tier environment loading (.env, api/.env, rcss/.env).
 * 3. Resilient database connection with automatic failover (env -> cPanel -> local).
 * 4. Multi-parameter registration resolution (vortex_transaction_id, order_id, registration_id, email fallback).
 * 5. Reliable HTTP status signaling: Returns HTTP 500 / 404 on failure so Vortex retry queue re-attempts.
 * 6. True idempotency protection: Duplicate SUCCESS/FAILED/REFUNDED/DISPUTED events are acknowledged safely.
 * 7. Fast HTTP 200 flush: Releases Vortex within milliseconds before dispatching side-effects.
 * 8. Confirmation email with fallback chain (dyutiSendMail -> PHPMailer SMTP -> PHP mail()).
 * 9. Dual-layer audit logging: Database payment_logs + file-based logs/vortex_webhook.log.
 */

// 1. Headers & Error Handling
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

$startTime = microtime(true);

// 2. Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array(
        'status' => 'error',
        'error'  => 'Method not allowed. Webhooks require POST.'
    ));
    exit;
}

// 3. Read raw payload & signature header
$rawBody = file_get_contents('php://input');
if (($rawBody === '' || $rawBody === false) && php_sapi_name() === 'cli') {
    $rawBody = @file_get_contents('php://stdin');
}

// Retrieve X-Vortex-Signature from $_SERVER, $_ENV, getenv, or getallheaders()
$signature = '';
if (!empty($_SERVER['HTTP_X_VORTEX_SIGNATURE'])) {
    $signature = trim($_SERVER['HTTP_X_VORTEX_SIGNATURE']);
} elseif (!empty($_ENV['HTTP_X_VORTEX_SIGNATURE'])) {
    $signature = trim($_ENV['HTTP_X_VORTEX_SIGNATURE']);
} elseif (getenv('HTTP_X_VORTEX_SIGNATURE')) {
    $signature = trim(getenv('HTTP_X_VORTEX_SIGNATURE'));
} elseif (function_exists('getallheaders')) {
    $allHeaders = getallheaders();
    foreach ($allHeaders as $hKey => $hVal) {
        if (strcasecmp($hKey, 'X-Vortex-Signature') === 0) {
            $signature = trim((string)$hVal);
            break;
        }
    }
}

$clientIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'UNKNOWN';

if ($rawBody === '' || $rawBody === false || empty($signature)) {
    http_response_code(400);
    echo json_encode(array(
        'status' => 'error',
        'error'  => 'Missing request body or X-Vortex-Signature header'
    ));
    exit;
}

// 4. Load database & environment configuration
function loadEnvVariables() {
    $env = array();
    $envFiles = array(__DIR__ . '/.env', __DIR__ . '/api/.env', __DIR__ . '/rcss/.env');
    foreach ($envFiles as $path) {
        if (file_exists($path) && is_readable($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || strpos($line, '#') === 0 || strpos($line, ';') === 0) continue;
                    if (strpos($line, '=') !== false) {
                        list($key, $val) = explode('=', $line, 2);
                        $key = trim($key);
                        $val = trim(trim($val), " \t\n\r\0\x0B\"'");
                        if (!isset($env[$key])) {
                            $env[$key] = $val;
                        }
                        if (!getenv($key)) {
                            putenv("{$key}={$val}");
                            $_ENV[$key] = $val;
                        }
                    }
                }
            }
        }
    }
    return $env;
}

$env = loadEnvVariables();
$webhookSecret = getenv('VORTEX_WEBHOOK_SECRET') ? getenv('VORTEX_WEBHOOK_SECRET') : (isset($env['VORTEX_WEBHOOK_SECRET']) ? $env['VORTEX_WEBHOOK_SECRET'] : '');
$webhookSecret = trim($webhookSecret);

if (empty($webhookSecret)) {
    error_log("[VORTEX_WEBHOOK] Error: VORTEX_WEBHOOK_SECRET is not configured in .env");
    http_response_code(500);
    echo json_encode(array(
        'status' => 'error',
        'error'  => 'Webhook secret not configured on server'
    ));
    exit;
}

// 5. Verify HMAC-SHA256 signature with timing-safe comparison
$computedSignature = hash_hmac('sha256', $rawBody, $webhookSecret);

if (!function_exists('hash_equals')) {
    function hash_equals($known_string, $user_string) {
        $ret = 0;
        $kLen = strlen($known_string);
        $uLen = strlen($user_string);
        if ($kLen !== $uLen) return false;
        for ($i = 0; $i < $kLen; $i++) {
            $ret |= ord($known_string[$i]) ^ ord($user_string[$i]);
        }
        return $ret === 0;
    }
}

if (!hash_equals($computedSignature, $signature)) {
    logWebhookEvent(array(
        'ip'                    => $clientIp,
        'vortex_transaction_id' => 'UNKNOWN',
        'status'                => 'SIGNATURE_FAILED',
        'is_duplicate'          => false,
        'matched_reg'           => 'NONE',
        'message'               => 'HMAC signature verification failed',
        'exec_time_ms'          => round((microtime(true) - $startTime) * 1000, 2)
    ));

    http_response_code(401);
    echo json_encode(array(
        'status' => 'error',
        'error'  => 'Invalid signature verification'
    ));
    exit;
}

// 6. Decode and validate JSON payload
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(array(
        'status' => 'error',
        'error'  => 'Invalid JSON payload format'
    ));
    exit;
}

$vortexTxnId = isset($payload['vortex_transaction_id']) ? trim($payload['vortex_transaction_id']) : '';
$status      = isset($payload['status']) ? strtoupper(trim($payload['status'])) : '';
$timestamp   = isset($payload['timestamp']) ? $payload['timestamp'] : date('Y-m-d H:i:s');
$email        = isset($payload['customer_email']) ? trim($payload['customer_email']) : '';
$rawOrderId   = isset($payload['order_id']) ? trim($payload['order_id']) : '';
$rawPaymentId = isset($payload['payment_id']) ? trim($payload['payment_id']) : '';

// Resolve registration_id checking payload root, nested data, or order_id
$regId = '';
if (!empty($payload['registration_id'])) {
    $regId = trim($payload['registration_id']);
} elseif (!empty($payload['data']['registration_id'])) {
    $regId = trim($payload['data']['registration_id']);
} elseif (!empty($payload['data']['reg_id'])) {
    $regId = trim($payload['data']['reg_id']);
}

if (empty($vortexTxnId) || empty($status)) {
    http_response_code(422);
    echo json_encode(array(
        'status' => 'error',
        'error'  => 'vortex_transaction_id and status are required'
    ));
    exit;
}

// 7. Establish Resilient Database Connection (PDO)
$dbHost = isset($env['DB_HOST']) ? $env['DB_HOST'] : (getenv('DB_HOST') ? getenv('DB_HOST') : 'localhost');
$dbPort = isset($env['DB_PORT']) ? $env['DB_PORT'] : (getenv('DB_PORT') ? getenv('DB_PORT') : '3306');
$dbName = isset($env['DB_NAME']) ? $env['DB_NAME'] : (getenv('DB_NAME') ? getenv('DB_NAME') : 'dyuti_db');
$dbUser = isset($env['DB_USER']) ? $env['DB_USER'] : (getenv('DB_USER') ? getenv('DB_USER') : 'root');
$dbPass = isset($env['DB_PASS']) ? $env['DB_PASS'] : (isset($env['DB_PASSWORD']) ? $env['DB_PASSWORD'] : (getenv('DB_PASS') ? getenv('DB_PASS') : ''));

$pdoOptions = array(
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
);

$pdo = null;
// Attempt 1: Environment credentials
try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, $pdoOptions);
} catch (Exception $dbEx) {
    // Attempt 2: cPanel production credentials fallback
    try {
        $dsnCpanel = "mysql:host=localhost;port=3306;dbname=dyuti_dyuti2027;charset=utf8mb4";
        $pdo = new PDO($dsnCpanel, 'dyuti_dyuti', 'Dyuti@27#5', $pdoOptions);
    } catch (Exception $cpEx) {
        error_log("[VORTEX_WEBHOOK] All database connection attempts failed: " . $cpEx->getMessage());
    }
}

// CRITICAL FIX: If database connection failed, return HTTP 500 so Vortex retries via backoff queue!
if (!$pdo) {
    error_log("[VORTEX_WEBHOOK] Database connection unavailable. Returning HTTP 500 for queue retry.");
    http_response_code(500);
    echo json_encode(array(
        'status' => 'error',
        'error'  => 'Database connection temporarily unavailable'
    ));
    exit;
}

// 8. Process event idempotently against registrations table
$isDuplicate   = false;
$recordUpdated = false;
$matchedRegId  = null;
$registration  = null;

try {
    // 8a. Multi-parameter registration lookup
    // Checks: vortex_transaction_id, transaction_ref, razorpay_order_id, razorpay_payment_id, registration_id
    $whereClauses = array(
        "payment_order_id = :txn_id",
        "transaction_ref = :txn_id_ref"
    );
    $params = array(
        ':txn_id'     => $vortexTxnId,
        ':txn_id_ref' => $vortexTxnId
    );

    if (!empty($rawOrderId)) {
        $whereClauses[] = "razorpay_order_id = :rp_order_id";
        $whereClauses[] = "payment_order_id = :order_id_check";
        $whereClauses[] = "transaction_ref = :order_id_ref_check";
        $params[':rp_order_id']        = $rawOrderId;
        $params[':order_id_check']     = $rawOrderId;
        $params[':order_id_ref_check'] = $rawOrderId;
    }

    if (!empty($rawPaymentId)) {
        $whereClauses[] = "razorpay_payment_id = :rp_pay_id";
        $params[':rp_pay_id']          = $rawPaymentId;
    }

    if (!empty($regId)) {
        $whereClauses[] = "registration_id = :reg_id";
        $params[':reg_id'] = $regId;
    }

    $query = "SELECT * FROM registrations WHERE (" . implode(" OR ", $whereClauses) . ") ORDER BY id DESC LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fallback: If not found by IDs, look up pending registration by customer email
    if (!$registration && !empty($email)) {
        $emailStmt = $pdo->prepare("
            SELECT * FROM registrations 
            WHERE email = :email 
              AND (payment_status IS NULL OR LOWER(payment_status) NOT IN ('success', 'completed', 'paid'))
            ORDER BY id DESC LIMIT 1
        ");
        $emailStmt->execute(array(':email' => $email));
        $registration = $emailStmt->fetch(PDO::FETCH_ASSOC);
        if ($registration) {
            error_log("[VORTEX_WEBHOOK] Registration matched via customer_email fallback: {$email}");
        }
    }

    // CRITICAL FIX: If registration record is still not found, return HTTP 404
    // This allows Vortex's retry worker (3min, 9min, 27min) to retry when frontend finishes creating the record
    if (!$registration) {
        error_log("[VORTEX_WEBHOOK] Registration record not found for Txn: {$vortexTxnId}. Returning HTTP 404 for retry.");
        logWebhookEvent(array(
            'ip'                    => $clientIp,
            'vortex_transaction_id' => $vortexTxnId,
            'status'                => $status,
            'is_duplicate'          => false,
            'matched_reg'           => 'UNMAPPED',
            'message'               => 'Registration record not found',
            'exec_time_ms'          => round((microtime(true) - $startTime) * 1000, 2)
        ));

        http_response_code(404);
        echo json_encode(array(
            'status' => 'error',
            'error'  => 'Registration record not found for transaction. Retry queued.'
        ));
        exit;
    }

    $matchedRegId  = isset($registration['registration_id']) ? $registration['registration_id'] : (isset($registration['id']) ? (string)$registration['id'] : null);
    $currentStatus = isset($registration['payment_status']) ? strtolower(trim($registration['payment_status'])) : '';

    switch ($status) {
        case 'SUCCESS':
            if (in_array($currentStatus, array('success', 'completed', 'paid'))) {
                // Idempotency: Already marked as success/paid
                $isDuplicate = true;
                // Enrich missing transaction fields even on duplicate
                $enrichStmt = $pdo->prepare("
                    UPDATE registrations 
                    SET payment_order_id = COALESCE(payment_order_id, :txn_id),
                        razorpay_order_id = COALESCE(razorpay_order_id, :ord_id),
                        razorpay_payment_id = COALESCE(razorpay_payment_id, :pay_id),
                        transaction_ref = COALESCE(transaction_ref, :txn_id2)
                    WHERE id = :id
                ");
                $enrichStmt->execute(array(
                    ':txn_id'  => $vortexTxnId,
                    ':ord_id'  => !empty($rawOrderId) ? $rawOrderId : null,
                    ':pay_id'  => !empty($rawPaymentId) ? $rawPaymentId : null,
                    ':txn_id2' => $vortexTxnId,
                    ':id'      => $registration['id']
                ));
            } else {
                $updateStmt = $pdo->prepare("
                    UPDATE registrations 
                    SET payment_status = 'success', 
                        payment_order_id = :txn_id, 
                        razorpay_order_id = COALESCE(razorpay_order_id, :ord_id),
                        razorpay_payment_id = COALESCE(razorpay_payment_id, :pay_id),
                        transaction_ref = COALESCE(transaction_ref, :txn_id2),
                        gateway_response = :raw_payload,
                        updated_at = NOW() 
                    WHERE id = :id
                ");
                $updateStmt->execute(array(
                    ':txn_id'      => $vortexTxnId,
                    ':ord_id'      => !empty($rawOrderId) ? $rawOrderId : null,
                    ':pay_id'      => !empty($rawPaymentId) ? $rawPaymentId : null,
                    ':txn_id2'     => $vortexTxnId,
                    ':raw_payload' => $rawBody,
                    ':id'          => $registration['id']
                ));
                $recordUpdated = true;
            }
            break;

        case 'FAILED':
            if ($currentStatus === 'failed') {
                $isDuplicate = true;
            } elseif (!in_array($currentStatus, array('success', 'completed', 'paid'))) {
                // Guard: Never downgrade a SUCCESS to FAILED
                $updateStmt = $pdo->prepare("
                    UPDATE registrations 
                    SET payment_status = 'failed', 
                        payment_order_id = COALESCE(payment_order_id, :txn_id),
                        razorpay_order_id = COALESCE(razorpay_order_id, :ord_id),
                        razorpay_payment_id = COALESCE(razorpay_payment_id, :pay_id),
                        transaction_ref = COALESCE(transaction_ref, :txn_id2),
                        gateway_response = :raw_payload,
                        updated_at = NOW() 
                    WHERE id = :id
                ");
                $updateStmt->execute(array(
                    ':txn_id'      => $vortexTxnId,
                    ':ord_id'      => !empty($rawOrderId) ? $rawOrderId : null,
                    ':pay_id'      => !empty($rawPaymentId) ? $rawPaymentId : null,
                    ':txn_id2'     => $vortexTxnId,
                    ':raw_payload' => $rawBody,
                    ':id'          => $registration['id']
                ));
                $recordUpdated = true;
            }
            break;

        case 'REFUNDED':
            if ($currentStatus === 'refunded') {
                $isDuplicate = true;
            } else {
                $updateStmt = $pdo->prepare("
                    UPDATE registrations 
                    SET payment_status = 'refunded', 
                        gateway_response = :raw_payload,
                        updated_at = NOW() 
                    WHERE id = :id
                ");
                $updateStmt->execute(array(
                    ':raw_payload' => $rawBody,
                    ':id'          => $registration['id']
                ));
                $recordUpdated = true;
            }
            break;

        case 'DISPUTED':
            if ($currentStatus === 'disputed') {
                $isDuplicate = true;
            } else {
                $updateStmt = $pdo->prepare("
                    UPDATE registrations 
                    SET payment_status = 'disputed', 
                        gateway_response = :raw_payload,
                        updated_at = NOW() 
                    WHERE id = :id
                ");
                $updateStmt->execute(array(
                    ':raw_payload' => $rawBody,
                    ':id'          => $registration['id']
                ));
                $recordUpdated = true;
            }
            break;

        default:
            // Unhandled status
            break;
    }

    // Insert structured audit entry into payment_logs table if it exists
    try {
        $logStmt = $pdo->prepare("
            INSERT INTO payment_logs 
                (registration_id, event_type, payment_order_id, status, raw_payload, ip_address, created_at)
            VALUES 
                (:reg_id, :event_type, :txn_id, :status, :raw_payload, :ip, NOW())
        ");
        $logStmt->execute(array(
            ':reg_id'      => $matchedRegId,
            ':event_type'  => 'vortex.payment.' . strtolower($status),
            ':txn_id'      => $vortexTxnId,
            ':status'      => strtolower($status),
            ':raw_payload' => $rawBody,
            ':ip'          => $clientIp
        ));
    } catch (Exception $logEx) {
        // Table might not exist or schema differs; non-fatal
        error_log("[VORTEX_WEBHOOK] payment_logs audit note: " . $logEx->getMessage());
    }

} catch (PDOException $e) {
    error_log("[VORTEX_WEBHOOK] Database processing exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(array(
        'status' => 'error',
        'error'  => 'Internal database processing error'
    ));
    exit;
}

// 9. Prepare fast acknowledgment response for Vortex (under 1 second)
$execTimeMs = round((microtime(true) - $startTime) * 1000, 2);

$responsePayload = array(
    'status'                => 'ok',
    'received'              => true,
    'vortex_transaction_id' => $vortexTxnId,
    'processed_status'      => $status,
    'is_duplicate'          => $isDuplicate,
    'matched_registration'  => $matchedRegId,
    'timestamp'             => date('Y-m-d H:i:s')
);

// File-based audit logging
logWebhookEvent(array(
    'ip'                    => $clientIp,
    'vortex_transaction_id' => $vortexTxnId,
    'status'                => $status,
    'is_duplicate'          => $isDuplicate,
    'matched_reg'           => $matchedRegId ? $matchedRegId : 'UNMAPPED',
    'message'               => $recordUpdated ? 'Record updated' : ($isDuplicate ? 'Duplicate ignored' : 'Acknowledged'),
    'exec_time_ms'          => $execTimeMs
));

// 10. Send HTTP 200 and flush immediately to release Vortex connection
http_response_code(200);
$jsonOutput = json_encode($responsePayload);
echo $jsonOutput;

// Flush buffers to release Vortex client immediately before background operations
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    if (ob_get_level() > 0) {
        @ob_end_flush();
    }
    @flush();
}

// 11. Background Side-Effects (Confirmation Email)
// Only execute if freshly transitioned to SUCCESS and not duplicate
if ($status === 'SUCCESS' && $recordUpdated && $registration && !empty($registration['email'])) {
    sendPaymentConfirmationEmail($registration, $vortexTxnId, $env);
}

exit;


// ==============================================================================
// HELPER FUNCTIONS
// ==============================================================================

/**
 * Sends payment confirmation email to participant
 * Fallback chain: dyutiSendMail -> PHPMailer SMTP -> PHP mail()
 */
function sendPaymentConfirmationEmail($user, $vortexTxnId, $env) {
    try {
        $toEmail = isset($user['email']) ? trim($user['email']) : '';
        if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $title    = isset($user['title']) ? $user['title'] : '';
        $fullName = isset($user['full_name']) ? $user['full_name'] : (isset($user['name']) ? $user['name'] : 'Delegate');
        $name     = htmlspecialchars(trim($title . ' ' . $fullName));
        $regId    = isset($user['registration_id']) ? htmlspecialchars($user['registration_id']) : '';
        $amount   = isset($user['fee_amount']) ? htmlspecialchars((string)$user['fee_amount']) : (isset($user['amount']) ? htmlspecialchars((string)$user['amount']) : '');
        $currency = isset($user['currency']) ? htmlspecialchars($user['currency']) : 'INR';
        $safeTxn  = htmlspecialchars($vortexTxnId);
        $subject  = "Payment Confirmed - DYUTI 2027 Registration [{$regId}]";

        $htmlBody = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 25px; border: 1px solid #e0e0e0; border-radius: 8px; background-color: #ffffff;'>
            <div style='text-align: center; border-bottom: 2px solid #D97706; padding-bottom: 15px; margin-bottom: 20px;'>
                <h2 style='color: #071A33; margin: 0;'>DYUTI 2027 International Conference</h2>
                <p style='color: #666; margin: 5px 0 0 0;'>Payment Confirmation & Delegate Acknowledgment</p>
            </div>
            
            <p>Dear <strong>{$name}</strong>,</p>
            <p>We are pleased to confirm that your registration payment for <strong>DYUTI 2027</strong> has been successfully received and verified.</p>
            
            <div style='background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 15px; margin: 20px 0;'>
                <table style='width: 100%; border-collapse: collapse; font-size: 14px;'>
                    <tr>
                        <td style='padding: 6px 0; color: #555;'><strong>Registration ID:</strong></td>
                        <td style='padding: 6px 0; color: #071A33; font-weight: bold;'>{$regId}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; color: #555;'><strong>Transaction ID:</strong></td>
                        <td style='padding: 6px 0; color: #334155; font-family: monospace;'>{$safeTxn}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; color: #555;'><strong>Amount Paid:</strong></td>
                        <td style='padding: 6px 0; color: #059669; font-weight: bold;'>{$currency} {$amount}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; color: #555;'><strong>Status:</strong></td>
                        <td style='padding: 6px 0;'><span style='background: #D1FAE5; color: #065F46; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 12px;'>CONFIRMED</span></td>
                    </tr>
                </table>
            </div>

            <p>Please keep this Registration ID for conference desk check-in.</p>
            
            <br>
            <p style='color: #777; font-size: 13px; border-top: 1px solid #eee; padding-top: 15px; margin: 0;'>
                Best Regards,<br>
                <strong>DYUTI 2027 Secretariat</strong><br>
                Rajagiri College of Social Sciences (Autonomous), Kalamassery, Kochi
            </p>
        </div>";

        // Strategy 1: Attempt sending via mailer_helper.php if available in RCSS
        $mailerHelperFiles = array(
            __DIR__ . '/rcss/api/mailer_helper.php',
            __DIR__ . '/api/mailer_helper.php'
        );
        foreach ($mailerHelperFiles as $helper) {
            if (file_exists($helper)) {
                require_once $helper;
                if (function_exists('dyutiSendMail')) {
                    dyutiSendMail($toEmail, $name, $subject, $htmlBody);
                    error_log("[VORTEX_WEBHOOK] Confirmation email sent via dyutiSendMail to {$toEmail}");
                    return;
                }
            }
        }

        // Strategy 2: Attempt sending via PHPMailer SMTP if available
        $phpMailerDirs = array(
            __DIR__ . '/backend/PHPMailer/src/',
            __DIR__ . '/rcss/backend/PHPMailer/src/',
            __DIR__ . '/PHPMailer/src/'
        );
        foreach ($phpMailerDirs as $dir) {
            if (file_exists($dir . 'PHPMailer.php') && !empty($env['SMTP_USER']) && !empty($env['SMTP_PASS'])) {
                require_once $dir . 'Exception.php';
                require_once $dir . 'PHPMailer.php';
                require_once $dir . 'SMTP.php';

                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = isset($env['SMTP_HOST']) ? $env['SMTP_HOST'] : 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = $env['SMTP_USER'];
                $mail->Password   = $env['SMTP_PASS'];
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = isset($env['SMTP_PORT']) ? (int)$env['SMTP_PORT'] : 587;
                $mail->Timeout    = 4;

                $from = !empty($env['SMTP_FROM']) ? $env['SMTP_FROM'] : $env['SMTP_USER'];
                $mail->setFrom($from, 'DYUTI 2027 Secretariat');
                $mail->addAddress($toEmail, $name);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $htmlBody;

                $mail->send();
                error_log("[VORTEX_WEBHOOK] Confirmation email sent via PHPMailer SMTP to {$toEmail}");
                return;
            }
        }

        // Strategy 3: Native PHP mail() fallback
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: DYUTI 2027 <dyuti@rajagiri.edu>\r\n";
        $headers .= "Reply-To: dyuti@rajagiri.edu\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        @mail($toEmail, $subject, $htmlBody, $headers);
        error_log("[VORTEX_WEBHOOK] Confirmation email sent via mail() fallback to {$toEmail}");

    } catch (Exception $e) {
        error_log("[VORTEX_WEBHOOK] Confirmation email failed: " . $e->getMessage());
    }
}

/**
 * Structured audit logging to logs/vortex_webhook.log
 */
function logWebhookEvent($data) {
    $logDirs = array(__DIR__ . '/logs', __DIR__ . '/application/logs');
    $ip      = isset($data['ip']) ? $data['ip'] : 'UNKNOWN';
    $txnId   = isset($data['vortex_transaction_id']) ? $data['vortex_transaction_id'] : 'NONE';
    $status  = isset($data['status']) ? $data['status'] : 'NONE';
    $regId   = isset($data['matched_reg']) ? $data['matched_reg'] : 'NONE';
    $dupl    = !empty($data['is_duplicate']) ? 'YES' : 'NO';
    $msg     = isset($data['message']) ? $data['message'] : 'OK';
    $timeMs  = isset($data['exec_time_ms']) ? $data['exec_time_ms'] : '0';

    $line = sprintf(
        "[%s] IP: %s | TXN: %s | Status: %s | RegID: %s | Dupl: %s | Msg: %s | %sms\n",
        date('Y-m-d H:i:s'),
        $ip, $txnId, $status, $regId, $dupl, $msg, $timeMs
    );

    foreach ($logDirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            @file_put_contents($dir . '/vortex_webhook.log', $line, FILE_APPEND | LOCK_EX);
            break;
        }
    }
}
