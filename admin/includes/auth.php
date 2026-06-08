<?php
/**
 * Authentication Core
 * ICSWHMH 2027 Admin Panel — Phase 1
 *
 * All authentication logic lives here.
 * Included by every protected page via bootstrap.
 */
declare(strict_types=1);

/* ──────────────────────────────────────────────────────────────────────────────
   SESSION MANAGEMENT
   ────────────────────────────────────────────────────────────────────────── */

/**
 * Require the user to be logged in.
 * Redirects to login page if not authenticated or session expired/hijacked.
 */
function require_auth(): void
{
    if (!is_logged_in()) {
        // Save intended URL for redirect after login
        $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? '';
        flash_set('error', 'Please log in to access this page.');
        redirect('/admin/auth/login.php');
    }

    // Session timeout check
    if (isset($_SESSION['last_activity'])) {
        if ((time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
            logout_user('timeout');
            flash_set('error', 'Your session has expired. Please log in again.');
            redirect('/admin/auth/login.php');
        }
    }

    // Session hijacking check — fingerprint must match
    if (isset($_SESSION['fingerprint'])) {
        if ($_SESSION['fingerprint'] !== session_fingerprint()) {
            logout_user('hijack');
            flash_set('error', 'Security alert: session fingerprint mismatch.');
            redirect('/admin/auth/login.php');
        }
    }

    // Periodic session ID regeneration to prevent fixation
    if (!isset($_SESSION['last_regen'])) {
        $_SESSION['last_regen'] = time();
    } elseif ((time() - $_SESSION['last_regen']) > SESSION_REGEN_INTERVAL) {
        session_regenerate_id(true);
        $_SESSION['last_regen'] = time();
    }

    // Update last activity
    $_SESSION['last_activity'] = time();
}

/**
 * Check if a user is logged in (session validation only, no redirect).
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['admin_id'])
        && !empty($_SESSION['admin_email'])
        && !empty($_SESSION['fingerprint']);
}

/**
 * Check if the logged-in user is a super admin.
 */
function is_super_admin(): bool
{
    return ($_SESSION['admin_role'] ?? '') === 'super_admin';
}

/* ──────────────────────────────────────────────────────────────────────────────
   LOGIN
   ────────────────────────────────────────────────────────────────────────── */

/**
 * Attempt to log in with email + password.
 * Returns an array with 'success' bool and 'message' / 'user' keys.
 */
function attempt_login(string $email, string $password, string $ip, string $ua): array
{
    $pdo = DB::get();

    // ── Rate-limit / lockout check ────────────────────────────────────────────
    if (is_ip_blocked($ip)) {
        return ['success' => false, 'message' => 'Too many failed attempts from your IP. Please wait ' . LOCKOUT_MINUTES . ' minutes.'];
    }

    // ── Find user ─────────────────────────────────────────────────────────────
    $stmt = $pdo->prepare(
        'SELECT * FROM admin_users WHERE email = :email LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    // ── Account checks ────────────────────────────────────────────────────────
    if (!$user) {
        record_login_attempt($email, $ip, $ua, false);
        audit_log(null, $email, 'failed_login', 'Account not found', $ip);
        // Constant-time fake verify to prevent user enumeration
        password_verify($password, '$argon2id$v=19$m=65536,t=4,p=1$fakesalt$fakehash');
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }

    // Check if account is inactive
    if ($user['status'] === 'inactive') {
        audit_log($user['id'], $user['email'], 'failed_login', 'Account inactive', $ip);
        return ['success' => false, 'message' => 'Your account has been deactivated.'];
    }

    // Check if account is locked
    if ($user['status'] === 'locked' && $user['locked_until']) {
        if (strtotime($user['locked_until']) > time()) {
            $until = date('H:i', strtotime($user['locked_until']));
            audit_log($user['id'], $user['email'], 'failed_login', 'Account locked', $ip);
            return ['success' => false, 'message' => "Account locked until $until. Please try again later."];
        }
        // Auto-unlock if lockout period has passed
        unlock_account($user['id']);
        $user['status'] = 'active';
        $user['failed_attempts'] = 0;
    }

    // ── Progressive delay (brute-force mitigation) ────────────────────────────
    add_progressive_delay((int) $user['failed_attempts']);

    // ── Password verification ─────────────────────────────────────────────────
    if (!password_verify($password, $user['password_hash'])) {
        record_login_attempt($email, $ip, $ua, false);
        increment_failed_attempts((int) $user['id']);
        audit_log($user['id'], $user['email'], 'failed_login', 'Wrong password', $ip);

        $remaining = MAX_ATTEMPTS - (int) $user['failed_attempts'] - 1;
        if ($remaining <= 0) {
            return ['success' => false, 'message' => 'Account locked due to too many failed attempts. Try again in ' . LOCKOUT_MINUTES . ' minutes.'];
        }
        return ['success' => false, 'message' => "Invalid email or password. $remaining attempt(s) remaining."];
    }

    // ── Rehash if needed (algorithm upgrade) ──────────────────────────────────
    if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID, PW_ARGON_OPTIONS)) {
        $newHash = password_hash($password, PASSWORD_ARGON2ID, PW_ARGON_OPTIONS);
        $pdo->prepare('UPDATE admin_users SET password_hash = :hash WHERE id = :id')
            ->execute([':hash' => $newHash, ':id' => $user['id']]);
    }

    // ── Success ───────────────────────────────────────────────────────────────
    record_login_attempt($email, $ip, $ua, true);
    reset_failed_attempts((int) $user['id']);
    update_last_login((int) $user['id'], $ip);
    audit_log($user['id'], $user['email'], 'login', 'Successful login', $ip);

    return ['success' => true, 'user' => $user];
}

/**
 * Create a fully authenticated session for the given user.
 * Call after successful credential verification.
 */
function create_auth_session(array $user): void
{
    // Session fixation protection — invalidate old session
    session_regenerate_id(true);

    $_SESSION['admin_id']       = $user['id'];
    $_SESSION['admin_name']     = $user['full_name'];
    $_SESSION['admin_email']    = $user['email'];
    $_SESSION['admin_role']     = $user['role'];
    $_SESSION['last_activity']  = time();
    $_SESSION['last_regen']     = time();
    $_SESSION['fingerprint']    = session_fingerprint();
    $_SESSION['login_time']     = time();
}

/**
 * Destroy session and optionally log the reason.
 */
function logout_user(string $reason = 'user_logout'): void
{
    $userId = $_SESSION['admin_id'] ?? null;
    $email  = $_SESSION['admin_email'] ?? null;
    $ip     = get_client_ip();

    audit_log($userId, $email, 'logout', "Reason: $reason", $ip);

    // Clear remember-me cookie and DB record
    clear_remember_me_cookie();

    // Destroy the session
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 86400,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

/* ──────────────────────────────────────────────────────────────────────────────
   REMEMBER ME
   ────────────────────────────────────────────────────────────────────────── */

/**
 * Create a remember-me token and set the cookie.
 * Uses selector:validator split pattern (prevents timing attacks).
 */
function set_remember_me(int $userId, string $ip, string $ua): void
{
    $pdo       = DB::get();
    $selector  = bin2hex(random_bytes(12));  // 24 hex chars
    $validator = bin2hex(random_bytes(32));  // 64 hex chars
    $hash      = hash('sha256', $validator);
    $expires   = date('Y-m-d H:i:s', strtotime('+' . REMEMBER_ME_DAYS . ' days'));

    // Clean up old tokens for this user
    $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = :uid')
        ->execute([':uid' => $userId]);

    // Store in DB
    $stmt = $pdo->prepare(
        'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at, ip_address, user_agent)
         VALUES (:uid, :sel, :hash, :exp, :ip, :ua)'
    );
    $stmt->execute([
        ':uid'  => $userId,
        ':sel'  => $selector,
        ':hash' => $hash,
        ':exp'  => $expires,
        ':ip'   => $ip,
        ':ua'   => substr($ua, 0, 500),
    ]);

    // Set cookie: selector:validator
    $cookieExpiry = time() + (REMEMBER_ME_DAYS * 86400);
    setcookie(REMEMBER_COOKIE, $selector . ':' . $validator, [
        'expires'  => $cookieExpiry,
        'path'     => '/admin/',
        'domain'   => '',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

/**
 * Attempt to log in via remember-me cookie.
 * Returns the user array on success, false on failure.
 */
function try_remember_me_login(): array|false
{
    $cookie = $_COOKIE[REMEMBER_COOKIE] ?? '';
    if (empty($cookie)) {
        return false;
    }

    $parts = explode(':', $cookie, 2);
    if (count($parts) !== 2) {
        clear_remember_me_cookie();
        return false;
    }

    [$selector, $validator] = $parts;
    $pdo = DB::get();

    $stmt = $pdo->prepare(
        'SELECT rt.*, au.*
         FROM remember_tokens rt
         JOIN admin_users au ON au.id = rt.user_id
         WHERE rt.selector = :sel
           AND rt.expires_at > NOW()
           AND au.status = :status
         LIMIT 1'
    );
    $stmt->execute([':sel' => $selector, ':status' => 'active']);
    $row = $stmt->fetch();

    if (!$row) {
        clear_remember_me_cookie();
        return false;
    }

    // Validate the token (timing-safe)
    $expectedHash = hash('sha256', $validator);
    if (!hash_equals($row['token_hash'], $expectedHash)) {
        // Possible token theft — delete all tokens for this user
        $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = :uid')
            ->execute([':uid' => $row['user_id']]);
        clear_remember_me_cookie();
        audit_log($row['user_id'], $row['email'], 'remember_me_theft', 'Token mismatch', get_client_ip());
        return false;
    }

    return $row;
}

/**
 * Clear the remember-me cookie and delete DB token.
 */
function clear_remember_me_cookie(): void
{
    $cookie = $_COOKIE[REMEMBER_COOKIE] ?? '';
    if ($cookie) {
        $selector = explode(':', $cookie, 2)[0];
        try {
            DB::get()->prepare('DELETE FROM remember_tokens WHERE selector = :sel')
                     ->execute([':sel' => $selector]);
        } catch (Throwable) {}
    }
    setcookie(REMEMBER_COOKIE, '', [
        'expires'  => time() - 86400,
        'path'     => '/admin/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

/* ──────────────────────────────────────────────────────────────────────────────
   BRUTE-FORCE PROTECTION
   ────────────────────────────────────────────────────────────────────────── */

/**
 * Check if an IP address has been blocked due to too many failures.
 */
function is_ip_blocked(string $ip): bool
{
    $pdo    = DB::get();
    $window = date('Y-m-d H:i:s', time() - (LOCKOUT_MINUTES * 60));
    $stmt   = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE ip_address = :ip AND success = 0 AND attempted_at > :window'
    );
    $stmt->execute([':ip' => $ip, ':window' => $window]);
    return (int) $stmt->fetchColumn() >= (MAX_ATTEMPTS * 2);
}

/**
 * Record a login attempt (success or failure).
 */
function record_login_attempt(string $email, string $ip, string $ua, bool $success): void
{
    $pdo  = DB::get();
    $stmt = $pdo->prepare(
        'INSERT INTO login_attempts (email, ip_address, user_agent, success)
         VALUES (:email, :ip, :ua, :success)'
    );
    $stmt->execute([
        ':email'   => $email,
        ':ip'      => $ip,
        ':ua'      => substr($ua, 0, 500),
        ':success' => $success ? 1 : 0,
    ]);
}

/**
 * Increment failed attempt counter and lock if threshold reached.
 */
function increment_failed_attempts(int $userId): void
{
    $pdo   = DB::get();
    $stmt  = $pdo->prepare('SELECT failed_attempts FROM admin_users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    $count = (int) $stmt->fetchColumn();
    $count++;

    if ($count >= MAX_ATTEMPTS) {
        $lockedUntil = date('Y-m-d H:i:s', time() + (LOCKOUT_MINUTES * 60));
        $pdo->prepare(
            'UPDATE admin_users
             SET failed_attempts = :count, status = :status, locked_until = :until
             WHERE id = :id'
        )->execute([
            ':count'  => $count,
            ':status' => 'locked',
            ':until'  => $lockedUntil,
            ':id'     => $userId,
        ]);
        audit_log($userId, null, 'account_locked', "Locked after $count attempts", get_client_ip());
    } else {
        $pdo->prepare('UPDATE admin_users SET failed_attempts = :count WHERE id = :id')
            ->execute([':count' => $count, ':id' => $userId]);
    }
}

/**
 * Reset failed attempts counter after successful login.
 */
function reset_failed_attempts(int $userId): void
{
    DB::get()->prepare(
        'UPDATE admin_users SET failed_attempts = 0, status = :s, locked_until = NULL WHERE id = :id'
    )->execute([':s' => 'active', ':id' => $userId]);
}

/**
 * Unlock an account after the lockout period expires.
 */
function unlock_account(int $userId): void
{
    DB::get()->prepare(
        'UPDATE admin_users SET status = :s, failed_attempts = 0, locked_until = NULL WHERE id = :id'
    )->execute([':s' => 'active', ':id' => $userId]);
}

/* ──────────────────────────────────────────────────────────────────────────────
   USER QUERIES
   ────────────────────────────────────────────────────────────────────────── */

function get_user_by_email(string $email): array|false
{
    $stmt = DB::get()->prepare('SELECT * FROM admin_users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    return $stmt->fetch();
}

function get_user_by_id(int $id): array|false
{
    $stmt = DB::get()->prepare('SELECT * FROM admin_users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

function update_last_login(int $userId, string $ip): void
{
    DB::get()->prepare(
        'UPDATE admin_users SET last_login = NOW(), last_login_ip = :ip WHERE id = :id'
    )->execute([':ip' => $ip, ':id' => $userId]);
}

/* ──────────────────────────────────────────────────────────────────────────────
   UTILITIES
   ────────────────────────────────────────────────────────────────────────── */

/**
 * Store a flash message for one-time display.
 */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

/**
 * Retrieve and clear a flash message.
 */
function flash_get(string $type): string
{
    $message = $_SESSION['flash'][$type] ?? '';
    unset($_SESSION['flash'][$type]);
    return $message;
}

/**
 * Redirect to a URL and exit.
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}
