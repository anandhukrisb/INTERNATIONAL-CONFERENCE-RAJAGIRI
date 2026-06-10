<?php
declare(strict_types=1);

$autoload_path = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload_path)) {
    http_response_code(500);
    echo 'Composer dependencies are missing. Run: cd D:\\razorpay && composer install (or composer require razorpay/razorpay).';
    exit;
}
require_once $autoload_path;

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'icsw_user');
define('DB_PASS', 'Jpr75V07');
define('DB_NAME', 'razorpay_demo');

$razorpay_key_id = 'rzp_test_Sfcp7CsLxfWLY1';
$razorpay_key_secret = 'm8A8d6pQ09vl26DMjUxOBg5w';

if ($razorpay_key_id === '' && getenv('RAZORPAY_KEY_ID') !== false) {
    $razorpay_key_id = (string)getenv('RAZORPAY_KEY_ID');
}
if ($razorpay_key_secret === '' && getenv('RAZORPAY_KEY_SECRET') !== false) {
    $razorpay_key_secret = (string)getenv('RAZORPAY_KEY_SECRET');
}

define('RAZORPAY_KEY_ID', $razorpay_key_id);
define('RAZORPAY_KEY_SECRET', $razorpay_key_secret);

$db_conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db_conn === false) {
    http_response_code(500);
    echo 'Database connection failed.';
    exit;
}

mysqli_set_charset($db_conn, 'utf8mb4');

