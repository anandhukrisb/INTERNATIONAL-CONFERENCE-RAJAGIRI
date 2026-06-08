<?php
/**
 * CSRF Protection
 * ICSWHMH 2027 Admin Panel — Phase 1
 *
 * Double-submit cookie pattern with per-session token.
 */
declare(strict_types=1);

/**
 * Generate or retrieve the CSRF token for the current session.
 */
function csrf_token(): string
{
    if (empty($_SESSION[CSRF_TOKEN_KEY]) || !isset($_SESSION[CSRF_TOKEN_KEY . '_exp'])
        || time() > $_SESSION[CSRF_TOKEN_KEY . '_exp']
    ) {
        $_SESSION[CSRF_TOKEN_KEY]          = bin2hex(random_bytes(32));
        $_SESSION[CSRF_TOKEN_KEY . '_exp'] = time() + CSRF_EXPIRY_SEC;
    }
    return $_SESSION[CSRF_TOKEN_KEY];
}

/**
 * Return a hidden HTML input field containing the CSRF token.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="' . CSRF_TOKEN_KEY . '" value="' . e(csrf_token()) . '">';
}

/**
 * Validate the CSRF token submitted with a POST request.
 * Dies with 403 on failure. Regenerates token after each use.
 */
function csrf_verify(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $submitted = $_POST[CSRF_TOKEN_KEY] ?? '';
    $stored    = $_SESSION[CSRF_TOKEN_KEY] ?? '';

    if (empty($submitted) || empty($stored)
        || !hash_equals($stored, $submitted)
    ) {
        audit_log(null, null, 'csrf_violation', 'CSRF validation failed', get_client_ip());
        http_response_code(403);
        die('Security token mismatch. Please go back and try again.');
    }

    // Regenerate after successful validation to prevent replay attacks
    $_SESSION[CSRF_TOKEN_KEY]          = bin2hex(random_bytes(32));
    $_SESSION[CSRF_TOKEN_KEY . '_exp'] = time() + CSRF_EXPIRY_SEC;
}
