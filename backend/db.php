<?php



$env = parse_ini_file(__DIR__ . '/../.env');

$host = $env['DB_HOST'] ?? 'localhost';
$db_name = $env['DB_NAME'] ?? '';
$username = $env['DB_USER'] ?? '';
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
} catch (\PDOException $e) {
    
    
    throw new Exception("Database connection failed. Please contact the administrator.");
}
?>
