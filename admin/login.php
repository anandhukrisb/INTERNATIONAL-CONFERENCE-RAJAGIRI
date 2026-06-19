<?php
session_start();
require_once __DIR__ . '/../backend/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($email) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin) {
                // Check if password matches hash
                if (password_verify($password, $admin['password'])) {
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_email'] = $admin['email'];
                    header("Location: dashboard.php");
                    exit;
                } 
                // Fallback: If the user manually inserted plain text password, hash it automatically
                else if ($admin['password'] === $password) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $update_stmt = $pdo->prepare("UPDATE admin_users SET password = :hash WHERE id = :id");
                    $update_stmt->execute([':hash' => $hashed_password, ':id' => $admin['id']]);
                    
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_email'] = $admin['email'];
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error = "Invalid email or password.";
                }
            } else {
                $error = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            $error = "Database error occurred.";
        }
    } else {
        $error = "Please enter both email and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - ICSWHMH 2027</title>
    <link rel="icon" type="image/x-icon" href="https://res.cloudinary.com/dswfp5fwx/image/upload/v1778131826/Favicon-192_hdltam.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <script src="../navbar.js" defer></script>
    <script src="../footer.js" defer></script>
    <style>
        :root {
            --primary-purple: #1d0a3f;
            --accent-gold: #C9A227;
            --bg-light: #FDFBF7;
            --text-dark: #2c2c2c;
            --white: #ffffff;
        }
        body {
            background-color: var(--bg-light);
            background-image: linear-gradient(135deg, rgba(29, 10, 63, 0.03) 0%, rgba(201, 162, 39, 0.05) 100%);
            color: var(--text-dark);
            font-family: 'Inter', sans-serif;
            margin: 0;
        }
        .main-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 280px);
            padding: 120px 20px 60px 20px;
            box-sizing: border-box;
        }
        .login-card {
            background: var(--white);
            padding: 50px 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 420px;
            border-top: 6px solid var(--primary-purple);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header img {
            max-width: 150px;
            margin-bottom: 20px;
        }
        .login-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.8rem;
            color: var(--primary-purple);
            margin: 0 0 10px 0;
        }
        .login-subtitle {
            color: #64748b;
            font-size: 0.95rem;
            margin: 0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--primary-purple);
            font-size: 0.95rem;
        }
        .form-control {
            width: 100%;
            padding: 14px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            box-sizing: border-box;
            transition: all 0.3s ease;
            background: #f8fafc;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary-purple);
            box-shadow: 0 0 0 3px rgba(29, 10, 63, 0.1);
            background: var(--white);
        }
        .btn-login {
            width: 100%;
            background-color: var(--accent-gold);
            color: var(--primary-purple);
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 1.05rem;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        .btn-login:hover {
            background-color: var(--primary-purple);
            color: var(--accent-gold);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(29, 10, 63, 0.2);
        }
        .error-msg {
            color: #C53030;
            background: #FFF5F5;
            border: 1px solid #FEB2B2;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            text-align: center;
        }
    </style>
</head>
<body>
    <floating-navbar base-path="../"></floating-navbar>
    <div class="main-wrapper">
        <div class="login-card">
            <div class="login-header">
            <img src="https://res.cloudinary.com/dswfp5fwx/image/upload/v1771434106/logo_zuz2f8.png" alt="ICSWHMH Logo">
            <h1 class="login-title">Admin Portal</h1>
            <p class="login-subtitle">Sign in to manage registrations</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" required placeholder="admin@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn-login">Sign In</button>
        </form>
    </div>
    </div>
    <main-footer base-path="../"></main-footer>
</body>
</html>
