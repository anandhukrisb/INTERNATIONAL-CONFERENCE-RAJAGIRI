<?php
/**
 * Application Configuration
 * ICSWHMH 2027 Admin Panel — Phase 1
 */
declare(strict_types=1);

// ── Environment ───────────────────────────────────────────────────────────────
define('APP_ENV',       'development');   // 'production' on live server
define('APP_NAME',      'ICSWHMH 2027 Admin');
define('APP_URL',       'http://localhost');  // No trailing slash — update for production
define('ADMIN_URL',     APP_URL . '/admin');

// ── Paths ─────────────────────────────────────────────────────────────────────
define('ADMIN_ROOT',    dirname(__DIR__));          // /admin
define('INCLUDES_DIR',  ADMIN_ROOT . '/includes/');
define('VIEWS_DIR',     ADMIN_ROOT . '/');
define('LOG_DIR',       ADMIN_ROOT . '/logs/');

// ── Session ───────────────────────────────────────────────────────────────────
define('SESSION_NAME',          'ICSWHMH_ADMIN_SID');
define('SESSION_LIFETIME',      1800);    // 30 minutes idle timeout (seconds)
define('SESSION_REGEN_INTERVAL', 300);   // Regenerate session ID every 5 minutes

// ── Remember Me ───────────────────────────────────────────────────────────────
define('REMEMBER_ME_DAYS',   30);
define('REMEMBER_COOKIE',    'icswhmh_rm');

// ── Security — Brute Force ────────────────────────────────────────────────────
define('MAX_ATTEMPTS',       5);          // Lock after 5 failed attempts
define('LOCKOUT_MINUTES',    15);         // Lockout duration

// ── Password ──────────────────────────────────────────────────────────────────
define('PW_MIN_LENGTH',      12);
define('PW_ARGON_OPTIONS',   [
    'memory_cost' => 65536,   // 64 MB
    'time_cost'   => 4,
    'threads'     => 2,
]);

// ── Password Reset ────────────────────────────────────────────────────────────
define('RESET_TOKEN_EXPIRY_MIN', 30);    // 30 minutes

// ── CSRF ──────────────────────────────────────────────────────────────────────
define('CSRF_TOKEN_KEY',    '_icswhmh_csrf');
define('CSRF_EXPIRY_SEC',   7200);       // 2 hours

// ── Timezone ──────────────────────────────────────────────────────────────────
date_default_timezone_set('Asia/Kolkata');

// ── Error Reporting ───────────────────────────────────────────────────────────
if (APP_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', LOG_DIR . 'php_errors.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}
