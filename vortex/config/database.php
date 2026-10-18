<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Safely attempt to load vortex/.env or root .env
try {
    if (file_exists(__DIR__ . '/../.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->safeLoad();
    }
} catch (\Throwable $e) {}

try {
    if (file_exists(__DIR__ . '/../../.env')) {
        $rootDotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
        $rootDotenv->safeLoad();
    }
} catch (\Throwable $e) {}

// Fallback loader if Dotenv is unavailable or did not populate $_ENV
if (file_exists(__DIR__ . '/../helpers/env_loader.php')) {
    require_once __DIR__ . '/../helpers/env_loader.php';
    if (function_exists('loadEnv')) {
        if (file_exists(__DIR__ . '/../.env')) loadEnv(__DIR__ . '/../.env');
        if (file_exists(__DIR__ . '/../../.env')) loadEnv(__DIR__ . '/../../.env');
    }
}

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
define('DB_HOST', $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '13.200.99.214');


/**
 * MySQL Port
 */
define('DB_PORT', $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306');


/**
 * Database Name
 */
define('DB_NAME', $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'icswhmh_db');


/**
 * Database Username
 */
define('DB_USER', $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'icsw_user');


/**
 * Database Password
 *
 * Checks DB_PASS, DB_PASSWORD, and system environment variables
 */
define('DB_PASS', $_ENV['DB_PASS'] ?? $_ENV['DB_PASSWORD'] ?? getenv('DB_PASS') ?: (getenv('DB_PASSWORD') ?: 'Jpr75V07'));


/**
 * Character Set
 */
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');

?>