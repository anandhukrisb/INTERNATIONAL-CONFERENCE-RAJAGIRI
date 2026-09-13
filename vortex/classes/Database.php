<?php

/**
 * ------------------------------------------------------------
 * Database.php
 * ------------------------------------------------------------
 * Purpose:
 * --------
 * This class is responsible for creating and managing the
 * application's database connection.
 *
 * It follows the Singleton Design Pattern, ensuring that only
 * one database connection exists throughout a single request.
 *
 * All business classes (Auth, Event, Payment, Refund, etc.)
 * obtain the database connection from this class.
 *
 * Author : Vortex Development Team
 * Project: Vortex Unified Payment Gateway
 * ------------------------------------------------------------
 */

// Load the Logger class.
require_once __DIR__ . '/Logger.php';


class Database
{
    /**
     * --------------------------------------------------------
     * Singleton Instance
     * --------------------------------------------------------
     */
    private static ?Database $instance = null;


    /**
     * --------------------------------------------------------
     * PDO Connection Object
     * --------------------------------------------------------
     */
    private PDO $connection;


    /**
     * --------------------------------------------------------
     * Constructor
     * --------------------------------------------------------
     */
    private function __construct()
    {
        /**
         * Load database configuration.
         */
        require_once __DIR__ . '/../config/database.php';


        /**
         * Create the PDO Data Source Name.
         */
        $dsn = sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
            DB_HOST,
            DB_PORT,
            DB_NAME
        );


        try {

            /**
             * Create the PDO connection.
             */
            $this->connection = new PDO(
                $dsn,
                DB_USER,
                DB_PASS,
                [

                    /**
                     * Throw exceptions whenever an SQL error occurs.
                     */
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

                    /**
                     * Return query results as associative arrays.
                     */
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                    /**
                     * Use native prepared statements.
                     */
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );

            // Ensure consistent session timezone matching application timezone (Asia/Kolkata: UTC+05:30).
            $this->connection->exec("SET time_zone = '+05:30'");

            /**
             * Database connection successful.
             */
            Logger::info(
                'Database connection established successfully.'
            );


        } catch (PDOException $exception) {

            /**
             * Log the technical database error.
             *
             * IMPORTANT:
             * Do not log DB_USER, DB_PASS, or the complete DSN.
             */
            Logger::error(
                'Database connection failed: '
                . $exception->getMessage()
            );


            /**
             * Do not expose the actual database error
             * to the application/user.
             */
            throw new Exception(
                "Unable to establish database connection.",
                0,
                $exception
            );
        }
    }


    /**
     * --------------------------------------------------------
     * Returns the Singleton Instance
     * --------------------------------------------------------
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {

            self::$instance = new self();

        }

        return self::$instance;
    }


    /**
     * --------------------------------------------------------
     * Returns the PDO Connection
     * --------------------------------------------------------
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }


    /**
     * --------------------------------------------------------
     * Prevent Cloning
     * --------------------------------------------------------
     */
    private function __clone()
    {
    }


    /**
     * --------------------------------------------------------
     * Prevent Unserialization
     * --------------------------------------------------------
     */
    public function __wakeup()
    {
        throw new Exception(
            "Cannot unserialize a Singleton Database instance."
        );
    }
}