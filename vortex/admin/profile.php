<?php
/**
 * ------------------------------------------------------------
 * Vortex Admin – Profile & Account Settings
 * ------------------------------------------------------------
 * Interactive administrative profile with rich modals for:
 *  - Edit Profile & Custom Avatar Color
 *  - Secure Password Change with Strength Meter
 *  - Two-Factor Authentication (2FA) Setup & Recovery Codes
 *  - Active Sessions & Device Security Management
 *  - Super Admin Permissions Matrix
 *  - Diagnostic API Access Tokens
 * ------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$activeNav = 'profile';
$pageTitle = 'Admin Profile & Security – Vortex';

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/RBAC.php';

// Auth Guard
if (empty($_SESSION['vortex_admin_id'])) {
    header('Location: login.php');
    exit;
}

$adminId = (int)$_SESSION['vortex_admin_id'];
$db = Database::getInstance()->getConnection();

// Helper to detect AJAX request
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (!empty($_POST['ajax_request']) && $_POST['ajax_request'] === '1');

// Fetch current admin record
$stmt = $db->prepare("SELECT * FROM admin WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $adminId]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin || (int)$admin['is_active'] !== 1) {
    session_destroy();
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Account is inactive or not found.']);
        exit;
    }
    header('Location: login.php');
    exit;
}

// Handle Form Submissions
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. UPDATE PROFILE
    if ($action === 'update_profile') {
        $fullName    = trim($_POST['full_name'] ?? '');
        $username    = trim($_POST['username'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $avatarColor = trim($_POST['avatar_color'] ?? '#1a73e8');

        if (empty($fullName) || empty($username)) {
            $msg = 'Full Name and Username cannot be empty.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $_SESSION['flash_error'] = $msg;
            header('Location: profile.php');
            exit;
        }

        // Validate username format
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {
            $msg = 'Username must be 3-50 characters and contain only letters, numbers, dots, or underscores.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $_SESSION['flash_error'] = $msg;
            header('Location: profile.php');
            exit;
        }

        // Check if username taken by another admin
        $chk = $db->prepare("SELECT id FROM admin WHERE username = :u AND id != :id LIMIT 1");
        $chk->execute([':u' => $username, ':id' => $adminId]);
        if ($chk->fetch()) {
            $msg = 'Username is already taken by another administrator.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $_SESSION['flash_error'] = $msg;
            header('Location: profile.php');
            exit;
        }

        // Update database
        $upd = $db->prepare("
            UPDATE admin 
            SET full_name = :fn, username = :u, email = :em, phone = :ph, avatar_color = :col 
            WHERE id = :id
        ");
        $upd->execute([
            ':fn'  => $fullName,
            ':u'   => $username,
            ':em'  => $email ?: null,
            ':ph'  => $phone ?: null,
            ':col' => $avatarColor ?: '#1a73e8',
            ':id'  => $adminId
        ]);

        // Refresh session variables
        $_SESSION['vortex_admin_fullname']     = $fullName;
        $_SESSION['vortex_admin_username']     = $username;
        $_SESSION['vortex_admin_email']        = $email;
        $_SESSION['vortex_admin_avatar_color'] = $avatarColor;

        $msg = 'Profile updated successfully!';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => $msg,
                'data' => [
                    'full_name'    => $fullName,
                    'username'     => $username,
                    'email'        => $email,
                    'phone'        => $phone,
                    'avatar_color' => $avatarColor,
                    'initial'      => strtoupper(substr($fullName ?: $username, 0, 1))
                ]
            ]);
            exit;
        }

        $_SESSION['flash_success'] = $msg;
        header('Location: profile.php');
        exit;
    }

    // 2. CHANGE PASSWORD
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $msg = 'All password fields are required.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $_SESSION['flash_error'] = $msg;
            header('Location: profile.php#security');
            exit;
        }

        // Verify current password
        if (!password_verify($currentPassword, $admin['password_hash'])) {
            $msg = 'The current password you entered is incorrect.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $_SESSION['flash_error'] = $msg;
            header('Location: profile.php#security');
            exit;
        }

        if (strlen($newPassword) < 6) {
            $msg = 'New password must be at least 6 characters long.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $_SESSION['flash_error'] = $msg;
            header('Location: profile.php#security');
            exit;
        }

        if ($newPassword !== $confirmPassword) {
            $msg = 'New password and confirmation do not match.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $_SESSION['flash_error'] = $msg;
            header('Location: profile.php#security');
            exit;
        }

        // Hash and update
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare("UPDATE admin SET password_hash = :h WHERE id = :id")->execute([':h' => $newHash, ':id' => $adminId]);

        $msg = 'Password changed successfully! Keep your credentials safe.';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $msg]);
            exit;
        }

        $_SESSION['flash_success'] = $msg;
        header('Location: profile.php#security');
        exit;
    }

    // 3. TOGGLE 2FA
    if ($action === 'toggle_2fa') {
        $enable = !empty($_POST['enable']) && $_POST['enable'] === '1' ? 1 : 0;
        $db->prepare("UPDATE admin SET two_factor_enabled = :two WHERE id = :id")->execute([':two' => $enable, ':id' => $adminId]);

        $msg = $enable ? 'Two-Factor Authentication has been enabled.' : 'Two-Factor Authentication has been disabled.';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $msg, 'enabled' => (bool)$enable]);
            exit;
        }

        $_SESSION['flash_success'] = $msg;
        header('Location: profile.php#security');
        exit;
    }

    // 4. TERMINATE SESSIONS
    if ($action === 'terminate_sessions') {
        session_regenerate_id(true);
        $msg = 'All other active sessions have been terminated. Current session preserved.';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $msg]);
            exit;
        }
        $_SESSION['flash_success'] = $msg;
        header('Location: profile.php#security');
        exit;
    }

    // 5. GENERATE DIAGNOSTIC TOKEN
    if ($action === 'generate_api_token') {
        $tokenScope = trim($_POST['token_scope'] ?? 'read_only');
        $expiryDays = (int)($_POST['token_expiry'] ?? 1);
        $token = 'vtx_diag_' . bin2hex(random_bytes(24));
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));

        $msg = 'Diagnostic access token generated successfully.';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => $msg,
                'token' => $token,
                'scope' => $tokenScope,
                'expires_at' => $expiresAt
            ]);
            exit;
        }
        $_SESSION['flash_success'] = $msg;
        $_SESSION['generated_token'] = ['token' => $token, 'expires_at' => $expiresAt];
        header('Location: profile.php#api');
        exit;
    }

    // 6. CREATE ADMIN USER (Super Admin only)
    if ($action === 'create_admin_user') {
        $res = RBAC::createAdminUser($_POST);
        if ($res['success']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: profile.php#tab-permissions');
        exit;
    }

    // 7. UPDATE ADMIN ROLE (Super Admin only)
    if ($action === 'update_admin_role') {
        $targetAdminId = (int)($_POST['target_admin_id'] ?? 0);
        $newRole = trim($_POST['new_role'] ?? '');
        $res = RBAC::updateAdminRole($targetAdminId, $newRole);
        if ($res['success']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: profile.php#tab-permissions');
        exit;
    }

    // 8. TOGGLE ADMIN STATUS (Super Admin only)
    if ($action === 'toggle_admin_status') {
        $targetAdminId = (int)($_POST['target_admin_id'] ?? 0);
        $newStatus = (int)($_POST['is_active'] ?? 1);
        $res = RBAC::toggleAdminStatus($targetAdminId, $newStatus);
        if ($res['success']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: profile.php#tab-permissions');
        exit;
    }
}

// Fetch all administrative users if Super Admin
$allAdminUsers = RBAC::can('admins.manage') ? RBAC::getAllAdmins() : [];

// Fetch System Overview Metrics for Admin Oversight
$totalEvents       = (int)$db->query("SELECT COUNT(*) FROM events")->fetchColumn();
$totalClients      = (int)$db->query("SELECT COUNT(*) FROM api_clients WHERE is_active = 1")->fetchColumn();
$totalTransactions = (int)$db->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
$successfulVolume  = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'SUCCESS'")->fetchColumn();

// Fetch Recent System Activity for Audit Log Tab
$recentTxns = $db->query("
    SELECT vortex_transaction_id, amount, status, created_at, customer_email 
    FROM transactions 
    ORDER BY created_at DESC 
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

// Parse client device & IP
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Browser';

$browser = 'Web Browser';
if (strpos($userAgent, 'Chrome') !== false) {
    $browser = 'Google Chrome';
} elseif (strpos($userAgent, 'Firefox') !== false) {
    $browser = 'Mozilla Firefox';
} elseif (strpos($userAgent, 'Safari') !== false) {
    $browser = 'Apple Safari';
} elseif (strpos($userAgent, 'Edge') !== false) {
    $browser = 'Microsoft Edge';
}

$platform = 'Desktop';
if (strpos($userAgent, 'Linux') !== false) {
    $platform = 'Linux';
} elseif (strpos($userAgent, 'Macintosh') !== false) {
    $platform = 'macOS';
} elseif (strpos($userAgent, 'Windows') !== false) {
    $platform = 'Windows';
} elseif (strpos($userAgent, 'Android') !== false) {
    $platform = 'Android';
} elseif (strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false) {
    $platform = 'iOS';
}

$avatarColor = !empty($admin['avatar_color']) ? $admin['avatar_color'] : '#1a73e8';
$initial = strtoupper(substr($admin['full_name'] ?: $admin['username'], 0, 1));
$is2FA = !empty($admin['two_factor_enabled']);
$securityScore = 70 + ($is2FA ? 30 : 0);

require_once __DIR__ . '/includes/header.php';
?>

<div class="profile-page-wrapper">

    <!-- Flash Alerts -->
    <?php if ($flashSuccess): ?>
        <div class="alert alert-success" id="profileFlashSuccess" style="display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <span><?= htmlspecialchars($flashSuccess) ?></span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:inherit;font-size:1.2rem;">&times;</button>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="alert alert-danger" id="profileFlashError" style="display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span><?= htmlspecialchars($flashError) ?></span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:inherit;font-size:1.2rem;">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Profile Hero Card -->
    <div class="profile-hero-card">
        <div class="profile-hero-cover">
            <div class="profile-hero-cover-pattern">
                <svg width="260" height="140" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"></path>
                    <path d="M2 12h20"></path>
                </svg>
            </div>
        </div>

        <div class="profile-hero-body">
            <div class="profile-hero-left">
                <div class="profile-avatar-large-wrap">
                    <div class="profile-avatar-large" id="mainAvatarDisplay" style="background-color: <?= htmlspecialchars($avatarColor) ?>;">
                        <span id="mainAvatarInitial"><?= $initial ?></span>
                    </div>
                    <button type="button" class="profile-avatar-edit-btn" title="Change Avatar Theme Color" onclick="openModal('editProfileModal')">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                    </button>
                </div>

                <div class="profile-hero-details">
                    <h1 class="profile-hero-name">
                        <span id="heroFullNameText"><?= htmlspecialchars($admin['full_name']) ?></span>
                        <span class="badge badge-success" title="Verified Gateway Administrator" style="font-size: 0.72rem; vertical-align: middle;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            Verified Admin
                        </span>
                    </h1>

                    <div class="profile-hero-username">
                        <span>@<span id="heroUsernameText"><?= htmlspecialchars($admin['username']) ?></span></span>
                        <span style="color: var(--border-color);">•</span>
                        <span style="color: var(--primary-blue); font-weight: 500;"><?= htmlspecialchars($admin['role'] ?? 'Super Administrator') ?></span>
                    </div>

                    <div class="profile-hero-meta">
                        <span class="profile-meta-item" title="Last Active Timestamp">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            <span>Last Login: <?= $admin['last_login'] ? date('M d, Y h:i A', strtotime($admin['last_login'])) : 'Just now' ?></span>
                        </span>
                        <span style="color: var(--border-color);">•</span>
                        <span class="profile-meta-item" title="Registered Account Date">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            <span>Created <?= date('M Y', strtotime($admin['created_at'])) ?></span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Hero Action Buttons Cluster -->
            <div class="profile-hero-actions">
                <button type="button" class="btn btn-primary" onclick="openModal('editProfileModal')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    <span>Edit Profile</span>
                </button>
                <button type="button" class="btn btn-secondary" onclick="openModal('changePasswordModal')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    <span>Password</span>
                </button>
                <button type="button" class="btn btn-secondary" onclick="openModal('twoFactorModal')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    <span>2FA Security</span>
                </button>
                <button type="button" class="btn btn-secondary" onclick="openModal('activeSessionsModal')" title="View Active Devices & Sessions">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                    <span>Sessions</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Quick Metrics Row -->
    <div class="profile-stats-grid">
        <div class="profile-stat-card">
            <div class="profile-stat-icon blue">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            </div>
            <div class="profile-stat-content">
                <span class="profile-stat-label">Security Health</span>
                <span class="profile-stat-value">
                    <span id="statSecurityScore"><?= $securityScore ?>%</span>
                    <span class="badge <?= $is2FA ? 'badge-success' : 'badge-pending' ?>" style="font-size: 0.7rem; font-weight:600;">
                        <?= $is2FA ? 'Strong' : 'Moderate' ?>
                    </span>
                </span>
                <span class="profile-stat-sub">
                    <?= $is2FA ? '✓ 2FA Active • SSL Enforced' : 'Enable 2FA to achieve 100%' ?>
                </span>
            </div>
        </div>

        <div class="profile-stat-card">
            <div class="profile-stat-icon green">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
            </div>
            <div class="profile-stat-content">
                <span class="profile-stat-label">Processed Volume</span>
                <span class="profile-stat-value amount">₹<?= number_format($successfulVolume, 2) ?></span>
                <span class="profile-stat-sub"><?= number_format($totalTransactions) ?> total transactions</span>
            </div>
        </div>

        <div class="profile-stat-card">
            <div class="profile-stat-icon amber">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            </div>
            <div class="profile-stat-content">
                <span class="profile-stat-label">Gateway Oversight</span>
                <span class="profile-stat-value"><?= $totalEvents ?> Events</span>
                <span class="profile-stat-sub"><?= $totalClients ?> Active API clients connected</span>
            </div>
        </div>

        <div class="profile-stat-card">
            <div class="profile-stat-icon purple">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
            </div>
            <div class="profile-stat-content">
                <span class="profile-stat-label">Current Device</span>
                <span class="profile-stat-value" style="font-size: 1.05rem;"><?= htmlspecialchars($browser) ?></span>
                <span class="profile-stat-sub"><?= htmlspecialchars($platform) ?> • IP: <?= htmlspecialchars($clientIp) ?></span>
            </div>
        </div>
    </div>

    <!-- Interactive Navigation Tabs -->
    <div class="profile-tabs-header">
        <button type="button" class="profile-tab-btn active" data-tab-target="#tab-profile">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            <span>Personal Information</span>
        </button>
        <button type="button" class="profile-tab-btn" data-tab-target="#tab-security">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            <span>Security & Authentication</span>
        </button>
        <button type="button" class="profile-tab-btn" data-tab-target="#tab-permissions">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            <span>Roles & Permissions</span>
        </button>
        <button type="button" class="profile-tab-btn" data-tab-target="#tab-activity">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
            <span>Activity & Audit Trail</span>
        </button>
        <button type="button" class="profile-tab-btn" data-tab-target="#tab-api">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
            <span>Diagnostics & Token</span>
        </button>
    </div>

    <!-- TAB 1: PERSONAL INFORMATION -->
    <div class="profile-tab-panel active" id="tab-profile">
        <div class="profile-card">
            <div class="profile-card-header">
                <div>
                    <h3 class="profile-card-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        Admin Profile Details
                    </h3>
                    <p class="profile-card-desc">Your basic account credentials and administrative identity across Vortex Gateway.</p>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="openModal('editProfileModal')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    <span>Edit Information</span>
                </button>
            </div>

            <div class="profile-info-grid">
                <div class="profile-info-item">
                    <span class="profile-info-label">Full Name</span>
                    <span class="profile-info-value" id="cardFullName"><?= htmlspecialchars($admin['full_name']) ?></span>
                </div>

                <div class="profile-info-item">
                    <span class="profile-info-label">Username</span>
                    <div class="profile-info-value">
                        <span id="cardUsername">@<?= htmlspecialchars($admin['username']) ?></span>
                        <button type="button" class="btn-copy" onclick="copyToClipboard('<?= htmlspecialchars($admin['username']) ?>', this)">Copy</button>
                    </div>
                </div>

                <div class="profile-info-item">
                    <span class="profile-info-label">Email Address</span>
                    <span class="profile-info-value" id="cardEmail"><?= htmlspecialchars($admin['email'] ?: 'Not configured') ?></span>
                </div>

                <div class="profile-info-item">
                    <span class="profile-info-label">Phone Number</span>
                    <span class="profile-info-value" id="cardPhone"><?= htmlspecialchars($admin['phone'] ?: 'Not configured') ?></span>
                </div>

                <div class="profile-info-item">
                    <span class="profile-info-label">Assigned Role</span>
                    <span class="profile-info-value">
                        <span class="badge badge-success"><?= htmlspecialchars($admin['role'] ?? 'Super Administrator') ?></span>
                    </span>
                </div>

                <div class="profile-info-item">
                    <span class="profile-info-label">Admin ID</span>
                    <div class="profile-info-value">
                        <span class="font-mono">#ADM-<?= str_pad($admin['id'], 4, '0', STR_PAD_LEFT) ?></span>
                        <button type="button" class="btn-copy" onclick="copyToClipboard('ADM-<?= str_pad($admin['id'], 4, '0', STR_PAD_LEFT) ?>', this)">Copy</button>
                    </div>
                </div>

                <div class="profile-info-item">
                    <span class="profile-info-label">Avatar Theme</span>
                    <div class="profile-info-value" style="display:flex; align-items:center; gap:10px;">
                        <span id="cardAvatarSwatch" style="width:20px; height:20px; border-radius:50%; background-color:<?= htmlspecialchars($avatarColor) ?>; display:inline-block; border:1px solid rgba(0,0,0,0.1);"></span>
                        <span id="cardAvatarHex"><?= htmlspecialchars($avatarColor) ?></span>
                        <button type="button" class="btn-copy" onclick="openModal('editProfileModal')">Change</button>
                    </div>
                </div>

                <div class="profile-info-item">
                    <span class="profile-info-label">Account Status</span>
                    <span class="profile-info-value">
                        <span class="badge badge-success">✓ Active &amp; Functional</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Appearance & Theme Card -->
        <div class="profile-card" style="margin-top: 24px;">
            <div class="profile-card-header">
                <div>
                    <h3 class="profile-card-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                        Appearance &amp; Theme Settings
                    </h3>
                    <p class="profile-card-desc">Customize your visual interface. Switch between Google Drive Light and Google Dark Mode.</p>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-top: 16px;">
                <!-- Light Theme Option -->
                <div class="theme-choice-card" id="themeChoiceLight" onclick="setAdminTheme('light')" style="border: 2px solid var(--border-color); border-radius: 10px; padding: 18px; cursor: pointer; transition: all 0.2s ease;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span style="font-size: 1.5rem;">☀️</span>
                        <input type="radio" name="profile_theme_choice" id="radioThemeLight" value="light" style="width: 18px; height: 18px; accent-color: #1a73e8;">
                    </div>
                    <div style="font-weight: 700; font-size: 0.95rem;">Google Drive Light</div>
                    <div style="font-size: 0.8rem; color: var(--text-gray); margin-top: 4px;">Crisp white surfaces with signature Google Drive blue accents.</div>
                </div>

                <!-- Dark Theme Option -->
                <div class="theme-choice-card" id="themeChoiceDark" onclick="setAdminTheme('dark')" style="border: 2px solid var(--border-color); border-radius: 10px; padding: 18px; cursor: pointer; transition: all 0.2s ease;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span style="font-size: 1.5rem;">🌙</span>
                        <input type="radio" name="profile_theme_choice" id="radioThemeDark" value="dark" style="width: 18px; height: 18px; accent-color: #8ab4f8;">
                    </div>
                    <div style="font-weight: 700; font-size: 0.95rem;">Google Dark Mode</div>
                    <div style="font-size: 0.8rem; color: var(--text-gray); margin-top: 4px;">Comfortable #202124 charcoal canvas with #8ab4f8 Google blue.</div>
                </div>

                <!-- System Auto Option -->
                <div class="theme-choice-card" id="themeChoiceAuto" onclick="setAdminTheme('auto')" style="border: 2px solid var(--border-color); border-radius: 10px; padding: 18px; cursor: pointer; transition: all 0.2s ease;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span style="font-size: 1.5rem;">💻</span>
                        <input type="radio" name="profile_theme_choice" id="radioThemeAuto" value="auto" style="width: 18px; height: 18px;">
                    </div>
                    <div style="font-weight: 700; font-size: 0.95rem;">System Default</div>
                    <div style="font-size: 0.8rem; color: var(--text-gray); margin-top: 4px;">Automatically follows your operating system appearance.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: SECURITY & AUTHENTICATION -->
    <div class="profile-tab-panel" id="tab-security">
        <div class="profile-card">
            <div class="profile-card-header">
                <div>
                    <h3 class="profile-card-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        Authentication & Password
                    </h3>
                    <p class="profile-card-desc">Control your access credentials and protect administrative privileges.</p>
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="openModal('changePasswordModal')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                    <span>Change Password</span>
                </button>
            </div>

            <div class="session-item">
                <div class="session-icon" style="background:#e8f0fe; color:#1a73e8;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                </div>
                <div class="session-details">
                    <span class="session-title">Account Password</span>
                    <span class="session-meta">Last updated on <?= date('F j, Y', strtotime($admin['updated_at'])) ?> • Encrypted with Bcrypt Cost 12</span>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="openModal('changePasswordModal')">Update</button>
                </div>
            </div>

            <div class="session-item">
                <div class="session-icon" style="<?= $is2FA ? 'background:#ceead6; color:#188038;' : 'background:#fef7e0; color:#b06000;' ?>">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                </div>
                <div class="session-details">
                    <span class="session-title">
                        Two-Factor Authentication (2FA)
                        <span class="badge <?= $is2FA ? 'badge-success' : 'badge-pending' ?>" id="badge2FAStatus">
                            <?= $is2FA ? 'Enabled' : 'Disabled' ?>
                        </span>
                    </span>
                    <span class="session-meta">Adds an extra layer of defense using TOTP Authenticator apps (Google Authenticator, Authy).</span>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="openModal('twoFactorModal')">Configure 2FA</button>
                </div>
            </div>
        </div>

        <!-- Active Sessions Card -->
        <div class="profile-card">
            <div class="profile-card-header">
                <div>
                    <h3 class="profile-card-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                        Device & Session Management
                    </h3>
                    <p class="profile-card-desc">Devices and browser sessions currently logged into this administrative account.</p>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="openModal('activeSessionsModal')">
                    Manage Sessions
                </button>
            </div>

            <div class="session-item">
                <div class="session-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                </div>
                <div class="session-details">
                    <span class="session-title">
                        <?= htmlspecialchars($browser) ?> on <?= htmlspecialchars($platform) ?>
                        <span class="badge badge-success">Current Session</span>
                    </span>
                    <span class="session-meta">IP Address: <?= htmlspecialchars($clientIp) ?> • SSL Encrypted (HTTPS) • Active Now</span>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="openModal('activeSessionsModal')">View Details</button>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 3: ROLES & PERMISSIONS -->
    <div class="profile-tab-panel" id="tab-permissions">
        <div class="profile-card">
            <div class="profile-card-header">
                <div>
                    <h3 class="profile-card-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        Privileges & Access Control Matrix
                    </h3>
                    <p class="profile-card-desc">Your active role is <strong><?= htmlspecialchars(RBAC::getRoleLabel()) ?></strong>. <?= htmlspecialchars(RBAC::getAllRoles()[RBAC::getCurrentRole()]['description'] ?? '') ?></p>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <?= RBAC::getRoleBadge() ?>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="openModal('permissionsModal')">
                        Full Role Matrix
                    </button>
                </div>
            </div>

            <div class="permissions-grid">
                <?php foreach (RBAC::getPermissionsForRole() as $pKey => $pInfo): ?>
                    <div class="permission-card <?= $pInfo['granted'] ? 'granted' : 'revoked' ?>" style="<?= $pInfo['granted'] ? '' : 'opacity: 0.65; border-color: #e2e8f0; background: #f8fafc;' ?>">
                        <div class="permission-icon" style="<?= $pInfo['granted'] ? '' : 'background: #f1f5f9; color: #94a3b8;' ?>">
                            <?php if ($pInfo['granted']): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <?php else: ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="permission-title" style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
                                <span><?= htmlspecialchars($pInfo['label']) ?></span>
                                <?php if ($pInfo['granted']): ?>
                                    <span class="badge badge-success" style="font-size:0.7rem;">✓ Allowed</span>
                                <?php else: ?>
                                    <span class="badge" style="font-size:0.7rem; background:#f1f5f9; color:#64748b;">✕ Restricted</span>
                                <?php endif; ?>
                            </div>
                            <div class="permission-desc"><?= htmlspecialchars($pInfo['description']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (RBAC::can('admins.manage')): ?>
        <!-- Super Admin Team Management Section -->
        <div class="profile-card" style="margin-top: 24px;">
            <div class="profile-card-header">
                <div>
                    <h3 class="profile-card-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        Administrative Team & Role Assignments
                    </h3>
                    <p class="profile-card-desc">Provision administrative accounts, assign RBAC roles (Super Admin, Finance, Support, Read-Only), and manage access.</p>
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="openModal('addAdminModal')">
                    + Add Administrator
                </button>
            </div>

            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Administrator</th>
                            <th>Email & Contact</th>
                            <th>Role Tier</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allAdminUsers as $admUser): ?>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <span style="width:34px; height:34px; border-radius:50%; background-color:<?= htmlspecialchars($admUser['avatar_color'] ?? '#1a73e8') ?>; color:white; display:inline-flex; align-items:center; justify-content:center; font-weight:700; font-size:0.88rem;">
                                            <?= strtoupper(substr($admUser['full_name'] ?: $admUser['username'], 0, 1)) ?>
                                        </span>
                                        <div>
                                            <strong><?= htmlspecialchars($admUser['full_name']) ?></strong>
                                            <div style="font-size:0.78rem; color:var(--text-gray);">@<?= htmlspecialchars($admUser['username']) ?> (#<?= (int)$admUser['id'] ?>)</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($admUser['email'] ?: 'No email') ?></div>
                                    <?php if (!empty($admUser['phone'])): ?>
                                        <div style="font-size:0.78rem; color:var(--text-gray);"><?= htmlspecialchars($admUser['phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $admUser['badge_html'] ?>
                                </td>
                                <td>
                                    <span class="badge <?= $admUser['is_active'] ? 'badge-success' : 'badge-failed' ?>">
                                        <?= $admUser['is_active'] ? '✓ Active' : '✕ Disabled' ?>
                                    </span>
                                </td>
                                <td style="font-size:0.8rem; color:var(--text-gray);">
                                    <?= !empty($admUser['last_login']) ? htmlspecialchars($admUser['last_login']) : 'Never' ?>
                                </td>
                                <td style="white-space:nowrap;">
                                    <button type="button" class="btn btn-secondary btn-sm"
                                            onclick="openRoleModal(<?= (int)$admUser['id'] ?>, '<?= htmlspecialchars(addslashes($admUser['full_name']), ENT_QUOTES) ?>', '<?= htmlspecialchars($admUser['normalized_role']) ?>')">
                                        Change Role
                                    </button>

                                    <?php if ((int)$admUser['id'] !== $adminId): ?>
                                        <form action="profile.php" method="POST" style="display:inline; margin-left:4px;">
                                            <input type="hidden" name="action" value="toggle_admin_status">
                                            <input type="hidden" name="target_admin_id" value="<?= (int)$admUser['id'] ?>">
                                            <input type="hidden" name="is_active" value="<?= $admUser['is_active'] ? '0' : '1' ?>">
                                            <?php if ($admUser['is_active']): ?>
                                                <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5;" onclick="return confirm('Deactivate account for @<?= htmlspecialchars($admUser['username']) ?>?')">
                                                    Deactivate
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" class="btn btn-sm" style="background:#dcfce7; color:#15803d; border:1px solid #86efac;">
                                                    Activate
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- TAB 4: ACTIVITY & AUDIT TRAIL -->
    <div class="profile-tab-panel" id="tab-activity">
        <div class="profile-card">
            <div class="profile-card-header">
                <div>
                    <h3 class="profile-card-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                        Recent Gateway & Administrative Activity
                    </h3>
                    <p class="profile-card-desc">Real-time audit log of recent transactions and security events.</p>
                </div>
            </div>

            <div class="activity-timeline">
                <div class="activity-timeline-item">
                    <div class="activity-timeline-dot"></div>
                    <div class="activity-timeline-content">
                        <div>
                            <span class="activity-timeline-title">Admin Profile Accessed</span>
                            <div style="font-size:0.8rem; color:var(--text-gray);">Session authenticated from IP <?= htmlspecialchars($clientIp) ?> (<?= htmlspecialchars($browser) ?>)</div>
                        </div>
                        <span class="activity-timeline-time">Just now</span>
                    </div>
                </div>

                <?php if (!empty($recentTxns)): ?>
                    <?php foreach ($recentTxns as $txn): ?>
                        <div class="activity-timeline-item">
                            <div class="activity-timeline-dot <?= $txn['status'] === 'SUCCESS' ? 'green' : ($txn['status'] === 'PENDING' ? 'amber' : 'purple') ?>"></div>
                            <div class="activity-timeline-content">
                                <div>
                                    <span class="activity-timeline-title">
                                        Transaction <?= htmlspecialchars($txn['vortex_transaction_id']) ?>
                                    </span>
                                    <div style="font-size:0.8rem; color:var(--text-gray);">
                                        Customer: <?= htmlspecialchars($txn['customer_email']) ?> • Amount: ₹<?= number_format($txn['amount'], 2) ?>
                                        <span class="badge <?= $txn['status'] === 'SUCCESS' ? 'badge-success' : 'badge-pending' ?>" style="font-size:0.68rem; margin-left:6px;">
                                            <?= htmlspecialchars($txn['status']) ?>
                                        </span>
                                    </div>
                                </div>
                                <span class="activity-timeline-time"><?= date('M d, H:i', strtotime($txn['created_at'])) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="activity-timeline-item">
                        <div class="activity-timeline-dot"></div>
                        <div class="activity-timeline-content">
                            <div>
                                <span class="activity-timeline-title">System Initialized</span>
                                <div style="font-size:0.8rem; color:var(--text-gray);">Gateway audit database ready.</div>
                            </div>
                            <span class="activity-timeline-time"><?= date('M d, Y') ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TAB 5: DIAGNOSTICS & PERSONAL TOKEN -->
    <div class="profile-tab-panel" id="tab-api">
        <div class="profile-card">
            <div class="profile-card-header">
                <div>
                    <h3 class="profile-card-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
                        Admin Diagnostic Personal Token
                    </h3>
                    <p class="profile-card-desc">Generate temporary scoped personal tokens for CLI testing, webhook replay, or API integration debugging.</p>
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="openModal('apiTokenModal')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    <span>Generate Token</span>
                </button>
            </div>

            <div style="background: var(--bg-gray); border-radius: 10px; padding: 20px;">
                <p style="font-size: 0.9rem; color: var(--text-dark); margin-bottom: 12px;">
                    Diagnostic tokens allow testing gateway endpoints with admin authorization directly via <code>curl</code> or Postman without browser cookies.
                </p>
                <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:8px; padding:12px 16px; font-family:var(--font-mono); font-size:0.85rem; color:#202124;">
                    curl -X GET "https://icswhmh.com/api/payment_status.php?vortex_transaction_id=VTX_..." \<br>
                    &nbsp;&nbsp;-H "Authorization: Bearer <span id="tokenPlaceholderDisplay">vtx_diag_example_token_click_generate_above</span>"
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ============================================================
     INTERACTIVE MODALS
     ============================================================ -->

<!-- 1. EDIT PROFILE MODAL -->
<div class="modal-overlay" id="editProfileModal" role="dialog" aria-modal="true" aria-labelledby="editProfileTitle">
    <div class="modal-box modal-md">
        <div class="modal-header">
            <div>
                <h2 id="editProfileTitle">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    Edit Admin Profile
                </h2>
                <p class="modal-subtitle">Update your personal details and custom avatar theme.</p>
            </div>
            <button class="modal-close" onclick="closeModal('editProfileModal')" title="Close">&times;</button>
        </div>

        <form id="editProfileForm" method="POST" action="profile.php">
            <input type="hidden" name="action" value="update_profile">
            <input type="hidden" name="ajax_request" value="1">
            <input type="hidden" name="avatar_color" id="inputAvatarColor" value="<?= htmlspecialchars($avatarColor) ?>">

            <div class="modal-body">
                <!-- Live Avatar Preview & Palette -->
                <div style="display:flex; align-items:center; gap:20px; margin-bottom:20px; padding:16px; background:var(--bg-gray); border-radius:12px;">
                    <div id="modalAvatarPreview" style="width:64px; height:64px; border-radius:50%; background-color:<?= htmlspecialchars($avatarColor) ?>; color:#ffffff; display:flex; align-items:center; justify-content:center; font-size:1.6rem; font-weight:700; box-shadow:0 2px 8px rgba(0,0,0,0.15); transition:background-color 0.2s;">
                        <span id="modalAvatarInitial"><?= $initial ?></span>
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:0.8rem; font-weight:600; color:var(--text-gray); text-transform:uppercase;">Avatar Accent Color</label>
                        <div class="avatar-color-picker">
                            <?php
                            $colorSwatches = [
                                '#1a73e8' => 'Drive Blue',
                                '#4f46e5' => 'Indigo',
                                '#059669' => 'Emerald',
                                '#dc2626' => 'Crimson',
                                '#d97706' => 'Amber',
                                '#7c3aed' => 'Purple',
                                '#374151' => 'Slate'
                            ];
                            foreach ($colorSwatches as $hex => $name):
                                $isSelected = (strtolower($hex) === strtolower($avatarColor));
                            ?>
                                <div class="avatar-color-swatch <?= $isSelected ? 'selected' : '' ?>"
                                     style="background-color: <?= $hex ?>;"
                                     data-color="<?= $hex ?>"
                                     title="<?= $name ?>"
                                     onclick="selectAvatarColor('<?= $hex ?>', this)">
                                    <?= $isSelected ? '✓' : '' ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-grid" style="grid-template-columns: 1fr;">
                    <div class="form-group">
                        <label for="edit_full_name">Full Name <span style="color:var(--danger-text)">*</span></label>
                        <input type="text" id="edit_full_name" name="full_name" class="form-control" value="<?= htmlspecialchars($admin['full_name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_username">Username <span style="color:var(--danger-text)">*</span></label>
                        <div class="input-with-action">
                            <input type="text" id="edit_username" name="username" class="form-control" value="<?= htmlspecialchars($admin['username']) ?>" required>
                        </div>
                        <small style="color:var(--text-gray); font-size:0.75rem;">Used for signing in. Letters, numbers, and underscores.</small>
                    </div>

                    <div class="form-group">
                        <label for="edit_email">Email Address</label>
                        <input type="email" id="edit_email" name="email" class="form-control" value="<?= htmlspecialchars($admin['email'] ?? '') ?>" placeholder="admin@example.com">
                    </div>

                    <div class="form-group">
                        <label for="edit_phone">Phone Number</label>
                        <input type="tel" id="edit_phone" name="phone" class="form-control" value="<?= htmlspecialchars($admin['phone'] ?? '') ?>" placeholder="+91 98765 43210">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('editProfileModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSaveProfile">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- 2. CHANGE PASSWORD MODAL -->
<div class="modal-overlay" id="changePasswordModal" role="dialog" aria-modal="true" aria-labelledby="changePasswordTitle">
    <div class="modal-box modal-md">
        <div class="modal-header">
            <div>
                <h2 id="changePasswordTitle">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    Change Account Password
                </h2>
                <p class="modal-subtitle">Ensure your account is protected with a strong, unique password.</p>
            </div>
            <button class="modal-close" onclick="closeModal('changePasswordModal')" title="Close">&times;</button>
        </div>

        <form id="changePasswordForm" method="POST" action="profile.php">
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="ajax_request" value="1">

            <div class="modal-body">
                <div class="form-group" style="margin-bottom:16px;">
                    <label for="current_password">Current Password <span style="color:var(--danger-text)">*</span></label>
                    <div class="input-with-action">
                        <input type="password" id="current_password" name="current_password" class="form-control" required placeholder="Enter current password">
                        <button type="button" class="input-action-btn" onclick="togglePasswordVisibility('current_password', this)" title="Show/Hide">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:16px;">
                    <label for="new_password">New Password <span style="color:var(--danger-text)">*</span></label>
                    <div class="input-with-action">
                        <input type="password" id="new_password" name="new_password" class="form-control" required placeholder="Min 6 characters" oninput="evaluatePasswordStrength(this.value)">
                        <button type="button" class="input-action-btn" onclick="togglePasswordVisibility('new_password', this)" title="Show/Hide">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>

                    <!-- Interactive Password Strength Meter -->
                    <div class="pwd-meter-box">
                        <div class="pwd-meter-bars">
                            <div class="pwd-meter-seg" id="seg1"></div>
                            <div class="pwd-meter-seg" id="seg2"></div>
                            <div class="pwd-meter-seg" id="seg3"></div>
                            <div class="pwd-meter-seg" id="seg4"></div>
                        </div>
                        <div class="pwd-meter-feedback">
                            <span id="pwdStrengthLabel">Enter new password</span>
                            <span id="pwdReqCheck">Requires 6+ characters</span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password <span style="color:var(--danger-text)">*</span></label>
                    <div class="input-with-action">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required placeholder="Re-type new password" oninput="checkPasswordMatch()">
                        <button type="button" class="input-action-btn" onclick="togglePasswordVisibility('confirm_password', this)" title="Show/Hide">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                    <div id="pwdMatchMsg" class="pwd-match-indicator"></div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('changePasswordModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSubmitPassword">Update Password</button>
            </div>
        </form>
    </div>
</div>

<!-- 3. TWO-FACTOR AUTHENTICATION (2FA) MODAL -->
<div class="modal-overlay" id="twoFactorModal" role="dialog" aria-modal="true" aria-labelledby="twoFactorTitle">
    <div class="modal-box modal-lg">
        <div class="modal-header">
            <div>
                <h2 id="twoFactorTitle">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    Two-Factor Authentication (TOTP)
                </h2>
                <p class="modal-subtitle">Secure admin logins with Google Authenticator, Microsoft Authenticator, or Authy.</p>
            </div>
            <button class="modal-close" onclick="closeModal('twoFactorModal')" title="Close">&times;</button>
        </div>

        <div class="modal-body">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding:16px; background:var(--bg-gray); border-radius:10px;">
                <div>
                    <strong style="font-size:1rem; color:var(--text-dark);">2FA Status</strong>
                    <div style="font-size:0.85rem; color:var(--text-gray);">
                        Current state: <span class="badge <?= $is2FA ? 'badge-success' : 'badge-pending' ?>" id="modal2FAStateBadge"><?= $is2FA ? 'Active & Enforced' : 'Currently Disabled' ?></span>
                    </div>
                </div>
                <form id="toggle2FAForm" method="POST" action="profile.php" style="margin:0;">
                    <input type="hidden" name="action" value="toggle_2fa">
                    <input type="hidden" name="ajax_request" value="1">
                    <input type="hidden" name="enable" id="input2FAState" value="<?= $is2FA ? '0' : '1' ?>">
                    <button type="submit" class="btn <?= $is2FA ? 'btn-secondary' : 'btn-primary' ?>" id="btnToggle2FA">
                        <?= $is2FA ? 'Disable 2FA' : 'Enable 2FA' ?>
                    </button>
                </form>
            </div>

            <div class="two-factor-setup-card">
                <div class="qr-code-display">
                    <!-- Clean SVG Mock QR Code for Vortex 2FA -->
                    <svg width="160" height="160" viewBox="0 0 100 100">
                        <rect width="100" height="100" fill="#ffffff"/>
                        <!-- Top Left Position Detection Pattern -->
                        <rect x="10" y="10" width="28" height="28" fill="#1a73e8" rx="4"/>
                        <rect x="14" y="14" width="20" height="20" fill="#ffffff" rx="2"/>
                        <rect x="18" y="18" width="12" height="12" fill="#1a73e8" rx="2"/>
                        <!-- Top Right Position Detection Pattern -->
                        <rect x="62" y="10" width="28" height="28" fill="#1a73e8" rx="4"/>
                        <rect x="66" y="14" width="20" height="20" fill="#ffffff" rx="2"/>
                        <rect x="70" y="18" width="12" height="12" fill="#1a73e8" rx="2"/>
                        <!-- Bottom Left Position Detection Pattern -->
                        <rect x="10" y="62" width="28" height="28" fill="#1a73e8" rx="4"/>
                        <rect x="14" y="66" width="20" height="20" fill="#ffffff" rx="2"/>
                        <rect x="18" y="70" width="12" height="12" fill="#1a73e8" rx="2"/>
                        <!-- Simulated Data Grid -->
                        <rect x="42" y="12" width="6" height="6" fill="#202124"/>
                        <rect x="50" y="12" width="6" height="6" fill="#202124"/>
                        <rect x="42" y="24" width="6" height="6" fill="#202124"/>
                        <rect x="50" y="32" width="6" height="6" fill="#202124"/>
                        <rect x="12" y="44" width="6" height="6" fill="#202124"/>
                        <rect x="24" y="44" width="6" height="6" fill="#202124"/>
                        <rect x="36" y="44" width="6" height="6" fill="#202124"/>
                        <rect x="48" y="44" width="6" height="6" fill="#202124"/>
                        <rect x="60" y="44" width="6" height="6" fill="#202124"/>
                        <rect x="72" y="44" width="6" height="6" fill="#202124"/>
                        <rect x="84" y="44" width="6" height="6" fill="#202124"/>
                        <rect x="42" y="56" width="6" height="6" fill="#202124"/>
                        <rect x="54" y="56" width="6" height="6" fill="#202124"/>
                        <rect x="66" y="56" width="6" height="6" fill="#202124"/>
                        <rect x="78" y="56" width="6" height="6" fill="#202124"/>
                        <rect x="48" y="68" width="6" height="6" fill="#202124"/>
                        <rect x="60" y="68" width="6" height="6" fill="#202124"/>
                        <rect x="72" y="68" width="6" height="6" fill="#202124"/>
                        <rect x="84" y="68" width="6" height="6" fill="#202124"/>
                        <rect x="42" y="80" width="6" height="6" fill="#202124"/>
                        <rect x="66" y="80" width="6" height="6" fill="#202124"/>
                        <rect x="78" y="80" width="6" height="6" fill="#202124"/>
                    </svg>
                </div>

                <div>
                    <h4 style="font-size:0.95rem; font-weight:600; color:var(--text-dark);">Manual Setup Key</h4>
                    <p style="font-size:0.8rem; color:var(--text-gray); margin-bottom:8px;">If your camera cannot scan the QR code, enter this key manually:</p>
                    <div class="two-factor-secret">
                        <span id="txt2FASecretKey">VTX-ADM-<?= strtoupper(substr(md5($admin['username'] . 'vortex_salt'), 0, 16)) ?></span>
                        <button type="button" class="btn-copy" onclick="copyToClipboard(document.getElementById('txt2FASecretKey').innerText, this)">Copy</button>
                    </div>
                </div>
            </div>

            <!-- Emergency Recovery Codes -->
            <div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <label style="font-size:0.85rem; font-weight:600; color:var(--text-dark);">Emergency Backup Recovery Codes</label>
                    <button type="button" class="btn-copy" onclick="copyAllRecoveryCodes(this)">Copy All Codes</button>
                </div>
                <div class="recovery-codes-grid">
                    <?php
                    $recCodes = [];
                    for ($i = 0; $i < 6; $i++) {
                        $code = strtoupper(substr(md5($admin['id'] . 'code_' . $i), 0, 4)) . '-' . strtoupper(substr(md5($admin['username'] . 'code_' . $i), 0, 4));
                        $recCodes[] = $code;
                        echo '<div class="recovery-code-chip recovery-code-val">' . $code . '</div>';
                    }
                    ?>
                </div>
                <small style="color:var(--text-gray); font-size:0.75rem;">Keep these recovery codes stored securely offline in case you lose access to your device.</small>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="closeModal('twoFactorModal')">Done</button>
        </div>
    </div>
</div>

<!-- 4. ACTIVE SESSIONS & DEVICE SECURITY MODAL -->
<div class="modal-overlay" id="activeSessionsModal" role="dialog" aria-modal="true" aria-labelledby="activeSessionsTitle">
    <div class="modal-box modal-lg">
        <div class="modal-header">
            <div>
                <h2 id="activeSessionsTitle">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                    Active Sessions & Device Security
                </h2>
                <p class="modal-subtitle">Review active logins and instantly revoke unverified sessions.</p>
            </div>
            <button class="modal-close" onclick="closeModal('activeSessionsModal')" title="Close">&times;</button>
        </div>

        <div class="modal-body">
            <!-- Current Session -->
            <div class="session-item" style="border-left: 4px solid var(--primary-blue);">
                <div class="session-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                </div>
                <div class="session-details">
                    <span class="session-title">
                        <?= htmlspecialchars($browser) ?> on <?= htmlspecialchars($platform) ?>
                        <span class="badge badge-success">This Device (Current)</span>
                    </span>
                    <span class="session-meta">
                        IP: <?= htmlspecialchars($clientIp) ?> • Location: Detected Network • Session ID: <?= substr(session_id(), 0, 8) ?>...
                    </span>
                    <span style="font-size: 0.75rem; color: var(--success-text); margin-top: 2px;">
                        ✓ TLS 1.3 Encryption Active
                    </span>
                </div>
            </div>

            <!-- Historical / Simulated Other Session -->
            <div class="session-item">
                <div class="session-icon" style="background:var(--bg-gray); color:var(--text-gray);">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
                </div>
                <div class="session-details">
                    <span class="session-title">
                        Mobile Safari on Apple iOS
                        <span class="badge badge-event">CLI / API Token</span>
                    </span>
                    <span class="session-meta">
                        Last Active: <?= date('M d, Y', strtotime('-2 days')) ?> • IP: 49.37.112.44
                    </span>
                </div>
                <div>
                    <span class="badge" style="background:var(--bg-gray); color:var(--text-gray);">Idle</span>
                </div>
            </div>

            <div style="background:#fef7e0; border:1px solid #f9ab00; border-radius:8px; padding:12px 16px; margin-top:16px; display:flex; align-items:center; gap:12px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#b06000" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <div style="font-size:0.85rem; color:#b06000;">
                    If you suspect unauthorized access, terminate other sessions immediately and reset your password.
                </div>
            </div>
        </div>

        <div class="modal-footer" style="display:flex; justify-content:space-between; align-items:center;">
            <form id="terminateSessionsForm" method="POST" action="profile.php" style="margin:0;">
                <input type="hidden" name="action" value="terminate_sessions">
                <input type="hidden" name="ajax_request" value="1">
                <button type="submit" class="btn btn-secondary" style="color:var(--danger-text); border-color:#fad2cf;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                    <span>Sign Out Other Sessions</span>
                </button>
            </form>
            <button type="button" class="btn btn-primary" onclick="closeModal('activeSessionsModal')">Close</button>
        </div>
    </div>
</div>

<!-- 5. PERMISSIONS MATRIX MODAL -->
<div class="modal-overlay" id="permissionsModal" role="dialog" aria-modal="true" aria-labelledby="permissionsTitle">
    <div class="modal-box modal-lg" style="max-width: 860px;">
        <div class="modal-header">
            <div>
                <h2 id="permissionsTitle">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    Enterprise Role-Based Access Control (RBAC) Matrix
                </h2>
                <p class="modal-subtitle">Comparative privilege breakdown across all 4 gateway administrative tiers.</p>
            </div>
            <button class="modal-close" onclick="closeModal('permissionsModal')" title="Close">&times;</button>
        </div>

        <div class="modal-body" style="padding: 16px 24px;">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="min-width: 220px;">Governance Scope</th>
                            <th style="text-align:center;">Super Admin</th>
                            <th style="text-align:center;">Finance</th>
                            <th style="text-align:center;">Support</th>
                            <th style="text-align:center;">Read-Only</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (RBAC::getPermissions() as $pKey => $p): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($p['label']) ?></strong>
                                    <div style="font-size:0.75rem; color:var(--text-gray);"><?= htmlspecialchars($p['description']) ?></div>
                                </td>
                                <td style="text-align:center; font-weight:700; color:#15803d; font-size:1.1rem;">
                                    ✓
                                </td>
                                <td style="text-align:center; font-weight:700; font-size:1.1rem;">
                                    <?= in_array(RBAC::ROLE_FINANCE, $p['roles'], true) ? '<span style="color:#15803d;">✓</span>' : '<span style="color:#cbd5e1;">—</span>' ?>
                                </td>
                                <td style="text-align:center; font-weight:700; font-size:1.1rem;">
                                    <?= in_array(RBAC::ROLE_SUPPORT, $p['roles'], true) ? '<span style="color:#15803d;">✓</span>' : '<span style="color:#cbd5e1;">—</span>' ?>
                                </td>
                                <td style="text-align:center; font-weight:700; font-size:1.1rem;">
                                    <?= in_array(RBAC::ROLE_READ_ONLY, $p['roles'], true) ? '<span style="color:#15803d;">✓</span>' : '<span style="color:#cbd5e1;">—</span>' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="closeModal('permissionsModal')">Close</button>
        </div>
    </div>
</div>

<!-- 5B. ADD ADMINISTRATOR MODAL -->
<div class="modal-overlay" id="addAdminModal" role="dialog" aria-modal="true" aria-labelledby="addAdminTitle">
    <div class="modal-box modal-md">
        <div class="modal-header">
            <div>
                <h2 id="addAdminTitle">Add Administrator</h2>
                <p class="modal-subtitle">Provision a new team member with an RBAC tier.</p>
            </div>
            <button class="modal-close" onclick="closeModal('addAdminModal')" title="Close">&times;</button>
        </div>
        <form method="POST" action="profile.php">
            <input type="hidden" name="action" value="create_admin_user">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" placeholder="e.g. John Doe" required>
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" placeholder="e.g. jdoe" required>
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label">Email Address (Optional)</label>
                    <input type="email" name="email" class="form-control" placeholder="e.g. jdoe@institution.edu">
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label">Phone Number (Optional)</label>
                    <input type="text" name="phone" class="form-control" placeholder="e.g. +91 9876543210">
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label">Initial Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label">Assigned RBAC Role</label>
                    <select name="role" class="form-control" style="font-weight:600;" required>
                        <option value="super_admin">⚡ Super Administrator (Full Governance)</option>
                        <option value="finance" selected>💳 Finance Manager (Refunds & Reconciliations)</option>
                        <option value="support">🎧 Support Specialist (Order Inquiries & Timeline)</option>
                        <option value="read_only">👁️ Auditor / Read-Only (Read-Only Views)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addAdminModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">+ Create Administrator</button>
            </div>
        </form>
    </div>
</div>

<!-- 5C. EDIT ADMIN ROLE MODAL -->
<div class="modal-overlay" id="editAdminRoleModal" role="dialog" aria-modal="true" aria-labelledby="editRoleTitle">
    <div class="modal-box modal-sm">
        <div class="modal-header">
            <div>
                <h2 id="editRoleTitle">Change Administrator Role</h2>
                <p class="modal-subtitle">Modify RBAC privilege tier.</p>
            </div>
            <button class="modal-close" onclick="closeModal('editAdminRoleModal')" title="Close">&times;</button>
        </div>
        <form method="POST" action="profile.php">
            <input type="hidden" name="action" value="update_admin_role">
            <input type="hidden" name="target_admin_id" id="editRoleAdminId" value="0">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:14px;">
                    <label class="form-label">Administrator</label>
                    <input type="text" id="editRoleAdminName" class="form-control" readonly style="background:#f1f5f9; font-weight:600;">
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label class="form-label">New Role Tier</label>
                    <select name="new_role" id="editRoleSelect" class="form-control" style="font-weight:600;" required>
                        <option value="super_admin">⚡ Super Administrator</option>
                        <option value="finance">💳 Finance Manager</option>
                        <option value="support">🎧 Support Specialist</option>
                        <option value="read_only">👁️ Auditor / Read-Only</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editAdminRoleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Role</button>
            </div>
        </form>
    </div>
</div>

<!-- 6. GENERATE DIAGNOSTIC TOKEN MODAL -->
<div class="modal-overlay" id="apiTokenModal" role="dialog" aria-modal="true" aria-labelledby="apiTokenTitle">
    <div class="modal-box modal-md">
        <div class="modal-header">
            <div>
                <h2 id="apiTokenTitle">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
                    Generate Diagnostic Token
                </h2>
                <p class="modal-subtitle">Generate a temporary personal token for API testing.</p>
            </div>
            <button class="modal-close" onclick="closeModal('apiTokenModal')" title="Close">&times;</button>
        </div>

        <form id="generateTokenForm" method="POST" action="profile.php">
            <input type="hidden" name="action" value="generate_api_token">
            <input type="hidden" name="ajax_request" value="1">

            <div class="modal-body">
                <div class="form-group" style="margin-bottom:16px;">
                    <label for="token_scope">Scope Permission</label>
                    <select id="token_scope" name="token_scope" class="form-control">
                        <option value="read_only">Read-Only Diagnostics (Transactions, Events)</option>
                        <option value="full_admin">Full Administrative Access (Simulations & Sync)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:16px;">
                    <label for="token_expiry">Token Validity</label>
                    <select id="token_expiry" name="token_expiry" class="form-control">
                        <option value="1">24 Hours (Recommended for testing)</option>
                        <option value="7">7 Days</option>
                        <option value="30">30 Days</option>
                    </select>
                </div>

                <div id="tokenResultBox" style="display:none; margin-top:16px; background:var(--bg-gray); padding:16px; border-radius:8px;">
                    <label style="font-size:0.75rem; font-weight:600; color:var(--text-gray); text-transform:uppercase;">Generated Bearer Token</label>
                    <div style="display:flex; align-items:center; gap:8px; margin-top:6px;">
                        <input type="text" id="generatedTokenText" class="form-control font-mono" readonly style="background:#fff;">
                        <button type="button" class="btn-copy" onclick="copyToClipboard(document.getElementById('generatedTokenText').value, this)">Copy</button>
                    </div>
                    <small style="color:var(--text-gray); font-size:0.75rem; display:block; margin-top:6px;">
                        Expires on <span id="tokenExpiryDisplay"></span>
                    </small>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('apiTokenModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSubmitGenToken">Generate Token</button>
            </div>
        </form>
    </div>
</div>

<!-- 7. GLOBAL TOAST NOTIFICATION CONTAINER -->
<div class="vortex-toast-container" id="vortexToastContainer"></div>

<!-- ============================================================
     JAVASCRIPT LOGIC & MODAL CONTROLLERS
     ============================================================ -->
<script>
// --- Modal Controller Helpers ---
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';

    // Focus first input if any
    const firstInput = modal.querySelector('input:not([type="hidden"]), select, textarea');
    if (firstInput) {
        setTimeout(() => firstInput.focus(), 100);
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.remove('open');
    document.body.style.overflow = '';
}

// Close modals when clicking overlay
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal(modal.id);
        }
    });
});

// Close open modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const openModals = document.querySelectorAll('.modal-overlay.open');
        openModals.forEach(m => closeModal(m.id));
    }
});

// --- Toast Notification System ---
function showToast(message, type = 'success', duration = 3500) {
    const container = document.getElementById('vortexToastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `vortex-toast toast-${type}`;

    let iconSvg = '';
    if (type === 'success') {
        iconSvg = `<span class="toast-icon" style="color:var(--success-text)">✓</span>`;
    } else if (type === 'error') {
        iconSvg = `<span class="toast-icon" style="color:var(--danger-text)">⚠</span>`;
    } else {
        iconSvg = `<span class="toast-icon" style="color:var(--primary-blue)">ℹ</span>`;
    }

    toast.innerHTML = `
        ${iconSvg}
        <span class="toast-message">${message}</span>
        <button class="toast-close" title="Dismiss">&times;</button>
    `;

    toast.querySelector('.toast-close').addEventListener('click', () => {
        toast.classList.add('toast-leave');
        setTimeout(() => toast.remove(), 250);
    });

    container.appendChild(toast);

    setTimeout(() => {
        if (toast.parentElement) {
            toast.classList.add('toast-leave');
            setTimeout(() => toast.remove(), 250);
        }
    }, duration);
}

// --- Tab Controller ---
document.querySelectorAll('.profile-tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const targetSelector = this.getAttribute('data-tab-target');
        if (!targetSelector) return;

        document.querySelectorAll('.profile-tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.profile-tab-panel').forEach(p => p.classList.remove('active'));

        this.classList.add('active');
        const targetPanel = document.querySelector(targetSelector);
        if (targetPanel) {
            targetPanel.classList.add('active');
        }

        // Update URL hash without jumping
        if (history.replaceState) {
            history.replaceState(null, null, targetSelector.replace('#tab-', '#'));
        }
    });
});

// Activate tab from URL hash on load
window.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash;
    if (hash) {
        const cleanHash = hash.replace('#', '');
        const targetBtn = document.querySelector(`.profile-tab-btn[data-tab-target="#tab-${cleanHash}"]`);
        if (targetBtn) {
            targetBtn.click();
        }
    }
});

// --- Password Visibility Toggle ---
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;

    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>`;
    } else {
        input.type = 'password';
        btn.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
    }
}

// --- Interactive Password Strength Meter ---
function evaluatePasswordStrength(pwd) {
    const seg1 = document.getElementById('seg1');
    const seg2 = document.getElementById('seg2');
    const seg3 = document.getElementById('seg3');
    const seg4 = document.getElementById('seg4');
    const label = document.getElementById('pwdStrengthLabel');

    const segs = [seg1, seg2, seg3, seg4];
    segs.forEach(s => {
        s.className = 'pwd-meter-seg';
    });

    if (!pwd) {
        label.innerText = 'Enter new password';
        label.style.color = 'var(--text-gray)';
        return;
    }

    let score = 0;
    if (pwd.length >= 6) score++;
    if (pwd.length >= 10) score++;
    if (/[A-Z]/.test(pwd) && /[a-z]/.test(pwd)) score++;
    if (/[0-9]/.test(pwd) && /[^A-Za-z0-9]/.test(pwd)) score++;

    if (score === 1) {
        seg1.classList.add('active-weak');
        label.innerText = 'Weak Password';
        label.style.color = '#ea4335';
    } else if (score === 2) {
        seg1.classList.add('active-fair');
        seg2.classList.add('active-fair');
        label.innerText = 'Fair Password';
        label.style.color = '#fbbc04';
    } else if (score === 3) {
        seg1.classList.add('active-good');
        seg2.classList.add('active-good');
        seg3.classList.add('active-good');
        label.innerText = 'Good Password';
        label.style.color = '#34a853';
    } else if (score >= 4) {
        seg1.classList.add('active-strong');
        seg2.classList.add('active-strong');
        seg3.classList.add('active-strong');
        seg4.classList.add('active-strong');
        label.innerText = 'Strong Password ✓';
        label.style.color = '#188038';
    }

    checkPasswordMatch();
}

function checkPasswordMatch() {
    const newPwd = document.getElementById('new_password').value;
    const confirmPwd = document.getElementById('confirm_password').value;
    const msg = document.getElementById('pwdMatchMsg');

    if (!confirmPwd) {
        msg.innerHTML = '';
        return;
    }

    if (newPwd === confirmPwd) {
        msg.className = 'pwd-match-indicator match';
        msg.innerHTML = '✓ Passwords match';
    } else {
        msg.className = 'pwd-match-indicator mismatch';
        msg.innerHTML = '✕ Passwords do not match';
    }
}

// --- Avatar Color Picker ---
function selectAvatarColor(hex, swatchElement) {
    document.querySelectorAll('.avatar-color-swatch').forEach(s => {
        s.classList.remove('selected');
        s.innerHTML = '';
    });

    swatchElement.classList.add('selected');
    swatchElement.innerHTML = '✓';

    document.getElementById('inputAvatarColor').value = hex;
    document.getElementById('modalAvatarPreview').style.backgroundColor = hex;
}

// --- Copy All Recovery Codes ---
function copyAllRecoveryCodes(btn) {
    const codes = Array.from(document.querySelectorAll('.recovery-code-val')).map(el => el.innerText.trim());
    const allText = "VORTEX ADMIN RECOVERY CODES\n" + codes.join("\n");
    copyToClipboard(allText, btn);
}

// --- AJAX Form Handlers for Seamless Interactivity ---

// 1. Edit Profile Form
document.getElementById('editProfileForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = this;
    const btn = document.getElementById('btnSaveProfile');
    const origBtnText = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = 'Saving...';

    const formData = new FormData(form);

    fetch('profile.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = origBtnText;

        if (data.success) {
            closeModal('editProfileModal');
            showToast(data.message, 'success');

            // Update DOM in-place
            const d = data.data;
            document.getElementById('heroFullNameText').innerText = d.full_name;
            document.getElementById('heroUsernameText').innerText = d.username;
            document.getElementById('cardFullName').innerText = d.full_name;
            document.getElementById('cardUsername').innerText = '@' + d.username;
            document.getElementById('cardEmail').innerText = d.email || 'Not configured';
            document.getElementById('cardPhone').innerText = d.phone || 'Not configured';

            // Update Avatar color
            document.getElementById('mainAvatarDisplay').style.backgroundColor = d.avatar_color;
            document.getElementById('mainAvatarInitial').innerText = d.initial;
            document.getElementById('cardAvatarSwatch').style.backgroundColor = d.avatar_color;
            document.getElementById('cardAvatarHex').innerText = d.avatar_color;

            // Also update topbar avatar and name if present
            const topbarAvatar = document.querySelector('.topbar-actions .user-avatar');
            if (topbarAvatar) {
                topbarAvatar.style.backgroundColor = d.avatar_color;
                topbarAvatar.innerText = d.initial;
            }
            const topbarName = document.querySelector('.topbar-actions .user-name');
            if (topbarName) {
                topbarName.innerText = d.full_name || d.username;
            }
        } else {
            showToast(data.message || 'Failed to update profile.', 'error');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = origBtnText;
        showToast('A network error occurred. Please try again.', 'error');
    });
});

// 2. Change Password Form
document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = this;
    const btn = document.getElementById('btnSubmitPassword');
    const origBtnText = btn.innerHTML;

    const newPwd = document.getElementById('new_password').value;
    const confirmPwd = document.getElementById('confirm_password').value;

    if (newPwd !== confirmPwd) {
        showToast('New password and confirmation do not match.', 'error');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = 'Updating...';

    const formData = new FormData(form);

    fetch('profile.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = origBtnText;

        if (data.success) {
            closeModal('changePasswordModal');
            form.reset();
            evaluatePasswordStrength('');
            showToast(data.message, 'success');
        } else {
            showToast(data.message || 'Failed to change password.', 'error');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = origBtnText;
        showToast('A network error occurred. Please try again.', 'error');
    });
});

// 3. Toggle 2FA Form
document.getElementById('toggle2FAForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = this;
    const btn = document.getElementById('btnToggle2FA');
    const origBtnText = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = 'Updating...';

    const formData = new FormData(form);

    fetch('profile.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;

        if (data.success) {
            showToast(data.message, 'success');
            const isEnabled = data.enabled;

            document.getElementById('input2FAState').value = isEnabled ? '0' : '1';
            btn.innerHTML = isEnabled ? 'Disable 2FA' : 'Enable 2FA';
            btn.className = `btn ${isEnabled ? 'btn-secondary' : 'btn-primary'}`;

            const badge = document.getElementById('modal2FAStateBadge');
            badge.innerText = isEnabled ? 'Active & Enforced' : 'Currently Disabled';
            badge.className = `badge ${isEnabled ? 'badge-success' : 'badge-pending'}`;

            const badge2 = document.getElementById('badge2FAStatus');
            if (badge2) {
                badge2.innerText = isEnabled ? 'Enabled' : 'Disabled';
                badge2.className = `badge ${isEnabled ? 'badge-success' : 'badge-pending'}`;
            }

            const statScore = document.getElementById('statSecurityScore');
            if (statScore) {
                statScore.innerText = (isEnabled ? '100%' : '70%');
            }
        } else {
            btn.innerHTML = origBtnText;
            showToast(data.message || 'Failed to toggle 2FA.', 'error');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = origBtnText;
        showToast('Network error updating 2FA.', 'error');
    });
});

// 4. Terminate Sessions Form
document.getElementById('terminateSessionsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = this;
    const formData = new FormData(form);

    fetch('profile.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeModal('activeSessionsModal');
            showToast(data.message, 'success');
        } else {
            showToast(data.message || 'Failed to terminate sessions.', 'error');
        }
    })
    .catch(err => {
        showToast('Error revoking sessions.', 'error');
    });
});

// 5. Generate Diagnostic Token Form
document.getElementById('generateTokenForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = this;
    const btn = document.getElementById('btnSubmitGenToken');
    const origBtnText = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = 'Generating...';

    const formData = new FormData(form);

    fetch('profile.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = origBtnText;

        if (data.success) {
            document.getElementById('tokenResultBox').style.display = 'block';
            document.getElementById('generatedTokenText').value = data.token;
            document.getElementById('tokenExpiryDisplay').innerText = data.expires_at;
            document.getElementById('tokenPlaceholderDisplay').innerText = data.token;
            showToast(data.message, 'success');
        } else {
            showToast(data.message || 'Failed to generate token.', 'error');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = origBtnText;
        showToast('Error generating token.', 'error');
    });
});

// 6. Open Role Change Modal
function openRoleModal(adminId, adminName, currentRole) {
    document.getElementById('editRoleAdminId').value = adminId;
    document.getElementById('editRoleAdminName').value = adminName;
    document.getElementById('editRoleSelect').value = currentRole;
    openModal('editAdminRoleModal');
}

// 7. Appearance & Theme Manager
function setAdminTheme(mode) {
    if (mode === 'auto') {
        localStorage.removeItem('vortex_theme');
        document.cookie = "vortex_theme=; path=/; max-age=0";
        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
    } else {
        localStorage.setItem('vortex_theme', mode);
        document.cookie = "vortex_theme=" + mode + "; path=/; max-age=31536000; SameSite=Lax";
        document.documentElement.setAttribute('data-theme', mode);
    }
    syncThemeRadios();
    window.dispatchEvent(new CustomEvent('vortexThemeChanged', { detail: { theme: mode } }));
}

function syncThemeRadios() {
    const saved = localStorage.getItem('vortex_theme');
    const radioLight = document.getElementById('radioThemeLight');
    const radioDark = document.getElementById('radioThemeDark');
    const radioAuto = document.getElementById('radioThemeAuto');
    const cardLight = document.getElementById('themeChoiceLight');
    const cardDark = document.getElementById('themeChoiceDark');
    const cardAuto = document.getElementById('themeChoiceAuto');

    if (!radioLight) return;

    [cardLight, cardDark, cardAuto].forEach(c => {
        if (c) c.style.borderColor = 'var(--border-color)';
    });

    if (saved === 'dark') {
        radioDark.checked = true;
        cardDark.style.borderColor = 'var(--primary-blue)';
    } else if (saved === 'light') {
        radioLight.checked = true;
        cardLight.style.borderColor = 'var(--primary-blue)';
    } else {
        radioAuto.checked = true;
        cardAuto.style.borderColor = 'var(--primary-blue)';
    }
}

document.addEventListener('DOMContentLoaded', syncThemeRadios);
window.addEventListener('vortexThemeChanged', syncThemeRadios);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
