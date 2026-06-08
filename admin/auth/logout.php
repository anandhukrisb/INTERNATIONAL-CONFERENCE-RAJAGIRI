<?php
/**
 * Admin Logout
 * ICSWHMH 2027 Admin Panel — Phase 1
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

// Only process POST to prevent CSRF via GET requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    logout_user('user_logout');
    session_start();  // Restart for flash message
    configure_session();
    session_name(SESSION_NAME);
    session_start();
    flash_set('success', 'You have been signed out securely.');
}

redirect('/admin/auth/login.php');
