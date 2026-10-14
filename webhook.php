<?php
/**
 * webhook.php - Vortex Payment Gateway Webhook Receiver
 *
 * Secure, production-grade webhook endpoint for Vortex Unified Payment Gateway.
 * Handles asynchronous payment status notifications (SUCCESS, FAILED, REFUNDED, DISPUTED)
 * with strict HMAC-SHA256 signature verification, idempotency protection, fast 5-second
 * timeout compliance, database fulfillment on user_registrations, and structured audit logging.
 */

// 1. Set headers & disable display_errors to prevent JSON corruption
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

$startTime = microtime(true);

// 2. Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'error'  => 'Method not allowed. Webhooks require POST.'
    ]);
    exit;
}

// 3. Read raw payload & signature header
$rawBody = file_get_contents('php://input');
if (($rawBody === '' || $rawBody === false) && php_sapi_name() === 'cli') {
    $rawBody = @file_get_contents('php://stdin');
}

// Retrieve X-Vortex-Signature from $_SERVER, $_ENV, getenv, or getallheaders()
$signature = $_SERVER['HTTP_X_VORTEX_SIGNATURE'] ?? $_ENV['HTTP_X_VORTEX_SIGNATURE'] ?? (getenv('HTTP_X_VORTEX_SIGNATURE') ?: '');
if (empty($signature) && function_exists('getallheaders')) {
    $allHeaders = getallheaders();
    foreach ($allHeaders as $hKey => $hVal) {
        if (strcasecmp($hKey, 'X-Vortex-Signature') === 0) {
            $signature = $hVal;
            break;
        }
    }
}

$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

if ($rawBody === '' || $rawBody === false || empty($signature)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'error'  => 'Missing request body or X-Vortex-Signature header'
    ]);
    exit;
}

// 4. Load database & environment
require_once __DIR__ . '/backend/db.php';

$webhookSecret = $env['VORTEX_WEBHOOK_SECRET'] ?? '';
if (empty($webhookSecret)) {
    error_log("[VORTEX_WEBHOOK] Error: VORTEX_WEBHOOK_SECRET is not configured in .env");
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error'  => 'Webhook secret not configured on server'
    ]);
    exit;
}

// 5. Verify HMAC-SHA256 signature with timing-safe comparison
$computedSignature = hash_hmac('sha256', $rawBody, $webhookSecret);

if (!hash_equals($computedSignature, $signature)) {
    logWebhookEvent([
        'ip'                    => $clientIp,
        'vortex_transaction_id' => 'UNKNOWN',
        'status'                => 'SIGNATURE_FAILED',
        'is_duplicate'          => false,
        'matched_reg'           => 'NONE',
        'message'               => 'HMAC signature verification failed',
        'exec_time_ms'          => round((microtime(true) - $startTime) * 1000, 2)
    ]);

    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'error'  => 'Invalid signature verification'
    ]);
    exit;
}

// 6. Decode and validate JSON payload
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'error'  => 'Invalid JSON payload format'
    ]);
    exit;
}

$vortexTxnId = trim($payload['vortex_transaction_id'] ?? '');
$status      = strtoupper(trim($payload['status'] ?? ''));
$timestamp   = $payload['timestamp'] ?? date('Y-m-d H:i:s');
$orderId     = trim($payload['registration_id'] ?? $payload['order_id'] ?? '');

if (empty($vortexTxnId) || empty($status)) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'error'  => 'vortex_transaction_id and status are required'
    ]);
    exit;
}

// 7. Process event idempotently against user_registrations
$isDuplicate   = false;
$recordUpdated = false;
$matchedRegId  = null;
$registration  = null;

try {
    // 7a. Look up the registration record
    $query = "SELECT * FROM user_registrations WHERE transaction_id = :txn_id";
    $params = [':txn_id' => $vortexTxnId];

    if (!empty($orderId)) {
        $query .= " OR registration_id = :reg_id";
        $params[':reg_id'] = $orderId;
    }
    $query .= " LIMIT 1";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($registration) {
        $matchedRegId  = $registration['registration_id'];
        $currentStatus = $registration['payment_status'] ?? '';
        $currentRefund = $registration['refund_status'] ?? '';

        switch ($status) {
            case 'SUCCESS':
                if ($currentStatus === 'Completed') {
                    // Already processed and fulfilled
                    $isDuplicate = true;
                } else {
                    $updateStmt = $pdo->prepare("
                        UPDATE user_registrations 
                        SET payment_status = 'Completed', 
                            transaction_id = :txn_id, 
                            updated_at = NOW() 
                        WHERE id = :id
                    ");
                    $updateStmt->execute([
                        ':txn_id' => $vortexTxnId,
                        ':id'     => $registration['id']
                    ]);
                    $recordUpdated = true;
                }
                break;

            case 'FAILED':
                if ($currentStatus === 'Failed') {
                    $isDuplicate = true;
                } elseif ($currentStatus !== 'Completed') {
                    // Do not overwrite Completed status if already paid
                    $updateStmt = $pdo->prepare("
                        UPDATE user_registrations 
                        SET payment_status = 'Failed', 
                            transaction_id = COALESCE(transaction_id, :txn_id),
                            updated_at = NOW() 
                        WHERE id = :id
                    ");
                    $updateStmt->execute([
                        ':txn_id' => $vortexTxnId,
                        ':id'     => $registration['id']
                    ]);
                    $recordUpdated = true;
                }
                break;

            case 'REFUNDED':
                if ($currentStatus === 'Refunded' || $currentRefund === 'Refunded') {
                    $isDuplicate = true;
                } else {
                    $updateStmt = $pdo->prepare("
                        UPDATE user_registrations 
                        SET payment_status = 'Refunded', 
                            refund_status = 'Refunded', 
                            updated_at = NOW() 
                        WHERE id = :id
                    ");
                    $updateStmt->execute([':id' => $registration['id']]);
                    $recordUpdated = true;
                }
                break;

            case 'DISPUTED':
                if ($currentRefund === 'Disputed - On Hold') {
                    $isDuplicate = true;
                } else {
                    $updateStmt = $pdo->prepare("
                        UPDATE user_registrations 
                        SET refund_status = 'Disputed - On Hold', 
                            updated_at = NOW() 
                        WHERE id = :id
                    ");
                    $updateStmt->execute([':id' => $registration['id']]);
                    $recordUpdated = true;
                }
                break;

            default:
                // Unhandled status - log and ignore
                break;
        }
    } else {
        // Record not matched by transaction_id yet, but payload is verified
        // Note: transaction might be a test event or received before user record checkout linkage
        error_log("[VORTEX_WEBHOOK] Verified webhook received for unmapped transaction: {$vortexTxnId} (Status: {$status})");
    }

} catch (PDOException $e) {
    error_log("[VORTEX_WEBHOOK] Database error during processing: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error'  => 'Internal database processing error'
    ]);
    exit;
}

// 8. Prepare fast acknowledgment response for Vortex (under 1-2s)
$execTimeMs = round((microtime(true) - $startTime) * 1000, 2);

$responsePayload = [
    'status'                => 'ok',
    'received'              => true,
    'vortex_transaction_id' => $vortexTxnId,
    'processed_status'      => $status,
    'is_duplicate'          => $isDuplicate,
    'matched_registration'  => $matchedRegId,
    'timestamp'             => date('Y-m-d H:i:s')
];

// Write file-based audit log
logWebhookEvent([
    'ip'                    => $clientIp,
    'vortex_transaction_id' => $vortexTxnId,
    'status'                => $status,
    'is_duplicate'          => $isDuplicate,
    'matched_reg'           => $matchedRegId ?? 'UNMAPPED',
    'message'               => $recordUpdated ? 'Record updated' : ($isDuplicate ? 'Duplicate ignored' : 'Acknowledged'),
    'exec_time_ms'          => $execTimeMs
]);

// 9. Send response immediately
http_response_code(200);
$jsonOutput = json_encode($responsePayload);
echo $jsonOutput;

// If fastcgi_finish_request is available, flush HTTP 200 immediately so Vortex doesn't wait
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// 10. Background Side-Effects (Confirmation Email)
// Only execute if freshly transitioned to SUCCESS and not a duplicate
if ($status === 'SUCCESS' && $recordUpdated && $registration && !empty($registration['email'])) {
    sendPaymentConfirmationEmail($registration, $vortexTxnId, $env);
}

exit;


// ==============================================================================
// HELPER FUNCTIONS
// ==============================================================================

/**
 * Sends payment confirmation email to participant via PHPMailer
 */
function sendPaymentConfirmationEmail($user, $vortexTxnId, $env) {
    try {
        $mailerPath = __DIR__ . '/backend/PHPMailer/src/';
        if (!file_exists($mailerPath . 'PHPMailer.php')) {
            return;
        }

        require_once $mailerPath . 'Exception.php';
        require_once $mailerPath . 'PHPMailer.php';
        require_once $mailerPath . 'SMTP.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $env['SMTP_USER'] ?? '';
        $mail->Password   = $env['SMTP_PASS'] ?? '';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 3; // Keep timeout short

        $senderEmail = $env['SMTP_USER'] ?? 'icswhmh2027@rajagiri.edu';
        $mail->setFrom($senderEmail, 'ICSWHMH Organizing Team');
        $mail->addAddress($user['email'], trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
        $mail->addReplyTo($senderEmail, 'ICSWHMH Support');

        $mail->isHTML(true);
        $mail->Subject = 'Payment Confirmation - ICSWHMH Registration ' . ($user['registration_id'] ?? '');

        $name = htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
        $regId = htmlspecialchars($user['registration_id'] ?? '');
        $package = htmlspecialchars($user['package'] ?? 'Standard');
        $amount = htmlspecialchars((string)($user['base_amount'] ?? ''));
        $currency = htmlspecialchars($user['currency'] ?? 'INR');
        $safeTxn = htmlspecialchars($vortexTxnId);

        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 25px; border: 1px solid #e0e0e0; border-radius: 8px; background-color: #ffffff;'>
            <div style='text-align: center; border-bottom: 2px solid #C9A227; padding-bottom: 15px; margin-bottom: 20px;'>
                <h2 style='color: #1D0A3F; margin: 0;'>Payment Confirmation</h2>
                <p style='color: #666; margin: 5px 0 0 0;'>International Conference - Rajagiri</p>
            </div>
            
            <p>Dear <strong>{$name}</strong>,</p>
            <p>We are delighted to confirm that your payment for the upcoming International Conference has been received successfully.</p>
            
            <div style='background-color: #FDFBF7; border: 1px solid #EFE8DA; border-radius: 6px; padding: 15px; margin: 20px 0;'>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 6px 0; color: #555;'><strong>Registration ID:</strong></td>
                        <td style='padding: 6px 0; color: #1D0A3F; font-weight: bold;'>{$regId}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; color: #555;'><strong>Transaction ID:</strong></td>
                        <td style='padding: 6px 0; color: #2D3436; font-family: monospace;'>{$safeTxn}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; color: #555;'><strong>Selected Package:</strong></td>
                        <td style='padding: 6px 0; color: #2D3436;'>{$package}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; color: #555;'><strong>Amount Paid:</strong></td>
                        <td style='padding: 6px 0; color: #28a745; font-weight: bold;'>{$currency} {$amount}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; color: #555;'><strong>Status:</strong></td>
                        <td style='padding: 6px 0;'><span style='background: #e6f9ed; color: #1e7e34; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 12px;'>CONFIRMED</span></td>
                    </tr>
                </table>
            </div>

            <p>Please keep this email and your Registration ID safe for check-in and access to conference sessions.</p>
            
            <br>
            <p style='color: #777; font-size: 13px; border-top: 1px solid #eee; padding-top: 15px; margin: 0;'>
                Best Regards,<br>
                <strong>ICSWHMH Organizing Committee</strong><br>
                Rajagiri College of Social Sciences
            </p>
        </div>";

        $mail->send();
        error_log("[VORTEX_WEBHOOK] Confirmation email sent successfully to " . $user['email']);
    } catch (\Throwable $e) {
        error_log("[VORTEX_WEBHOOK] Confirmation email sending failed: " . $e->getMessage());
    }
}

/**
 * Structured audit logging to logs/vortex_webhook.log
 */
function logWebhookEvent($data) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/vortex_webhook.log';
    $line = sprintf(
        "[%s] IP: %s | TXN: %s | Status: %s | RegID: %s | Dupl: %s | Msg: %s | %sms\n",
        date('Y-m-d H:i:s'),
        $data['ip'] ?? 'UNKNOWN',
        $data['vortex_transaction_id'] ?? 'NONE',
        $data['status'] ?? 'NONE',
        $data['matched_reg'] ?? 'NONE',
        !empty($data['is_duplicate']) ? 'YES' : 'NO',
        $data['message'] ?? 'OK',
        $data['exec_time_ms'] ?? '0'
    );

    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}
