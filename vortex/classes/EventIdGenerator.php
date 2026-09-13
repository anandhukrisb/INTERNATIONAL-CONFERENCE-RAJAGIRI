<?php

/**
 * ------------------------------------------------------------
 * EventIdGenerator.php
 * ------------------------------------------------------------
 * Generates unique event IDs for the Vortex system.
 *
 * Event ID format:
 *
 * FIRST 4 LETTERS + START DATE + BASE36 TIMESTAMP
 *
 * Example:
 *
 * TECH20260808D9K4P7X
 * ------------------------------------------------------------
 */

// Load the Logger class.
require_once __DIR__ . '/Logger.php';


class EventIdGenerator
{
    /**
     * Generate an Event ID.
     *
     * @param string $eventName
     * @param string $startDate
     * @return string
     */
    public static function generate($eventName, $startDate)
    {
        try {

            // Remove spaces from the beginning and end.
            $eventName = trim($eventName);


            // Remove spaces and special characters from
            // the event name.
            $cleanName = preg_replace(
                "/[^a-zA-Z0-9]/",
                "",
                $eventName
            );


            // Get the first four characters.
            $prefix = substr($cleanName, 0, 4);


            // Convert the prefix to uppercase.
            $prefix = strtoupper($prefix);


            // Convert the start date into YYYYMMDD format.
            $date = date(
                'Ymd',
                strtotime($startDate)
            );


            // Get the current Unix timestamp in milliseconds.
            $milliseconds = (int) (
                microtime(true) * 1000
            );


            // Convert the millisecond value from Base10
            // to Base36.
            $timeCode = strtoupper(
                base_convert(
                    $milliseconds,
                    10,
                    36
                )
            );


            // Generate a secure random number.
            $randomNumber = random_int(
                1000,
                9999
            );


            // Convert the random number from Base10
            // to Base36.
            $randomCode = strtoupper(
                base_convert(
                    $randomNumber,
                    10,
                    36
                )
            );


            // Combine all parts to create the final Event ID.
            $eventId =
                $prefix
                . $date
                . $timeCode
                . $randomCode;


            // Return the generated Event ID.
            return $eventId;


        } catch (\Throwable $e) {

            /**
             * Log the generation error.
             *
             * We don't log sensitive information here because
             * this class does not handle credentials.
             */
            Logger::error(
                'Event ID generation failed: '
                . $e->getMessage()
            );

            // Re-throw the exception so the calling class
            // can handle the failure properly.
            throw $e;
        }
    }
}