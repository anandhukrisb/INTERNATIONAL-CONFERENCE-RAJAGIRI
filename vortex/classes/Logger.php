<?php

/**
 * ------------------------------------------------------------
 * Logger.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 *
 * Purpose:
 * This class writes application logs into a file.
 *
 * It is mainly used for:
 * - Database Errors
 * - Payment Logs
 * - Webhook Logs
 * - Refund Logs
 * ------------------------------------------------------------
 */

class Logger
{
    /**
     * Log an information message.
     */
    public static function info($message)
    {
        self::writeLog("INFO", $message);
    }

    /**
     * Log an error message.
     */
    public static function error($message)
    {
        self::writeLog("ERROR", $message);
    }

    /**
     * Write the log message into the log file.
     */
    private static function writeLog($type, $message)
    {
        // Log file location
        $file = __DIR__ . "/../logs/vortex.log";

        // Current date and time
        $date = date("Y-m-d H:i:s");

        // Final log message
        $log = "[" . $date . "] ";
        $log .= "[" . $type . "] ";
        $log .= $message;
        $log .= PHP_EOL;

        // Append the log to the file
        file_put_contents($file, $log, FILE_APPEND);
    }
}