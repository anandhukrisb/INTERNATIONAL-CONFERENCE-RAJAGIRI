<?php
/**
 * ------------------------------------------------------------
 * NotificationService.php – Multi-Channel Client Alerts Engine
 * ------------------------------------------------------------
 * Project : PayVortex Unified Payment Gateway
 * ------------------------------------------------------------
 * Delivers real-time Email (Google SMTP / native) and SMS alerts
 * to API Clients (merchants) on:
 * 1. Payment Success
 * 2. Refund Processed
 * 3. Payment Failure / Dispute
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/AuditLogger.php';

class NotificationService
{
    // Canonical Event Types
    public const EVENT_PAYMENT_SUCCESS  = 'payment.success';
    public const EVENT_REFUND_PROCESSED  = 'refund.processed';
    public const EVENT_PAYMENT_FAILED    = 'payment.failed';
    public const EVENT_PAYMENT_DISPUTED  = 'payment.disputed';

    /**
     * Dispatch Payment Success Notification to Merchant
     */
    public static function notifyPaymentSuccess(array $tx): array
    {
        $clientId = (int)($tx['api_client_id'] ?? 0);
        $client = self::getClient($clientId);

        if (!$client) {
            return ['success' => false, 'message' => 'Client not found'];
        }

        $results = ['email' => null, 'sms' => null];
        $txId = $tx['vortex_transaction_id'] ?? ('TX#' . ($tx['id'] ?? ''));
        $amount = number_format((float)($tx['amount'] ?? 0), 2);
        $currency = $tx['currency'] ?? 'INR';
        $customer = $tx['customer_name'] ?? $tx['customer_email'] ?? 'Customer';
        $eventName = $tx['event_name'] ?? ('Event #' . ($tx['event_id'] ?? ''));

        // 1. Email Alert
        if (!empty($client['email']) && (int)($client['notify_email_payment'] ?? 1) === 1) {
            $subject = "Payment Captured: {$currency} {$amount} [{$txId}]";
            $html = self::renderEmailTemplate('Payment Captured Successfully', '#16a34a', [
                'Status'             => 'SUCCESS / CAPTURED',
                'Amount'             => "{$currency} {$amount}",
                'Vortex Tx ID'       => $txId,
                'Razorpay Order ID'  => $tx['razorpay_order_id'] ?? '–',
                'Razorpay Payment ID'=> $tx['razorpay_payment_id'] ?? '–',
                'Customer'           => $customer,
                'Customer Email'     => $tx['customer_email'] ?? '–',
                'Customer Phone'     => $tx['customer_mobile'] ?? '–',
                'Event / Purpose'    => $eventName,
                'Date & Time'        => date('Y-m-d H:i:s T', strtotime($tx['created_at'] ?? 'now')),
            ]);

            $results['email'] = self::sendEmail($client['email'], $subject, $html, self::EVENT_PAYMENT_SUCCESS, $clientId);
        }

        // 2. SMS Alert
        if (!empty($client['phone']) && (int)($client['notify_sms_payment'] ?? 0) === 1) {
            $smsText = "PayVortex Alert: Payment of {$currency} {$amount} captured for {$eventName}. Tx: {$txId}.";
            $results['sms'] = self::sendSms($client['phone'], $smsText, self::EVENT_PAYMENT_SUCCESS, $clientId);
        }

        return ['success' => true, 'dispatched' => $results];
    }

    /**
     * Dispatch Refund Processed Notification to Merchant
     */
    public static function notifyRefundProcessed(array $tx, array $refundData): array
    {
        $clientId = (int)($tx['api_client_id'] ?? 0);
        $client = self::getClient($clientId);

        if (!$client) {
            return ['success' => false, 'message' => 'Client not found'];
        }

        $results = ['email' => null, 'sms' => null];
        $txId = $tx['vortex_transaction_id'] ?? ('TX#' . ($tx['id'] ?? ''));
        $refundAmount = number_format((float)($refundData['amount'] ?? $tx['amount'] ?? 0), 2);
        $currency = $tx['currency'] ?? 'INR';
        $refundId = $refundData['refund_id'] ?? ('REF#' . ($refundData['id'] ?? ''));
        $reason = $refundData['reason'] ?? 'Merchant initiated refund';

        // 1. Email Alert
        if (!empty($client['email']) && (int)($client['notify_email_refund'] ?? 1) === 1) {
            $subject = "Refund Processed: {$currency} {$refundAmount} [{$txId}]";
            $html = self::renderEmailTemplate('Refund Processed', '#7c3aed', [
                'Status'             => 'REFUNDED',
                'Refund Amount'      => "{$currency} {$refundAmount}",
                'Refund Reference'   => $refundId,
                'Original Tx ID'     => $txId,
                'Reason'             => $reason,
                'Customer'           => $tx['customer_name'] ?? '–',
                'Customer Email'     => $tx['customer_email'] ?? '–',
                'Date & Time'        => date('Y-m-d H:i:s T'),
            ]);

            $results['email'] = self::sendEmail($client['email'], $subject, $html, self::EVENT_REFUND_PROCESSED, $clientId);
        }

        // 2. SMS Alert
        if (!empty($client['phone']) && (int)($client['notify_sms_refund'] ?? 1) === 1) {
            $smsText = "PayVortex Alert: Refund of {$currency} {$refundAmount} processed for Tx: {$txId}. Ref: {$refundId}.";
            $results['sms'] = self::sendSms($client['phone'], $smsText, self::EVENT_REFUND_PROCESSED, $clientId);
        }

        return ['success' => true, 'dispatched' => $results];
    }

    /**
     * Dispatch Payment Failure / Dispute Alert to Merchant
     */
    public static function notifyFailureOrDispute(array $tx, string $reason, string $eventType = self::EVENT_PAYMENT_FAILED): array
    {
        $clientId = (int)($tx['api_client_id'] ?? 0);
        $client = self::getClient($clientId);

        if (!$client) {
            return ['success' => false, 'message' => 'Client not found'];
        }

        $results = ['email' => null, 'sms' => null];
        $txId = $tx['vortex_transaction_id'] ?? ('TX#' . ($tx['id'] ?? ''));
        $amount = number_format((float)($tx['amount'] ?? 0), 2);
        $currency = $tx['currency'] ?? 'INR';

        $isDispute = ($eventType === self::EVENT_PAYMENT_DISPUTED);
        $title = $isDispute ? 'Payment Disputed / Chargeback Alert' : 'Payment Failed Notice';
        $accentColor = '#dc2626';

        // 1. Email Alert
        if (!empty($client['email']) && (int)($client['notify_email_dispute'] ?? 1) === 1) {
            $subject = ($isDispute ? "[ACTION REQUIRED] Dispute on " : "Payment Failed: ") . "{$currency} {$amount} [{$txId}]";
            $html = self::renderEmailTemplate($title, $accentColor, [
                'Alert Type'         => $isDispute ? 'CHARGEBACK / DISPUTE' : 'PAYMENT FAILED',
                'Amount'             => "{$currency} {$amount}",
                'Vortex Tx ID'       => $txId,
                'Failure Reason'     => $reason,
                'Customer'           => $tx['customer_name'] ?? '–',
                'Customer Email'     => $tx['customer_email'] ?? '–',
                'Customer Mobile'    => $tx['customer_mobile'] ?? '–',
                'Date & Time'        => date('Y-m-d H:i:s T'),
            ]);

            $results['email'] = self::sendEmail($client['email'], $subject, $html, $eventType, $clientId);
        }

        // 2. SMS Alert
        if (!empty($client['phone']) && (int)($client['notify_sms_dispute'] ?? 1) === 1) {
            $smsText = "PayVortex Urgent: " . ($isDispute ? "Dispute opened on" : "Failed payment") . " of {$currency} {$amount} (Tx: {$txId}). Reason: {$reason}.";
            $results['sms'] = self::sendSms($client['phone'], $smsText, $eventType, $clientId);
        }

        return ['success' => true, 'dispatched' => $results];
    }

    /**
     * Send Welcome Onboarding Pack with Credentials & Portal Link
     */
    public static function sendWelcomeEmail(array $clientData): array
    {
        $email = trim($clientData['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Valid email required for welcome notification.'];
        }

        $clientId = (int)($clientData['id'] ?? $clientData['client_id'] ?? 0);
        $clientName = $clientData['client_name'] ?? 'Merchant';
        $portalUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8080/vortex', '/') . '/portal/login.php';

        $subject = "Welcome to PayVortex – Merchant Credentials & Getting Started";
        $html = self::renderEmailTemplate("Welcome to PayVortex, {$clientName}!", '#2563eb', [
            'Merchant ID'          => "#{$clientId}",
            'Merchant Name'        => $clientName,
            'Live API Key'         => $clientData['api_key'] ?? 'N/A',
            'Live API Secret'      => $clientData['api_secret'] ?? 'N/A',
            'Test API Key'         => $clientData['test_api_key'] ?? 'N/A',
            'Test API Secret'      => $clientData['test_api_secret'] ?? 'N/A',
            'Webhook Endpoint'     => !empty($clientData['webhook_url']) ? $clientData['webhook_url'] : 'Not configured',
            'Webhook Secret'       => !empty($clientData['webhook_secret']) ? $clientData['webhook_secret'] : 'N/A',
            'Merchant Portal URL'  => "<a href=\"{$portalUrl}\" style=\"color: #2563eb; font-weight: 600;\">{$portalUrl}</a>",
            'Next Steps'           => "Log in to the Merchant Portal using your API Key & Secret or set an account password in your settings."
        ]);

        return self::sendEmail($email, $subject, $html, 'merchant.welcome', $clientId);
    }

    /**
     * Send Email via Google SMTP (or native mail / simulation)
     */
    public static function sendEmail(string $toEmail, string $subject, string $htmlContent, string $eventType = 'general', ?int $clientId = null): array
    {
        $toEmail = trim($toEmail);
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            self::logNotification($clientId, $toEmail, 'email', $eventType, $subject, $htmlContent, 'FAILED', 'Invalid email address');
            return ['success' => false, 'message' => 'Invalid email address'];
        }

        $smtpHost = !empty($_ENV['SMTP_HOST']) ? $_ENV['SMTP_HOST'] : (getenv('SMTP_HOST') ?: 'smtp.gmail.com');
        $smtpPort = (int)(!empty($_ENV['SMTP_PORT']) ? $_ENV['SMTP_PORT'] : (getenv('SMTP_PORT') ?: 587));
        $smtpUser = !empty($_ENV['SMTP_USERNAME']) ? $_ENV['SMTP_USERNAME'] : (getenv('SMTP_USERNAME') ?: (!empty($_ENV['SMTP_USER']) ? $_ENV['SMTP_USER'] : (getenv('SMTP_USER') ?: '')));
        $smtpPass = !empty($_ENV['SMTP_PASSWORD']) ? $_ENV['SMTP_PASSWORD'] : (getenv('SMTP_PASSWORD') ?: (!empty($_ENV['SMTP_PASS']) ? $_ENV['SMTP_PASS'] : (getenv('SMTP_PASS') ?: '')));
        $smtpFrom = !empty($_ENV['SMTP_FROM_EMAIL']) ? $_ENV['SMTP_FROM_EMAIL'] : (getenv('SMTP_FROM_EMAIL') ?: ($smtpUser ?: 'no-reply@payvortex.io'));
        $fromName = !empty($_ENV['SMTP_FROM_NAME']) ? $_ENV['SMTP_FROM_NAME'] : (getenv('SMTP_FROM_NAME') ?: 'PayVortex Gateway');

        // Check if SMTP is configured
        if (!empty($smtpHost) && !empty($smtpUser) && !empty($smtpPass)) {
            try {
                $smtpRes = self::sendViaSmtpSocket($smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFrom, $fromName, $toEmail, $subject, $htmlContent);
                if ($smtpRes['success']) {
                    self::logNotification($clientId, $toEmail, 'email', $eventType, $subject, $htmlContent, 'SENT');
                    return ['success' => true, 'transport' => 'smtp', 'message' => 'Email sent via SMTP'];
                } else {
                    Logger::error("SMTP Dispatch Error: " . $smtpRes['message']);
                    // Fall back to native mail
                }
            } catch (\Throwable $e) {
                Logger::error("SMTP Exception: " . $e->getMessage());
            }
        }

        // Fallback A: PHP Native mail() if server has local sendmail/postfix
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$fromName} <{$smtpFrom}>\r\n";
        $headers .= "Reply-To: {$smtpFrom}\r\n";
        $headers .= "X-Mailer: PayVortex-Notifier/2.0\r\n";

        $mailSent = @mail($toEmail, $subject, $htmlContent, $headers);
        $status = $mailSent ? 'SENT' : 'SIMULATED';
        $note = $mailSent ? 'Sent via native mail' : 'Simulated (SMTP credentials not configured)';

        self::logNotification($clientId, $toEmail, 'email', $eventType, $subject, $htmlContent, $status, $mailSent ? null : $note);
        return ['success' => true, 'transport' => $mailSent ? 'mail' : 'simulated', 'message' => $note];
    }

    /**
     * Send SMS via configured driver (mock, twilio, fast2sms)
     */
    public static function sendSms(string $toPhone, string $message, string $eventType = 'general', ?int $clientId = null): array
    {
        $toPhone = preg_replace('/[^0-9+]/', '', trim($toPhone));
        if (strlen($toPhone) < 10) {
            self::logNotification($clientId, $toPhone, 'sms', $eventType, null, $message, 'FAILED', 'Invalid phone number format');
            return ['success' => false, 'message' => 'Invalid phone number format'];
        }

        $driver = strtolower($_ENV['SMS_DRIVER'] ?? getenv('SMS_DRIVER') ?: 'mock');

        if ($driver === 'twilio') {
            $sid = $_ENV['TWILIO_ACCOUNT_SID'] ?? '';
            $token = $_ENV['TWILIO_AUTH_TOKEN'] ?? '';
            $fromNumber = $_ENV['TWILIO_FROM_NUMBER'] ?? '';

            if (!empty($sid) && !empty($token) && !empty($fromNumber)) {
                $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json");
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => http_build_query(['To' => $toPhone, 'From' => $fromNumber, 'Body' => $message]),
                    CURLOPT_USERPWD        => "{$sid}:{$token}",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 8
                ]);
                $resp = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($code >= 200 && $code < 300) {
                    self::logNotification($clientId, $toPhone, 'sms', $eventType, null, $message, 'SENT');
                    return ['success' => true, 'driver' => 'twilio', 'message' => 'SMS sent via Twilio'];
                } else {
                    self::logNotification($clientId, $toPhone, 'sms', $eventType, null, $message, 'FAILED', "Twilio HTTP {$code}: " . substr($resp, 0, 150));
                    return ['success' => false, 'message' => "Twilio error (HTTP {$code})"];
                }
            }
        } elseif ($driver === 'fast2sms') {
            $apiKey = $_ENV['FAST2SMS_API_KEY'] ?? '';
            if (!empty($apiKey)) {
                $ch = curl_init('https://www.fast2sms.com/dev/bulkV2');
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => json_encode([
                        'route'     => 'v3',
                        'sender_id' => 'TXTIND',
                        'message'   => $message,
                        'language'  => 'english',
                        'numbers'   => $toPhone,
                    ]),
                    CURLOPT_HTTPHEADER     => [
                        'authorization: ' . $apiKey,
                        'Content-Type: application/json'
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 8
                ]);
                $resp = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($code >= 200 && $code < 300) {
                    self::logNotification($clientId, $toPhone, 'sms', $eventType, null, $message, 'SENT');
                    return ['success' => true, 'driver' => 'fast2sms', 'message' => 'SMS sent via Fast2SMS'];
                }
            }
        }

        // Default: Mock / Simulated SMS
        Logger::info("SMS DISPATCH [SIMULATED]: To {$toPhone} -> {$message}");
        self::logNotification($clientId, $toPhone, 'sms', $eventType, null, $message, 'SIMULATED', 'Logged to database (mock driver)');
        return ['success' => true, 'driver' => 'mock', 'message' => 'SMS simulated and logged'];
    }

    /**
     * Update client notification channels and preference flags
     */
    public static function updateClientNotificationSettings(int $clientId, array $settings): array
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                UPDATE api_clients SET
                    email                = :email,
                    phone                = :phone,
                    notify_email_payment = :nep,
                    notify_email_refund  = :ner,
                    notify_email_dispute = :ned,
                    notify_sms_payment   = :nsp,
                    notify_sms_refund    = :nsr,
                    notify_sms_dispute   = :nsd
                WHERE id = :id
            ");

            $email = trim((string)($settings['email'] ?? ''));
            $phone = trim((string)($settings['phone'] ?? ''));

            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email address format'];
            }

            $stmt->execute([
                ':email' => $email ?: null,
                ':phone' => $phone ?: null,
                ':nep'   => !empty($settings['notify_email_payment']) ? 1 : 0,
                ':ner'   => !empty($settings['notify_email_refund']) ? 1 : 0,
                ':ned'   => !empty($settings['notify_email_dispute']) ? 1 : 0,
                ':nsp'   => !empty($settings['notify_sms_payment']) ? 1 : 0,
                ':nsr'   => !empty($settings['notify_sms_refund']) ? 1 : 0,
                ':nsd'   => !empty($settings['notify_sms_dispute']) ? 1 : 0,
                ':id'    => $clientId,
            ]);

            AuditLogger::logClientAction(
                $clientId,
                'client.notifications_updated',
                null,
                $settings,
                "API Client #{$clientId} updated notification channels and event preferences"
            );

            return ['success' => true, 'message' => 'Notification preferences saved successfully.'];
        } catch (\Throwable $e) {
            Logger::error("Failed to update notification preferences: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Get recent notifications dispatched for a client
     */
    public static function getClientNotificationLogs(int $clientId, int $limit = 20): array
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT * FROM notification_logs 
                WHERE client_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->bindValue(1, $clientId, PDO::PARAM_INT);
            $stmt->bindValue(2, max(1, min($limit, 100)), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Dispatch a Test Alert (Email and/or SMS) to verify client configuration
     */
    public static function sendTestNotification(int $clientId): array
    {
        $client = self::getClient($clientId);
        if (!$client) {
            return ['success' => false, 'message' => 'Client account not found'];
        }

        $results = ['email' => null, 'sms' => null];

        // 1. Test Email
        if (!empty($client['email'])) {
            $subject = "[TEST] PayVortex Notification Alert Verification";
            $html = self::renderEmailTemplate('Test Notification Alert', '#0284c7', [
                'Alert Type'    => 'TEST DISPATCH / VERIFICATION',
                'Status'        => 'DELIVERY VERIFIED',
                'Client ID'     => '#' . $clientId,
                'Business Name' => $client['client_name'] ?? 'Merchant',
                'Email Channel' => $client['email'],
                'SMS Channel'   => $client['phone'] ?? 'None configured',
                'Dispatched At' => date('Y-m-d H:i:s T'),
                'Note'          => 'Your notification pipeline is connected and ready to deliver real-time payment alerts.'
            ]);
            $results['email'] = self::sendEmail($client['email'], $subject, $html, 'test.alert', $clientId);
        } else {
            $results['email'] = ['success' => false, 'message' => 'No contact email configured'];
        }

        // 2. Test SMS
        if (!empty($client['phone'])) {
            $smsMsg = "PayVortex Alert: Test notification received for " . ($client['client_name'] ?? 'Merchant') . " (#{$clientId}). System operational.";
            $results['sms'] = self::sendSms($client['phone'], $smsMsg, 'test.alert', $clientId);
        }

        $anySent = (!empty($results['email']['success']) || !empty($results['sms']['success']));
        return [
            'success' => $anySent,
            'message' => $anySent ? 'Test alert dispatched successfully.' : 'Unable to dispatch test alert.',
            'details' => $results
        ];
    }

    /**
     * Native Socket-level SMTP Client (RFC 5321 with STARTTLS support for Gmail/Google Workspace)
     */
    private static function sendViaSmtpSocket(
        string $host,
        int $port,
        string $user,
        string $pass,
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $subject,
        string $htmlBody
    ): array {
        $timeout = 10;
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if (!$socket) {
            return ['success' => false, 'message' => "Socket connection failed to {$host}:{$port} ({$errstr})"];
        }

        $readResponse = function ($sock, $expectedCode = '250') {
            $response = '';
            while ($str = fgets($sock, 515)) {
                $response .= $str;
                if (substr($str, 3, 1) === ' ') break;
            }
            if (substr($response, 0, 3) !== (string)$expectedCode) {
                return [false, $response];
            }
            return [true, $response];
        };

        $sendCommand = function ($sock, $cmd, $expectedCode = '250') use ($readResponse) {
            fputs($sock, $cmd . "\r\n");
            return $readResponse($sock, $expectedCode);
        };

        // 1. Initial Greeting
        [$ok, $res] = $readResponse($socket, '220');
        if (!$ok) { fclose($socket); return ['success' => false, 'message' => "Initial banner rejected: {$res}"]; }

        // 2. EHLO
        [$ok, $res] = $sendCommand($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'), '250');
        if (!$ok) { fclose($socket); return ['success' => false, 'message' => "EHLO rejected: {$res}"]; }

        // 3. STARTTLS if port 587
        if ($port === 587) {
            [$ok, $res] = $sendCommand($socket, "STARTTLS", '220');
            if ($ok) {
                $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if (!$crypto) {
                    fclose($socket);
                    return ['success' => false, 'message' => "STARTTLS handshake negotiation failed"];
                }
                // Resend EHLO over TLS
                [$ok, $res] = $sendCommand($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'), '250');
            }
        }

        // 4. AUTH LOGIN
        [$ok, $res] = $sendCommand($socket, "AUTH LOGIN", '334');
        if (!$ok) { fclose($socket); return ['success' => false, 'message' => "AUTH LOGIN rejected: {$res}"]; }

        [$ok, $res] = $sendCommand($socket, base64_encode($user), '334');
        if (!$ok) { fclose($socket); return ['success' => false, 'message' => "Username rejected: {$res}"]; }

        [$ok, $res] = $sendCommand($socket, base64_encode($pass), '235');
        if (!$ok) { fclose($socket); return ['success' => false, 'message' => "Password rejected: {$res}"]; }

        // 5. MAIL FROM
        [$ok, $res] = $sendCommand($socket, "MAIL FROM:<{$fromEmail}>", '250');
        if (!$ok) { fclose($socket); return ['success' => false, 'message' => "MAIL FROM rejected: {$res}"]; }

        // 6. RCPT TO
        [$ok, $res] = $sendCommand($socket, "RCPT TO:<{$toEmail}>", '250');
        if (!$ok) { fclose($socket); return ['success' => false, 'message' => "RCPT TO rejected: {$res}"]; }

        // 7. DATA
        [$ok, $res] = $sendCommand($socket, "DATA", '354');
        if (!$ok) { fclose($socket); return ['success' => false, 'message' => "DATA rejected: {$res}"]; }

        // Build RFC 822 message
        $headers  = "From: {$fromName} <{$fromEmail}>\r\n";
        $headers .= "To: <{$toEmail}>\r\n";
        $headers .= "Subject: {$subject}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "X-Mailer: PayVortex-Google-SMTP/2.0\r\n\r\n";

        fputs($socket, $headers . $htmlBody . "\r\n.\r\n");
        [$ok, $res] = $readResponse($socket, '250');
        if (!$ok) { fclose($socket); return ['success' => false, 'message' => "Message body rejected: {$res}"]; }

        // 8. QUIT
        $sendCommand($socket, "QUIT", '221');
        fclose($socket);

        return ['success' => true, 'message' => 'Dispatched successfully via Google SMTP'];
    }

    /**
     * Branded HTML Email Template Generator
     */
    private static function renderEmailTemplate(string $title, string $accentColor, array $fields): string
    {
        $rowsHtml = '';
        foreach ($fields as $label => $value) {
            $rowsHtml .= "
                <tr>
                    <td style='padding: 8px 12px; font-size: 13px; color: #64748b; font-weight: 600; width: 38%; border-bottom: 1px solid #f1f5f9;'>{$label}</td>
                    <td style='padding: 8px 12px; font-size: 13px; color: #0f172a; font-weight: 700; border-bottom: 1px solid #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, monospace;'>{$value}</td>
                </tr>
            ";
        }

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>{$title}</title>
        </head>
        <body style='margin: 0; padding: 24px; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; color: #0f172a;'>
            <table role='presentation' width='100%' cellspacing='0' cellpadding='0'>
                <tr>
                    <td align='center'>
                        <table role='presentation' width='100%' style='max-width: 580px; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);'>
                            <!-- Top Accent Bar -->
                            <tr><td style='height: 4px; background: {$accentColor};'></td></tr>
                            
                            <!-- Header -->
                            <tr>
                                <td style='padding: 24px 28px 16px; border-bottom: 1px solid #f1f5f9;'>
                                    <table width='100%'>
                                        <tr>
                                            <td>
                                                <div style='font-size: 12px; font-weight: 800; color: #2563eb; letter-spacing: 0.8px; text-transform: uppercase;'>PayVortex Merchant Alert</div>
                                                <h1 style='font-size: 20px; font-weight: 800; margin: 4px 0 0; color: #0f172a;'>{$title}</h1>
                                            </td>
                                            <td align='right' style='vertical-align: top;'>
                                                <span style='background: #eff6ff; color: #2563eb; font-size: 11px; font-weight: 700; padding: 4px 8px; border-radius: 4px; text-transform: uppercase;'>Gateway Active</span>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <!-- Details Table -->
                            <tr>
                                <td style='padding: 20px 28px;'>
                                    <table width='100%' cellspacing='0' cellpadding='0' style='border-collapse: collapse;'>
                                        {$rowsHtml}
                                    </table>
                                </td>
                            </tr>

                            <!-- Action CTA -->
                            <tr>
                                <td style='padding: 0 28px 24px;' align='center'>
                                    <a href='https://rpay.rajagiri.edu.com/vortex/portal/login.php' style='display: inline-block; background: #2563eb; color: #ffffff; font-size: 13px; font-weight: 700; padding: 10px 20px; text-decoration: none; border-radius: 6px;'>
                                        View in Merchant Portal &rarr;
                                    </a>
                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td style='background: #f8fafc; padding: 16px 28px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #94a3b8; text-align: center;'>
                                    You received this automated notification according to your PayVortex merchant preferences.<br>
                                    To adjust alert triggers, update your settings in the <a href='https://rpay.rajagiri.edu.com/vortex/portal/settings.php' style='color: #2563eb; text-decoration: none;'>Merchant Portal</a>.
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }

    /**
     * Record notification in database
     */
    private static function logNotification(
        ?int $clientId,
        string $recipient,
        string $channel,
        string $eventType,
        ?string $subject,
        string $content,
        string $status,
        ?string $errorMessage = null
    ): void {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO notification_logs (
                    client_id, recipient, channel, event_type, subject, content, status, error_message
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");
            $stmt->execute([
                $clientId,
                $recipient,
                $channel,
                $eventType,
                $subject,
                $content,
                $status,
                $errorMessage
            ]);
        } catch (\Throwable $e) {
            Logger::error("Failed to insert notification log: " . $e->getMessage());
        }
    }

    /**
     * Helper to fetch client with notification settings
     */
    private static function getClient(int $clientId): ?array
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM api_clients WHERE id = ? LIMIT 1");
            $stmt->execute([$clientId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
