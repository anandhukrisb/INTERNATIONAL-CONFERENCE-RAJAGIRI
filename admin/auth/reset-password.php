<?php
/**
 * Password Reset Page
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

$token     = trim($_GET['token'] ?? '');
$tokenHash = $token ? hash('sha256', $token) : '';
$error     = '';
$success   = '';

// ── Validate token ────────────────────────────────────────────────────────────
function validate_reset_token(string $tokenHash): array|false
{
    if (!$tokenHash) return false;

    $stmt = DB::get()->prepare(
        'SELECT pr.*, au.email, au.full_name, au.id AS user_id
         FROM password_resets pr
         JOIN admin_users au ON au.id = pr.user_id
         WHERE pr.token_hash = :hash
           AND pr.used_at IS NULL
           AND pr.expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute([':hash' => $tokenHash]);
    return $stmt->fetch();
}

$reset = validate_reset_token($tokenHash);

if (!$reset) {
    $error = 'This password reset link is invalid or has expired. Please request a new one.';
}

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset) {
    csrf_verify();

    $newPass = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $ip      = get_client_ip();

    if ($newPass !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $pwErrors = validate_password($newPass);
        if ($pwErrors) {
            $error = implode(' ', $pwErrors);
        } else {
            $pdo  = DB::get();
            $hash = password_hash($newPass, PASSWORD_ARGON2ID, PW_ARGON_OPTIONS);

            // Update password
            $pdo->prepare(
                'UPDATE admin_users
                 SET password_hash = :hash, password_changed_at = NOW()
                 WHERE id = :uid'
            )->execute([':hash' => $hash, ':uid' => $reset['user_id']]);

            // Mark token as used (single-use enforcement)
            $pdo->prepare(
                'UPDATE password_resets SET used_at = NOW() WHERE token_hash = :hash'
            )->execute([':hash' => $tokenHash]);

            // Invalidate all other tokens for this user
            $pdo->prepare(
                'UPDATE password_resets SET used_at = NOW()
                 WHERE user_id = :uid AND used_at IS NULL'
            )->execute([':uid' => $reset['user_id']]);

            // Audit
            audit_log(
                (int) $reset['user_id'],
                $reset['email'],
                'password_reset_success',
                'Password reset via email link',
                $ip
            );

            flash_set('success', 'Password changed successfully. Please log in with your new password.');
            redirect('/admin/auth/login.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Reset Password — ICSWHMH 2027 Admin</title>
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

            <!-- Password requirements -->
            <div class="auth-info-card">
                <p style="color:rgba(255,255,255,0.7);font-size:0.82rem;margin-bottom:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;">
                    Password Requirements
                </p>
                <div class="auth-req-item" id="req-length">
                    <i class="bi bi-circle req-icon"></i>
                    Minimum <?= PW_MIN_LENGTH ?> characters
                </div>
                <div class="auth-req-item" id="req-upper">
                    <i class="bi bi-circle req-icon"></i>
                    One uppercase letter (A-Z)
                </div>
                <div class="auth-req-item" id="req-lower">
                    <i class="bi bi-circle req-icon"></i>
                    One lowercase letter (a-z)
                </div>
                <div class="auth-req-item" id="req-number">
                    <i class="bi bi-circle req-icon"></i>
                    One number (0-9)
                </div>
                <div class="auth-req-item" id="req-special">
                    <i class="bi bi-circle req-icon"></i>
                    One special character (!@#$%...)
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="auth-right">
        <div class="auth-form-box">

            <div class="auth-form-header">
                <h2 class="auth-form-title">Create New Password</h2>
                <?php if ($reset): ?>
                <p class="auth-form-subtitle">
                    Setting new password for <strong><?= e($reset['email']) ?></strong>
                </p>
                <?php endif; ?>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?= e($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($reset && !$success): ?>
            <form method="POST" action="" id="reset-form" novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label" for="password">
                        <i class="bi bi-lock"></i> New Password
                    </label>
                    <div class="input-with-icon">
                        <input type="password" id="password" name="password"
                               class="form-control" placeholder="Create a strong password"
                               required autocomplete="new-password">
                        <button type="button" class="input-eye-btn" id="toggle-pw1"
                                aria-label="Toggle password">
                            <i class="bi bi-eye" id="icon-pw1"></i>
                        </button>
                    </div>
                    <!-- Strength bar -->
                    <div class="pw-strength-bar">
                        <div class="pw-strength-fill" id="pw-strength-fill"></div>
                    </div>
                    <span class="pw-strength-label" id="pw-strength-label"></span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">
                        <i class="bi bi-lock-fill"></i> Confirm Password
                    </label>
                    <div class="input-with-icon">
                        <input type="password" id="confirm_password" name="confirm_password"
                               class="form-control" placeholder="Re-enter your password"
                               required autocomplete="new-password">
                        <button type="button" class="input-eye-btn" id="toggle-pw2"
                                aria-label="Toggle password">
                            <i class="bi bi-eye" id="icon-pw2"></i>
                        </button>
                    </div>
                    <span class="form-hint" id="match-hint"></span>
                </div>

                <button type="submit" class="btn btn-gold btn-full" id="save-btn" disabled>
                    <i class="bi bi-check-lg"></i>
                    Set New Password
                </button>
            </form>

            <?php else: ?>
            <div class="text-center" style="padding:20px 0;">
                <a href="/admin/auth/forgot-password.php" class="btn btn-gold">
                    <i class="bi bi-arrow-repeat"></i>
                    Request New Reset Link
                </a>
            </div>
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
// Password toggle helpers
function togglePw(inputId, iconId) {
    const pw   = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    const show = pw.type === 'password';
    pw.type    = show ? 'text' : 'password';
    icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
}
document.getElementById('toggle-pw1')?.addEventListener('click', () => togglePw('password', 'icon-pw1'));
document.getElementById('toggle-pw2')?.addEventListener('click', () => togglePw('confirm_password', 'icon-pw2'));

// Password strength & requirements checker
const pwInput   = document.getElementById('password');
const cfInput   = document.getElementById('confirm_password');
const saveBtn   = document.getElementById('save-btn');
const fillBar   = document.getElementById('pw-strength-fill');
const fillLabel = document.getElementById('pw-strength-label');
const matchHint = document.getElementById('match-hint');

const reqs = {
    'req-length':  v => v.length >= <?= PW_MIN_LENGTH ?>,
    'req-upper':   v => /[A-Z]/.test(v),
    'req-lower':   v => /[a-z]/.test(v),
    'req-number':  v => /[0-9]/.test(v),
    'req-special': v => /[\W_]/.test(v),
};

function checkRequirements(value) {
    let passed = 0;
    Object.entries(reqs).forEach(([id, fn]) => {
        const el   = document.getElementById(id);
        const icon = el?.querySelector('.req-icon');
        const ok   = fn(value);
        if (ok) {
            el?.classList.add('req-ok');
            el?.classList.remove('req-fail');
            if (icon) icon.className = 'bi bi-check-circle-fill req-icon';
            passed++;
        } else {
            el?.classList.remove('req-ok');
            el?.classList.add('req-fail');
            if (icon) icon.className = 'bi bi-circle req-icon';
        }
    });
    return passed;
}

function updateStrength(value) {
    const passed = checkRequirements(value);
    const pct    = (passed / 5) * 100;
    if (fillBar) {
        fillBar.style.width = pct + '%';
        fillBar.className   = 'pw-strength-fill';
        if (pct < 40) {
            fillBar.style.background = '#ef4444';
            if (fillLabel) { fillLabel.textContent = 'Weak'; fillLabel.style.color = '#ef4444'; }
        } else if (pct < 80) {
            fillBar.style.background = '#f59e0b';
            if (fillLabel) { fillLabel.textContent = 'Moderate'; fillLabel.style.color = '#f59e0b'; }
        } else {
            fillBar.style.background = '#22c55e';
            if (fillLabel) { fillLabel.textContent = 'Strong'; fillLabel.style.color = '#22c55e'; }
        }
    }
    return passed === 5;
}

function checkMatch() {
    if (!cfInput.value) { if(matchHint) matchHint.textContent = ''; return false; }
    const ok = pwInput.value === cfInput.value;
    if (matchHint) {
        matchHint.textContent = ok ? '✓ Passwords match' : '✗ Passwords do not match';
        matchHint.style.color = ok ? '#22c55e' : '#ef4444';
    }
    return ok;
}

function updateButton() {
    const strong = updateStrength(pwInput.value);
    const match  = checkMatch();
    if (saveBtn) saveBtn.disabled = !(strong && match);
}

pwInput?.addEventListener('input', updateButton);
cfInput?.addEventListener('input', updateButton);

// Submit loading state
document.getElementById('reset-form')?.addEventListener('submit', function () {
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="btn-spinner"></span> Saving…';
    }
});
</script>
</body>
</html>
