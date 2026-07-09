<?php
require_once __DIR__ . '/config.php';

$sql = "ALTER TABLE user_registrations ADD COLUMN currency VARCHAR(10) DEFAULT NULL AFTER base_amount";
if (mysqli_query($db_conn, $sql)) {
    echo "Successfully added currency column to user_registrations.\n";
} else {
    echo "Error updating table: " . mysqli_error($db_conn) . "\n";
}
