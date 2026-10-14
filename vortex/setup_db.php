<?php
/**
 * ------------------------------------------------------------
 * setup_db.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 * Purpose : Automatically creates/initializes database tables
 *           from database/schema.sql (or imports a provided SQL dump).
 *
 * Usage:
 * php setup_db.php                (runs database/schema.sql)
 * php setup_db.php [path_to.sql]  (imports custom dump/migration)
 * ------------------------------------------------------------
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Access denied: Database setup script can only be run via CLI.\n");
}

require_once __DIR__ . '/config/database.php';

echo "=== Vortex Payment Gateway Database Setup ===\n";

try {
    $dbName = DB_NAME;
    $host   = DB_HOST;
    $port   = DB_PORT;
    $user   = DB_USER;
    $pass   = DB_PASS;

    // 1. Connect to MySQL server (auto-create database if not exists)
    echo "Connecting to MySQL server on {$host}:{$port} as '{$user}'...\n";
    
    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbName}";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) {
        // Unknown database error (1049) -> attempt to create it
        if ($e->getCode() == 1049 || strpos($e->getMessage(), 'Unknown database') !== false) {
            echo "Database '{$dbName}' not found. Attempting to create it...\n";
            $serverPdo = new PDO("mysql:host={$host};port={$port}", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "✅ Database '{$dbName}' created.\n";

            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbName}", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } else {
            throw $e;
        }
    }

    // 2. Determine target SQL file (default: database/schema.sql)
    $sqlFile = $argv[1] ?? (__DIR__ . '/database/schema.sql');
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found at: {$sqlFile}");
    }

    echo "Reading SQL from: " . basename($sqlFile) . "...\n";
    $sql = file_get_contents($sqlFile);

    // 3. Execute SQL Statements
    echo "Running SQL setup against '{$dbName}'...\n";
    $pdo->exec($sql);

    echo "✅ Setup completed successfully on database '{$dbName}'!\n";
    echo "Tables ready: events, api_clients, transactions, checkout_sessions, admin, refunds, webhooks, transaction_timeline.\n";

} catch (Exception $e) {
    echo "❌ Error during database setup: " . $e->getMessage() . "\n";
    exit(1);
}
