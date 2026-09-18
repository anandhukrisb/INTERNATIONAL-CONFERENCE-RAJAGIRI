<?php
/**
 * seed_admin.php – Run once to create the admin table and seed the default admin.
 * DELETE or restrict access after running!
 */
require_once __DIR__ . '/config/database.php';

$dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pdo->exec("
    CREATE TABLE IF NOT EXISTS admin (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(80) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        full_name VARCHAR(150) NOT NULL DEFAULT 'Administrator',
        is_active TINYINT(1) DEFAULT 1,
        last_login TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
echo "admin table created (or already exists).\n";

$hash = password_hash('admin@123', PASSWORD_BCRYPT, ['cost' => 12]);

$stmt = $pdo->prepare("INSERT IGNORE INTO admin (username, password_hash, full_name) VALUES (:u, :h, :f)");
$stmt->execute([':u' => 'admin', ':h' => $hash, ':f' => 'Vortex Administrator']);

if ($stmt->rowCount() > 0) {
    echo "Admin user inserted.\n";
} else {
    echo "Admin user already exists – skipped.\n";
}
echo "Done!\n";
