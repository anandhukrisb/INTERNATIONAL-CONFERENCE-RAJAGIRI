<?php

/**
 * =============================================================
 * BILLDESK PAYMENT GATEWAY — CENTRALISED CONFIGURATION
 * =============================================================
 *
 * All credentials and endpoint URLs live here.
 * No other file should hard-code keys or URLs.
 *
 * Environment: Set BD_ENV to 'production' to switch endpoints.
 * =============================================================
 */

// ── Timezone (required by BillDesk: IST) ─────────────────────
date_default_timezone_set('Asia/Kolkata');

// ── Environment ───────────────────────────────────────────────
// Set the environment variable BD_ENV=production on the server
// to switch to live credentials automatically.
$bd_env = getenv('BD_ENV') ?: 'uat';   // 'uat' | 'production'

// ── Credentials ───────────────────────────────────────────────
// Replace every placeholder below with the values BillDesk
// provided in their on-boarding e-mail / merchant portal.

$merchant_id = 'YOUR_MERCHANT_ID';   // e.g. BDUAT2K758
$client_id   = 'YOUR_CLIENT_ID';     // e.g. BDUAT2K758

// AES-256-GCM encryption key — MUST be exactly 32 characters.
$enc_key     = 'YOUR_ENCRYPTION_KEY_32_CHARS!!';

// HMAC-SHA256 signing key.
$sign_key    = 'YOUR_SIGNING_KEY';

// Key identifiers supplied by BillDesk.
$enc_key_id  = 'YOUR_ENC_KEY_ID';
$sign_key_id = 'YOUR_SIGN_KEY_ID';

// ── Endpoint URLs ─────────────────────────────────────────────
if ($bd_env === 'production') {

    // ── PRODUCTION ─────────────────────────────────────────
    $create_order_url = 'https://pgbilldesk.com/u2/payments/ve1_2/orders/create';
    $retrieve_url     = 'https://pgbilldesk.com/u2/payments/ve1_2/transactions/get';
    $return_url       = 'https://YOUR_LIVE_DOMAIN/bdtest/response.php';

} else {

    // ── UAT / STAGING ──────────────────────────────────────
    $create_order_url = 'https://uat1.billdesk.com/u2/payments/ve1_2/orders/create';
    $retrieve_url     = 'https://uat1.billdesk.com/u2/payments/ve1_2/transactions/get';
    $return_url       = 'http://localhost/conference/bdtest/response.php';
}

// ── Log file location ─────────────────────────────────────────
// Ensure this directory is writable by the web server.
// In production, place this OUTSIDE the public web root.
define('BD_LOG_DIR', __DIR__ . '/logs');

// ── Shared logging helper ─────────────────────────────────────
// All files call bd_log() to write structured JSON log entries.
// Log files rotate by date: billdesk_YYYY-MM-DD.log
function bd_log(string $channel, string $event, array $data = []): void
{
    $dir = BD_LOG_DIR;

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $entry = json_encode([
        'timestamp'   => date('Y-m-d H:i:s'),
        'environment' => getenv('BD_ENV') ?: 'uat',
        'channel'     => $channel,
        'event'       => $event,
        'data'        => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

    $file = $dir . '/billdesk_' . date('Y-m-d') . '.log';
    file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
}

// ── Configuration validator ───────────────────────────────────
// Called by pay.php, retrieve.php to guard against placeholder values.
function bd_validate_config(
    string $merchant_id,
    string $client_id,
    string $enc_key,
    string $sign_key
): void {

    if (empty($merchant_id) || $merchant_id === 'YOUR_MERCHANT_ID') {
        throw new RuntimeException('BillDesk config error: merchant_id is not set.');
    }

    if (empty($client_id) || $client_id === 'YOUR_CLIENT_ID') {
        throw new RuntimeException('BillDesk config error: client_id is not set.');
    }

    if (strlen($enc_key) !== 32) {
        throw new RuntimeException(
            'BillDesk config error: enc_key must be exactly 32 characters '
            . '(current: ' . strlen($enc_key) . ').'
        );
    }

    if (empty($sign_key) || $sign_key === 'YOUR_SIGNING_KEY') {
        throw new RuntimeException('BillDesk config error: sign_key is not set.');
    }
}