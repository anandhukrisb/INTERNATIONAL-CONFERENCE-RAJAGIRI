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
    
<<<<<<< HEAD
    
=======
>>>>>>> 6724e6266f8bcb714445e130d556e785792d7e0d
    $stmtUser = $pdo->prepare("SELECT * FROM user_registrations WHERE registration_id = :reg_id");
    $stmtUser->execute([':reg_id' => $reg_id]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("User not found.");
    }

<<<<<<< HEAD
    
    $query = "
        SELECT 
            po.razorpay_order_id,
            pa.razorpay_payment_id,
            pa.status as attempt_status,
            pa.error_description,
            COALESCE(pa.created_at, po.created_at) as event_date
        FROM payment_orders po
        LEFT JOIN payment_attempts pa ON po.razorpay_order_id = pa.razorpay_order_id
        WHERE po.registration_id = :reg_id
        ORDER BY event_date DESC
    ";
    
    $stmtHistory = $pdo->prepare($query);
    $stmtHistory->execute([':reg_id' => $reg_id]);
    $historyRaw = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);
    
    
    $uniqueHistory = [];
    $totalAttempts = 0;
    
    foreach ($historyRaw as $row) {
        if (!empty($row['attempt_status'])) {
            $totalAttempts++;
        }
        
        $pid = $row['razorpay_payment_id'] ?? '';
        $oid = $row['razorpay_order_id'];
        
        
        
        
        $uniqueKey = $pid ? $pid : $oid;
        
        if (!isset($uniqueHistory[$uniqueKey])) {
            $uniqueHistory[$uniqueKey] = $row;
        } else {
            
            $currentStatus = strtolower($uniqueHistory[$uniqueKey]['attempt_status'] ?? '');
            $newStatus = strtolower($row['attempt_status'] ?? '');
            
            $isNewSuccessful = in_array($newStatus, ['captured', 'authorized', 'paid']);
            $isCurrentSuccessful = in_array($currentStatus, ['captured', 'authorized', 'paid']);
            
            if ($isNewSuccessful && !$isCurrentSuccessful) {
                $uniqueHistory[$uniqueKey] = $row;
            }
        }
    }
    
    
    $history = array_values($uniqueHistory);
    usort($history, function($a, $b) {
        return strtotime($b['event_date']) - strtotime($a['event_date']);
    });

=======
>>>>>>> 6724e6266f8bcb714445e130d556e785792d7e0d
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<<<<<<< HEAD
    <title>User History - <?php echo htmlspecialchars($reg_id); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">

    <script src="../footer.js" defer></script>
=======
    <title>User Payment Info - <?php echo htmlspecialchars($reg_id); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
>>>>>>> 6724e6266f8bcb714445e130d556e785792d7e0d
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
<<<<<<< HEAD
            max-width: 1200px;
            margin: 20px auto 40px auto;
            min-height: calc(100vh - 440px);
        }
        h1 { 
            font-family: 'Outfit', sans-serif; 
            color: var(--primary-purple); 
            margin-top: 0;
            font-size: 2.2rem;
        }
        h2 {
            font-family: 'Outfit', sans-serif; 
            color: var(--primary-purple); 
            font-size: 1.5rem;
            margin-top: 30px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
=======
            max-width: 800px;
            margin: 20px auto 40px auto;
>>>>>>> 6724e6266f8bcb714445e130d556e785792d7e0d
        }
        .card {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            border-top: 4px solid var(--accent-gold);
            padding: 30px;
<<<<<<< HEAD
            margin-bottom: 30px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
=======
        }
        h1, h2 { font-family: 'Outfit', sans-serif; color: var(--primary-purple); }
        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
>>>>>>> 6724e6266f8bcb714445e130d556e785792d7e0d
        }
        .summary-item {
            background: #f8fafc;
            padding: 15px 20px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
<<<<<<< HEAD
        .summary-label {
            font-size: 0.8rem;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.05em;
            margin-bottom: 5px;
        }
        .summary-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-purple);
        }
        .table-responsive {
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
            white-space: nowrap;
        }
        th, td {
            padding: 16px 24px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.05em;
        }
        tbody tr:nth-child(even) {
            background-color: #fafbfc;
        }
        tbody tr:hover {
            background-color: #f1f5f9;
        }
        tr:last-child td {
            border-bottom: none;
        }
        .status-captured { color: #15803d; font-weight: 600; }
        .status-failed { color: #b91c1c; font-weight: 600; }
        .status-created { color: #c2410c; font-weight: 600; }
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-completed { background-color: #dcfce7; color: #166534; }
        .badge-pending { background-color: #ffedd5; color: #9a3412; }
        .badge-failed { background-color: #fee2e2; color: #991b1b; }
        
=======
        .summary-label { font-size: 0.85rem; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 5px; }
        .summary-value { font-size: 1.1rem; font-weight: 500; color: var(--primary-purple); word-break: break-all; }
>>>>>>> 6724e6266f8bcb714445e130d556e785792d7e0d
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
<<<<<<< HEAD
            transition: background-color 0.2s;
        }
        .btn-back:hover {
            background-color: #cbd5e1;
        }
        .btn-back svg {
            margin-right: 8px;
        }
        @keyframes spin { 100% { transform: rotate(360deg); } }
    </style>
    <script>
        const spinnerSvg = '<svg style="animation: spin 1s linear infinite; margin-right: 8px; width: 18px; height: 18px; display: inline-block; vertical-align: text-bottom;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" style="opacity: 0.25;"><\/circle><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" style="opacity: 0.75;"><\/path><\/svg>';
        
        function showLinkLoading(link, text) {
            if (!link.hasAttribute('data-original-text')) {
                link.setAttribute('data-original-text', link.innerHTML);
            }
            link.style.pointerEvents = 'none';
            link.innerHTML = spinnerSvg + text;
        }

        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                document.querySelectorAll('[data-original-text]').forEach(function(el) {
                    el.innerHTML = el.getAttribute('data-original-text');
                    el.style.pointerEvents = 'auto';
                });
            }
        });
    </script>
</head>
<body>

    <div class="container">
        <a href="dashboard.php" onclick="showLinkLoading(this, 'Returning...')" class="btn-back">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Dashboard
        </a>

        <div class="card">
            <h1>User Payment History</h1>
            
            <?php if (isset($error)): ?>
                <div style="padding: 20px; color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; margin-bottom: 20px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php else: ?>
                
                <h2>User Summary</h2>
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-label">Name</div>
                        <div class="summary-value"><?php echo htmlspecialchars(trim($user['first_name'] . ' ' . (!empty($user['middle_name']) ? $user['middle_name'] . ' ' : '') . $user['last_name'])); ?></div>
=======
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
>>>>>>> 6724e6266f8bcb714445e130d556e785792d7e0d
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Registration ID</div>
                        <div class="summary-value"><?php echo htmlspecialchars($user['registration_id']); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Current Status</div>
<<<<<<< HEAD
                        <div class="summary-value">
                            <?php 
                                $statusClass = 'badge-pending';
                                if ($user['payment_status'] === 'Completed') $statusClass = 'badge-completed';
                                elseif ($user['payment_status'] === 'Failed') $statusClass = 'badge-failed';
                            ?>
                            <span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($user['payment_status']); ?></span>
                        </div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Total Attempts</div>
                        <div class="summary-value"><?php echo $totalAttempts; ?></div>
                    </div>
                </div>

                <h2>Detailed Transaction History Table</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Order ID</th>
                                <th>Payment ID</th>
                                <th>Action / Status</th>
                                <th>Error Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($history) > 0): ?>
                                <?php foreach ($history as $row): ?>
                                    <tr>
                                        <td><?php echo date('M d, g:i A', strtotime($row['event_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['razorpay_order_id']); ?></td>
                                        <td><?php echo htmlspecialchars($row['razorpay_payment_id'] ?? '-'); ?></td>
                                        <td>
                                            <?php 
                                                $displayStatus = '';
                                                $statusClass = '';
                                                
                                                if (in_array(strtolower($row['attempt_status'] ?? ''), ['captured', 'authorized', 'paid'])) {
                                                    $displayStatus = '✅ Successful';
                                                    $statusClass = 'status-captured';
                                                } elseif ($row['attempt_status'] === 'failed') {
                                                    
                                                    if (stripos($row['error_description'], 'cancel') !== false) {
                                                        $displayStatus = '❌ Cancelled';
                                                        $statusClass = 'status-failed';
                                                    } else {
                                                        $displayStatus = '❌ Failed';
                                                        $statusClass = 'status-failed';
                                                    }
                                                } elseif (empty($row['attempt_status'])) {
                                                    $displayStatus = '⏳ Initiated';
                                                    $statusClass = 'status-created';
                                                } else {
                                                    $displayStatus = '⏳ Pending'; 
                                                    $statusClass = 'status-created';
                                                }
                                            ?>
                                            <span class="<?php echo $statusClass; ?>"><?php echo $displayStatus; ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['error_description'] ?: 'None'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #64748b; padding: 30px;">No transaction history found for this user.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>
        </div>
    </div>
    
    <main-footer base-path="../"></main-footer>
=======
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
>>>>>>> 6724e6266f8bcb714445e130d556e785792d7e0d
</body>
</html>
