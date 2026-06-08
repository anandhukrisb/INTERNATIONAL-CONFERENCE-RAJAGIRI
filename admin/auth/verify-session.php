<?php
/**
 * Session Verification Utility
 * ICSWHMH 2027 Admin Panel — Phase 1
 *
 * Include at the top of every protected page as a one-liner:
 *   require_once __DIR__ . '/../auth/verify-session.php';
 *
 * Also bootstraps all config and includes automatically.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

apply_security_headers();
configure_session();

// Attempt remember-me auto-login before session check
if (!is_logged_in() && isset($_COOKIE[REMEMBER_COOKIE])) {
    $remembered = try_remember_me_login();
    if ($remembered) {
        create_auth_session($remembered);
        audit_log(
            $remembered['id'],
            $remembered['email'],
            'login',
            'Via remember-me token (auto)',
            get_client_ip()
        );
    }
}

// Enforce authentication — redirects to login if not authenticated
require_auth();
