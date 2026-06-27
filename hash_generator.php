<?php
// Password Hash Generator Utility
// Use this file to generate secure hashes to manually insert into your database.

$password_to_hash = "Admin@123"; // Change this to your desired password
$hash = password_hash($password_to_hash, PASSWORD_DEFAULT);

echo "<h1>Password Hash Generator</h1>";
echo "<p><strong>Password:</strong> " . htmlspecialchars($password_to_hash) . "</p>";
echo "<p><strong>Secure Hash:</strong> <code>" . htmlspecialchars($hash) . "</code></p>";
echo "<hr>";
echo "<h3>SQL Insert Statement:</h3>";
echo "<pre>INSERT INTO `admin_users` (`email`, `password`) VALUES ('icswhmh2027@rajagiri.edu', '" . htmlspecialchars($hash) . "');</pre>";
?>
