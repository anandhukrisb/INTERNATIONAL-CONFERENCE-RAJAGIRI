<?php
/**
 * ------------------------------------------------------------
 * Vortex Admin Dashboard
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 * Purpose : Central administration dashboard for client demo and testing.
 *           Uses REAL database data with existing classes and methods.
 * ------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Event.php';
require_once __DIR__ . '/../classes/ApiClient.php';

$eventModel = new Event();
$apiClientModel = new ApiClient();
$db = Database::getInstance()->getConnection();

$eventAlert = null;
$clientAlert = null;
$createdClientDetails = null;

// Handle Form Actions Submitted directly to dashboard.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Create Event
    if ($action === 'create_event') {
        $eventName  = trim($_POST['event_name'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $rawStart   = trim($_POST['start_date'] ?? '');
        $rawEnd     = trim($_POST['end_date'] ?? '');

        // Format dates into full datetime strings for Event::createEvent and MySQL DATETIME
        if (!empty($rawStart) && !empty($rawEnd) && strtotime($rawStart) !== false && strtotime($rawEnd) !== false) {
            $startDate = date('Y-m-d 00:00:00', strtotime($rawStart));
            $endDate   = date('Y-m-d 23:59:59', strtotime($rawEnd));
        } else {
            $startDate = $rawStart;
            $endDate   = $rawEnd;
        }

        $result = $eventModel->createEvent($eventName, $department, $startDate, $endDate);

        if ($result['success']) {
            $eventAlert = [
                'type'       => 'success',
                'event_id'   => $result['data']['event_id'],
                'event_name' => $eventName,
                'department' => $department,
                'start_date' => $rawStart,
                'end_date'   => $rawEnd
            ];
        } else {
            $eventAlert = [
                'type'    => 'error',
                'message' => $result['message']
            ];
        }
    }

    // 2. Create API Client
    if ($action === 'create_client') {
        $clientName = trim($_POST['client_name'] ?? '');

        $result = $apiClientModel->createClient($clientName);

        if ($result['success']) {
            $clientAlert = [
                'type' => 'success'
            ];
            $createdClientDetails = $result['data'];
        } else {
            $clientAlert = [
                'type'    => 'error',
                'message' => $result['message']
            ];
        }
    }

    // 3. Direct Fallback: Activate / Deactivate Event
    if ($action === 'activate_event') {
        $eventId = trim($_POST['event_id'] ?? '');
        if (!empty($eventId)) {
            $res = $eventModel->activateEvent($eventId);
            if ($res['success']) {
                $_SESSION['flash_success'] = "Event '{$eventId}' activated successfully.";
            } else {
                $_SESSION['flash_error'] = "Failed to activate event: " . $res['message'];
            }
        }
        header('Location: dashboard.php#events-section');
        exit;
    }

    if ($action === 'deactivate_event') {
        $eventId = trim($_POST['event_id'] ?? '');
        if (!empty($eventId)) {
            $res = $eventModel->deactivateEvent($eventId);
            if ($res['success']) {
                $_SESSION['flash_success'] = "Event '{$eventId}' deactivated successfully.";
            } else {
                $_SESSION['flash_error'] = "Failed to deactivate event: " . $res['message'];
            }
        }
        header('Location: dashboard.php#events-section');
        exit;
    }

    // 4. Direct Fallback: Activate / Deactivate API Client
    if ($action === 'activate_client') {
        $clientId = (int)($_POST['client_id'] ?? 0);
        if ($clientId > 0) {
            $res = $apiClientModel->activateClient($clientId);
            if ($res['success']) {
                $_SESSION['flash_success'] = "API Client ID {$clientId} activated successfully.";
            } else {
                $_SESSION['flash_error'] = "Failed to activate API client: " . $res['message'];
            }
        }
        header('Location: dashboard.php#clients-section');
        exit;
    }

    if ($action === 'deactivate_client') {
        $clientId = (int)($_POST['client_id'] ?? 0);
        if ($clientId > 0) {
            $res = $apiClientModel->deactivateClient($clientId);
            if ($res['success']) {
                $_SESSION['flash_success'] = "API Client ID {$clientId} deactivated successfully.";
            } else {
                $_SESSION['flash_error'] = "Failed to deactivate API client: " . $res['message'];
            }
        }
        header('Location: dashboard.php#clients-section');
        exit;
    }
}

// Flash Messages
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Real Database Metrics for Dashboard Summary Cards
$totalEvents        = $eventModel->getTotalCount();
$totalClients       = $apiClientModel->getTotalCount();
$totalPayments      = 0;
$successfulPayments = 0;
$failedPayments     = 0;
$totalPaymentAmount = 0.00;

try {
    $totalPayments      = (int) $db->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
    $successfulPayments = (int) $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'SUCCESS'")->fetchColumn();
    $failedPayments     = (int) $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'FAILED'")->fetchColumn();
    $totalPaymentAmount = (float) $db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'SUCCESS'")->fetchColumn();
} catch (\Throwable $e) {
    // Database fallback
}

// Real Database Records for Tables
$events = $eventModel->getAllEvents(100);
$apiClients = $apiClientModel->getAllClients(100);

// Recent Payments (fetching razorpay_order_id and razorpay_payment_id as requested)
$recentPayments = [];
try {
    $stmtPayments = $db->query("SELECT id, vortex_transaction_id, customer_email, customer_mobile, amount, currency, razorpay_order_id, razorpay_payment_id, status, created_at 
                                FROM transactions 
                                ORDER BY id DESC 
                                LIMIT 20");
    $recentPayments = $stmtPayments->fetchAll();
} catch (\Throwable $e) {
    $recentPayments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vortex Admin Dashboard</title>
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --sidebar-bg: #0f172a;
            --sidebar-hover: #1e293b;
            --sidebar-active: #2563eb;
            --sidebar-text: #94a3b8;
            --sidebar-text-active: #ffffff;
            --bg-main: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --success-bg: #ecfdf5;
            --success-border: #a7f3d0;
            --success-text: #065f46;
            --danger-bg: #fef2f2;
            --danger-border: #fecaca;
            --danger-text: #991b1b;
            --warning-bg: #fffbeb;
            --warning-border: #fde68a;
            --warning-text: #92400e;
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

        .brand-badge {
            background-color: var(--primary);
            color: white;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 9999px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
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
            background-color: var(--sidebar-active);
            color: #ffffff;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 0.8rem;
            color: #64748b;
        }

        /* Main Container */
        .main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        /* Header */
        header.top-bar {
            background-color: #ffffff;
            border-bottom: 1px solid var(--border-color);
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-title h1 {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .header-title p {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .header-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #059669;
            background-color: #d1fae5;
            padding: 6px 14px;
            border-radius: 9999px;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #10b981;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
        }

        /* Content Area */
        .content {
            padding: 32px;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        /* Summary Cards Grid */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .summary-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04);
            display: flex;
            flex-direction: column;
            gap: 8px;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
        }

        .summary-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .summary-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .summary-value.success {
            color: #059669;
        }

        .summary-value.danger {
            color: #dc2626;
        }

        .summary-value.amount {
            color: #2563eb;
        }

        /* Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 0.95rem;
            line-height: 1.5;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .alert-success {
            background-color: var(--success-bg);
            border: 1px solid var(--success-border);
            color: var(--success-text);
        }

        .alert-danger {
            background-color: var(--danger-bg);
            border: 1px solid var(--danger-border);
            color: var(--danger-text);
        }

        .alert-warning {
            background-color: var(--warning-bg);
            border: 1px solid var(--warning-border);
            color: var(--warning-text);
        }

        .alert-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 8px 16px;
            margin-top: 8px;
            background: #ffffff;
            padding: 12px 16px;
            border-radius: 6px;
            border: 1px solid var(--success-border);
            font-size: 0.9rem;
        }

        .secret-box {
            background: #ffffff;
            border: 1px dashed #d97706;
            border-radius: 6px;
            padding: 12px 16px;
            margin-top: 8px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.95rem;
            color: #b45309;
            word-break: break-all;
        }

        .badge-id {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
            padding: 3px 8px;
            border-radius: 4px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-weight: 600;
            font-size: 0.88rem;
        }

        /* Forms Grid */
        .grid-forms {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 32px;
        }

        @media (max-width: 992px) {
            .grid-forms {
                grid-template-columns: 1fr;
            }
            .sidebar {
                width: 70px;
            }
            .sidebar-brand span, .sidebar-nav span, .sidebar-footer {
                display: none;
            }
        }

        /* Card Component */
        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .card-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border-color);
            background-color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-header h2 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .card-body {
            padding: 24px;
        }

        /* Form Inputs */
        .form-group {
            margin-bottom: 18px;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
        }

        input[type="text"],
        input[type="date"] {
            width: 100%;
            padding: 10px 14px;
            font-size: 0.95rem;
            font-family: inherit;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-main);
            background-color: #ffffff;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        input[type="text"]:focus,
        input[type="date"]:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 20px;
            font-size: 0.95rem;
            font-weight: 600;
            color: #ffffff;
            background-color: var(--primary);
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.15s ease, transform 0.1s ease;
            width: 100%;
        }

        .btn:hover {
            background-color: var(--primary-hover);
        }

        .btn:active {
            transform: scale(0.99);
        }

        .btn-success {
            background-color: #059669;
        }

        .btn-success:hover {
            background-color: #047857;
        }

        /* Table Action Buttons */
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 14px;
            font-size: 0.82rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.15s ease;
        }

        .btn-action-deactivate {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .btn-action-deactivate:hover {
            background-color: #fecaca;
        }

        .btn-action-activate {
            background-color: #dcfce7;
            color: #166534;
        }

        .btn-action-activate:hover {
            background-color: #bbf7d0;
        }

        .btn-action-view {
            background-color: #eff6ff;
            color: #1d4ed8;
        }

        .btn-action-view:hover {
            background-color: #dbeafe;
        }

        /* Data Tables Section */
        .tables-section {
            display: flex;
            flex-direction: column;
            gap: 32px;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.9rem;
        }

        th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 600;
            padding: 12px 18px;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }

        td {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border-color);
            color: #1e293b;
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background-color: #f8fafc;
        }

        .mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.85rem;
            color: #334155;
            background-color: #f1f5f9;
            padding: 3px 6px;
            border-radius: 4px;
        }

        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 10px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-active {
            background-color: #dcfce7;
            color: #166534;
        }

        .badge-inactive {
            background-color: #f1f5f9;
            color: #64748b;
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

        .table-count-badge {
            background-color: #e2e8f0;
            color: #475569;
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 9999px;
            font-weight: 600;
        }

        .empty-state {
            padding: 36px 20px;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <span>⚡ Vortex Admin</span>
            <span class="brand-badge">Control</span>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="active">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                </svg>
                <span>Dashboard</span>
            </a>
            <a href="#events-section">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span>Events</span>
            </a>
            <a href="#clients-section">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                </svg>
                <span>API Clients</span>
            </a>
            <a href="#payments-section">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                </svg>
                <span>Recent Payments</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            Vortex Gateway v1.0.0<br>
            Production DB Connected
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="main-wrapper">
        <!-- Top Bar Header -->
        <header class="top-bar">
            <div class="header-title">
                <h1>Vortex Admin</h1>
                <p>Dashboard & Testing Control Center</p>
            </div>
            <div class="header-status">
                <span class="status-dot"></span>
                <span>Gateway Online</span>
            </div>
        </header>

        <!-- Main Body Content -->
        <main class="content">

            <!-- Flash Session Alerts (Activation / Deactivation) -->
            <?php if ($flashSuccess): ?>
                <div class="alert alert-success">
                    <strong>✓ Success:</strong> <?php echo htmlspecialchars($flashSuccess); ?>
                </div>
            <?php endif; ?>

            <?php if ($flashError): ?>
                <div class="alert alert-danger">
                    <strong>✕ Error:</strong> <?php echo htmlspecialchars($flashError); ?>
                </div>
            <?php endif; ?>

            <!-- Event Creation Alert Feedback -->
            <?php if ($eventAlert): ?>
                <?php if ($eventAlert['type'] === 'success'): ?>
                    <div class="alert alert-success">
                        <strong style="font-size: 1.05rem;">✓ Event created successfully</strong>
                        <div class="alert-details-grid">
                            <div><strong>Event ID:</strong> <span class="badge-id"><?php echo htmlspecialchars($eventAlert['event_id']); ?></span></div>
                            <div><strong>Event Name:</strong> <?php echo htmlspecialchars($eventAlert['event_name']); ?></div>
                            <div><strong>Department:</strong> <?php echo htmlspecialchars($eventAlert['department']); ?></div>
                            <div><strong>Start Date:</strong> <?php echo htmlspecialchars($eventAlert['start_date']); ?></div>
                            <div><strong>End Date:</strong> <?php echo htmlspecialchars($eventAlert['end_date']); ?></div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <strong>✕ Event Creation Failed:</strong>
                        <span><?php echo htmlspecialchars($eventAlert['message']); ?></span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- API Client Creation Alert Feedback (Single-turn: NOT displayed after refresh) -->
            <?php if ($clientAlert): ?>
                <?php if ($clientAlert['type'] === 'success' && $createdClientDetails): ?>
                    <div class="alert alert-warning">
                        <strong style="font-size: 1.05rem;">✓ API Client created successfully</strong>
                        <div style="margin-top: 6px;">
                            <strong>Client ID:</strong> <?php echo (int)$createdClientDetails['id']; ?> | 
                            <strong>Client Name:</strong> <?php echo htmlspecialchars($createdClientDetails['client_name']); ?>
                        </div>
                        <div style="margin-top: 6px;">
                            <strong>API Key:</strong> <span class="mono"><?php echo htmlspecialchars($createdClientDetails['api_key']); ?></span>
                        </div>
                        <div class="secret-box">
                            <strong>API Secret:</strong><br>
                            <?php echo htmlspecialchars($createdClientDetails['api_secret']); ?>
                        </div>
                        <small style="color: #92400e; font-weight: 700; margin-top: 6px; display: block;">
                            ⚠️ API Secret is shown only once at creation. Store it securely.
                        </small>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <strong>✕ API Client Creation Failed:</strong>
                        <span><?php echo htmlspecialchars($clientAlert['message']); ?></span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Summary Cards Section (Real Values from Database) -->
            <div class="summary-grid">
                <!-- 1. Total Events -->
                <div class="summary-card">
                    <span class="summary-label">Total Events</span>
                    <span class="summary-value"><?php echo $totalEvents; ?></span>
                </div>

                <!-- 2. Total API Clients -->
                <div class="summary-card">
                    <span class="summary-label">Total API Clients</span>
                    <span class="summary-value"><?php echo $totalClients; ?></span>
                </div>

                <!-- 3. Total Payments -->
                <div class="summary-card">
                    <span class="summary-label">Total Payments</span>
                    <span class="summary-value"><?php echo $totalPayments; ?></span>
                </div>

                <!-- 4. Successful Payments -->
                <div class="summary-card">
                    <span class="summary-label">Successful Payments</span>
                    <span class="summary-value success"><?php echo $successfulPayments; ?></span>
                </div>

                <!-- 5. Failed Payments -->
                <div class="summary-card">
                    <span class="summary-label">Failed Payments</span>
                    <span class="summary-value danger"><?php echo $failedPayments; ?></span>
                </div>

                <!-- 6. Total Payment Amount -->
                <div class="summary-card">
                    <span class="summary-label">Total Payment Amount</span>
                    <span class="summary-value amount">₹<?php echo number_format($totalPaymentAmount, 2); ?></span>
                </div>
            </div>

            <!-- Forms Section: Create Event & Create API Client -->
            <div class="grid-forms">

                <!-- 1. Create Event Card -->
                <div class="card" id="create-event-card">
                    <div class="card-header">
                        <h2>Create Event</h2>
                        <span class="table-count-badge">Events Module</span>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="dashboard.php">
                            <input type="hidden" name="action" value="create_event">

                            <div class="form-group">
                                <label for="event_name">Event Name *</label>
                                <input type="text" id="event_name" name="event_name" placeholder="e.g. Vortex Tech Summit 2026" required>
                            </div>

                            <div class="form-group">
                                <label for="department">Department *</label>
                                <input type="text" id="department" name="department" placeholder="e.g. Computer Engineering" required>
                            </div>

                            <div class="form-group">
                                <label for="start_date">Start Date *</label>
                                <input type="date" id="start_date" name="start_date" required>
                            </div>

                            <div class="form-group">
                                <label for="end_date">End Date *</label>
                                <input type="date" id="end_date" name="end_date" required>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn" id="btn-create-event">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    Create Event
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 2. Create API Client Card -->
                <div class="card" id="create-client-card">
                    <div class="card-header">
                        <h2>Create API Client</h2>
                        <span class="table-count-badge">Auth Module</span>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="dashboard.php">
                            <input type="hidden" name="action" value="create_client">

                            <div class="form-group">
                                <label for="client_name">Client Name *</label>
                                <input type="text" id="client_name" name="client_name" placeholder="e.g. Dyuti Registration Web App" required>
                            </div>

                            <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 20px; line-height: 1.4;">
                                Registers an external application or partner service. An API Key and unique API Secret will be automatically generated.
                            </p>

                            <div class="form-group" style="margin-top: 48px;">
                                <button type="submit" class="btn btn-success" id="btn-create-client">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                                    </svg>
                                    Create API Client
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>

            <!-- Tables Section -->
            <div class="tables-section">

                <!-- 1. Real Events Table -->
                <div class="card" id="events-section">
                    <div class="card-header">
                        <h2>Existing Events</h2>
                        <span class="table-count-badge"><?php echo count($events); ?> Total</span>
                    </div>
                    <div class="table-container">
                        <?php if (empty($events)): ?>
                            <div class="empty-state">No events recorded in the database yet. Use the form above to create one.</div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Event ID</th>
                                        <th>Event Name</th>
                                        <th>Department</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($events as $evt): ?>
                                        <tr>
                                            <td><span class="mono"><?php echo htmlspecialchars($evt['event_id']); ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($evt['event_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($evt['department']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($evt['start_date'], 0, 10)); ?></td>
                                            <td><?php echo htmlspecialchars(substr($evt['end_date'], 0, 10)); ?></td>
                                            <td>
                                                <?php if ((int)$evt['is_active'] === 1): ?>
                                                    <span class="badge-status badge-active">Active</span>
                                                <?php else: ?>
                                                    <span class="badge-status badge-inactive">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ((int)$evt['is_active'] === 1): ?>
                                                    <form method="POST" action="events/deactivate_event.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to deactivate this event?');">
                                                        <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($evt['event_id']); ?>">
                                                        <button type="submit" class="btn-action btn-action-deactivate">Deactivate</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" action="events/activate_event.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to activate this event?');">
                                                        <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($evt['event_id']); ?>">
                                                        <button type="submit" class="btn-action btn-action-activate">Activate</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2. Real API Clients Table -->
                <div class="card" id="clients-section">
                    <div class="card-header">
                        <h2>Existing API Clients</h2>
                        <span class="table-count-badge"><?php echo count($apiClients); ?> Total</span>
                    </div>
                    <div class="table-container">
                        <?php if (empty($apiClients)): ?>
                            <div class="empty-state">No API clients registered yet. Use the form above to register one.</div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Client ID</th>
                                        <th>Client Name</th>
                                        <th>API Key</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($apiClients as $client): ?>
                                        <tr>
                                            <td><?php echo (int)$client['id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($client['client_name']); ?></strong></td>
                                            <td><span class="mono"><?php echo htmlspecialchars($client['api_key']); ?></span></td>
                                            <td>
                                                <?php if ((int)$client['is_active'] === 1): ?>
                                                    <span class="badge-status badge-active">Active</span>
                                                <?php else: ?>
                                                    <span class="badge-status badge-inactive">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ((int)$client['is_active'] === 1): ?>
                                                    <form method="POST" action="api_clients/deactivate_api_client.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to deactivate this API client?');">
                                                        <input type="hidden" name="client_id" value="<?php echo (int)$client['id']; ?>">
                                                        <button type="submit" class="btn-action btn-action-deactivate">Deactivate</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" action="api_clients/activate_api_client.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to activate this API client?');">
                                                        <input type="hidden" name="client_id" value="<?php echo (int)$client['id']; ?>">
                                                        <button type="submit" class="btn-action btn-action-activate">Activate</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 3. Real Recent Payments Table (with Razorpay Order ID and Razorpay Payment ID) -->
                <div class="card" id="payments-section">
                    <div class="card-header">
                        <h2>Recent Payments</h2>
                        <span class="table-count-badge"><?php echo count($recentPayments); ?> Recent</span>
                    </div>
                    <div class="table-container">
                        <?php if (empty($recentPayments)): ?>
                            <div class="empty-state">No payment transactions recorded yet.</div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Transaction ID</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Currency</th>
                                        <th>Razorpay Order ID</th>
                                        <th>Razorpay Payment ID</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentPayments as $payment): ?>
                                        <tr>
                                            <td><span class="mono"><?php echo htmlspecialchars($payment['vortex_transaction_id']); ?></span></td>
                                            <td>
                                                <div><?php echo 'N/A'; ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($payment['customer_email']); ?></small>
                                            </td>
                                            <td><strong><?php echo number_format((float)$payment['amount'], 2); ?></strong></td>
                                            <td><?php echo htmlspecialchars($payment['currency'] ?? 'INR'); ?></td>
                                            <td>
                                                <?php if (!empty($payment['razorpay_order_id'])): ?>
                                                    <span class="mono"><?php echo htmlspecialchars($payment['razorpay_order_id']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($payment['razorpay_payment_id'])): ?>
                                                    <span class="mono"><?php echo htmlspecialchars($payment['razorpay_payment_id']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                    $st = strtoupper((string)$payment['status']);
                                                    if ($st === 'SUCCESS') {
                                                        echo '<span class="badge-status badge-success">✓ SUCCESS</span>';
                                                    } elseif ($st === 'FAILED') {
                                                        echo '<span class="badge-status badge-failed">✕ FAILED</span>';
                                                    } else {
                                                        echo '<span class="badge-status badge-pending">⏳ ' . htmlspecialchars($st) . '</span>';
                                                    }
                                                ?>
                                            </td>
                                            <td><small class="text-muted"><?php echo htmlspecialchars($payment['created_at']); ?></small></td>
                                            <td>
                                                <a href="payments/payment_details.php?id=<?php echo urlencode($payment['vortex_transaction_id']); ?>" class="btn-action btn-action-view">
                                                    View Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </main>
    </div>

</body>
</html>
