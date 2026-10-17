<?php
/**
 * ------------------------------------------------------------
 * login.php – PayVortex Merchant Client Portal Login
 * ------------------------------------------------------------
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Already logged in
if (!empty($_SESSION['vortex_client_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/ApiClient.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $credential = trim($_POST['credential'] ?? '');
    $secret = trim($_POST['secret'] ?? '');

    if (empty($credential) || empty($secret)) {
        $error = 'Both credentials are required to sign in.';
    } else {
        $clientModel = new ApiClient();
        $client = $clientModel->authenticatePortalUser($credential, $secret);

        if ($client) {
            session_regenerate_id(true);
            $_SESSION['vortex_client_id']         = (int)$client['id'];
            $_SESSION['vortex_client_name']       = $client['client_name'];
            $_SESSION['vortex_client_login_mode'] = $client['login_mode'] ?? 'live_key';

            header('Location: dashboard.php');
            exit;
        } else {
            sleep(1);
            $error = 'Invalid merchant credentials. Please check your API Key & Secret or Email & Password.';
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
    <title>Merchant Portal Sign In – PayVortex</title>
    <link rel="stylesheet" href="assets/css/portal.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #0b1329;
            background: radial-gradient(circle at 50% 20%, #1e293b 0%, #090e1a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        .login-card-wrapper {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border-radius: var(--radius-lg);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.08);
            overflow: hidden;
        }

        .login-card-header {
            padding: 32px 32px 24px;
            text-align: center;
            background: #ffffff;
            border-bottom: 1px solid var(--border-light);
        }

        .login-brand {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 12px;
        }

        .login-brand img {
            height: 38px;
            width: auto;
        }

        .login-brand h1 {
            font-size: 1.6rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
        }

        .login-badge {
            font-size: 0.7rem;
            font-weight: 700;
            background: #eff6ff;
            color: #2563eb;
            padding: 2px 8px;
            border-radius: 4px;
            margin-left: 6px;
        }

        .login-subtitle {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .login-card-body {
            padding: 28px 32px;
        }

        .login-footer-info {
            padding: 16px 32px 24px;
            text-align: center;
            font-size: 0.78rem;
            color: var(--text-muted);
            border-top: 1px solid var(--border-light);
            background: #f8fafc;
        }

        .help-callout {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: var(--radius-sm);
            padding: 10px 12px;
            font-size: 0.78rem;
            color: #1e40af;
            margin-bottom: 20px;
            line-height: 1.4;
        }
    </style>
</head>
<body>

<div class="login-card-wrapper">
    <div class="login-card-header">
        <div class="login-brand">
            <img src="https://res.cloudinary.com/dswfp5fwx/image/upload/v1789742057/VortexBlue_smpgca.png" alt="PayVortex">
            <h1>ORTEX</h1>
            <span class="login-badge">Merchant Portal</span>
        </div>
        <p class="login-subtitle">Self-service dashboard for integrated merchants and applications</p>
    </div>

    <div class="login-card-body">
        <?php if ($loggedOut): ?>
            <div class="alert alert-success">✓ You have been signed out successfully.</div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="help-callout">
            💡 <strong>Quick Sign In</strong>: Use your merchant <strong>API Key</strong> as your ID and <strong>API Secret</strong> (Live or Test) as your password.
        </div>

        <form method="POST" action="login.php" autocomplete="off">
            <div class="form-group">
                <label class="form-label" for="credential">Merchant API Key or Email</label>
                <input type="text" id="credential" name="credential" class="form-control"
                       placeholder="e.g. 94ce1b3505... or merchant@org.com"
                       value="<?= htmlspecialchars($_POST['credential'] ?? '') ?>"
                       required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="secret">API Secret or Password</label>
                <input type="password" id="secret" name="secret" class="form-control"
                       placeholder="Enter your API Secret or account password"
                       required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 0.95rem; margin-top: 8px;">
                Sign In to Merchant Portal &rarr;
            </button>
        </form>
    </div>

    <div class="login-footer-info">
        PayVortex Orchestrator &bull; Need credentials? Contact your Platform Administrator.
    </div>
</div>

</body>
</html>
