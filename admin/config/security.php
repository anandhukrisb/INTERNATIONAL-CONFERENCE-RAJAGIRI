<?php
/**
 * Security Headers & Session Configuration
 * ICSWHMH 2027 Admin Panel — Phase 1
 * Called once at the very top of every page before any output.
 */
declare(strict_types=1);

/**
 * Apply all security headers.
 * Call this before any HTML output.
 */
function apply_security_headers(): void
{
    // Prevent clickjacking
    header('X-Frame-Options: DENY');

    // Prevent MIME-type sniffing
    header('X-Content-Type-Options: nosniff');

    // XSS protection (legacy browsers)
    header('X-XSS-Protection: 1; mode=block');

    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Permissions policy — restrict dangerous APIs
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');

    // HSTS — enable ONLY when HTTPS is configured
    // header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');

    // Content Security Policy
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
        "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net",
        "img-src 'self' data: https://res.cloudinary.com",
        "connect-src 'self'",
        "frame-src 'none'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "upgrade-insecure-requests",
    ]);
    header("Content-Security-Policy: $csp");

    // Remove fingerprinting headers
    header_remove('X-Powered-By');
    header_remove('Server');
}

/**
 * Configure and start the session with hardened settings.
 * Call once per request, before session_start().
 */
function configure_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return; // Already started
    }

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    // Session cookie parameters
    session_set_cookie_params([
        'lifetime' => 0,            // Browser session (not persistent)
        'path'     => '/admin/',
        'domain'   => '',
        'secure'   => $isSecure,    // HTTPS only in production
        'httponly' => true,         // Not accessible via JavaScript
        'samesite' => 'Strict',     // CSRF protection
    ]);

    // PHP session INI settings
    ini_set('session.use_strict_mode',    '1');  // Reject unrecognized session IDs
    ini_set('session.use_only_cookies',   '1');  // No session IDs in URL
    ini_set('session.use_trans_sid',      '0');  // Disable trans_sid
    ini_set('session.cookie_httponly',    '1');
    ini_set('session.cookie_samesite',    'Strict');
    ini_set('session.gc_maxlifetime',     (string) SESSION_LIFETIME);
    ini_set('session.entropy_length',     '32');

    session_name(SESSION_NAME);
    session_start();
}

/**
 * Build a session fingerprint to detect session hijacking.
 * Binds the session to browser + partial IP.
 */
function session_fingerprint(): string
{
    $ua      = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip      = get_client_ip();
    // Use only first 3 octets of IP to handle load balancers/NAT
    $ipParts = explode('.', $ip);
    $ipBase  = implode('.', array_slice($ipParts, 0, 3));
    return hash('sha256', $ua . $ipBase . SESSION_NAME);
}

/**
 * Get the real client IP address.
 */
function get_client_ip(): string
{
    $candidates = [
        'HTTP_CF_CONNECTING_IP',   // Cloudflare
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR',
    ];
    foreach ($candidates as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    // Fall back to REMOTE_ADDR (may be private)
    return trim($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/**
 * Escape output to prevent XSS.
 * Use this on EVERY variable output in HTML.
 */
function e(mixed $val): string
{
    return htmlspecialchars((string) $val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Validate password complexity.
 * Returns array of error messages (empty = valid).
 */
function validate_password(string $password): array
{
    $errors = [];
    if (strlen($password) < PW_MIN_LENGTH) {
        $errors[] = 'Password must be at least ' . PW_MIN_LENGTH . ' characters.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Must contain at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Must contain at least one lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Must contain at least one number.';
    }
    if (!preg_match('/[\W_]/', $password)) {
        $errors[] = 'Must contain at least one special character (!@#$%^&*...).';
    }
    return $errors;
}

/**
 * Rate limiting via a simple in-memory approach.
 * Enhanced rate limiting is handled in the database via login_attempts.
 */
function add_progressive_delay(int $attempts): void
{
    if ($attempts >= 3 && $attempts < 5) {
        sleep(2);  // 2 second delay after 3rd attempt
    } elseif ($attempts >= 5) {
        sleep(5);  // 5 second delay at max
    }
}
