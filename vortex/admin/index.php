<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['vortex_admin_id'])) {
    header('Location: login.php');
    exit;
}
header('Location: dashboard.php');
exit;
