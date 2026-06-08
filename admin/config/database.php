<?php
/**
 * Database Configuration — PDO Singleton
 * ICSWHMH 2027 Admin Panel — Phase 1
 *
 * SECURITY: Use environment variables in production.
 * Never commit real credentials to version control.
 */
declare(strict_types=1);

class DB
{
    private static ?PDO $pdo = null;

    // ── Database Credentials — UPDATE BEFORE DEPLOYMENT ──────────────────────
    private static string $host     = '127.0.0.1';
    private static string $dbname   = 'icswhmh2027';    // ← your database name
    private static string $username = 'root';             // ← your DB username
    private static string $password = '';                 // ← your DB password
    private static string $charset  = 'utf8mb4';
    private static int    $port     = 3306;

    /**
     * Returns singleton PDO instance.
     * Throws RuntimeException on failure (never exposes credentials in message).
     */
    public static function get(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                self::$host,
                self::$port,
                self::$dbname,
                self::$charset
            );

            try {
                self::$pdo = new PDO($dsn, self::$username, self::$password, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,   // Real prepared statements
                    PDO::ATTR_PERSISTENT         => false,
                    PDO::MYSQL_ATTR_FOUND_ROWS   => true,
                ]);
            } catch (PDOException $e) {
                // Never expose DB credentials in error output
                error_log('DB connection failed: ' . $e->getMessage());
                throw new RuntimeException('Database connection failed. Please try again later.');
            }
        }

        return self::$pdo;
    }

    // Prevent instantiation / cloning / unserialization
    private function __construct() {}
    private function __clone() {}
}
