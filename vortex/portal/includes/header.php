<?php
/**
 * ------------------------------------------------------------
 * header.php – Merchant / Client Portal Header
 * ------------------------------------------------------------
 * Project : PayVortex Unified Payment Gateway
 * ------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = $pageTitle ?? 'Merchant Portal – Rpay';
$activeNav  = $activeNav ?? 'dashboard';

// Authentication Guard: require logged-in client session
if (empty($_SESSION['vortex_client_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/ApiClient.php';

$db = Database::getInstance()->getConnection();
$clientId = (int)$_SESSION['vortex_client_id'];
$clientModel = new ApiClient();
$currentClient = $clientModel->getClientById($clientId);

if (!$currentClient || (int)$currentClient['is_active'] !== 1) {
    session_destroy();
    header('Location: login.php?error=inactive');
    exit;
}

$clientName   = $currentClient['client_name'] ?? 'Merchant';
$clientInitials = strtoupper(substr($clientName, 0, 2));
$loginMode    = $_SESSION['vortex_client_login_mode'] ?? 'live_key';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="assets/images/rpay-icon.png">
    <link rel="stylesheet" href="assets/css/portal.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<div class="portal-layout">
    <!-- Sidebar -->
    <aside class="portal-sidebar">
        <div class="portal-brand">
            <a href="dashboard.php" style="display: inline-flex; align-items: center; text-decoration: none;">
                <img src="assets/images/rpay-logo-white-text.png" alt="Rpay Logo" style="height: 30px; width: auto; object-fit: contain;">
            </a>
            <span class="portal-brand-badge">Merchant</span>
        </div>

        <nav class="portal-nav">
            <span class="nav-section-title">Overview</span>
            <a href="dashboard.php" class="nav-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect></svg>
                <span>Dashboard</span>
            </a>
            <a href="transactions.php" class="nav-link <?= $activeNav === 'transactions' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                <span>Transactions</span>
            </a>

            <span class="nav-section-title">Developer & Integrations</span>
            <a href="api_keys.php" class="nav-link <?= $activeNav === 'api_keys' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 2l-2 2m-6 6l7-7-2-2-7 7v4h4z"></path><circle cx="7.5" cy="16.5" r="5.5"></circle></svg>
                <span>API Keys & Quickstart</span>
            </a>
            <a href="webhooks.php" class="nav-link <?= $activeNav === 'webhooks' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                <span>Webhooks</span>
            </a>

            <span class="nav-section-title">Account</span>
            <a href="settings.php" class="nav-link <?= $activeNav === 'settings' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                <span>Merchant Settings</span>
            </a>
        </nav>

        <div class="portal-sidebar-footer">
            <div class="client-profile-mini">
                <div class="client-avatar"><?= htmlspecialchars($clientInitials) ?></div>
                <div class="client-info-wrap">
                    <div class="client-name-text"><?= htmlspecialchars($clientName) ?></div>
                    <div class="client-id-text">Client #<?= (int)$clientId ?></div>
                </div>
                <a href="logout.php" title="Sign out" style="color:#94a3b8; text-decoration:none;" onclick="return confirm('Sign out of merchant portal?');">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="portal-main">
        <!-- Topbar -->
        <header class="portal-topbar">
            <div class="topbar-left">
                <h2 class="page-title"><?= htmlspecialchars($pageTitle) ?></h2>
            </div>
            <div class="topbar-right">
                <span class="mode-badge mode-live">
                    <span class="mode-dot"></span> Gateway Active
                </span>
                <a href="logout.php" class="btn btn-secondary btn-sm" onclick="return confirm('Sign out of merchant portal?');">
                    Sign Out
                </a>
            </div>
        </header>

        <div class="portal-body">
