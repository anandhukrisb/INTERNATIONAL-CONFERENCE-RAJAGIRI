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
            $stmt = $db->prepare("SELECT id, username, password_hash, full_name, is_active FROM admin WHERE username = :u LIMIT 1");
            $stmt->execute([':u' => $username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && (int)$admin['is_active'] === 1 && password_verify($password, $admin['password_hash'])) {
                // Regenerate session ID to prevent fixation
                session_regenerate_id(true);

                $_SESSION['vortex_admin_id']       = $admin['id'];
                $_SESSION['vortex_admin_username']  = $admin['username'];
                $_SESSION['vortex_admin_fullname']  = $admin['full_name'];

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
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .login-box {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 40px 36px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
        }

        .login-logo {
            text-align: center;
            margin-bottom: 28px;
        }

        .login-logo h1 {
            font-size: 1.8rem;
            font-weight: 800;
            color: #0A1938;
            letter-spacing: -0.5px;
        }

        .login-logo p {
            font-size: 0.85rem;
            color: #64748b;
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
            color: #0f172a;
        }

        .form-group input {
            padding: 10px 12px;
            font-size: 0.95rem;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            color: #0f172a;
            background: #f8fafc;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #3165EC;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(49,101,236,0.12);
        }

        .btn-login {
            width: 100%;
            padding: 11px;
            background-color: #3165EC;
            color: #ffffff;
            font-size: 0.95rem;
            font-weight: 700;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 8px;
            transition: background-color 0.15s ease;
        }

        .btn-login:hover {
            background-color: #133989;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
            border-radius: 6px;
            padding: 10px 14px;
            font-size: 0.88rem;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
            border-radius: 6px;
            padding: 10px 14px;
            font-size: 0.88rem;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .login-footer {
            text-align: center;
            font-size: 0.78rem;
            color: #94a3b8;
            margin-top: 24px;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="login-logo">
            <h1>Vortex</h1>
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
</body>
</html>
