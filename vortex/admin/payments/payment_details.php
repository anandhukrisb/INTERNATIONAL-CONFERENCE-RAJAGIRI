<?php
/**
 * ------------------------------------------------------------
 * payment_details.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 * Purpose : View comprehensive transaction details for admin.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Logger.php';

$txnParam = trim((string)($_GET['id'] ?? $_GET['txn_id'] ?? $_GET['vortex_transaction_id'] ?? ''));

$transaction = null;
$errorMessage = null;

if ($txnParam === '') {
    $errorMessage = 'No transaction identifier specified.';
} else {
    try {
        $db = Database::getInstance()->getConnection();

        // Secure prepared statement to fetch transaction details
        $query = "SELECT t.id,
                         t.vortex_transaction_id,
                         t.event_id,
                         t.api_client_id,
                         t.customer_email,
                         t.customer_mobile,
                         t.amount,
                         t.currency,
                         t.razorpay_order_id,
                         t.razorpay_payment_id,
                         t.razorpay_signature,
                         t.status,
                         t.created_at,
                         t.updated_at,
                         e.event_name,
                         c.client_name
                  FROM transactions t
                  LEFT JOIN events e ON t.event_id = e.event_id
                  LEFT JOIN api_clients c ON t.api_client_id = c.id
                  WHERE t.vortex_transaction_id = ?
                     OR t.id = ?
                  LIMIT 1";

        $stmt = $db->prepare($query);
        $numericId = is_numeric($txnParam) ? (int)$txnParam : 0;
        $stmt->execute([$txnParam, $numericId]);
        $transaction = $stmt->fetch();

        if (!$transaction) {
            $errorMessage = 'Transaction not found for identifier: ' . htmlspecialchars($txnParam);
            Logger::error("Admin payment details lookup failed for ID: " . $txnParam);
        }
    } catch (\PDOException $e) {
        Logger::error("Admin payment details database error: " . $e->getMessage());
        $errorMessage = 'A database error occurred while retrieving transaction details.';
    } catch (\Throwable $e) {
        Logger::error("Admin payment details unexpected error: " . $e->getMessage());
        $errorMessage = 'An unexpected error occurred while retrieving transaction details.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Details - Vortex Admin</title>
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --sidebar-bg: #0f172a;
            --sidebar-hover: #1e293b;
            --sidebar-text: #94a3b8;
            --bg-main: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--bg-main);
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styling */
        .sidebar {
            width: 260px;
            background-color: var(--sidebar-bg);
            color: var(--sidebar-text);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
        }

        .sidebar-brand {
            padding: 24px 20px;
            font-size: 1.25rem;
            font-weight: 700;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .sidebar-nav {
            padding: 20px 0;
            flex: 1;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 24px;
            color: var(--sidebar-text);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .sidebar-nav a:hover {
            background-color: var(--sidebar-hover);
            color: #ffffff;
        }

        .sidebar-nav a.active {
            background-color: var(--primary);
            color: #ffffff;
        }

        /* Main Container */
        .main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        header.top-bar {
            background-color: #ffffff;
            border-bottom: 1px solid var(--border-color);
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-title h1 {
            font-size: 1.3rem;
            font-weight: 700;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #ffffff;
            border: 1px solid var(--border-color);
            color: #334155;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.15s ease;
        }

        .btn-back:hover {
            background: #f1f5f9;
            color: var(--primary);
        }

        .content {
            padding: 32px;
            max-width: 1100px;
            margin: 0 auto;
            width: 100%;
        }

        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .card-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: #ffffff;
        }

        .card-header h2 {
            font-size: 1.15rem;
            font-weight: 600;
        }

        .card-body {
            padding: 24px;
        }

        /* Detail List */
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        @media (max-width: 768px) {
            .detail-grid {
                grid-template-columns: 1fr;
            }
            .sidebar {
                width: 70px;
            }
            .sidebar-brand span:first-child, .sidebar-nav span {
                display: none;
            }
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 12px;
        }

        .detail-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .detail-value {
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--text-main);
            word-break: break-all;
        }

        .mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.88rem;
            color: #1e293b;
            background-color: #f1f5f9;
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-success {
            background-color: #dcfce7;
            color: #166534;
        }

        .badge-failed {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .badge-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .alert-error {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-brand">
            <span>⚡ Vortex Admin</span>
        </div>
        <nav class="sidebar-nav">
            <a href="../dashboard.php">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                </svg>
                <span>Dashboard</span>
            </a>
            <a href="../dashboard.php#payments-section" class="active">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                </svg>
                <span>Payments</span>
            </a>
        </nav>
    </aside>

    <div class="main-wrapper">
        <header class="top-bar">
            <div class="header-title">
                <h1>Payment Transaction Details</h1>
            </div>
            <a href="../dashboard.php#payments-section" class="btn-back">
                ← Back to Dashboard
            </a>
        </header>

        <main class="content">
            <?php if ($errorMessage): ?>
                <div class="alert-error">
                    <strong>✕ Error:</strong> <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php elseif ($transaction): ?>
                <div class="card">
                    <div class="card-header">
                        <h2>Vortex Transaction #<?php echo (int)$transaction['id']; ?></h2>
                        <div>
                            <?php
                                $st = strtoupper((string)$transaction['status']);
                                if ($st === 'SUCCESS') {
                                    echo '<span class="badge-status badge-success">✓ SUCCESS</span>';
                                } elseif ($st === 'FAILED') {
                                    echo '<span class="badge-status badge-failed">✕ FAILED</span>';
                                } else {
                                    echo '<span class="badge-status badge-pending">⏳ ' . htmlspecialchars($st) . '</span>';
                                }
                            ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="detail-grid">
                            <div class="detail-item">
                                <span class="detail-label">Vortex Transaction ID</span>
                                <span class="detail-value mono"><?php echo htmlspecialchars($transaction['vortex_transaction_id']); ?></span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">Payment Status</span>
                                <span class="detail-value"><strong><?php echo htmlspecialchars($transaction['status']); ?></strong></span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">Amount</span>
                                <span class="detail-value" style="font-size: 1.15rem; font-weight: 700; color: #047857;">
                                    <?php echo htmlspecialchars($transaction['currency'] ?? 'INR'); ?> <?php echo number_format((float)$transaction['amount'], 2); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">Currency</span>
                                <span class="detail-value"><?php echo htmlspecialchars($transaction['currency'] ?? 'INR'); ?></span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">Event ID</span>
                                <span class="detail-value mono"><?php echo htmlspecialchars($transaction['event_id']); ?></span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">Event Name</span>
                                <span class="detail-value"><?php echo htmlspecialchars($transaction['event_name'] ?? 'N/A'); ?></span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">API Client Name (App)</span>
                                <span class="detail-value"><?php echo htmlspecialchars($transaction['client_name'] ?? ('Client #' . $transaction['api_client_id'])); ?></span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">Customer Name</span>
                                <span class="detail-value"><?php echo 'N/A (Not Collected)'; ?></span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">Customer Email</span>
                                <span class="detail-value"><?php echo htmlspecialchars($transaction['customer_email']); ?></span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">Customer Mobile</span>
                                <span class="detail-value"><?php echo htmlspecialchars($transaction['customer_mobile']); ?></span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">Razorpay Order ID</span>
                                <span class="detail-value mono"><?php echo htmlspecialchars($transaction['razorpay_order_id'] ?? 'N/A'); ?></span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">Razorpay Payment ID</span>
                                <span class="detail-value mono"><?php echo htmlspecialchars($transaction['razorpay_payment_id'] ?? 'N/A'); ?></span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">Created Date</span>
                                <span class="detail-value"><?php echo htmlspecialchars($transaction['created_at']); ?></span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">Updated Date</span>
                                <span class="detail-value"><?php echo htmlspecialchars($transaction['updated_at'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

</body>
</html>
