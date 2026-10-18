<?php
/**
 * ------------------------------------------------------------
 * revenue.php – Forwarder to /admin/payments/revenue.php
 * ------------------------------------------------------------
 */
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: payments/revenue.php' . $qs);
exit;
