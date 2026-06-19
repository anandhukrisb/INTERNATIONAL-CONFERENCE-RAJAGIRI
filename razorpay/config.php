<?php
declare(strict_types=1);

$autoload_path = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload_path)) {
    http_response_code(500);
    echo 'Composer dependencies are missing. Run: cd D:\\razorpay && composer install (or composer require razorpay/razorpay).';
    exit;
}
require_once $autoload_path;

$env = parse_ini_file(__DIR__ . '/../.env');
define('DB_HOST', $env['DB_HOST'] ?? '127.0.0.1');
define('DB_USER', $env['DB_USER'] ?? 'icsw_user');
define('DB_PASS', $env['DB_PASSWORD'] ?? 'Jpr75V07');
define('DB_NAME', $env['DB_NAME'] ?? 'icswhmh_db');

$razorpay_key_id = $env['RAZORPAY_KEY_ID'] ?? getenv('RAZORPAY_KEY_ID') ?: '';
$razorpay_key_secret = $env['RAZORPAY_KEY_SECRET'] ?? getenv('RAZORPAY_KEY_SECRET') ?: '';

define('RAZORPAY_KEY_ID', $razorpay_key_id);
define('RAZORPAY_KEY_SECRET', $razorpay_key_secret);

$db_conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db_conn === false) {
    http_response_code(500);
    echo 'Database connection failed.';
    exit;
}

mysqli_set_charset($db_conn, 'utf8mb4');

