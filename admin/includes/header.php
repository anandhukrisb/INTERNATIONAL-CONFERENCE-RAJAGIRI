<?php
/**
 * Admin Panel — Shared HTML Header
 * ICSWHMH 2027 Admin Panel — Phase 1
 *
 * Required vars before including:
 *   $pageTitle (string)
 *   $bodyClass (string, optional)
 */
declare(strict_types=1);

$_adminName    = $_SESSION['admin_name']  ?? 'Admin';
$_adminRole    = $_SESSION['admin_role']  ?? '';
$_adminInitial = strtoupper(substr($_adminName, 0, 1));
$_currentUri   = $_SERVER['REQUEST_URI'] ?? '';
$_isSuperAdmin = ($_adminRole === 'super_admin');

// Helper: active class for sidebar nav
function nav_active(string $path): string {
    global $_currentUri;
    return str_contains($_currentUri, $path) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle ?? 'Admin') ?> — ICSWHMH 2027</title>

    <link rel="icon" type="image/x-icon"
          href="https://res.cloudinary.com/dswfp5fwx/image/upload/v1778131826/Favicon-192_hdltam.ico">

    <!-- Google Fonts — same as conference website -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Outfit:wght@400;600;800&display=swap"
          rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- Admin CSS -->
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body class="admin-body <?= e($bodyClass ?? '') ?>">

<!-- Mobile sidebar overlay -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<div class="admin-wrapper">

<!-- ═══════════════════════════════════════════════════════════════ SIDEBAR ══ -->
<aside class="sidebar" id="sidebar">

    <!-- Brand / Logo -->
    <a href="/admin/dashboard/index.php" class="sidebar-brand">
        <div class="sidebar-brand-icon">A</div>
        <div>
            <div class="sidebar-brand-name">ICSWHMH 2027</div>
            <div class="sidebar-brand-sub">Admin Panel</div>
        </div>
    </a>

    <!-- Admin info pill -->
    <div class="sidebar-user">
        <div class="sidebar-user-avatar"><?= e($_adminInitial) ?></div>
        <div style="min-width:0;">
            <div class="sidebar-user-name"><?= e($_adminName) ?></div>
            <div class="sidebar-user-role">
                <?= $_isSuperAdmin ? '⭐ Super Admin' : '🛡 Admin' ?>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <div class="sidebar-section-label">Navigation</div>
    <ul class="sidebar-nav">
        <li>
            <a href="/admin/dashboard/index.php"
               class="<?= nav_active('dashboard') ?>">
                <i class="bi bi-speedometer2 nav-icon"></i>
                Dashboard
            </a>
        </li>
        <!-- Phase 2+ modules will be added here -->
        <li>
            <a href="#" class="nav-link-disabled" title="Coming in Phase 2"
               style="opacity:0.4;cursor:not-allowed;">
                <i class="bi bi-people-fill nav-icon"></i>
                Registrations
                <span class="nav-badge" style="margin-left:auto;font-size:0.6rem;background:rgba(255,255,255,0.15);color:rgba(255,255,255,0.6);">Soon</span>
            </a>
        </li>
        <li>
            <a href="#" class="nav-link-disabled" title="Coming in Phase 2"
               style="opacity:0.4;cursor:not-allowed;">
                <i class="bi bi-file-earmark-text nav-icon"></i>
                Abstracts
                <span class="nav-badge" style="margin-left:auto;font-size:0.6rem;background:rgba(255,255,255,0.15);color:rgba(255,255,255,0.6);">Soon</span>
            </a>
        </li>
        <li>
            <a href="#" class="nav-link-disabled" title="Coming in Phase 2"
               style="opacity:0.4;cursor:not-allowed;">
                <i class="bi bi-credit-card nav-icon"></i>
                Payments
                <span class="nav-badge" style="margin-left:auto;font-size:0.6rem;background:rgba(255,255,255,0.15);color:rgba(255,255,255,0.6);">Soon</span>
            </a>
        </li>
    </ul>

    <?php if ($_isSuperAdmin): ?>
    <div class="sidebar-section-label">Super Admin</div>
    <ul class="sidebar-nav">
        <li>
            <a href="#" class="nav-link-disabled" style="opacity:0.4;cursor:not-allowed;">
                <i class="bi bi-shield-lock nav-icon"></i>
                Manage Admins
                <span class="nav-badge" style="margin-left:auto;font-size:0.6rem;background:rgba(255,255,255,0.15);color:rgba(255,255,255,0.6);">Soon</span>
            </a>
        </li>
    </ul>
    <?php endif; ?>

    <!-- Logout -->
    <div class="sidebar-footer">
        <form method="POST" action="/admin/auth/logout.php" style="margin:0;">
            <?= csrf_field() ?>
            <button type="submit" class="btn-sidebar-logout">
                <i class="bi bi-box-arrow-left nav-icon"></i>
                Sign Out
            </button>
        </form>
    </div>

</aside>

<!-- ════════════════════════════════════════════════════════ MAIN CONTENT ══ -->
<div class="main-content">

    <!-- Top Bar -->
    <header class="topbar">
        <button class="topbar-hamburger" id="sidebar-toggle" aria-label="Toggle sidebar">
            <span></span><span></span><span></span>
        </button>

        <div class="topbar-title"><?= e($pageTitle ?? 'Dashboard') ?></div>

        <span class="topbar-clock" id="topbar-clock"></span>

        <div class="topbar-admin">
            <div class="topbar-avatar"><?= e($_adminInitial) ?></div>
            <span class="topbar-name"><?= e($_adminName) ?></span>
        </div>
    </header>

    <!-- Page content starts here -->
    <div class="page-container">
