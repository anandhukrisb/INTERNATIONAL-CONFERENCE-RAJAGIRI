<?php



<<<<<<< HEAD
$env = parse_ini_file(__DIR__ . '/../.env');

$host = $env['DB_HOST'] ?? 'localhost';
$db_name = $env['DB_NAME'] ?? '';
$username = $env['DB_USER'] ?? '';
=======
$env_path = __DIR__ . '/../.env';
$env = file_exists($env_path) ? @parse_ini_file($env_path) : [];
if (!is_array($env)) {
    $env = [];
}

$host = $env['DB_HOST'] ?? '127.0.0.1';
$db_name = $env['DB_NAME'] ?? 'icswhmh_db';
$username = $env['DB_USER'] ?? 'icsw_user';
>>>>>>> 6724e6266f8bcb714445e130d556e785792d7e0d
$password = $env['DB_PASSWORD'] ?? '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db_name;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       
    PDO::ATTR_EMULATE_PREPARES   => false,                  
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
<<<<<<< HEAD
} catch (\PDOException $e) {
    
    
=======
} catch (\Throwable $e) {
    error_log("Database connection error: " . $e->getMessage());
>>>>>>> 6724e6266f8bcb714445e130d556e785792d7e0d
    throw new Exception("Database connection failed. Please contact the administrator.");
}
?>
