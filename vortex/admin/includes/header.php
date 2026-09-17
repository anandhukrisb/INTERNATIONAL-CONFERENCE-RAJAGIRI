<?php
/**
 * Vortex Admin Header Include
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = $pageTitle ?? 'Vortex Admin Dashboard';
$activeNav = $activeNav ?? 'dashboard';

// Determine base URL dynamically (must be before auth guard)
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$adminBase = (strpos($scriptName, '/admin/payments/') !== false
           || strpos($scriptName, '/admin/events/') !== false
           || strpos($scriptName, '/admin/api_clients/') !== false) ? '../' : '';

// Auth Guard – redirect to login if not authenticated
if (empty($_SESSION['vortex_admin_id'])) {
    header('Location: ' . $adminBase . 'login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= $adminBase ?>assets/css/admin.css">
</head>
<body>

    <!-- Sidebar Navigation -->
    <aside class="admin-sidebar">
        <div class="sidebar-header">
            <span>⚡ Vortex</span>
            <span class="sidebar-badge">Admin</span>
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="<?= $adminBase ?>dashboard.php#overview" class="<?= ($activeNav === 'dashboard' || $activeNav === 'overview') ? 'active' : '' ?>">
                    <span>📊 Dashboard Overview</span>
                </a>
            </li>
            <li>
                <a href="<?= $adminBase ?>payments/payment_details.php" class="<?= $activeNav === 'payments' ? 'active' : '' ?>">
                    <span>💳 Search Transactions</span>
                </a>
            </li>
            <li>
                <a href="<?= $adminBase ?>dashboard.php#clients" class="<?= $activeNav === 'clients' ? 'active' : '' ?>">
                    <span>🔌 API Clients</span>
                </a>
            </li>
            <li>
                <a href="<?= $adminBase ?>dashboard.php#events" class="<?= $activeNav === 'events' ? 'active' : '' ?>">
                    <span>🎟️ Events & Departments</span>
                </a>
            </li>
        </ul>
        <div class="sidebar-footer">
            Vortex Gateway v1.0 &bull; Traditional Administration
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="admin-wrapper">
        <header class="admin-topbar">
            <div class="topbar-left">
                <h1><?= htmlspecialchars($pageTitle) ?></h1>
                <p>Unified Payment Gateway Administration</p>
            </div>

            <!-- Global Search Form for Vortex ID -->
            <form action="<?= $adminBase ?>payments/payment_details.php" method="GET" class="global-search-form">
                <div class="search-input-group">
                    <span class="search-icon">🔍</span>
                    <input type="text" name="vortex_transaction_id" placeholder="Search by Vortex ID (e.g. VTX_...)" value="<?= htmlspecialchars($_GET['vortex_transaction_id'] ?? $_GET['id'] ?? '') ?>" required>
                </div>
                <button type="submit" class="btn-search">Search Details</button>
            </form>

            <!-- Logged-in user + Logout -->
            <div style="display:flex;align-items:center;gap:12px;flex-shrink:0;">
                <span style="font-size:0.85rem;color:#64748b;">
                    👤 <?= htmlspecialchars($_SESSION['vortex_admin_fullname'] ?? $_SESSION['vortex_admin_username'] ?? 'Admin') ?>
                </span>
                <a href="<?= $adminBase ?>logout.php"
                   style="padding:7px 14px;background:#dc2626;color:#fff;font-size:0.82rem;font-weight:700;border-radius:5px;text-decoration:none;"
                   onclick="return confirm('Sign out of Vortex Admin?');">Logout</a>
            </div>
        </header>

        <main class="admin-main">
