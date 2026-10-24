<?php
/**
 * sync_dyuti_from_vortex.php
 *
 * Dedicated CLI Reconciliation & Manual Status Sync for Dyuti 2027.
 * Connects to Dyuti's database, queries PayVortex Gateway for all pending/failed
 * transactions, and updates the registrations table with the authoritative status.
 *
 * Usage:
 *   php sync_dyuti_from_vortex.php                # Sync all pending/failed registrations
 *   php sync_dyuti_from_vortex.php --all          # Sync all registrations with transaction refs
 *   php sync_dyuti_from_vortex.php VTX1792...     # Sync a specific transaction ID
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Access denied: This reconciliation script can only be run via CLI.\n");
}

echo "============================================================\n";
echo "Dyuti 2027 - PayVortex Transaction Status Synchronization\n";
echo "============================================================\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";

// 1. Load Environment Configuration
function loadEnv() {
    $env = array();
    $paths = array(__DIR__ . '/.env', __DIR__ . '/api/.env', __DIR__ . '/rcss/.env', __DIR__ . '/vortex/.env');
    foreach ($paths as $p) {
        if (file_exists($p) && is_readable($p)) {
            $lines = file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || strpos($line, '#') === 0) continue;
                    if (strpos($line, '=') !== false) {
                        list($k, $v) = explode('=', $line, 2);
                        $k = trim($k);
                        $v = trim(trim($v), " \t\n\r\0\x0B\"'");
                        if (!isset($env[$k])) $env[$k] = $v;
                    }
                }
            }
        }
    }
    return $env;
}

$env = loadEnv();

// Vortex API Configuration
$vortexApiUrl = isset($env['VORTEX_API_URL']) ? rtrim($env['VORTEX_API_URL'], '/') : 'https://rpay.rajagiri.edu/vortex/api';
$vortexApiKey = isset($env['VORTEX_API_KEY']) ? $env['VORTEX_API_KEY'] : '839109936a140f77e293b0c17c50173f';
$vortexApiSecret = isset($env['VORTEX_API_SECRET']) ? $env['VORTEX_API_SECRET'] : '0b48b7bd0f581246fc3f80130ba8c2e9';

// Ensure URL points to get_payment_status.php
$statusEndpoint = (strpos($vortexApiUrl, 'get_payment_status.php') !== false) 
    ? $vortexApiUrl 
    : rtrim($vortexApiUrl, '/') . '/get_payment_status.php';

// 2. Connect to Dyuti Database (Tries dyuti_db, dyuti_dyuti2027, then env fallback)
$dbHost = isset($env['DB_HOST']) ? $env['DB_HOST'] : 'localhost';
$dbPort = isset($env['DB_PORT']) ? $env['DB_PORT'] : '3306';
$dbCandidates = array('dyuti_db', 'dyuti_dyuti2027');
if (!empty($env['DYUTI_DB_NAME'])) array_unshift($dbCandidates, $env['DYUTI_DB_NAME']);
if (!empty($env['DB_NAME'])) $dbCandidates[] = $env['DB_NAME'];

$pdo = null;
$connectedDb = '';
$credentials = array(
    array('user' => 'root', 'pass' => 'Anandhu@123#AKBmysql'),
    array('user' => isset($env['DB_USER']) ? $env['DB_USER'] : 'root', 'pass' => !empty($env['DB_PASS']) ? $env['DB_PASS'] : (isset($env['DB_PASSWORD']) ? $env['DB_PASSWORD'] : 'Anandhu@123#AKBmysql')),
    array('user' => 'dyuti_dyuti', 'pass' => 'Dyuti@27#5'),
    array('user' => 'root', 'pass' => '')
);

$dbHosts = array('localhost', '127.0.0.1');
if (!empty($env['DB_HOST']) && !in_array($env['DB_HOST'], $dbHosts)) {
    $dbHosts[] = $env['DB_HOST'];
}

foreach ($dbHosts as $host) {
    foreach ($dbCandidates as $candidateName) {
        if (empty($candidateName)) continue;
        foreach ($credentials as $cred) {
            try {
                $dsn = "mysql:host={$host};port={$dbPort};dbname={$candidateName};charset=utf8mb4";
                $testPdo = new PDO($dsn, $cred['user'], $cred['pass'], array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 2
                ));
                // Check if table 'registrations' exists in this DB
                $check = $testPdo->query("SHOW TABLES LIKE 'registrations'")->fetch();
                if ($check) {
                    $pdo = $testPdo;
                    $connectedDb = $candidateName;
                    break 3;
                }
            } catch (Exception $ex) {
                continue;
            }
        }
    }
}

if (!$pdo) {
    die("❌ Could not connect to any Dyuti database with 'registrations' table.\n");
}
$dbName = $connectedDb;

echo "Database : {$dbName}\n";
echo "Endpoint : {$statusEndpoint}\n";
echo "------------------------------------------------------------\n\n";

// 3. Parse command line arguments
$filterArg = isset($argv[1]) ? trim($argv[1]) : '';

if ($filterArg === '--all') {
    $query = "SELECT * FROM registrations WHERE (payment_order_id IS NOT NULL AND payment_order_id != '') OR (transaction_ref IS NOT NULL AND transaction_ref != '') ORDER BY id DESC";
    $stmt = $pdo->query($query);
    $records = $stmt->fetchAll();
} elseif ($filterArg !== '' && strpos($filterArg, '--') !== 0) {
    // Specific transaction ID or registration ID
    $stmt = $pdo->prepare("SELECT * FROM registrations WHERE payment_order_id = :id OR transaction_ref = :id OR registration_id = :id LIMIT 1");
    $stmt->execute(array(':id' => $filterArg));
    $records = $stmt->fetchAll();
} else {
    // Default: Check all registrations not yet marked 'success'
    $query = "SELECT * FROM registrations WHERE (payment_status IS NULL OR payment_status NOT IN ('success', 'completed')) AND ((payment_order_id IS NOT NULL AND payment_order_id != '') OR (transaction_ref IS NOT NULL AND transaction_ref != '')) ORDER BY id DESC";
    $stmt = $pdo->query($query);
    $records = $stmt->fetchAll();
}

$total = count($records);
echo "Found {$total} registration record(s) to verify against PayVortex.\n\n";

if ($total === 0) {
    echo "No pending registrations found requiring reconciliation.\n";
    exit(0);
}

$updatedCount = 0;
$sameCount    = 0;
$errorCount   = 0;

$updateStmt = $pdo->prepare("
    UPDATE registrations 
    SET payment_status = :status, 
        payment_order_id = COALESCE(payment_order_id, :vtx_id),
        updated_at = NOW() 
    WHERE id = :id
");

// 4. Query PayVortex for each record
foreach ($records as $row) {
    $regId = !empty($row['registration_id']) ? $row['registration_id'] : '#' . $row['id'];
    $vortexTxnId = !empty($row['payment_order_id']) ? $row['payment_order_id'] : $row['transaction_ref'];
    $currStatus = strtolower(trim((string)$row['payment_status']));

    if (empty($vortexTxnId)) {
        continue;
    }

    // Call Vortex API
    $payload = json_encode(array(
        'api_key'               => $vortexApiKey,
        'api_secret'            => $vortexApiSecret,
        'vortex_transaction_id' => $vortexTxnId
    ));

    $ch = curl_init($statusEndpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Accept: application/json'));
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);

    if (strpos($statusEndpoint, 'localhost') !== false || strpos($statusEndpoint, '127.0.0.1') !== false) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr !== '') {
        echo "❌ [{$regId} | {$vortexTxnId}]: cURL Error: {$curlErr}\n";
        $errorCount++;
        continue;
    }

    $json = json_decode($response, true);
    if ($httpCode !== 200 || !isset($json['status']) || $json['status'] !== 'success') {
        $msg = isset($json['message']) ? $json['message'] : 'HTTP ' . $httpCode;
        echo "⚠️  [{$regId} | {$vortexTxnId}]: Vortex response not successful ({$msg})\n";
        $errorCount++;
        continue;
    }

    $vortexStatus = strtoupper(trim((string)$json['data']['payment_status']));
    $targetDyutiStatus = 'pending';

    if ($vortexStatus === 'SUCCESS') {
        $targetDyutiStatus = 'success';
    } elseif ($vortexStatus === 'FAILED') {
        $targetDyutiStatus = 'failed';
    } elseif ($vortexStatus === 'REFUNDED') {
        $targetDyutiStatus = 'refunded';
    } elseif ($vortexStatus === 'PENDING') {
        $targetDyutiStatus = 'pending';
    }

    if ($currStatus !== $targetDyutiStatus) {
        $updateStmt->execute(array(
            ':status' => $targetDyutiStatus,
            ':vtx_id' => $vortexTxnId,
            ':id'     => $row['id']
        ));

        echo "✅ [{$regId} | {$vortexTxnId}]\n";
        echo "   Status: [{$currStatus}] -> [{$targetDyutiStatus}]\n";
        echo "   Email : " . ($row['email'] ? $row['email'] : 'N/A') . "\n\n";
        $updatedCount++;
    } else {
        echo "ℹ️  [{$regId} | {$vortexTxnId}]: In sync (Status: {$currStatus})\n";
        $sameCount++;
    }
}

echo "============================================================\n";
echo "Sync Summary:\n";
echo "  - Total Processed : {$total}\n";
echo "  - Updated Records : {$updatedCount}\n";
echo "  - Already in Sync : {$sameCount}\n";
echo "  - Errors          : {$errorCount}\n";
echo "============================================================\n";
