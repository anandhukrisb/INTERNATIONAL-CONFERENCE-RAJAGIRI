<?php
/**
 * ------------------------------------------------------------
 * index.php – Merchant Portal Entry Router
 * ------------------------------------------------------------
 * Project : PayVortex Unified Payment Gateway
 * ------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['vortex_client_id'])) {
    header('Location: dashboard.php');
    exit;
}

header('Location: login.php');
exit;
