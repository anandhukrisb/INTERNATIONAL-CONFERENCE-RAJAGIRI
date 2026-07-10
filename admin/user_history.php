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
    
    // Fetch user details
    $stmtUser = $pdo->prepare("SELECT * FROM user_registrations WHERE registration_id = :reg_id");
    $stmtUser->execute([':reg_id' => $reg_id]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("User not found.");
    }

    // Fetch transaction history
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
    
    // Deduplicate history by order_id and calculate total attempts
    $uniqueHistory = [];
    $totalAttempts = 0;
    
    foreach ($historyRaw as $row) {
        if (!empty($row['attempt_status'])) {
            $totalAttempts++;
        }
        
        $pid = $row['razorpay_payment_id'] ?? '';
        $oid = $row['razorpay_order_id'];
        
        // Group by Payment ID if it exists, otherwise fall back to Order ID.
        // This ensures distinct payment attempts (failures vs successes) on the same order are shown,
        // but webhook duplicates for the exact same payment ID are merged.
        $uniqueKey = $pid ? $pid : $oid;
        
        if (!isset($uniqueHistory[$uniqueKey])) {
            $uniqueHistory[$uniqueKey] = $row;
        } else {
            // Prefer successful statuses over failed/pending ones when deduplicating
            $currentStatus = strtolower($uniqueHistory[$uniqueKey]['attempt_status'] ?? '');
            $newStatus = strtolower($row['attempt_status'] ?? '');
            
            $isNewSuccessful = in_array($newStatus, ['captured', 'authorized', 'paid']);
            $isCurrentSuccessful = in_array($currentStatus, ['captured', 'authorized', 'paid']);
            
            if ($isNewSuccessful && !$isCurrentSuccessful) {
                $uniqueHistory[$uniqueKey] = $row;
            }
        }
    }
    
    // Re-index and sort by date descending
    $history = array_values($uniqueHistory);
    usort($history, function($a, $b) {
        return strtotime($b['event_date']) - strtotime($a['event_date']);
    });

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User History - <?php echo htmlspecialchars($reg_id); ?></title>
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
            font-family: 'Inter', sans-serif; 
            background-color: var(--bg-light); 
            margin: 0; 
            color: var(--text-dark);
        }
        .container {
            padding: 40px;
            max-width: 1200px;
            margin: 120px auto 40px auto;
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
        }
        .card {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            border-top: 4px solid var(--accent-gold);
            padding: 30px;
            margin-bottom: 30px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .summary-item {
            background: #f8fafc;
            padding: 15px 20px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
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
            transition: background-color 0.2s;
        }
        .btn-back:hover {
            background-color: #cbd5e1;
        }
        .btn-back svg {
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <floating-navbar base-path="../"></floating-navbar>

    <div class="container">
        <a href="dashboard.php" class="btn-back">
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
                        <div class="summary-value"><?php echo htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Registration ID</div>
                        <div class="summary-value"><?php echo htmlspecialchars($user['registration_id']); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Current Status</div>
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
                                                    // Check if it's a cancellation or just failed
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
                                                    $displayStatus = '⏳ Pending'; // Fallback for other non-final states
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
</body>
</html>
