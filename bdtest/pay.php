<?php

require_once 'config.php';
require_once 'billdesk_helper.php';

// ── Only accept JSON requests ──────────────────────────────────
header('Content-Type: application/json');

try {

    // ── 1. Validate Configuration ─────────────────────────────
    bd_validate_config($merchant_id, $client_id, $enc_key, $sign_key);

    // ── 2. Read & validate POST input ─────────────────────────
    // Expected from the registration form:
    //   amount       — registration fee in INR (string, e.g. "5000.00")
    //   registration_id — internal ID linking payment to registrant
    $amount          = trim($_POST['amount']          ?? '');
    $registration_id = trim($_POST['registration_id'] ?? '');

    if (empty($amount) || !is_numeric($amount) || (float)$amount <= 0) {
        throw new RuntimeException('Invalid or missing amount.');
    }

    if (empty($registration_id)) {
        throw new RuntimeException('Missing registration_id.');
    }

    // Format amount as "XXXX.XX" (two decimal places, no comma)
    $amount = number_format((float)$amount, 2, '.', '');

    // ── 3. Generate unique IDs ────────────────────────────────
    // Rules per BillDesk spec: alphanumeric only, 10–35 chars.
    $order_id = 'REG' . strtoupper(bin2hex(random_bytes(8)));  // 3 + 16 = 19 chars
    $trace_id = 'TRC' . strtoupper(bin2hex(random_bytes(8)));

    // Timestamp in IST: yyyymmddHHmmss
    $timestamp = date('YmdHis');

    // ── 4. Build payload ──────────────────────────────────────
    $payload = [
        'mercid'          => $merchant_id,
        'orderid'         => $order_id,
        'amount'          => $amount,
        'order_date'      => date('Y-m-d\TH:i:sP'),   // ISO 8601 with +05:30
        'currency'        => '356',                     // 356 = INR
        'ru'              => $return_url,
        'itemcode'        => 'DIRECT',
        'additional_info' => [
            'additional_info1' => $registration_id,    // store reg ID for lookup
        ],
        'device'          => [
            'init_channel'  => 'internet',
            'ip'            => $_SERVER['REMOTE_ADDR']  ?? '127.0.0.1',
            'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'accept_header' => $_SERVER['HTTP_ACCEPT']  ?? 'text/html',
        ],
    ];

    bd_log('pay', 'ORDER_INIT', [
        'order_id'        => $order_id,
        'registration_id' => $registration_id,
        'amount'          => $amount,
        'trace_id'        => $trace_id,
    ]);

    // ── 5. Encrypt → Sign ─────────────────────────────────────
    $jws_body = bd_sign(
        bd_encrypt($payload, $enc_key, $enc_key_id, $client_id),
        $sign_key,
        $sign_key_id,
        $client_id
    );

    // ── 6. Call BillDesk Create Order API ────────────────────
    $ch = curl_init($create_order_url);

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jws_body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/jose',
            'Accept: application/jose',
            'BD-Traceid: '   . $trace_id,
            'BD-Timestamp: ' . $timestamp,
        ],
    ]);

    $raw_response = curl_exec($ch);
    $http_code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error   = curl_error($ch);
    curl_close($ch);

    // ── 7. Handle cURL / HTTP errors ─────────────────────────
    if (!empty($curl_error)) {
        bd_log('pay', 'CURL_ERROR', ['error' => $curl_error, 'order_id' => $order_id]);
        throw new RuntimeException('Network error communicating with BillDesk.');
    }

    if ($http_code === 400) {
        bd_log('pay', 'HTTP_400', ['order_id' => $order_id, 'response' => $raw_response]);
        throw new RuntimeException('BillDesk rejected the request (HTTP 400). Check merchant credentials and payload.');
    }

    if ($http_code === 401) {
        bd_log('pay', 'HTTP_401', ['order_id' => $order_id]);
        throw new RuntimeException('BillDesk authorisation failed (HTTP 401). Keys may not be active yet.');
    }

    if ($http_code !== 200) {
        bd_log('pay', 'HTTP_ERROR', ['http_code' => $http_code, 'order_id' => $order_id]);
        throw new RuntimeException("BillDesk returned unexpected HTTP {$http_code}.");
    }

    // ── 8. Verify → Decrypt response ─────────────────────────
    $jwe   = bd_verify($raw_response, $sign_key);
    $order = bd_decrypt($jwe, $enc_key);

    // ── 9. Extract redirect link ──────────────────────────────
    $redirect = null;

    foreach (($order['links'] ?? []) as $link) {
        if (($link['rel'] ?? '') === 'redirect') {
            $redirect = $link;
            break;
        }
    }

    if (!$redirect) {
        bd_log('pay', 'NO_REDIRECT', ['order_id' => $order_id, 'order' => $order]);
        throw new RuntimeException('BillDesk response did not contain a redirect URL.');
    }

    bd_log('pay', 'ORDER_CREATED', [
        'order_id'   => $order_id,
        'bdorderid'  => $redirect['parameters']['bdorderid'] ?? '',
        'amount'     => $amount,
    ]);

    // ── 10. Return redirect parameters to frontend JS ─────────
    echo json_encode([
        'success'    => true,
        'action_url' => $redirect['href'],
        'bdorderid'  => $redirect['parameters']['bdorderid'] ?? '',
        'merchantid' => $redirect['parameters']['mercid']    ?? '',
        'rdata'      => $redirect['parameters']['rdata']     ?? '',
        'order_id'   => $order_id,
    ], JSON_UNESCAPED_SLASHES);

} catch (RuntimeException $e) {

    bd_log('pay', 'ERROR', ['message' => $e->getMessage()]);
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);

} catch (Exception $e) {

    bd_log('pay', 'UNEXPECTED_ERROR', [
        'message' => $e->getMessage(),
        'trace'   => $e->getTraceAsString(),
    ]);
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error'   => 'An unexpected error occurred. Please try again.',
    ]);
}