<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Set application-wide default timezone
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Kolkata');

/**
 * ------------------------------------------------------------
 * Database Configuration File
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 *
 * This file only defines database configuration.
 * It does not create a database connection.
 * ------------------------------------------------------------
 */


/**
 * Database Server Host
 */
define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');


/**
 * MySQL Port
 */
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');


/**
 * Database Name
 */
define('DB_NAME', $_ENV['DB_NAME'] ?? 'vortex_gateway');


/**
 * Database Username
 */
define('DB_USER', $_ENV['DB_USER'] ?? 'root');


/**
 * Database Password
 *
 * The actual password is stored in .env,
 * not in this source file.
 */
define('DB_PASS', $_ENV['DB_PASS'] ?? '');


/**
 * Character Set
 */
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');

?>