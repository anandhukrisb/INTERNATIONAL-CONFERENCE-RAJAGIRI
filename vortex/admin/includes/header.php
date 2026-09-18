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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

    <div class="app-layout">
        <!-- Top Navigation Bar -->
        <header class="app-topbar">
            <div class="topbar-brand">
                <div class="brand-logo">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M13 2L3 14H12L11 22L21 10H12L13 2Z" fill="#1A73E8" stroke="#1A73E8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <span class="brand-name">Vortex</span>
            </div>

            <!-- Global Search Form -->
            <form action="<?= $adminBase ?>payments/payment_details.php" method="GET" class="global-search-form">
                <div class="search-input-group">
                    <span class="search-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    </span>
                    <input type="text" name="vortex_transaction_id" placeholder="Search in Vortex..." value="<?= htmlspecialchars($_GET['vortex_transaction_id'] ?? $_GET['id'] ?? '') ?>" required>
                </div>
            </form>

            <div class="topbar-actions">
                <span class="user-profile">
                    <span class="user-avatar"><?= strtoupper(substr($_SESSION['vortex_admin_fullname'] ?? $_SESSION['vortex_admin_username'] ?? 'A', 0, 1)) ?></span>
                    <span class="user-name"><?= htmlspecialchars($_SESSION['vortex_admin_fullname'] ?? $_SESSION['vortex_admin_username'] ?? 'Admin') ?></span>
                </span>
                <a href="<?= $adminBase ?>logout.php" class="btn-logout" title="Sign out" onclick="return confirm('Sign out of Vortex Admin?');">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                </a>
            </div>
        </header>

        <div class="app-body">
            <!-- Sidebar Navigation -->
            <aside class="app-sidebar">
                <div class="sidebar-primary-action">
                    <button type="button" class="btn-new" id="sidebarCreateEventBtn" onclick="if(typeof openEventModal === 'function') openEventModal(event);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        New Event
                    </button>
                </div>
                <ul class="sidebar-menu">
                    <li>
                        <a href="<?= $adminBase ?>dashboard.php#overview" class="<?= ($activeNav === 'dashboard' || $activeNav === 'overview') ? 'active' : '' ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                            <span>Overview</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= $adminBase ?>payments/payment_details.php" class="<?= $activeNav === 'payments' ? 'active' : '' ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                            <span>Transactions</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= $adminBase ?>dashboard.php#clients" class="<?= $activeNav === 'clients' ? 'active' : '' ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            <span>API Clients</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= $adminBase ?>dashboard.php#events" class="<?= $activeNav === 'events' ? 'active' : '' ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            <span>Events</span>
                        </a>
                    </li>
                </ul>
                
                <div class="sidebar-storage">
                    <div class="storage-details">
                        <span class="storage-label">System Status</span>
                        <div class="progress-bar"><div class="progress-fill" style="width: 100%;"></div></div>
                        <span class="storage-text">All systems operational</span>
                    </div>
                </div>
            </aside>

            <main class="app-main">
