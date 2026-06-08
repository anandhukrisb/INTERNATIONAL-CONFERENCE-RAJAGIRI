<?php
/**
 * PHPMailer Wrapper
 * ICSWHMH 2027 Admin Panel — Phase 1
 *
 * Requires: composer require phpmailer/phpmailer
 * Or place PHPMailer files in: admin/vendor/phpmailer/
 */
declare(strict_types=1);

/**
 * Try to load PHPMailer from multiple locations.
 */
function load_phpmailer(): bool
{
    // 1. Composer autoload
    $composer = ADMIN_ROOT . '/vendor/autoload.php';
    if (file_exists($composer)) {
        require_once $composer;
        return true;
    }
    // 2. Manual install (download from GitHub)
    $manual = ADMIN_ROOT . '/vendor/phpmailer/src/';
    if (is_dir($manual)) {
        require_once $manual . 'Exception.php';
        require_once $manual . 'PHPMailer.php';
        require_once $manual . 'SMTP.php';
        return true;
    }
    return false;
}

/**
 * Send the password reset email.
 *
 * @param string $toEmail   Recipient email
 * @param string $toName    Recipient name
 * @param string $resetLink Full reset URL
 * @return bool             True on success
 * @throws RuntimeException If PHPMailer not found
 */
function send_password_reset_email(string $toEmail, string $toName, string $resetLink): bool
{
    if (!load_phpmailer()) {
        throw new RuntimeException(
            'PHPMailer not found. Run: composer require phpmailer/phpmailer'
        );
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->Port       = MAIL_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;

        $mail->SMTPSecure = (MAIL_ENCRYPTION === 'SSL')
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

        // Timeout & retry
        $mail->Timeout    = 15;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false,
            ],
        ];

        // Sender / Recipient
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo(MAIL_FROM, MAIL_FROM_NAME);

        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Password Reset — ICSWHMH 2027 Admin Panel';
        $mail->Body    = build_reset_email_html($toName, $resetLink);
        $mail->AltBody = build_reset_email_text($toName, $resetLink);

        $mail->send();
        return true;

    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('[MAILER_ERROR] ' . $e->getMessage());
        return false;
    }
}

/**
 * Build the HTML email body for password reset.
 */
function build_reset_email_html(string $name, string $resetLink): string
{
    $expiry   = RESET_TOKEN_EXPIRY_MIN;
    $siteName = APP_NAME;
    $year     = date('Y');
    $safeLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Password Reset</title>
</head>
<body style="margin:0;padding:0;background-color:#FDFBF7;font-family:'Segoe UI',Roboto,Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#FDFBF7;padding:40px 20px;">
    <tr>
      <td align="center">
        <table width="580" cellpadding="0" cellspacing="0"
               style="background:#ffffff;border-radius:20px;overflow:hidden;
                      box-shadow:0 10px 40px rgba(29,10,63,0.1);max-width:580px;width:100%;">

          <!-- Header -->
          <tr>
            <td style="background:linear-gradient(135deg,#1d0a3f 0%,#4b1c9b 100%);
                       padding:36px 40px;text-align:center;position:relative;">
              <div style="display:inline-block;background:rgba(201,162,39,0.15);
                          border:2px solid rgba(201,162,39,0.5);border-radius:14px;
                          padding:12px 16px;margin-bottom:16px;">
                <span style="font-size:28px;">🔐</span>
              </div>
              <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;
                         font-family:'Segoe UI',Arial,sans-serif;letter-spacing:-0.5px;">
                Password Reset Request
              </h1>
              <p style="margin:8px 0 0;color:rgba(255,255,255,0.65);font-size:13px;">
                {$siteName}
              </p>
            </td>
          </tr>

          <!-- Gold accent bar -->
          <tr>
            <td style="background:#C9A227;height:4px;font-size:0;line-height:0;">&nbsp;</td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:36px 40px;">
              <p style="margin:0 0 16px;color:#2c2c2c;font-size:15px;line-height:1.6;">
                Hello, <strong style="color:#1d0a3f;">{$safeName}</strong>,
              </p>
              <p style="margin:0 0 24px;color:#555;font-size:15px;line-height:1.7;">
                We received a request to reset your admin panel password.
                Click the button below to create a new password.
                This link is valid for <strong>{$expiry} minutes</strong> and can only be used once.
              </p>

              <!-- CTA Button -->
              <table cellpadding="0" cellspacing="0" width="100%">
                <tr>
                  <td align="center" style="padding:8px 0 28px;">
                    <a href="{$safeLink}"
                       style="display:inline-block;background:#C9A227;color:#1d0a3f;
                              padding:14px 36px;border-radius:50px;text-decoration:none;
                              font-weight:700;font-size:15px;font-family:'Segoe UI',Arial,sans-serif;
                              letter-spacing:0.3px;box-shadow:0 4px 15px rgba(201,162,39,0.35);">
                      Reset My Password
                    </a>
                  </td>
                </tr>
              </table>

              <!-- Security notice -->
              <div style="background:#f9f7ff;border-left:4px solid #1d0a3f;border-radius:0 10px 10px 0;
                          padding:16px 18px;margin:0 0 24px;">
                <p style="margin:0;color:#555;font-size:13px;line-height:1.6;">
                  🔒 <strong style="color:#1d0a3f;">Security Notice:</strong>
                  If you did not request a password reset, please ignore this email.
                  Your password will remain unchanged. Consider reviewing your account for suspicious activity.
                </p>
              </div>

              <!-- Fallback link -->
              <p style="margin:0 0 8px;color:#888;font-size:12px;">
                If the button above doesn't work, copy and paste this link into your browser:
              </p>
              <p style="margin:0;word-break:break-all;">
                <a href="{$safeLink}" style="color:#1d0a3f;font-size:12px;">{$safeLink}</a>
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background:#1d0a3f;padding:20px 40px;text-align:center;">
              <p style="margin:0;color:rgba(255,255,255,0.5);font-size:12px;">
                &copy; {$year} {$siteName}. This email was sent to {$toEmail}.
                <br>All admin actions are logged and monitored.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

/**
 * Build the plain-text version of the reset email.
 */
function build_reset_email_text(string $name, string $resetLink): string
{
    $expiry = RESET_TOKEN_EXPIRY_MIN;
    return "Hello {$name},\n\n"
         . "We received a request to reset your ICSWHMH 2027 admin password.\n\n"
         . "Click the link below to reset your password (valid for {$expiry} minutes):\n"
         . "{$resetLink}\n\n"
         . "If you did not request this, please ignore this email.\n\n"
         . "— ICSWHMH 2027 Admin Panel";
}
