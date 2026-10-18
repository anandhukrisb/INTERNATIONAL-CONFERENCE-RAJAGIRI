<?php
/**
 * ------------------------------------------------------------
 * logout.php – Merchant Portal Logout
 * ------------------------------------------------------------
 * Project : PayVortex Unified Payment Gateway
 * ------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset($_SESSION['vortex_client_id']);
unset($_SESSION['vortex_client_name']);
unset($_SESSION['vortex_client_login_mode']);

header('Location: login.php?logged_out=1');
exit;
