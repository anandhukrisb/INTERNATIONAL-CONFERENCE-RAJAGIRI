<?php
// Setup script to initialize database and tables automatically

require_once __DIR__ . '/config/database.php';

echo "=== Vortex Payment Gateway Database Setup ===\n";

try {
    // 1. Connect to the database
    echo "Connecting to MySQL server on " . DB_HOST . ":" . DB_PORT . " as '" . DB_USER . "'...\n";
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // 2. Read schema file
    $schemaFile = __DIR__ . '/database/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("Schema file not found at " . $schemaFile);
    }

    $sql = file_get_contents($schemaFile);

    // 3. Execute SQL Statements
    echo "Running database schema setup...\n";
    $pdo->exec($sql);

    echo "✅ Database 'vortex_gateway' and tables ('events', 'api_clients', 'transactions') created successfully!\n";
} catch (Exception $e) {
    echo "❌ Error during database setup: " . $e->getMessage() . "\n";
    exit(1);
}
