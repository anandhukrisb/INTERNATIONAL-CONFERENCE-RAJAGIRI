<?php
/**
 * ------------------------------------------------------------
 * dump_data.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 * Purpose : CLI tool to export schema & data for all PayVortex
 *           tables into a standalone SQL migration/dump file.
 *
 * Usage:
 * php dump_data.php [output_file.sql]
 * ------------------------------------------------------------
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Access denied: This export script can only be run via CLI.\n");
}

require_once __DIR__ . '/config/database.php';

$outputFile = $argv[1] ?? (__DIR__ . '/database/payvortex_data_dump_' . date('Ymd_His') . '.sql');

echo "=== PayVortex Database Export Utility ===\n";
echo "Connecting to MySQL server on " . DB_HOST . ":" . DB_PORT . " (Database: " . DB_NAME . ")...\n";

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $tables = [
        'events',
        'api_clients',
        'transactions',
        'checkout_sessions',
        'admin',
        'refunds',
        'webhooks',
        'transaction_timeline'
    ];

    $sqlDump = "-- ============================================================\n";
    $sqlDump .= "-- PayVortex Database Dump\n";
    $sqlDump .= "-- Exported on: " . date('Y-m-d H:i:s') . "\n";
    $sqlDump .= "-- Source Database: " . DB_NAME . "\n";
    $sqlDump .= "-- ============================================================\n\n";
    $sqlDump .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $sqlDump .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n";

    foreach ($tables as $table) {
        // Check if table exists in source database
        $checkStmt = $pdo->prepare("SHOW TABLES LIKE :table");
        $checkStmt->execute([':table' => $table]);
        if (!$checkStmt->fetch()) {
            echo "⚠️  Table '{$table}' not found in source database, skipping...\n";
            continue;
        }

        echo "Exporting table '{$table}'... ";

        // 1. Get CREATE TABLE definition
        $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
        $createRow = $createStmt->fetch();
        $createSql = $createRow['Create Table'] ?? '';

        $sqlDump .= "-- ------------------------------------------------------------\n";
        $sqlDump .= "-- Table structure for `{$table}`\n";
        $sqlDump .= "-- ------------------------------------------------------------\n";
        $sqlDump .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $sqlDump .= $createSql . ";\n\n";

        // 2. Get table rows
        $dataStmt = $pdo->query("SELECT * FROM `{$table}`");
        $rows = $dataStmt->fetchAll();
        $rowCount = count($rows);

        if ($rowCount > 0) {
            $sqlDump .= "-- Data for `{$table}` ({$rowCount} rows)\n";
            $columns = array_keys($rows[0]);
            $colList = implode('`, `', $columns);

            foreach ($rows as $row) {
                $values = [];
                foreach ($row as $val) {
                    if ($val === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = $pdo->quote($val);
                    }
                }
                $valList = implode(', ', $values);
                $sqlDump .= "INSERT INTO `{$table}` (`{$colList}`) VALUES ({$valList});\n";
            }
            $sqlDump .= "\n";
        }

        echo "Done ({$rowCount} rows)\n";
    }

    $sqlDump .= "SET FOREIGN_KEY_CHECKS=1;\n";
    $sqlDump .= "-- Dump completed.\n";

    file_put_contents($outputFile, $sqlDump);

    echo "\n✅ Successfully exported database to: {$outputFile}\n";
    echo "File size: " . number_format(filesize($outputFile) / 1024, 2) . " KB\n";

} catch (Exception $e) {
    echo "\n❌ Export failed: " . $e->getMessage() . "\n";
    exit(1);
}
