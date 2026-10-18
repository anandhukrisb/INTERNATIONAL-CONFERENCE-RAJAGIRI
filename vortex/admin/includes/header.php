<?php
/**
 * Vortex Admin Header Include
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = $pageTitle ?? 'Rpay Admin Dashboard';
$activeNav = $activeNav ?? 'dashboard';

// Determine base URL dynamically (must be before auth guard)
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$adminSubdirs = ['/admin/payments/', '/admin/events/', '/admin/api_clients/'];
$adminBase = '';
foreach ($adminSubdirs as $subdir) {
    if (strpos($scriptName, $subdir) !== false) {
        $adminBase = '../';
        break;
    }
}

require_once __DIR__ . '/../../classes/RBAC.php';

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
    <link rel="icon" type="image/png" href="<?= $adminBase ?>assets/images/rpay-icon.png">
    <link rel="stylesheet" href="<?= $adminBase ?>assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Immediate Flash-Free Theme Initializer -->
    <script>
        (function() {
            var saved = localStorage.getItem('vortex_theme');
            if (saved === 'dark' || (!saved && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
</head>
<body>

    <div class="app-layout">
        <!-- Top Navigation Bar -->
        <header class="app-topbar">
            <div class="topbar-brand">
                <a href="<?= $adminBase ?>dashboard.php" class="brand-link" style="display: inline-flex; align-items: center; text-decoration: none;">
                    <img src="<?= $adminBase ?>assets/images/rpay-logo.png" alt="Rpay Logo" class="rpay-logo rpay-logo-light" style="height: 32px; width: auto; object-fit: contain;">
                    <img src="<?= $adminBase ?>assets/images/rpay-logo-white-text.png" alt="Rpay Logo" class="rpay-logo rpay-logo-dark" style="height: 32px; width: auto; object-fit: contain; display: none;">
                </a>
            </div>

            <!-- Global Search Form with Omnisearch Autocomplete -->
            <form action="<?= $adminBase ?>search.php" method="GET" class="global-search-form" id="topbarSearchForm" autocomplete="off">
                <div class="search-input-group">
                    <span class="search-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    </span>
                    <input type="text" name="q" id="topbarSearchInput" placeholder="Search transactions, emails, IDs, merchants..." value="<?= htmlspecialchars($_GET['q'] ?? $_GET['vortex_transaction_id'] ?? '') ?>" required>
                    <div id="topbarSearchDropdown" class="search-autocomplete-dropdown"></div>
                </div>
            </form>

            <div class="topbar-actions">
                <!-- In-App Notification Center Bell & Dropdown -->
                <div class="topbar-notif-wrapper" style="position: relative;">
                    <button type="button" id="notifBellBtn" class="btn-notif-bell" title="Notification Center (Alerts & Activity)" aria-label="Notifications" aria-expanded="false">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        <span id="notifBadge" class="notif-badge" style="display: none;">0</span>
                    </button>

                    <!-- Topbar Floating Notification Palette -->
                    <div id="notifDropdown" class="notif-dropdown" style="display: none;">
                        <div class="notif-dropdown-header">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-weight: 700; font-size: 0.95rem; color: var(--text-dark);">Notifications</span>
                                <span id="notifDropdownBadge" class="notif-pill-count">0</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <button type="button" id="notifMarkAllReadBtn" class="notif-action-btn" title="Mark all as read">
                                    ✓ Read All
                                </button>
                                <a href="<?= $adminBase ?>notifications.php" class="notif-action-btn" title="Open full notification center">
                                    All Alerts →
                                </a>
                            </div>
                        </div>

                        <!-- Notification Items Container -->
                        <div id="notifItemsContainer" class="notif-items-list">
                            <div class="notif-loading" style="text-align: center; padding: 24px; color: var(--text-gray); font-size: 0.85rem;">
                                Loading alerts...
                            </div>
                        </div>

                        <div class="notif-dropdown-footer">
                            <a href="<?= $adminBase ?>notifications.php" style="color: var(--primary-blue); text-decoration: none; font-size: 0.84rem; font-weight: 600; display: block; text-align: center;">
                                Open Notification Center & Activity History →
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Google Theme Toggle Button -->
                <button type="button" id="themeToggleBtn" class="btn-theme-toggle" title="Switch between Dark and Light mode" aria-label="Toggle Theme">
                    <span class="theme-icon-sun">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                    </span>
                    <span class="theme-icon-moon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                    </span>
                </button>

                <!-- User Profile Dropdown Menu -->
                <div class="topbar-user-wrapper" style="position: relative;">
                    <button type="button" class="user-profile <?= ($activeNav === 'profile') ? 'active-profile' : '' ?>" id="userProfileDropdownBtn" aria-haspopup="true" aria-expanded="false" title="Admin Account Menu">
                        <span class="user-avatar" style="<?= !empty($_SESSION['vortex_admin_avatar_color']) ? 'background-color: ' . htmlspecialchars($_SESSION['vortex_admin_avatar_color']) . ';' : '' ?>"><?= strtoupper(substr($_SESSION['vortex_admin_fullname'] ?? $_SESSION['vortex_admin_username'] ?? 'A', 0, 1)) ?></span>
                        <span class="user-name"><?= htmlspecialchars($_SESSION['vortex_admin_fullname'] ?? $_SESSION['vortex_admin_username'] ?? 'Admin') ?></span>
                        <?= RBAC::getRoleBadge() ?>
                        <svg class="profile-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </button>

                    <!-- Profile Dropdown Palette -->
                    <div id="userProfileDropdown" class="user-profile-dropdown" style="display: none;">
                        <div class="user-dropdown-header">
                            <div class="user-dropdown-avatar" style="<?= !empty($_SESSION['vortex_admin_avatar_color']) ? 'background-color: ' . htmlspecialchars($_SESSION['vortex_admin_avatar_color']) . ';' : '' ?>">
                                <?= strtoupper(substr($_SESSION['vortex_admin_fullname'] ?? $_SESSION['vortex_admin_username'] ?? 'A', 0, 1)) ?>
                            </div>
                            <div class="user-dropdown-info">
                                <div class="user-dropdown-name"><?= htmlspecialchars($_SESSION['vortex_admin_fullname'] ?? $_SESSION['vortex_admin_username'] ?? 'Admin') ?></div>
                                <div class="user-dropdown-meta">@<?= htmlspecialchars($_SESSION['vortex_admin_username'] ?? 'admin') ?></div>
                            </div>
                        </div>

                        <div class="user-dropdown-divider"></div>

                        <ul class="user-dropdown-menu">
                            <li>
                                <a href="<?= $adminBase ?>profile.php" class="user-dropdown-item">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                    <span>Profile &amp; Settings</span>
                                </a>
                            </li>
                            <li>
                                <a href="<?= $adminBase ?>profile.php#security" class="user-dropdown-item">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                    <span>Security &amp; Password</span>
                                </a>
                            </li>
                            <li>
                                <a href="<?= $adminBase ?>audit_logs.php" class="user-dropdown-item">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                                    <span>Audit Logs</span>
                                </a>
                            </li>
                            <li class="user-dropdown-divider"></li>
                            <li>
                                <a href="<?= $adminBase ?>logout.php" class="user-dropdown-item user-dropdown-logout" onclick="return confirm('Sign out of Rpay Admin?');">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                    <span>Log Out</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </header>

        <div class="app-body">
            <!-- Sidebar Navigation -->
            <aside class="app-sidebar">
                <?php if (RBAC::can('events.create')): ?>
                <div class="sidebar-primary-action">
                    <button type="button" class="btn-new" id="sidebarCreateEventBtn" onclick="if(typeof openEventModal === 'function') openEventModal(event);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        New Event
                    </button>
                </div>
                <?php endif; ?>
                <ul class="sidebar-menu">
                    <li>
                        <a href="<?= $adminBase ?>dashboard.php" class="<?= ($activeNav === 'dashboard' || $activeNav === 'overview') ? 'active' : '' ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                            <span>Overview</span>
                        </a>
                    </li>
                    <?php if (RBAC::can('clients.view')): ?>
                    <li>
                        <a href="<?= $adminBase ?>api_clients/index.php" class="<?= $activeNav === 'clients' ? 'active' : '' ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            <span>API Clients</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (RBAC::can('events.view')): ?>
                    <li>
                        <a href="<?= $adminBase ?>events/index.php" class="<?= $activeNav === 'events' ? 'active' : '' ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            <span>Events</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (RBAC::can('payments.view')): ?>
                    <li>
                        <a href="<?= $adminBase ?>payments/list_payments.php" class="<?= $activeNav === 'payments' ? 'active' : '' ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                            <span>Transactions</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= $adminBase ?>payments/revenue.php" class="<?= $activeNav === 'revenue' ? 'active' : '' ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                            <span>Event Revenue</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="<?= $adminBase ?>audit_logs.php" class="<?= $activeNav === 'audit' ? 'active' : '' ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                            <span>Audit Trail</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= $adminBase ?>notifications.php" class="<?= $activeNav === 'notifications' ? 'active' : '' ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                            <span>Notifications</span>
                            <span id="sidebarNotifBadge" class="sidebar-badge-count" style="display: none;">0</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= $adminBase ?>profile.php" class="<?= $activeNav === 'profile' ? 'active' : '' ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            <span>Admin Profile</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= $adminBase ?>../portal/login.php" target="_blank" title="Open Merchant Self-Service Portal">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                            <span>Merchant Portal &#8599;</span>
                        </a>
                    </li>
                </ul>
            </aside>

            <main class="app-main">
