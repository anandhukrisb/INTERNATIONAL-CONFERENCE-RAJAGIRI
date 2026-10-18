<?php
/**
 * Vortex Admin – Login Page
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Already logged in
if (!empty($_SESSION['vortex_admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../classes/Database.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Username and password are required.';
    } else {
        try {
            $db   = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id, username, password_hash, full_name, email, role, avatar_color, is_active FROM admin WHERE username = :u LIMIT 1");
            $stmt->execute([':u' => $username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && (int)$admin['is_active'] === 1 && password_verify($password, $admin['password_hash'])) {
                // Regenerate session ID to prevent fixation
                session_regenerate_id(true);

                $_SESSION['vortex_admin_id']           = $admin['id'];
                $_SESSION['vortex_admin_username']      = $admin['username'];
                $_SESSION['vortex_admin_fullname']      = $admin['full_name'];
                $_SESSION['vortex_admin_email']         = $admin['email'] ?? '';
                $_SESSION['vortex_admin_role']          = $admin['role'] ?? 'Super Administrator';
                $_SESSION['vortex_admin_avatar_color']  = $admin['avatar_color'] ?? '#1a73e8';

                // Update last_login
                $db->prepare("UPDATE admin SET last_login = NOW() WHERE id = :id")->execute([':id' => $admin['id']]);

                header('Location: dashboard.php');
                exit;
            } else {
                // Timing-safe: sleep briefly to deter brute force
                sleep(1);
                $error = 'Invalid username or password.';
            }
        } catch (Throwable $e) {
            $error = 'A system error occurred. Please try again.';
        }
    }
}
$loggedOut = isset($_GET['logged_out']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vortex Admin – Login</title>
    <!-- Immediate Flash-Free Theme Initializer -->
    <script>
        (function() {
            var saved = localStorage.getItem('vortex_theme');
            if (saved === 'dark' || (!saved && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root, [data-theme="light"] {
            --bg-canvas: #f1f5f9;
            --bg-card: #ffffff;
            --border-card: #cbd5e1;
            --text-heading: #0A1938;
            --text-muted: #64748b;
            --text-label: #0f172a;
            --bg-input: #f8fafc;
            --border-input: #cbd5e1;
            --text-input: #0f172a;
            --btn-bg: #3165EC;
            --btn-text: #ffffff;
            --btn-hover: #133989;
            --footer-text: #94a3b8;
            --toggle-bg: #ffffff;
            --toggle-border: #cbd5e1;
            --toggle-icon: #5f6368;
            --box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            --alert-err-bg: #fee2e2;
            --alert-err-text: #b91c1c;
            --alert-err-border: #fca5a5;
            --alert-succ-bg: #dcfce7;
            --alert-succ-text: #15803d;
            --alert-succ-border: #86efac;
        }

        [data-theme="dark"] {
            --bg-canvas: #202124;
            --bg-card: #303134;
            --border-card: #3c4043;
            --text-heading: #e8eaed;
            --text-muted: #9aa0a6;
            --text-label: #e8eaed;
            --bg-input: #292a2d;
            --border-input: #3c4043;
            --text-input: #e8eaed;
            --btn-bg: #8ab4f8;
            --btn-text: #202124;
            --btn-hover: #aecbfa;
            --footer-text: #9aa0a6;
            --toggle-bg: #303134;
            --toggle-border: #3c4043;
            --toggle-icon: #e8eaed;
            --box-shadow: 0 8px 24px rgba(0,0,0,0.4);
            --alert-err-bg: rgba(242, 139, 130, 0.15);
            --alert-err-text: #f28b82;
            --alert-err-border: rgba(242, 139, 130, 0.3);
            --alert-succ-bg: rgba(129, 201, 149, 0.15);
            --alert-succ-text: #81c995;
            --alert-succ-border: rgba(129, 201, 149, 0.3);
        }

        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) {
                --bg-canvas: #202124;
                --bg-card: #303134;
                --border-card: #3c4043;
                --text-heading: #e8eaed;
                --text-muted: #9aa0a6;
                --text-label: #e8eaed;
                --bg-input: #292a2d;
                --border-input: #3c4043;
                --text-input: #e8eaed;
                --btn-bg: #8ab4f8;
                --btn-text: #202124;
                --btn-hover: #aecbfa;
                --footer-text: #9aa0a6;
                --toggle-bg: #303134;
                --toggle-border: #3c4043;
                --toggle-icon: #e8eaed;
                --box-shadow: 0 8px 24px rgba(0,0,0,0.4);
                --alert-err-bg: rgba(242, 139, 130, 0.15);
                --alert-err-text: #f28b82;
                --alert-err-border: rgba(242, 139, 130, 0.3);
                --alert-succ-bg: rgba(129, 201, 149, 0.15);
                --alert-succ-text: #81c995;
                --alert-succ-border: rgba(129, 201, 149, 0.3);
            }
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--bg-canvas);
            color: var(--text-label);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            position: relative;
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        /* Floating Theme Toggle in top-right corner */
        .login-theme-toggle {
            position: fixed;
            top: 20px;
            right: 24px;
            z-index: 100;
        }

        .btn-theme-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background-color: var(--toggle-bg);
            border: 1px solid var(--toggle-border);
            color: var(--toggle-icon);
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.2s ease;
            padding: 0;
        }

        .btn-theme-toggle:hover {
            transform: scale(1.08);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        :root:not([data-theme="dark"]) .theme-icon-sun { display: none !important; }
        :root:not([data-theme="dark"]) .theme-icon-moon { display: flex !important; align-items: center; justify-content: center; color: #5f6368; }

        [data-theme="dark"] .theme-icon-sun { display: flex !important; align-items: center; justify-content: center; color: #fdd663; }
        [data-theme="dark"] .theme-icon-moon { display: none !important; }

        /* Remove unnecessary borders on all div elements */
        div {
            border: none !important;
        }

        .login-box {
            background: var(--bg-card);
            border: none !important;
            border-radius: 12px;
            padding: 40px 36px;
            width: 100%;
            max-width: 420px;
            box-shadow: var(--box-shadow);
            transition: background-color 0.2s ease, box-shadow 0.2s ease;
        }

        .login-logo {
            text-align: center;
            margin-bottom: 28px;
        }

        .login-logo h1 {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--text-heading);
            letter-spacing: -0.5px;
            margin: 0;
            transition: color 0.2s ease;
        }

        .login-logo p {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 16px;
        }

        .form-group label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-label);
        }

        .form-group input {
            padding: 10px 12px;
            font-size: 0.95rem;
            border: 1px solid var(--border-input);
            border-radius: 6px;
            color: var(--text-input);
            background: var(--bg-input);
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #3165EC;
            box-shadow: 0 0 0 3px rgba(49,101,236,0.18);
        }

        [data-theme="dark"] .form-group input:focus {
            border-color: #8ab4f8;
            box-shadow: 0 0 0 3px rgba(138, 180, 248, 0.2);
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background-color: var(--btn-bg);
            color: var(--btn-text);
            font-size: 0.95rem;
            font-weight: 700;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 8px;
            transition: background-color 0.15s ease, transform 0.15s ease;
        }

        .btn-login:hover {
            background-color: var(--btn-hover);
            transform: translateY(-1px);
        }

        .alert-error {
            background-color: var(--alert-err-bg);
            color: var(--alert-err-text);
            border: 1px solid var(--alert-err-border);
            border-radius: 6px;
            padding: 10px 14px;
            font-size: 0.88rem;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: var(--alert-succ-bg);
            color: var(--alert-succ-text);
            border: 1px solid var(--alert-succ-border);
            border-radius: 6px;
            padding: 10px 14px;
            font-size: 0.88rem;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .login-footer {
            text-align: center;
            font-size: 0.78rem;
            color: var(--footer-text);
            margin-top: 24px;
        }
    </style>
</head>
<body>
    <!-- Theme Toggle Floating in Header -->
    <div class="login-theme-toggle">
        <button type="button" id="themeToggleBtn" class="btn-theme-toggle" title="Switch between Dark and Light mode" aria-label="Toggle Theme">
            <span class="theme-icon-sun">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
            </span>
            <span class="theme-icon-moon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
            </span>
        </button>
    </div>

    <div class="login-box">
        <div class="login-logo">
            <div style="display: flex; align-items: center; justify-content: center; gap: 0; margin-bottom: 8px;">
                <img src="https://res.cloudinary.com/dswfp5fwx/image/upload/v1789742057/VortexBlue_smpgca.png" alt="Vortex Logo" style="height: 44px; width: auto; object-fit: contain; margin-right: -4px;">
                <h1>ORTEX</h1>
            </div>
            <p>Unified Payment Gateway — Administration</p>
        </div>

        <?php if ($loggedOut): ?>
            <div class="alert-success">✓ You have been signed out successfully.</div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert-error">⚠ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" autocomplete="off">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       placeholder="Enter admin username"
                       autofocus required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       placeholder="Enter password"
                       required>
            </div>
            <button type="submit" class="btn-login">Sign In</button>
        </form>

        <div class="login-footer">Vortex Gateway v1.0 &bull; Secure Administration Console</div>
    </div>
    <style>
        @keyframes btn-spinner-anim {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .vortex-btn-spinner {
            display: inline-block;
            width: 1em;
            height: 1em;
            margin-right: 8px;
            vertical-align: middle;
            animation: btn-spinner-anim 1s linear infinite;
        }
        .btn-loading-state {
            opacity: 0.8;
            cursor: not-allowed !important;
            pointer-events: none;
        }
    </style>
    <script>
        document.addEventListener('submit', function(e) {
            if (e.target.tagName === 'FORM') {
                const form = e.target;
                const submitBtn = form.querySelector('button[type="submit"]:focus') || form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    if (submitBtn.dataset.submitting) return;
                    submitBtn.dataset.submitting = 'true';
                    submitBtn.classList.add('btn-loading-state');
                    
                    const spinnerSvg = `<svg class="vortex-btn-spinner" viewBox="0 0 50 50"><circle cx="25" cy="25" r="20" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-dasharray="80" stroke-dashoffset="60"></circle></svg>`;
                    
                    submitBtn.innerHTML = `<span style="display: inline-flex; align-items: center; justify-content: center;">${spinnerSvg}<span>${submitBtn.innerHTML}</span></span>`;
                }
            }
        });

        // Google Dark Mode Theme Controller for Login
        (function() {
            const toggleBtn = document.getElementById('themeToggleBtn');
            if (!toggleBtn) return;

            function updateToggleState(theme) {
                document.documentElement.setAttribute('data-theme', theme);
                toggleBtn.setAttribute('title', theme === 'dark' ? 'Switch to Light mode (Google Drive Light)' : 'Switch to Dark mode (Google Dark)');
                toggleBtn.setAttribute('aria-label', theme === 'dark' ? 'Switch to Light mode' : 'Switch to Dark mode');
            }

            const currentTheme = document.documentElement.getAttribute('data-theme') || localStorage.getItem('vortex_theme') || 'light';
            updateToggleState(currentTheme);

            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const current = document.documentElement.getAttribute('data-theme') || 'light';
                const next = current === 'dark' ? 'light' : 'dark';

                localStorage.setItem('vortex_theme', next);
                document.cookie = "vortex_theme=" + next + "; path=/; max-age=31536000; SameSite=Lax";
                updateToggleState(next);
            });
        })();
    </script>
</body>
</html>
