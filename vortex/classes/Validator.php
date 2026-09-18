<?php

/**
 * ------------------------------------------------------------
 * Validator.php
 * ------------------------------------------------------------
 * Purpose:
 * Contains reusable validation methods used throughout
 * the Vortex Unified Payment Gateway.
 * ------------------------------------------------------------
 */

class Validator
{
    /**
     * --------------------------------------------------------
     * Check Required Field
     * --------------------------------------------------------
     *
     * Returns false when the value is empty.
     */
    public static function required($value)
    {
        // Convert the value to a string and remove spaces.
        $value = trim((string) $value);

        // Check whether the value is empty.
        if ($value === '') {
            return false;
        }

        return true;
    }


    /**
     * --------------------------------------------------------
     * Validate Email
     * --------------------------------------------------------
     */
    public static function email($email)
    {
        // Validate the email using PHP's built-in validator.
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        return false;
    }


    /**
     * --------------------------------------------------------
     * Validate Mobile Number
     * --------------------------------------------------------
     *
     * Only Indian 10-digit mobile numbers are allowed.
     *
     * Valid examples:
     *
     * 9876543210
     * 8123456789
     *
     * Invalid examples:
     *
     * 1234567890
     * 987654321
     * 98765432101
     */
    public static function mobile($mobile)
    {
        // Remove unnecessary spaces.
        $mobile = trim((string) $mobile);

        // Indian mobile number must:
        // - contain exactly 10 digits
        // - start with 6, 7, 8 or 9
        if (preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
            return true;
        }

        return false;
    }


    /**
     * --------------------------------------------------------
     * Validate Amount
     * --------------------------------------------------------
     *
     * Amount must:
     * - be numeric
     * - be greater than zero
     */
    public static function amount($amount)
    {
        if (is_numeric($amount) && $amount > 0) {
            return true;
        }

        return false;
    }


    /**
     * --------------------------------------------------------
     * Validate API Key
     * --------------------------------------------------------
     */
    public static function apiKey($key)
    {
        // Remove spaces.
        $key = trim((string) $key);

        // API key cannot be empty.
        if ($key === '') {
            return false;
        }

        return true;
    }


    /**
     * --------------------------------------------------------
     * Validate API Secret
     * --------------------------------------------------------
     */
    public static function apiSecret($secret)
    {
        // Remove spaces.
        $secret = trim((string) $secret);

        // API secret cannot be empty.
        if ($secret === '') {
            return false;
        }

        return true;
    }


    /**
     * --------------------------------------------------------
     * Validate Event ID
     * --------------------------------------------------------
     */
    public static function eventId($eventId)
    {
        // Remove spaces.
        $eventId = trim((string) $eventId);

        // Event ID cannot be empty.
        if ($eventId === '') {
            return false;
        }

        return true;
    }


    /**
     * --------------------------------------------------------
     * Validate Currency
     * --------------------------------------------------------
     *
     * Currently supported currencies:
     *
     * INR
     * USD
     */
    public static function currency($currency)
    {
        // Convert currency to uppercase.
        $currency = strtoupper(
            trim((string) $currency)
        );


        // List of currencies supported by Vortex.
        $allowedCurrencies = [
            'INR',
            'USD'
        ];


        // Check whether the currency exists
        // in the allowed list.
        return in_array(
            $currency,
            $allowedCurrencies,
            true
        );
    }


    /**
     * --------------------------------------------------------
     * Validate URL
     * --------------------------------------------------------
     * Validates redirect URL (redirect_url).
     * Accepts only valid http / https URLs. Rejects javascript:,
     * data:, and dangerous schemes.
     * --------------------------------------------------------
     */
    public static function url($url)
    {
        $url = trim((string) $url);

        if ($url === '') {
            return false;
        }

        // Validate syntax
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Validate scheme
        $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        return true;
    }
}

?>