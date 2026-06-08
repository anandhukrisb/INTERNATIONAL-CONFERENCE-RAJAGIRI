<?php
/**
 * Forgot Password
 * ICSWHMH 2027 Admin Panel — Phase 1
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';

apply_security_headers();
configure_session();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
    $ip    = get_client_ip();

    // Always show the same success message — prevents email enumeration
    $success = 'If that email belongs to an admin account, a reset link has been sent.';

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $user = get_user_by_email($email);

        if ($user && $user['status'] === 'active') {
            $pdo = DB::get();

            // Invalidate previous unused tokens for this user
            $pdo->prepare(
                'UPDATE password_resets SET used_at = NOW()
                 WHERE user_id = :uid AND used_at IS NULL'
            )->execute([':uid' => $user['id']]);

            // Generate cryptographically secure token
            $token     = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', time() + (RESET_TOKEN_EXPIRY_MIN * 60));

            // Store hashed token
            $stmt = $pdo->prepare(
                'INSERT INTO password_resets (user_id, token_hash, expires_at)
                 VALUES (:uid, :hash, :exp)'
            );
            $stmt->execute([
                ':uid'  => $user['id'],
                ':hash' => $tokenHash,
                ':exp'  => $expiresAt,
            ]);

            // Build reset link
            $resetLink = ADMIN_URL . '/auth/reset-password.php?token=' . urlencode($token);

            // Send email
            try {
                $sent = send_password_reset_email($user['email'], $user['full_name'], $resetLink);
                audit_log($user['id'], $user['email'], 'password_reset_request',
                    $sent ? 'Email sent' : 'Email send failed', $ip);
            } catch (RuntimeException $e) {
                // PHPMailer not installed — log and continue silently
                error_log('[RESET_EMAIL_ERROR] ' . $e->getMessage());
                audit_log($user['id'], $user['email'], 'password_reset_request', 'PHPMailer not configured', $ip);
            }
        }
    }
}

$error = $error ?: flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Forgot Password — ICSWHMH 2027 Admin</title>
    <link rel="icon" type="image/x-icon"
          href="https://res.cloudinary.com/dswfp5fwx/image/upload/v1778131826/Favicon-192_hdltam.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Outfit:wght@400;600;800&display=swap"
          rel="stylesheet">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body class="auth-body">

<div class="auth-wrapper">

    <!-- LEFT PANEL -->
    <div class="auth-left">
        <div class="auth-left-content">
            <div class="auth-logo-box">
                <img src="https://res.cloudinary.com/dswfp5fwx/image/upload/v1771434106/logo_zuz2f8.png"
                     alt="ICSWHMH 2027" class="auth-logo-img"
                     onerror="this.style.display='none'">
            </div>
            <h1 class="auth-conf-name">ICSWHMH<br>2027</h1>
            <p class="auth-conf-desc">
                International Conference on Social Work,<br>
                Health Management &amp; Human Rights
            </p>
            <div class="auth-conf-date">🗓 2027 · Rajagiri, Kerala</div>

            <div class="auth-info-card">
                <div class="auth-info-step">
                    <span class="auth-step-number">1</span>
                    <span>Enter your registered email</span>
                </div>
                <div class="auth-info-step">
                    <span class="auth-step-number">2</span>
                    <span>Check your inbox for a reset link</span>
                </div>
                <div class="auth-info-step">
                    <span class="auth-step-number">3</span>
                    <span>Create a new secure password</span>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="auth-right">
        <div class="auth-form-box">

            <div class="auth-form-header">
                <h2 class="auth-form-title">Forgot Password</h2>
                <p class="auth-form-subtitle">
                    Enter your email and we'll send a secure reset link.
                    Link expires in <?= RESET_TOKEN_EXPIRY_MIN ?> minutes.
                </p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?= e($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i>
                <?= e($success) ?>
            </div>
            <?php else: ?>

            <form method="POST" action="" id="forgot-form" novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label" for="email">
                        <i class="bi bi-envelope"></i> Email Address
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="your-email@icswhmh2027.com"
                        value="<?= e($_POST['email'] ?? '') ?>"
                        required
                        autofocus>
                </div>

                <button type="submit" class="btn btn-gold btn-full" id="reset-btn">
                    <i class="bi bi-send"></i>
                    Send Reset Link
                </button>
            </form>

            <?php endif; ?>

            <div class="auth-back-link">
                <a href="/admin/auth/login.php">
                    <i class="bi bi-arrow-left"></i> Back to Login
                </a>
            </div>

        </div>
    </div>

</div>

<script>
document.getElementById('forgot-form')?.addEventListener('submit', function () {
    const btn = document.getElementById('reset-btn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="btn-spinner"></span> Sending…';
    }
});
</script>
</body>
</html>
