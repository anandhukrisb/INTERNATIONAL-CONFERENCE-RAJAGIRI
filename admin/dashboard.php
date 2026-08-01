<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$search = trim($_GET['search'] ?? '');
$filter_status = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'date_desc';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    require_once __DIR__ . '/../backend/db.php';
    $query = "SELECT * FROM user_registrations WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $query .= " AND (email LIKE :search OR phone LIKE :search OR registration_id LIKE :search OR first_name LIKE :search OR last_name LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    if (!empty($filter_status)) {
        $query .= " AND payment_status = :status";
        $params[':status'] = $filter_status;
    }

    if (!empty($date_from)) {
        $query .= " AND DATE(created_at) >= :date_from";
        $params[':date_from'] = $date_from;
    }

    if (!empty($date_to)) {
        $query .= " AND DATE(created_at) <= :date_to";
        $params[':date_to'] = $date_to;
    }

    $countQuery = str_replace("SELECT *", "SELECT COUNT(*)", $query);
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $total_records = $countStmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    switch ($sort) {
        case 'date_asc':
            $query .= " ORDER BY created_at ASC";
            break;
        case 'name_asc':
            $query .= " ORDER BY first_name ASC, last_name ASC";
            break;
        case 'amount_desc':
            $query .= " ORDER BY base_amount DESC";
            break;
        case 'date_desc':
        default:
            $query .= " ORDER BY created_at DESC";
            break;
    }

    $is_export = (isset($_GET['export']) && $_GET['export'] === 'csv');
    if (!$is_export) {
        $query .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=registrations_' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        
        fputcsv($output, ['Registration ID', 'First Name', 'Middle Name', 'Last Name', 'Email', 'Organization', 'Phone', 'Participant Category', 'Country', 'Country Category', 'Package', 'Abstract Submitted', 'Base Amount', 'Currency', 'Payment Status', 'Transaction ID', 'Registration Date']);
        
        foreach ($registrations as $row) {
            $rowCurrency = 'INR';
            if (!empty($row['currency'])) {
                $c = strtoupper(trim($row['currency']));
                if ($c === 'USD' || $c === '$') {
                    $rowCurrency = 'USD';
                } elseif ($c === 'INR' || $c === '₹') {
                    $rowCurrency = 'INR';
                }
            } else {
                $countryCat = strtolower($row['country_category'] ?? '');
                $country = strtolower($row['country'] ?? '');
                $pkg = strtolower($row['package'] ?? '');

                if (strpos($pkg, 'usd') !== false || strpos($countryCat, 'international') !== false || strpos($countryCat, 'foreign') !== false) {
                    $rowCurrency = 'USD';
                } elseif (strpos($countryCat, 'india') !== false || $countryCat === 'national' || strpos($country, 'india') !== false) {
                    $rowCurrency = 'INR';
                } else {
                    $rowCurrency = 'USD';
                }
            }

            fputcsv($output, [
                $row['registration_id'],
                $row['first_name'],
                $row['middle_name'],
                $row['last_name'],
                $row['email'],
                $row['organization'],
                $row['phone'],
                $row['participant_category'],
                $row['country'],
                $row['country_category'],
                $row['package'],
                $row['abstract_submitted'],
                $row['base_amount'],
                $rowCurrency,
                $row['payment_status'],
                $row['transaction_id'],
                $row['created_at']
            ]);
        }
        fclose($output);
        exit;
    }

} catch (PDOException $e) {
    $registrations = [];
    $error = "Failed to load registrations.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ICSWHMH 2027</title>
    <link rel="icon" type="image/x-icon" href="https://res.cloudinary.com/dswfp5fwx/image/upload/v1778131826/Favicon-192_hdltam.ico">
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
            max-width: 1400px;
            margin: 20px auto 40px auto;
            min-height: calc(100vh - 440px);
        }
        h1 { 
            font-family: 'Outfit', sans-serif; 
            color: var(--primary-purple); 
            margin-top: 0;
            font-size: 2.2rem;
        }
        .card {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            border-top: 4px solid var(--accent-gold);
            overflow: hidden;
        }
        .table-responsive {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            white-space: nowrap;
        }
        th, td {
            padding: 16px 24px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #e2e8f0;
        }
        tbody tr:nth-child(even) {
            background-color: #fafbfc;
        }
        tr:last-child td {
            border-bottom: none;
        }
        tbody tr:hover {
            background-color: #f1f5f9;
        }
        .badge {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            display: inline-block;
        }
        .badge-pending {
            background-color: #fff7ed;
            color: #c2410c;
            border: 1px solid #fed7aa;
        }
        .badge-completed {
            background-color: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .badge-failed {
            background-color: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        .badge-refunded {
            background-color: #f3f4f6;
            color: #4b5563;
            border: 1px solid #d1d5db;
        }
        .amount-pending {
            color: #c2410c;
            font-weight: 600;
        }
        .amount-completed {
            color: #15803d;
            font-weight: 600;
        }
        .amount-failed {
            color: #b91c1c;
            font-weight: 600;
        }
        .filter-form {
            display: flex;
            gap: 15px;
            padding: 20px 25px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
            min-width: 180px;
        }
        .filter-group label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .filter-control {
            padding: 10px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            outline: none;
            background: white;
        }
        .filter-control:focus {
            border-color: var(--primary-purple);
            box-shadow: 0 0 0 3px rgba(29,10,63,0.1);
        }
        .btn-filter {
            background: var(--primary-purple);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            height: 40px;
            transition: opacity 0.2s;
            font-family: 'Inter', sans-serif;
        }
        .btn-filter:hover {
            opacity: 0.9;
        }
        .btn-clear {
            background: #e2e8f0;
            color: #475569;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-clear:hover {
            background: #cbd5e1;
            opacity: 1;
        }
        .btn-export {
            background: #10b981;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-left: auto; 
        }
        .btn-export:hover {
            background: #059669;
        }
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }
        .page-link {
            padding: 8px 16px;
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            color: var(--primary-purple);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .page-link:hover {
            background: #f1f5f9;
            border-color: var(--primary-purple);
        }
        .page-info {
            font-size: 0.95rem;
            color: #475569;
            font-weight: 500;
        }
        @keyframes spin { 100% { transform: rotate(360deg); } }
        @media (max-width: 600px) {
            .btn-export {
                margin-left: 0;
                width: 100%;
            }
        }
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

        function handleFormSubmit(event) {
            const btn = event.submitter;
            if (btn && btn.name !== 'export') {
                if (!btn.hasAttribute('data-original-text')) {
                    btn.setAttribute('data-original-text', btn.innerHTML);
                }
                btn.style.pointerEvents = 'none';
                btn.innerHTML = spinnerSvg + 'Searching...';
            } else if (btn && btn.name === 'export') {
                const originalText = btn.innerHTML;
                btn.style.pointerEvents = 'none';
                btn.innerHTML = spinnerSvg + 'Exporting...';
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.pointerEvents = 'auto';
                }, 2000);
            }
        }

        function approveRefund(regId, btn) {
            if (confirm('Are you sure you want to approve the refund for ' + regId + '?')) {
                const originalText = btn.innerHTML;
                btn.style.pointerEvents = 'none';
                btn.innerHTML = spinnerSvg + 'Approving...';
                
                fetch('process_refund.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ registration_id: regId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Refund approved successfully.');
                        window.location.reload();
                    } else {
                        alert('Error: ' + (data.error || 'Failed to approve refund'));
                        btn.innerHTML = originalText;
                        btn.style.pointerEvents = 'auto';
                    }
                })
                .catch(error => {
                    alert('An error occurred.');
                    btn.innerHTML = originalText;
                    btn.style.pointerEvents = 'auto';
                });
            }
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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>Dashboard</h1>
            <div>
                <span style="margin-right: 15px; font-weight: 500;">Welcome, <?php echo htmlspecialchars($_SESSION['admin_email']); ?></span>
                <a href="logout.php" onclick="showLinkLoading(this, 'Logging Out...')" style="background: var(--accent-gold); color: var(--primary-purple); font-weight: 600; padding: 10px 20px; border-radius: 6px; text-decoration: none; display: inline-block;">Log Out</a>
            </div>
        </div>
        <div class="card">
            
            <form method="GET" action="" class="filter-form" onsubmit="handleFormSubmit(event)">
                
                <div style="display: flex; gap: 20px; width: 100%; justify-content: space-between; flex-wrap: wrap;">
                    
                    
                    <div class="filter-group" style="flex: 2; min-width: 300px;">
                        <label for="search">Search</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" id="search" name="search" class="filter-control" style="flex: 1;" placeholder="Search name, email, ID... (Press Enter)" value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn-filter" style="height: 42px;">Search</button>
                        </div>
                    </div>

                    
                    <div style="display: flex; gap: 15px; flex-wrap: wrap; flex: 3; justify-content: flex-end;">
                        <div class="filter-group" style="min-width: 140px; flex: unset;">
                            <label for="date_from">Date From</label>
                            <input type="date" id="date_from" name="date_from" class="filter-control" value="<?php echo htmlspecialchars($date_from); ?>" onchange="this.form.submit()">
                        </div>
                        <div class="filter-group" style="min-width: 140px; flex: unset;">
                            <label for="date_to">Date To</label>
                            <input type="date" id="date_to" name="date_to" class="filter-control" value="<?php echo htmlspecialchars($date_to); ?>" onchange="this.form.submit()">
                        </div>
                        <div class="filter-group" style="min-width: 160px; flex: unset;">
                            <label for="status">Payment Status</label>
                            <select id="status" name="status" class="filter-control" onchange="this.form.submit()">
                                <option value="">All Statuses</option>
                                <option value="Completed" <?php if($filter_status === 'Completed') echo 'selected'; ?>>Completed</option>
                                <option value="Pending" <?php if($filter_status === 'Pending') echo 'selected'; ?>>Pending</option>
                                <option value="Failed" <?php if($filter_status === 'Failed') echo 'selected'; ?>>Failed</option>
                            </select>
                        </div>
                        <div class="filter-group" style="min-width: 220px; flex: unset;">
                            <label for="sort">Sort By</label>
                            <select id="sort" name="sort" class="filter-control" onchange="this.form.submit()">
                                <option value="date_desc" <?php if($sort === 'date_desc') echo 'selected'; ?>>Date (Newest First)</option>
                                <option value="date_asc" <?php if($sort === 'date_asc') echo 'selected'; ?>>Date (Oldest First)</option>
                                <option value="name_asc" <?php if($sort === 'name_asc') echo 'selected'; ?>>Name (A-Z)</option>
                                <option value="amount_desc" <?php if($sort === 'amount_desc') echo 'selected'; ?>>Amount (Highest First)</option>
                            </select>
                        </div>

                        <?php if(!empty($search) || !empty($filter_status) || !empty($date_from) || !empty($date_to) || $sort !== 'date_desc'): ?>
                            <a href="dashboard.php" onclick="showLinkLoading(this, 'Clearing...')" class="btn-filter btn-clear" style="height: 42px;">Clear</a>
                        <?php endif; ?>
                        
                        <button type="submit" name="export" value="csv" class="btn-filter btn-export" style="height: 42px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                            Export CSV
                        </button>
                    </div>
                </div>
            </form>

            <?php if (isset($error)): ?>
                <div style="padding: 30px; color: #b91c1c; background: #fef2f2; text-align: center; font-weight: 500;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table id="registrationsTable">
                    <thead>
                        <tr>
                            <th>Reg ID</th>
                            <th>Name</th>
                            <th>Email & Phone</th>
                            <th>Category</th>
                            <th>Package</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($registrations) > 0): ?>
                            <?php foreach ($registrations as $reg): ?>
                                <?php 
                                    $status = $reg['payment_status'];
                                    $badgeClass = 'badge-pending';
                                    $amountClass = 'amount-pending';
                                    if ($status === 'Completed') {
                                        $badgeClass = 'badge-completed';
                                        $amountClass = 'amount-completed';
                                    } elseif ($status === 'Failed') {
                                        $badgeClass = 'badge-failed';
                                        $amountClass = 'amount-failed';
                                    } elseif ($status === 'Refund Approved' || $status === 'Refunded') {
                                        $badgeClass = 'badge-refunded';
                                        $amountClass = 'amount-failed';
                                    }

                                    $currencySymbol = '₹';
                                    if (!empty($reg['currency'])) {
                                        $c = strtoupper(trim($reg['currency']));
                                        if ($c === 'USD' || $c === '$') {
                                            $currencySymbol = '$';
                                        } elseif ($c === 'INR' || $c === '₹') {
                                            $currencySymbol = '₹';
                                        }
                                    } else {
                                        $countryCat = strtolower($reg['country_category'] ?? '');
                                        $country = strtolower($reg['country'] ?? '');
                                        $pkg = strtolower($reg['package'] ?? '');

                                        if (strpos($pkg, 'usd') !== false || strpos($countryCat, 'international') !== false || strpos($countryCat, 'foreign') !== false) {
                                            $currencySymbol = '$';
                                        } elseif (strpos($countryCat, 'india') !== false || $countryCat === 'national' || strpos($country, 'india') !== false) {
                                            $currencySymbol = '₹';
                                        } else {
                                            $currencySymbol = '$';
                                        }
                                    }
                                ?>
                                <tr>
                                    <td><strong style="color: var(--primary-purple);"><?php echo htmlspecialchars($reg['registration_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars(trim($reg['first_name'] . ' ' . (!empty($reg['middle_name']) ? $reg['middle_name'] . ' ' : '') . $reg['last_name'])); ?></td>
                                    <td>
                                        <div style="font-size: 0.9em;"><?php echo htmlspecialchars($reg['email']); ?></div>
                                        <div style="font-size: 0.85em; color: #64748b; margin-top: 3px;"><?php echo htmlspecialchars($reg['phone']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($reg['participant_category']); ?></td>
                                    <td><?php echo htmlspecialchars($reg['package']); ?></td>
                                    <td class="<?php echo $amountClass; ?>"><?php echo $currencySymbol . number_format($reg['base_amount'], 2); ?></td>
                                    <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                                    <td style="color: #64748b; font-size: 0.85em;"><?php echo date('M d, Y', strtotime($reg['created_at'])); ?></td>
                                    <td>
                                        <div style="display: flex; gap: 8px;">
                                            <a href="user_history.php?reg_id=<?php echo urlencode($reg['registration_id']); ?>" onclick="showLinkLoading(this, 'Opening...')" style="display: inline-block; padding: 6px 12px; background-color: var(--primary-purple); color: white; text-decoration: none; border-radius: 4px; font-size: 0.8rem; font-weight: 600; white-space: nowrap;">View History</a>
                                            <?php if ($status === 'Completed'): ?>
                                                <button onclick="approveRefund('<?php echo htmlspecialchars($reg['registration_id']); ?>', this)" style="display: inline-block; padding: 6px 12px; background-color: #dc2626; color: white; text-decoration: none; border: none; border-radius: 4px; font-size: 0.8rem; font-weight: 600; cursor: pointer; white-space: nowrap;">Approve Refund</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: #64748b;">No registrations found yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (isset($total_pages) && $total_pages > 1): ?>
            <div class="pagination">
                <?php
                    $qsParams = $_GET;
                    unset($qsParams['page']);
                    $baseQs = http_build_query($qsParams);
                    $baseQs = $baseQs ? '&' . $baseQs : '';
                ?>
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo ($page - 1) . $baseQs; ?>" onclick="showLinkLoading(this, 'Loading...')" class="page-link">Previous</a>
                <?php endif; ?>
                
                <span class="page-info">Page <?php echo $page; ?> of <?php echo $total_pages; ?> (<?php echo $total_records; ?> total)</span>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo ($page + 1) . $baseQs; ?>" onclick="showLinkLoading(this, 'Loading...')" class="page-link">Next</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
    <main-footer base-path="../"></main-footer>
</body>
</html>
