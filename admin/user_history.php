<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['reg_id']) || empty($_GET['reg_id'])) {
    die("Registration ID is required.");
}

$reg_id = $_GET['reg_id'];

try {
    require_once __DIR__ . '/../backend/db.php';
    
    $stmtUser = $pdo->prepare("SELECT * FROM user_registrations WHERE registration_id = :reg_id");
    $stmtUser->execute([':reg_id' => $reg_id]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("User not found.");
    }

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Payment Info - <?php echo htmlspecialchars($reg_id); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-purple: #1d0a3f;
            --accent-gold: #C9A227;
            --bg-light: #FDFBF7;
            --text-dark: #2c2c2c;
            --white: #ffffff;
        }
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: var(--bg-light); 
            margin: 0; 
            color: var(--text-dark);
        }
        .container {
            padding: 40px;
            max-width: 800px;
            margin: 20px auto 40px auto;
        }
        .card {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            border-top: 4px solid var(--accent-gold);
            padding: 30px;
        }
        h1, h2 { font-family: 'Outfit', sans-serif; color: var(--primary-purple); }
        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .summary-item {
            background: #f8fafc;
            padding: 15px 20px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .summary-label { font-size: 0.85rem; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 5px; }
        .summary-value { font-size: 1.1rem; font-weight: 500; color: var(--primary-purple); word-break: break-all; }
        .btn-back {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            background-color: #e2e8f0;
            color: #475569;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .notice {
            background: #e0f2fe;
            border-left: 4px solid #0284c7;
            padding: 15px;
            margin-top: 20px;
            border-radius: 4px;
            color: #0369a1;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="btn-back">← Back to Dashboard</a>
        <div class="card">
            <h1>User Payment Info</h1>
            <?php if (isset($error)): ?>
                <div style="color: red;"><?php echo htmlspecialchars($error); ?></div>
            <?php else: ?>
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-label">Name</div>
                        <div class="summary-value"><?php echo htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Registration ID</div>
                        <div class="summary-value"><?php echo htmlspecialchars($user['registration_id']); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Current Status</div>
                        <div class="summary-value"><?php echo htmlspecialchars($user['payment_status']); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Vortex Transaction ID</div>
                        <div class="summary-value"><?php echo htmlspecialchars($user['transaction_id'] ?: 'N/A'); ?></div>
                    </div>
                </div>

                <div class="notice">
                    <strong>Note:</strong> Detailed transaction logs and attempts are securely managed by the Vortex Payment Gateway. To view the full history or process refunds for this transaction, please log into the <a href="../vortex/admin/dashboard.php" style="color: #0284c7; font-weight: bold;">Vortex Admin Dashboard</a>.
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
