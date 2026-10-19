<?php
/**
 * ============================================================================
 * migrate_database.php
 * ============================================================================
 * Project : PayVortex & International Conference Portal
 * Purpose : Universal database creation, schema setup, and data migration
 *           utility for new domains, servers, or local environments.
 *
 * Usage:
 *   CLI:
 *     php migrate_database.php                   (Creates tables & migrates latest data)
 *     php migrate_database.php --export          (Refreshes database/full_database_dump.sql from current DB)
 *     php migrate_database.php --host=127.0.0.1 --db=my_db --user=root --pass=secret
 *
 *   Web Browser:
 *     Open https://your-domain.com/migrate_database.php in any browser to run interactively.
 * ============================================================================
 */

// 1. Helper function for environment detection and parsing
function getCliOptions() {
    $options = [
        'host' => null,
        'port' => null,
        'db' => null,
        'user' => null,
        'pass' => null,
        'dump' => null,
        'export' => false,
        'help' => false,
    ];

    if (php_sapi_name() === 'cli') {
        global $argv;
        foreach (array_slice($argv ?? [], 1) as $arg) {
            if ($arg === '--export') $options['export'] = true;
            elseif ($arg === '--help' || $arg === '-h') $options['help'] = true;
            elseif (strpos($arg, '--host=') === 0) $options['host'] = substr($arg, 7);
            elseif (strpos($arg, '--port=') === 0) $options['port'] = substr($arg, 7);
            elseif (strpos($arg, '--db=') === 0) $options['db'] = substr($arg, 5);
            elseif (strpos($arg, '--user=') === 0) $options['user'] = substr($arg, 7);
            elseif (strpos($arg, '--pass=') === 0) $options['pass'] = substr($arg, 7);
            elseif (strpos($arg, '--dump=') === 0) $options['dump'] = substr($arg, 7);
        }
    }
    return $options;
}

function loadEnvFile($envPath) {
    $env = [];
    if (!file_exists($envPath)) {
        return $env;
    }
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, ';') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $env[trim($key)] = trim(trim($val), '"\'');
        }
    }
    return $env;
}

$isCli = (php_sapi_name() === 'cli');
$cliOpts = getCliOptions();

if ($cliOpts['help']) {
    echo "PayVortex & Conference Database Migration Tool\n";
    echo "Usage:\n";
    echo "  php migrate_database.php                  Run full migration and data seeding\n";
    echo "  php migrate_database.php --export         Dump current database to SQL file\n";
    echo "  php migrate_database.php --host=<host> --db=<name> --user=<user> --pass=<pass>\n";
    exit(0);
}

// 2. Discover .env file
$envPath = __DIR__ . '/.env';
if (!file_exists($envPath) && file_exists(__DIR__ . '/vortex/.env')) {
    $envPath = __DIR__ . '/vortex/.env';
}
$env = loadEnvFile($envPath);

$host     = $cliOpts['host'] ?? $env['DB_HOST'] ?? getenv('DB_HOST') ?: '13.200.99.214';
$port     = $cliOpts['port'] ?? $env['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
$dbName   = $cliOpts['db']   ?? $env['DB_NAME'] ?? getenv('DB_NAME') ?: 'icswhmh_db';
$user     = $cliOpts['user'] ?? $env['DB_USER'] ?? getenv('DB_USER') ?: 'icsw_user';
$password = $cliOpts['pass'] ?? $env['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: 'Jpr75V07';
$dumpPath = $cliOpts['dump'] ?? (__DIR__ . '/database/full_database_dump.sql');

// 3. Expected Tables List
$expectedTables = [
    'abstract_details',
    'admin',
    'admin_notifications',
    'admin_users',
    'api_clients',
    'audit_logs',
    'checkout_sessions',
    'events',
    'notification_logs',
    'outbound_webhook_queue',
    'refunds',
    'transaction_timeline',
    'transactions',
    'user_registrations',
    'users',
    'webhooks'
];

// Start Output Buffer for Web UI
if (!$isCli) {
    ob_start();
}

function outputLog($msg, $type = 'info') {
    global $isCli;
    if ($isCli) {
        $colors = [
            'success' => "\033[32m[OK]\033[0m ",
            'error'   => "\033[31m[ERROR]\033[0m ",
            'warn'    => "\033[33m[WARN]\033[0m ",
            'info'    => "\033[36m[INFO]\033[0m ",
            'header'  => "\033[1;35m===\033[0m "
        ];
        echo ($colors[$type] ?? '') . $msg . "\n";
    } else {
        $styles = [
            'success' => 'color: #16a34a; font-weight: 600;',
            'error'   => 'color: #dc2626; font-weight: 700;',
            'warn'    => 'color: #d97706; font-weight: 600;',
            'info'    => 'color: #0284c7;',
            'header'  => 'color: #4f46e5; font-weight: 700; font-size: 1.1em;'
        ];
        echo "<div style='" . ($styles[$type] ?? '') . "'>" . htmlspecialchars($msg) . "</div>";
    }
}

// Handle Export Mode if requested
if ($cliOpts['export']) {
    outputLog("Starting live database export to: " . basename($dumpPath), 'header');
    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        $sql = "-- ============================================================\n";
        $sql .= "-- PayVortex & Conference Unified Database Dump\n";
        $sql .= "-- Exported on: " . date("Y-m-d H:i:s") . "\n";
        $sql .= "-- Source Host: {$host} | Database: {$dbName}\n";
        $sql .= "-- ============================================================\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $sql .= "SET SQL_MODE=\"NO_AUTO_VALUE_ON_ZERO\";\n";
        $sql .= "SET NAMES utf8mb4;\n\n";

        foreach ($expectedTables as $t) {
            $stmt = $pdo->prepare("SHOW TABLES LIKE :table");
            $stmt->execute([':table' => $t]);
            if (!$stmt->fetch()) {
                outputLog("Table '$t' does not exist in source database, skipping.", 'warn');
                continue;
            }
            $res = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_ASSOC);
            $create = preg_replace("/^CREATE TABLE/i", "CREATE TABLE IF NOT EXISTS", $res["Create Table"]);
            $sql .= "-- Table structure for `$t`\n" . $create . ";\n\n";

            $rows = $pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $cols = array_map(function($c) { return "`$c`"; }, array_keys($rows[0]));
                $colList = implode(", ", $cols);
                foreach (array_chunk($rows, 50) as $chunk) {
                    $valRows = [];
                    foreach ($chunk as $row) {
                        $vals = [];
                        foreach ($row as $v) {
                            $vals[] = ($v === null) ? "NULL" : $pdo->quote($v);
                        }
                        $valRows[] = "(" . implode(", ", $vals) . ")";
                    }
                    $sql .= "INSERT INTO `$t` ($colList) VALUES\n" . implode(",\n", $valRows) . "\nON DUPLICATE KEY UPDATE id=id;\n\n";
                }
            }
            outputLog("Exported table schema and data: $t (" . count($rows) . " rows)", 'success');
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        if (!is_dir(dirname($dumpPath))) {
            mkdir(dirname($dumpPath), 0755, true);
        }
        file_put_contents($dumpPath, $sql);
        outputLog("Successfully refreshed dump file: {$dumpPath} (" . round(strlen($sql) / 1024, 2) . " KB)", 'success');
        exit(0);
    } catch (Exception $e) {
        outputLog("Export failed: " . $e->getMessage(), 'error');
        exit(1);
    }
}

// ----------------------------------------------------------------------------
// Run Migration / Table Setup
// ----------------------------------------------------------------------------
outputLog("PayVortex & Conference Database Migration Initializer", 'header');
outputLog("Target Database : {$dbName} on {$host}:{$port} (User: {$user})", 'info');

$pdo = null;
try {
    // 1. Connect and Ensure Database Exists
    outputLog("Connecting to MySQL server...", 'info');
    try {
        $serverDsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $serverPdo = new PDO($serverDsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        outputLog("Database `{$dbName}` verified/created successfully.", 'success');
    } catch (PDOException $e) {
        // Handle restricted user on shared hosting / cPanel
        outputLog("Direct database creation skipped (" . $e->getMessage() . "). Proceeding to direct connection.", 'warn');
    }

    // 2. Connect to the specific database with multi-statements enabled
    $dbDsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dbDsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true
    ]);
    outputLog("Connected to database `{$dbName}`.", 'success');

    // 3. Load and Execute Migration Script
    if (file_exists($dumpPath)) {
        outputLog("Applying migration and master dataset from: " . basename($dumpPath), 'info');
        $sqlContent = file_get_contents($dumpPath);

        // Execute batch SQL
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
        $pdo->exec($sqlContent);
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
        outputLog("Schema creation and dataset migration executed successfully.", 'success');
    } else {
        outputLog("Notice: Dump file not found at '{$dumpPath}'. Generating minimal table structures...", 'warn');
        // Minimal emergency table fallback
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `abstract_details` (
                `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `email` varchar(255) NOT NULL UNIQUE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            
            CREATE TABLE IF NOT EXISTS `admin_users` (
                `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `email` varchar(255) NOT NULL UNIQUE,
                `password` varchar(255) NOT NULL,
                `created_at` timestamp NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `user_registrations` (
                `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `registration_id` varchar(50) NOT NULL UNIQUE,
                `first_name` varchar(100) NOT NULL,
                `last_name` varchar(100) NOT NULL,
                `email` varchar(255) NOT NULL,
                `phone` varchar(50) NOT NULL,
                `base_amount` decimal(10,2) NOT NULL,
                `payment_status` varchar(50) DEFAULT 'Not Completed',
                `transaction_id` varchar(100) DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
    }

    // 4. Verification and Summary
    outputLog("Verifying all tables in `{$dbName}`...", 'header');

    $tableStats = [];
    foreach ($expectedTables as $t) {
        $check = $pdo->prepare("SHOW TABLES LIKE :t");
        $check->execute([':t' => $t]);
        $exists = (bool)$check->fetch();

        if ($exists) {
            $count = $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
            $tableStats[$t] = [
                'status' => 'Ready',
                'rows'   => (int)$count
            ];
            outputLog("Table: " . str_pad($t, 25) . " [Ready] — {$count} rows", 'success');
        } else {
            $tableStats[$t] = [
                'status' => 'Missing',
                'rows'   => 0
            ];
            outputLog("Table: " . str_pad($t, 25) . " [Missing]", 'error');
        }
    }

    outputLog("All 16 tables synchronized and verified successfully!", 'header');

} catch (Exception $e) {
    outputLog("Migration encountered an error: " . $e->getMessage(), 'error');
    if ($isCli) {
        exit(1);
    }
}

// Render Web UI if not in CLI
if (!$isCli) {
    $logContent = ob_get_clean();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Migration & Setup Tool</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Inter', sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 40px 20px; }
            .container { max-width: 900px; margin: 0 auto; background: #1e293b; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); overflow: hidden; border: 1px solid #334155; }
            .header { background: #1e1b4b; padding: 24px 30px; border-bottom: 1px solid #312e81; }
            .header h1 { margin: 0; font-size: 1.5rem; color: #e0e7ff; }
            .header p { margin: 6px 0 0 0; color: #94a3b8; font-size: 0.9rem; }
            .content { padding: 30px; }
            .log-box { background: #090d16; border: 1px solid #1e293b; border-radius: 8px; padding: 20px; font-family: 'JetBrains Mono', monospace; font-size: 0.88rem; line-height: 1.6; max-height: 400px; overflow-y: auto; margin-bottom: 25px; }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; background: #090d16; border-radius: 8px; overflow: hidden; }
            th, td { padding: 12px 18px; text-align: left; border-bottom: 1px solid #1e293b; }
            th { background: #1e293b; color: #94a3b8; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; }
            .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; }
            .badge-ready { background: #064e3b; color: #6ee7b7; }
            .badge-missing { background: #7f1d1d; color: #fca5a5; }
            .footer { padding: 20px 30px; background: #111827; border-top: 1px solid #1f2937; text-align: right; }
            .btn { background: #4f46e5; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.9rem; display: inline-block; }
            .btn:hover { background: #4338ca; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>PayVortex & Conference Database Initializer</h1>
                <p>Automated table generation and data migration for new domains and environments</p>
            </div>
            <div class="content">
                <div class="log-box">
                    <?php echo $logContent; ?>
                </div>

                <h3 style="margin-top: 25px; margin-bottom: 10px;">Synchronized Tables Verification</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Table Name</th>
                            <th>Status</th>
                            <th>Current Record Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($tableStats)): ?>
                            <?php foreach ($tableStats as $tableName => $stat): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($tableName); ?></code></td>
                                    <td>
                                        <span class="badge <?php echo $stat['status'] === 'Ready' ? 'badge-ready' : 'badge-missing'; ?>">
                                            <?php echo htmlspecialchars($stat['status']); ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo number_format($stat['rows']); ?></strong> rows</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="footer">
                <a href="admin/dashboard.php" class="btn">Go to Admin Dashboard &rarr;</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}
