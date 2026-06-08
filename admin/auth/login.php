<?php
/**
 * Admin Login Page
 * ICSWHMH 2027 Admin Panel — Phase 1
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

apply_security_headers();
configure_session();

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    redirect('/admin/dashboard/index.php');
}

// ── Attempt remember-me auto-login ────────────────────────────────────────────
if (!is_logged_in() && isset($_COOKIE[REMEMBER_COOKIE])) {
    $remembered = try_remember_me_login();
    if ($remembered) {
        create_auth_session($remembered);
        audit_log($remembered['id'], $remembered['email'], 'login', 'Via remember-me token', get_client_ip());
        redirect('/admin/dashboard/index.php');
    }
}

// ── Handle POST ───────────────────────────────────────────────────────────────
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email    = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember_me']);
    $ip       = get_client_ip();
    $ua       = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $result = attempt_login($email, $password, $ip, $ua);

    if ($result['success']) {
        create_auth_session($result['user']);

        if ($remember) {
            set_remember_me((int) $result['user']['id'], $ip, $ua);
        }

        // Redirect to intended URL or dashboard
        $intended = $_SESSION['intended'] ?? '/admin/dashboard/index.php';
        unset($_SESSION['intended']);
        redirect($intended);
    } else {
        $error = $result['message'];
    }
}

// Flash messages
$error   = $error   ?: flash_get('error');
$success = $success ?: flash_get('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Login — ICSWHMH 2027</title>
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

    <!-- LEFT PANEL — Conference Branding -->
    <div class="auth-left">
        <div class="auth-left-content">
            <div class="auth-logo-box">
                <img src="https://res.cloudinary.com/dswfp5fwx/image/upload/v1771434106/logo_zuz2f8.png"
                     alt="ICSWHMH 2027"
                     class="auth-logo-img"
                     onerror="this.style.display='none'">
            </div>
            <h1 class="auth-conf-name">ICSWHMH<br>2027</h1>
            <p class="auth-conf-desc">
                International Conference on Social Work,<br>
                Health Management &amp; Human Rights
            </p>
            <div class="auth-conf-date">🗓 2027 · Rajagiri, Kerala</div>

            <div class="auth-security-note">
                <i class="bi bi-shield-fill-check"></i>
                Secured access. All sessions are encrypted and monitored.
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL — Login Form -->
    <div class="auth-right">
        <div class="auth-form-box">

            <div class="auth-form-header">
                <h2 class="auth-form-title">Welcome Back</h2>
                <p class="auth-form-subtitle">Sign in to the admin panel</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-error" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?= e($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <i class="bi bi-check-circle-fill"></i>
                <?= e($success) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="" id="login-form" novalidate autocomplete="off">
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
                        placeholder="admin@icswhmh2027.com"
                        value="<?= e($_POST['email'] ?? '') ?>"
                        autocomplete="username"
                        required
                        autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">
                        <i class="bi bi-lock"></i> Password
                    </label>
                    <div class="input-with-icon">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            placeholder="Your secure password"
                            autocomplete="current-password"
                            required>
                        <button type="button" class="input-eye-btn" id="toggle-pw"
                                aria-label="Toggle password visibility">
                            <i class="bi bi-eye" id="pw-icon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="checkbox-inline">
                        <input type="checkbox" name="remember_me" id="remember-me">
                        <span>Remember me for <?= REMEMBER_ME_DAYS ?> days</span>
                    </label>
                    <a href="/admin/auth/forgot-password.php" class="link-gold">
                        Forgot password?
                    </a>
                </div>

                <button type="submit" class="btn btn-gold btn-full" id="login-btn">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Sign In Securely
                </button>
            </form>

            <p class="auth-footer-note">
                &copy; <?= date('Y') ?> ICSWHMH 2027 &mdash; Authorised personnel only.
            </p>

        </div>
    </div>

</div><!-- /auth-wrapper -->

<script>
// Password toggle
document.getElementById('toggle-pw').addEventListener('click', function () {
    const pw   = document.getElementById('password');
    const icon = document.getElementById('pw-icon');
    const show = pw.type === 'password';
    pw.type    = show ? 'text' : 'password';
    icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    this.setAttribute('aria-pressed', show ? 'true' : 'false');
});

// Submit loading state
document.getElementById('login-form').addEventListener('submit', function () {
    const btn = document.getElementById('login-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="btn-spinner"></span> Authenticating…';
});
</script>
</body>
</html>
