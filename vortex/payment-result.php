<?php

echo "<h1>Payment Response Received</h1>";

echo "<p>Transaction ID: " .
    htmlspecialchars($_GET['vortex_transaction_id'] ?? 'Not received') .
    "</p>";

echo "<p>Status Code: " .
    htmlspecialchars($_GET['status_code'] ?? 'Not received') .
    "</p>";

echo "<p>Amount: " .
    htmlspecialchars($_GET['amount'] ?? 'Not received') .
    "</p>";

echo "<p>Currency: " .
    htmlspecialchars($_GET['currency'] ?? 'Not received') .
    "</p>";

echo "<p>Date: " .
    htmlspecialchars($_GET['date_time'] ?? 'Not received') .
    "</p>";

?>