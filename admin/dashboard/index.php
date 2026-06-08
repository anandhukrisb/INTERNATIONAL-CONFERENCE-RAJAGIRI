<?php
/**
 * Admin Dashboard — index.php
 * ICSWHMH 2027 Admin Panel — Phase 1
 */
declare(strict_types=1);

require_once __DIR__ . '/../auth/verify-session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/mailer.php';

$pageTitle  = 'Dashboard';
$adminId    = (int) $_SESSION['admin_id'];
$adminName  = $_SESSION['admin_name']  ?? 'Admin';
$adminRole  = $_SESSION['admin_role']  ?? 'admin';
$loginTime  = $_SESSION['login_time']  ?? time();

// ── Fetch data ─────────────────────────────────────────────────────────────────
$pdo = DB::get();

// Current user details (last login + IP)
$user = get_user_by_id($adminId);

// Recent login activity from login_attempts
$recentLogins = $pdo->prepare(
    'SELECT la.*, au.full_name
     FROM login_attempts la
     LEFT JOIN admin_users au ON au.email = la.email
     ORDER BY la.attempted_at DESC
     LIMIT 10'
);
$recentLogins->execute();
$recentLoginRows = $recentLogins->fetchAll();

// Recent audit logs for this user
$auditRows = get_audit_logs(8, $adminId);

// System stats
$totalAdmins = (int) $pdo->query('SELECT COUNT(*) FROM admin_users WHERE status != "inactive"')->fetchColumn();
$lockedCount = (int) $pdo->query('SELECT COUNT(*) FROM admin_users WHERE status = "locked"')->fetchColumn();
$failedToday = (int) $pdo->query(
    "SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND DATE(attempted_at) = CURDATE()"
)->fetchColumn();
$successToday = (int) $pdo->query(
    "SELECT COUNT(*) FROM login_attempts WHERE success = 1 AND DATE(attempted_at) = CURDATE()"
)->fetchColumn();

// PHPMailer status
$mailerOk = file_exists(ADMIN_ROOT . '/vendor/autoload.php')
         || is_dir(ADMIN_ROOT . '/vendor/phpmailer/src/');

// Current session info
$sessionAge   = time() - $loginTime;
$sessionMins  = floor($sessionAge / 60);
$sessionSecs  = $sessionAge % 60;

include __DIR__ . '/../includes/header.php';
?>

<!-- ═══════════════════════════════════════════════════════ PAGE CONTENT ══ -->

<!-- Welcome Banner -->
<div class="welcome-banner">
    <div class="welcome-banner-content">
        <div>
            <h2 class="welcome-title">
                <?php
                $hour = (int) date('H');
                $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
                echo e($greeting) . ', ' . e(explode(' ', $adminName)[0]) . '!';
                ?>
            </h2>
            <p class="welcome-sub">
                <?= $adminRole === 'super_admin' ? '⭐ Super Administrator' : '🛡 Administrator' ?>
                &nbsp;·&nbsp;
                Session started <?= e($sessionMins) ?>m <?= e($sessionSecs) ?>s ago
            </p>
        </div>
        <div class="welcome-banner-meta">
            <div class="welcome-meta-item">
                <i class="bi bi-clock"></i>
                <span id="live-time">--:--:--</span>
            </div>
            <div class="welcome-meta-item">
                <i class="bi bi-calendar3"></i>
                <?= date('D, d M Y') ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Stat Cards ─────────────────────────────────────────────────────────── -->
<div class="stats-grid">

    <div class="stat-card gold">
        <span class="stat-card-icon">👤</span>
        <div class="stat-card-value"><?= e($totalAdmins) ?></div>
        <div class="stat-card-label">Active Admins</div>
    </div>

    <div class="stat-card <?= $failedToday > 5 ? 'danger' : 'info' ?>">
        <span class="stat-card-icon">🚫</span>
        <div class="stat-card-value"><?= e($failedToday) ?></div>
        <div class="stat-card-label">Failed Logins Today</div>
    </div>

    <div class="stat-card success">
        <span class="stat-card-icon">✅</span>
        <div class="stat-card-value"><?= e($successToday) ?></div>
        <div class="stat-card-label">Successful Logins Today</div>
    </div>

    <div class="stat-card <?= $lockedCount > 0 ? 'warning' : 'success' ?>">
        <span class="stat-card-icon">🔒</span>
        <div class="stat-card-value"><?= e($lockedCount) ?></div>
        <div class="stat-card-label">Locked Accounts</div>
    </div>

</div>

<!-- ── Two-column grid ────────────────────────────────────────────────────── -->
<div class="dash-grid">

    <!-- Security Status -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Security Status</span>
            <span class="badge badge-success">Phase 1 Active</span>
        </div>
        <div class="card-body">
            <div class="security-item <?= $mailerOk ? 'ok' : 'warn' ?>">
                <i class="bi <?= $mailerOk ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' ?>"></i>
                <div>
                    <div class="security-item-label">PHPMailer</div>
                    <div class="security-item-desc">
                        <?= $mailerOk ? 'Installed & configured' : 'Not installed — run: composer require phpmailer/phpmailer' ?>
                    </div>
                </div>
            </div>

            <div class="security-item ok">
                <i class="bi bi-check-circle-fill"></i>
                <div>
                    <div class="security-item-label">Argon2ID Hashing</div>
                    <div class="security-item-desc">Active — all passwords hashed</div>
                </div>
            </div>

            <div class="security-item ok">
                <i class="bi bi-check-circle-fill"></i>
                <div>
                    <div class="security-item-label">CSRF Protection</div>
                    <div class="security-item-desc">Token-based, double-submit</div>
                </div>
            </div>

            <div class="security-item ok">
                <i class="bi bi-check-circle-fill"></i>
                <div>
                    <div class="security-item-label">Brute Force Guard</div>
                    <div class="security-item-desc">Lockout after <?= MAX_ATTEMPTS ?> attempts / <?= LOCKOUT_MINUTES ?>min</div>
                </div>
            </div>

            <div class="security-item ok">
                <i class="bi bi-check-circle-fill"></i>
                <div>
                    <div class="security-item-label">Session Fingerprinting</div>
                    <div class="security-item-desc">Hijacking & fixation protection</div>
                </div>
            </div>

            <div class="security-item ok">
                <i class="bi bi-check-circle-fill"></i>
                <div>
                    <div class="security-item-label">Audit Logging</div>
                    <div class="security-item-desc">All actions tracked in DB</div>
                </div>
            </div>

            <?php if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'): ?>
            <div class="security-item ok">
                <i class="bi bi-check-circle-fill"></i>
                <div>
                    <div class="security-item-label">HTTPS / TLS</div>
                    <div class="security-item-desc">Secure connection active</div>
                </div>
            </div>
            <?php else: ?>
            <div class="security-item warn">
                <i class="bi bi-exclamation-circle-fill"></i>
                <div>
                    <div class="security-item-label">HTTPS / TLS</div>
                    <div class="security-item-desc">Not enabled — configure SSL for production</div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Login Information -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Your Login Details</span>
        </div>
        <div class="card-body">
            <div class="info-row">
                <span class="info-label"><i class="bi bi-person"></i> Full Name</span>
                <span class="info-value"><?= e($user['full_name'] ?? '—') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="bi bi-envelope"></i> Email</span>
                <span class="info-value"><?= e($user['email'] ?? '—') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="bi bi-shield"></i> Role</span>
                <span class="info-value">
                    <span class="badge <?= $adminRole === 'super_admin' ? 'badge-gold' : 'badge-purple' ?>">
                        <?= e(ucwords(str_replace('_', ' ', $adminRole))) ?>
                    </span>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="bi bi-clock-history"></i> Last Login</span>
                <span class="info-value">
                    <?= $user['last_login'] ? e(date('d M Y, H:i', strtotime($user['last_login']))) : 'First login' ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="bi bi-geo-alt"></i> Last IP</span>
                <span class="info-value">
                    <?= e($user['last_login_ip'] ?? '—') ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="bi bi-key"></i> Password Changed</span>
                <span class="info-value">
                    <?= $user['password_changed_at']
                        ? e(date('d M Y', strtotime($user['password_changed_at'])))
                        : '<span class="text-danger">Never changed</span>' ?>
                </span>
            </div>

            <!-- Session Timer -->
            <div class="session-timer-box">
                <div class="session-timer-label">
                    <i class="bi bi-stopwatch"></i> Session expires in
                </div>
                <div class="session-timer-value" id="session-countdown">—</div>
                <div class="session-timer-bar">
                    <div class="session-timer-fill" id="session-bar"></div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ── Recent Audit Log ────────────────────────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Your Recent Activity</span>
        <span class="text-muted" style="font-size:0.8rem;">Last 8 actions</span>
    </div>
    <div class="table-responsive">
        <?php if ($auditRows): ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Details</th>
                    <th>IP Address</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($auditRows as $row): ?>
                <tr>
                    <td>
                        <span class="badge <?= audit_action_badge(e($row['action'])) ?>">
                            <?= e(str_replace('_', ' ', $row['action'])) ?>
                        </span>
                    </td>
                    <td class="text-muted" style="font-size:0.82rem;">
                        <?= e($row['details'] ?? '—') ?>
                    </td>
                    <td><code style="font-size:0.8rem;"><?= e($row['ip_address']) ?></code></td>
                    <td style="font-size:0.82rem;white-space:nowrap;">
                        <?= e(date('d M, H:i', strtotime($row['created_at']))) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📋</div>
            <p>No activity recorded yet.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Recent Login Attempts ──────────────────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Recent Login Attempts</span>
        <span class="text-muted" style="font-size:0.8rem;">Last 10 attempts (all admins)</span>
    </div>
    <div class="table-responsive">
        <?php if ($recentLoginRows): ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Status</th>
                    <th>IP Address</th>
                    <th>Device</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentLoginRows as $row): ?>
                <tr>
                    <td><?= e($row['email']) ?></td>
                    <td>
                        <span class="badge <?= $row['success'] ? 'badge-success' : 'badge-failed' ?>">
                            <?= $row['success'] ? '✓ Success' : '✗ Failed' ?>
                        </span>
                    </td>
                    <td><code style="font-size:0.8rem;"><?= e($row['ip_address']) ?></code></td>
                    <td style="font-size:0.78rem;color:#888;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= e(shorten_ua($row['user_agent'] ?? '')) ?>
                    </td>
                    <td style="font-size:0.82rem;white-space:nowrap;">
                        <?= e(date('d M, H:i', strtotime($row['attempted_at']))) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">🔐</div>
            <p>No login attempts recorded yet.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Helper functions for this page
function audit_action_badge(string $action): string {
    return match(true) {
        str_contains($action, 'login') && str_contains($action, 'failed') => 'badge-failed',
        str_contains($action, 'login')    => 'badge-success',
        str_contains($action, 'logout')   => 'badge-purple',
        str_contains($action, 'password') => 'badge-warning',
        str_contains($action, 'locked')   => 'badge-failed',
        default                           => 'badge-gold',
    };
}

function shorten_ua(string $ua): string {
    if (!$ua) return '—';
    foreach (['Chrome','Firefox','Safari','Edge','Opera'] as $b) {
        if (str_contains($ua, $b)) return $b;
    }
    return substr($ua, 0, 40) . '…';
}
?>

<script>
// Live clock
function updateClock() {
    const now  = new Date();
    const h    = String(now.getHours()).padStart(2,'0');
    const m    = String(now.getMinutes()).padStart(2,'0');
    const s    = String(now.getSeconds()).padStart(2,'0');
    const el   = document.getElementById('live-time');
    if (el) el.textContent = `${h}:${m}:${s}`;
    const topEl = document.getElementById('topbar-clock');
    if (topEl) topEl.textContent = `${h}:${m}`;
}
setInterval(updateClock, 1000);
updateClock();

// Session countdown
const sessionMax  = <?= SESSION_LIFETIME ?>;
const loginTime   = <?= $loginTime ?>;

function updateCountdown() {
    const elapsed   = Math.floor(Date.now() / 1000) - loginTime;
    const remaining = Math.max(0, sessionMax - elapsed);
    const mins      = Math.floor(remaining / 60);
    const secs      = remaining % 60;
    const pct       = Math.min(100, (remaining / sessionMax) * 100);

    const countEl = document.getElementById('session-countdown');
    const barEl   = document.getElementById('session-bar');

    if (countEl) {
        countEl.textContent = `${String(mins).padStart(2,'0')}:${String(secs).padStart(2,'0')}`;
        countEl.style.color = remaining < 300 ? '#ef4444' : remaining < 600 ? '#f59e0b' : '#22c55e';
    }
    if (barEl) {
        barEl.style.width = pct + '%';
        barEl.style.background = remaining < 300 ? '#ef4444' : remaining < 600 ? '#f59e0b' : '#22c55e';
    }

    if (remaining === 0) {
        window.location.href = '/admin/auth/login.php';
    }
}
setInterval(updateCountdown, 1000);
updateCountdown();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
