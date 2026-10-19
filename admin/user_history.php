<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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

    $currencySymbol = '₹';
    if (!empty($user['currency'])) {
        $c = strtoupper(trim($user['currency']));
        if ($c === 'USD' || $c === '$') {
            $currencySymbol = '$';
        } elseif ($c === 'INR' || $c === '₹') {
            $currencySymbol = '₹';
        }
    } else {
        $countryCat = strtolower($user['country_category'] ?? '');
        $country = strtolower($user['country'] ?? '');
        $pkg = strtolower($user['package'] ?? '');

        if (strpos($pkg, 'usd') !== false || strpos($countryCat, 'international') !== false || strpos($countryCat, 'foreign') !== false) {
            $currencySymbol = '$';
        } elseif (strpos($countryCat, 'india') !== false || $countryCat === 'national' || strpos($country, 'india') !== false) {
            $currencySymbol = '₹';
        } else {
            $currencySymbol = '$';
        }
    }

    $timeline = [];
    if (!empty($user['transaction_id'])) {
        $stmtTl = $pdo->prepare("
            SELECT * FROM transaction_timeline 
            WHERE vortex_transaction_id = :vtx_id 
            ORDER BY created_at DESC
        ");
        $stmtTl->execute([':vtx_id' => $user['transaction_id']]);
        $timeline = $stmtTl->fetchAll(PDO::FETCH_ASSOC);
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
    <title>User Payment History - <?php echo htmlspecialchars($reg_id); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
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
            max-width: 1100px;
            margin: 20px auto 40px auto;
            min-height: calc(100vh - 440px);
        }
        h1 { 
            font-family: 'Outfit', sans-serif; 
            color: var(--primary-purple); 
            margin-top: 0;
            font-size: 2rem;
        }
        h2 {
            font-family: 'Outfit', sans-serif; 
            color: var(--primary-purple); 
            font-size: 1.35rem;
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
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--primary-purple);
            word-break: break-all;
        }
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
        .table-responsive {
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-top: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.92rem;
            white-space: nowrap;
        }
        th, td {
            padding: 14px 20px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.78rem;
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
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-completed, .badge-success { background-color: #dcfce7; color: #166534; }
        .badge-pending { background-color: #ffedd5; color: #9a3412; }
        .badge-failed { background-color: #fee2e2; color: #991b1b; }
        .badge-refunded { background-color: #f3e8ff; color: #6b21a8; }
        
        .notice {
            background: #e0f2fe;
            border-left: 4px solid #0284c7;
            padding: 15px 18px;
            margin-top: 25px;
            border-radius: 4px;
            color: #0369a1;
            font-size: 0.92rem;
            line-height: 1.5;
        }
        .notice a {
            color: #0284c7;
            font-weight: 700;
            text-decoration: underline;
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
                <div style="padding: 16px 20px; color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; margin-bottom: 20px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php else: ?>
                
                <h2>Participant Information</h2>
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-label">Name</div>
                        <div class="summary-value"><?php echo htmlspecialchars(trim($user['first_name'] . ' ' . (!empty($user['middle_name']) ? $user['middle_name'] . ' ' : '') . $user['last_name'])); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Registration ID</div>
                        <div class="summary-value"><?php echo htmlspecialchars($user['registration_id']); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Contact</div>
                        <div class="summary-value" style="font-size: 0.95rem;">
                            <div><?php echo htmlspecialchars($user['email']); ?></div>
                            <div style="color: #64748b; font-size: 0.85rem; margin-top: 2px;"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></div>
                        </div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Package & Amount</div>
                        <div class="summary-value">
                            <?php echo $currencySymbol . number_format($user['base_amount'], 2); ?>
                            <div style="color: #64748b; font-size: 0.85rem; font-weight: 500; margin-top: 2px;"><?php echo htmlspecialchars($user['package']); ?></div>
                        </div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Current Status</div>
                        <div class="summary-value">
                            <?php 
                                $status = $user['payment_status'];
                                $statusClass = 'badge-pending';
                                if ($status === 'Completed') $statusClass = 'badge-completed';
                                elseif ($status === 'Failed') $statusClass = 'badge-failed';
                                elseif ($status === 'Refund Approved' || $status === 'Refunded') $statusClass = 'badge-refunded';
                            ?>
                            <span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                        </div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Vortex Transaction ID</div>
                        <div class="summary-value"><?php echo htmlspecialchars($user['transaction_id'] ?: 'N/A'); ?></div>
                    </div>
                </div>

                <h2>Transaction Timeline & Events</h2>
                <?php if (!empty($timeline)): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Event</th>
                                    <th>Status</th>
                                    <th>Order / Payment ID</th>
                                    <th>Details / Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($timeline as $row): ?>
                                    <?php
                                        $rowStatus = strtoupper($row['status'] ?? '');
                                        $rowBadgeClass = 'badge-pending';
                                        if ($rowStatus === 'SUCCESS' || $rowStatus === 'PAID' || $rowStatus === 'CAPTURED') $rowBadgeClass = 'badge-success';
                                        elseif ($rowStatus === 'FAILED' || $rowStatus === 'CANCELLED') $rowBadgeClass = 'badge-failed';
                                        elseif (strpos($rowStatus, 'REFUND') !== false) $rowBadgeClass = 'badge-refunded';
                                    ?>
                                    <tr>
                                        <td><?php echo date('M d, Y g:i A', strtotime($row['created_at'])); ?></td>
                                        <td><strong><?php echo htmlspecialchars(str_replace('_', ' ', $row['event_type'] ?? '')); ?></strong></td>
                                        <td><span class="badge <?php echo $rowBadgeClass; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                        <td>
                                            <?php if (!empty($row['payment_id'])): ?>
                                                <div><code><?php echo htmlspecialchars($row['payment_id']); ?></code></div>
                                            <?php endif; ?>
                                            <?php if (!empty($row['order_id'])): ?>
                                                <div style="color: #64748b; font-size: 0.85em;"><code><?php echo htmlspecialchars($row['order_id']); ?></code></div>
                                            <?php endif; ?>
                                            <?php if (empty($row['payment_id']) && empty($row['order_id'])): ?>
                                                <span style="color: #94a3b8;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['reason'] ?: 'None'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="color: #64748b; font-size: 0.95rem; margin-top: 10px;">No transaction timeline events logged yet for this registration.</p>
                <?php endif; ?>

                <div class="notice">
                    <strong>Note:</strong> Detailed gateway transaction logs, webhook audits, and security metrics are securely managed by the Vortex Payment Gateway. To view the full system audit or process refunds, please log into the <a href="../vortex/admin/dashboard.php">Vortex Admin Dashboard</a>.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <main-footer base-path="../"></main-footer>
</body>
</html>
