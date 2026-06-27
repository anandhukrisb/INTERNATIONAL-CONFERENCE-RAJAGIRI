<?php
require_once __DIR__ . '/config.php';

$res = mysqli_query($db_conn, "SHOW CREATE TABLE payment_attempts");
if ($res) {
    $row = mysqli_fetch_assoc($res);
    echo $row['Create Table'] . "\n";
} else {
    echo "Error: " . mysqli_error($db_conn) . "\n";
}

$res = mysqli_query($db_conn, "SHOW CREATE TABLE payment_orders");
if ($res) {
    $row = mysqli_fetch_assoc($res);
    echo $row['Create Table'] . "\n";
}

$res = mysqli_query($db_conn, "SHOW CREATE TABLE user_registrations");
if ($res) {
    $row = mysqli_fetch_assoc($res);
    echo $row['Create Table'] . "\n";
}
