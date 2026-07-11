<?php
$error = '';
$user_details = null;

try {
    require_once __DIR__ . '/backend/db.php';
} catch (Exception $e) {
    $error = "We are currently experiencing database issues. Please try again later.";
}

$registration_id = $_REQUEST['registration_id'] ?? '';
$dob = $_REQUEST['dob'] ?? '';

if (!empty($registration_id) && !empty($dob) && empty($error)) {

    if (!empty($registration_id) && !empty($dob)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM user_registrations WHERE registration_id = :registration_id AND date_of_birth = :dob LIMIT 1");
            $stmt->execute([':registration_id' => $registration_id, ':dob' => $dob]);
            $user_details = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user_details) {
                $error = "No registration found with the provided Registration ID and Date of Birth.";
            } else {
                $stmtHistory = $pdo->prepare("
                    SELECT 
                        po.razorpay_order_id,
                        pa.razorpay_payment_id,
                        pa.status as attempt_status,
                        po.status as order_status,
                        pa.error_description,
                        MAX(COALESCE(pa.created_at, po.created_at)) as event_time
                    FROM payment_orders po
                    LEFT JOIN payment_attempts pa ON po.razorpay_order_id = pa.razorpay_order_id
                    WHERE po.registration_id = :reg_id
                    GROUP BY 
                        po.razorpay_order_id,
                        pa.razorpay_payment_id,
                        pa.status,
                        po.status,
                        pa.error_description
                    ORDER BY event_time DESC
                ");
                $stmtHistory->execute([':reg_id' => $user_details['registration_id']]);
                $history_records = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            $error = "An error occurred while fetching your details. Please try again later.";
        }
    } else {
        $error = "Please provide both Registration ID and Date of Birth.";
    }
}

function renderHistoryItem($item) {
    $statusText = 'Initiated';
    $statusClass = 'h-status-created';
    
    if ($item['attempt_status'] === 'captured' || $item['order_status'] === 'paid') {
        $statusText = 'Completed';
        $statusClass = 'h-status-captured';
    } elseif ($item['attempt_status'] === 'failed' || $item['order_status'] === 'failed') {
        $statusText = 'Failed';
        $statusClass = 'h-status-failed';
    }

    $time = date('M d, Y h:i A', strtotime($item['event_time']));
    $paymentIdText = $item['razorpay_payment_id'] ? " | Ref No: " . htmlspecialchars($item['razorpay_payment_id']) : "";
    $errorText = $item['error_description'] ? "<div style='color:#C53030; font-size: 0.85rem; margin-top: 5px;'>" . htmlspecialchars($item['error_description']) . "</div>" : "";

    return "
    <div class=\"history-item\">
        <div class=\"history-time\">{$time}</div>
        <div class=\"history-details\">
            <strong>Transaction Ref:</strong> " . htmlspecialchars($item['razorpay_order_id']) . "{$paymentIdText}
            {$errorText}
        </div>
        <div class=\"history-status {$statusClass}\">{$statusText}</div>
    </div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Transaction - ICSWHMH 2027</title>
    <link rel="icon" type="image/x-icon" href="https://res.cloudinary.com/dswfp5fwx/image/upload/v1778131826/Favicon-192_hdltam.ico">
    <script src="navbar.js" defer></script>
    <script src="footer.js" defer></script>
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        .container { max-width: 1000px; margin: 150px auto 60px auto; padding: 20px; }
        .form-card { background: #fff; padding: 40px; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); border-top: 5px solid var(--accent-gold); }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-family: 'Outfit', sans-serif; font-weight: 600; margin-bottom: 8px; color: var(--primary-purple); }
        .form-control { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 1rem; box-sizing: border-box; transition: all 0.3s ease; }
        .form-control:focus { outline: none; border-color: var(--primary-purple); box-shadow: 0 0 0 3px rgba(66, 32, 114, 0.1); }
        .error-msg { color: #C53030; background: #FFF5F5; border: 1px solid #FEB2B2; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        
        
        .status-header { display: flex; align-items: center; justify-content: space-between; padding-bottom: 25px; margin-bottom: 25px; border-bottom: 1px solid #eee; flex-wrap: wrap; gap: 15px; }
        .status-badge { display: inline-block; padding: 8px 16px; border-radius: 50px; font-weight: 700; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }
        .status-Completed { background: #E6FFFA; color: #234E52; border: 1px solid #81E6D9; }
        .status-Failed { background: #FFF5F5; color: #742A2A; border: 1px solid #FEB2B2; }
        .status-Pending { background: #FFFAF0; color: #7B341E; border: 1px solid #FBD38D; }
        
        .amount-display { text-align: right; }
        .amount-display .label { font-size: 0.85rem; color: var(--text-light); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 5px; }
        .amount-display .value { font-size: 2rem; font-family: 'Outfit', sans-serif; font-weight: 800; color: var(--primary-purple); line-height: 1; }
        
        .info-section { margin-bottom: 30px; }
        .info-section-title { font-family: 'Outfit', sans-serif; font-size: 1.2rem; color: var(--primary-purple); margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        .info-section-title svg { color: var(--accent-gold); }
        
        .details-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .detail-item { padding: 18px; background: #f8f9fa; border-radius: 10px; border-left: 4px solid var(--primary-purple); transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .detail-item:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .detail-label { font-size: 0.8rem; color: #6c757d; text-transform: uppercase; font-weight: 700; letter-spacing: 0.8px; margin-bottom: 6px; }
        .detail-value { font-size: 1.05rem; color: #212529; font-weight: 600; word-break: break-word; }

        
        .history-container { background: #f8f9fa; border-radius: 10px; border-left: 4px solid var(--primary-purple); overflow: hidden; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .history-item { padding: 15px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .history-item:last-child { border-bottom: none; }
        .history-time { font-size: 0.85rem; color: #64748b; font-weight: 600; min-width: 150px; }
        .history-details { flex: 1; min-width: 200px; font-size: 0.95rem; color: var(--text-dark); }
        .history-status { font-size: 0.8rem; font-weight: 700; padding: 6px 12px; border-radius: 50px; text-transform: uppercase; letter-spacing: 0.5px; }
        .h-status-captured { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .h-status-failed { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .h-status-created { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
        .history-drawer { max-height: 0; overflow: hidden; transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .history-drawer.open { max-height: 1000px; transition: max-height 0.6s ease-in-out; }
        .btn-toggle-history { display: block; width: 100%; background: #e2e8f0; border: none; padding: 12px; text-align: center; font-size: 0.9rem; font-weight: 600; color: var(--primary-purple); cursor: pointer; transition: background 0.2s ease, color 0.2s ease; }
        .btn-toggle-history:hover { background: #cbd5e1; }
        
        @media (max-width: 600px) { 
            .status-header { flex-direction: column; align-items: flex-start; }
            .amount-display { text-align: left; margin-top: 10px; }
            .history-item { flex-direction: column; align-items: flex-start; }
            .history-time { margin-bottom: 5px; }
        }
    </style>
</head>
<body>
    <floating-navbar></floating-navbar>
    
    <div class="container">
        <h2 class="page-title" style="text-align: center; display: block; font-size: 2.5rem;">Check Status</h2>
        
        <?php if ($error): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!$user_details): ?>
            <div class="form-card">
                <p style="margin-bottom: 20px; color: var(--text-light);">Enter your Registration ID and Date of Birth to view your registration and transaction details.</p>
                <div style="background-color: #EBF8FF; border-left: 4px solid #3182CE; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                    <p style="margin: 0; color: #2B6CB0; font-size: 0.9rem;">
                        <strong style="display: flex; align-items: center; gap: 8px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/></svg>
                            Need your Registration ID?
                        </strong>
                    </p>
                    <p style="margin: 5px 0 0 0; color: #2B6CB0; font-size: 0.85rem;">
                        Your Registration ID was sent to your email address when you first signed up. Please check your inbox (or spam folder) to find it.
                    </p>
                </div>
                <form method="GET" action="">
                    <div class="form-group">
                        <label for="registration_id" class="form-label">Registration ID</label>
                        <input type="text" id="registration_id" name="registration_id" class="form-control" required placeholder="ICSW270101-A1B2" value="<?php echo htmlspecialchars($_REQUEST['registration_id'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="dob" class="form-label">Date of Birth</label>
                        <input type="date" id="dob" name="dob" class="form-control" required value="<?php echo htmlspecialchars($_REQUEST['dob'] ?? ''); ?>">
                    </div>
                    <button type="submit" class="btn-action" style="width: 100%; border: none; cursor: pointer;" onclick="if(this.form.checkValidity()){this.disabled=true; this.innerHTML=spinnerSvg + ' Fetching Details...'; this.form.submit();}">View Details</button>
                </form>
            </div>
        <?php else: ?>
            <div class="form-card" style="padding: 0; overflow: hidden;">
                
                <div style="padding: 30px; background: linear-gradient(to right, rgba(66, 32, 114, 0.05), rgba(212, 175, 55, 0.05)); border-bottom: 1px solid #eee;">
                    <div class="status-header" style="margin: 0; padding: 0; border: none;">
                        <div>
                            <div style="font-size: 0.9rem; color: var(--text-light); text-transform: uppercase; font-weight: 600; letter-spacing: 1px; margin-bottom: 8px;">Registration Status</div>
                            <?php 
                                $status = $user_details['payment_status'];
                                $statusClass = 'status-Pending';
                                if ($status === 'Completed') $statusClass = 'status-Completed';
                                if ($status === 'Failed') $statusClass = 'status-Failed';
                            ?>
                            <div class="status-badge <?php echo $statusClass; ?>">
                                <?php echo htmlspecialchars($status); ?>
                            </div>
                            <div style="margin-top: 12px; font-size: 0.95rem; color: #475569;">
                                <strong>Reg ID:</strong> <span style="font-family: monospace; font-size: 1.05rem;"><?php echo htmlspecialchars($user_details['registration_id']); ?></span>
                            </div>
                        </div>
                        <div class="amount-display">
                            <div class="label"><?php echo $status === 'Completed' ? 'Amount Paid' : 'Amount Due'; ?></div>
                            <?php
                                $isIndian = (strpos(strtolower($user_details['country_category']), 'india') !== false || strtolower($user_details['country_category']) === 'national');
                                $currencySymbol = $isIndian ? '₹' : '$';
                            ?>
                            <div class="value" style="margin-bottom: 10px;"><?php echo $currencySymbol . htmlspecialchars(number_format($user_details['base_amount'], 2)); ?></div>
                            
                            <?php if ($status !== 'Completed'): ?>
                            <form method="GET" action="process_payment.php" style="margin: 0 0 10px 0; display: flex; justify-content: flex-end;">
                                <input type="hidden" name="reg_id" value="<?php echo htmlspecialchars($user_details['registration_id']); ?>">
                                <button type="submit" class="btn-action" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 20px; font-size: 0.9rem; border-radius: 50px; text-decoration: none; border: none; cursor: pointer; margin: 0;" onclick="this.disabled=true; this.innerHTML=spinnerSvg + ' Redirecting...'; this.form.submit();">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                                    Pay Again
                                </button>
                            </form>
                            <?php endif; ?>

                            <div style="font-size: 0.85rem; color: #64748b; font-family: monospace; background: #f1f5f9; padding: 6px 10px; border-radius: 6px; display: inline-block;">
                                TXN: <?php echo htmlspecialchars($user_details['transaction_id'] ?: 'Pending'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="padding: 30px;">
                    
                    <?php if (!empty($history_records)): ?>
                    <div class="info-section">
                        <h4 class="info-section-title">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            Transaction History
                        </h4>
                        
                        <div class="history-container">
                            <?php 
                                
                                echo renderHistoryItem($history_records[0]); 
                            ?>
                            
                            <?php if (count($history_records) > 1): ?>
                                <div id="historyDrawer" class="history-drawer">
                                    <?php 
                                        for ($i = 1; $i < count($history_records); $i++) {
                                            echo renderHistoryItem($history_records[$i]);
                                        }
                                    ?>
                                </div>
                                <button type="button" class="btn-toggle-history" onclick="toggleHistory(this)">
                                    Show Full History ▾
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    
                    <div class="info-section">
                        <h4 class="info-section-title">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            Personal Information
                        </h4>
                        <div class="details-grid">
                            <div class="detail-item">
                                <div class="detail-label">Full Name</div>
                                <div class="detail-value"><?php echo htmlspecialchars(trim($user_details['first_name'] . ' ' . $user_details['middle_name'] . ' ' . $user_details['last_name'])); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Email Address</div>
                                <div class="detail-value"><?php echo htmlspecialchars($user_details['email']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Phone Number</div>
                                <div class="detail-value"><?php echo htmlspecialchars($user_details['phone']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Organization</div>
                                <div class="detail-value"><?php echo htmlspecialchars($user_details['organization']); ?></div>
                            </div>
                        </div>
                    </div>

                    
                    <div class="info-section">
                        <h4 class="info-section-title">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            Conference Details
                        </h4>
                        <div class="details-grid">
                            <div class="detail-item" style="grid-column: 1 / -1; border-left-color: var(--accent-gold); background: #fffdf5;">
                                <div class="detail-label">Selected Package</div>
                                <div class="detail-value" style="font-size: 1.15rem; color: var(--primary-purple);"><?php echo htmlspecialchars($user_details['package']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Participant Category</div>
                                <div class="detail-value"><?php echo htmlspecialchars($user_details['participant_category']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Country</div>
                                <div class="detail-value"><?php echo htmlspecialchars($user_details['country']); ?></div>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 40px; text-align: center; display: flex; justify-content: center; align-items: center;">
                        <a href="view_transaction.php" class="btn-outline-action" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 28px; border-radius: 50px; text-decoration: none; margin: 0;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                            Check Another Registration
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <main-footer></main-footer>

    <script>
    const spinnerSvg = '<svg style="animation: spin 1s linear infinite; margin-right: 8px; width: 18px; height: 18px; display: inline-block; vertical-align: text-bottom;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" style="opacity: 0.25;"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" style="opacity: 0.75;"></path></svg>';

    
    window.addEventListener("pageshow", function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });

    function toggleHistory(btn) {
        const drawer = document.getElementById('historyDrawer');
        if (drawer.classList.contains('open')) {
            drawer.classList.remove('open');
            btn.innerText = 'Show Full History ▾';
        } else {
            drawer.classList.add('open');
            btn.innerText = 'Hide History ▴';
        }
    }
    </script>
</body>
</html>
