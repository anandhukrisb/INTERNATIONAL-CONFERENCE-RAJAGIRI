<?php

$merchant_id = 'YOUR_MERCHANT_ID';
$client_id   = 'YOUR_CLIENT_ID';
$enc_key     = 'YOUR_ENCRYPTION_KEY';  
$sign_key    = 'YOUR_SIGNING_KEY';
$enc_key_id  = 'YOUR_ENC_KEY_ID';
$sign_key_id = 'YOUR_SIGN_KEY_ID';
$return_url  = 'http://localhost/conference/bdtest/response.php'; // change to live URL when deploying
$api_url     = 'https://uat1.billdesk.com/u2/payments/ve1_2/orders/create';

header('Content-Type: application/json');

// Set timezone to IST (required by BillDesk)
date_default_timezone_set('Asia/Kolkata');

function b64e($d) { return rtrim(strtr(base64_encode($d),'+/','-_'),'='); }
function b64d($d) { return base64_decode(strtr($d,'-_','+/').str_repeat('=',3-(3+strlen($d))%4)); }

function bd_encrypt($payload,$enc_key,$enc_key_id,$client_id){
    $h  = b64e(json_encode(['alg'=>'dir','enc'=>'A256GCM','kid'=>$enc_key_id,'clientid'=>$client_id]));
    $iv = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt(json_encode($payload),'aes-256-gcm',$enc_key,OPENSSL_RAW_DATA,$iv,$tag,$h,16);
    if($ct === false) throw new Exception('Encryption failed — check enc_key is exactly 32 chars');
    return implode('.', [$h,'',b64e($iv),b64e($ct),b64e($tag)]);
}

function bd_sign($jwe,$sign_key,$sign_key_id,$client_id){
    $h = b64e(json_encode(['alg'=>'HS256','kid'=>$sign_key_id,'clientid'=>$client_id]));
    $p = b64e($jwe);
    return "$h.$p.".b64e(hash_hmac('sha256',"$h.$p",$sign_key,true));
}

function bd_verify($jws,$sign_key){
    $parts = explode('.',$jws);
    if(count($parts) !== 3) throw new Exception('Invalid JWS format');
    [$h,$p,$s] = $parts;
    if(!hash_equals(b64e(hash_hmac('sha256',"$h.$p",$sign_key,true)),$s))
        throw new Exception('Signature invalid — check sign_key');
    return b64d($p);
}

function bd_decrypt($jwe,$enc_key){
    $parts = explode('.',$jwe);
    if(count($parts) !== 5) throw new Exception('Invalid JWE format');
    [$hb64,,$iv64,$ct64,$tag64] = $parts;
    $plain = openssl_decrypt(b64d($ct64),'aes-256-gcm',$enc_key,OPENSSL_RAW_DATA,b64d($iv64),b64d($tag64),$hb64);
    if($plain === false) throw new Exception('Decryption failed — check enc_key is exactly 32 chars');
    return json_decode($plain,true);
}

try {

    // ── Validate keys before doing anything ──────────
    if(strlen($enc_key) !== 32)
        throw new Exception('enc_key must be exactly 32 characters. Current length: ' . strlen($enc_key));
    if(empty($sign_key))
        throw new Exception('sign_key is empty');
    if(empty($merchant_id) || $merchant_id === 'YOUR_MERCHANT_ID')
        throw new Exception('merchant_id not set');
    if(empty($client_id) || $client_id === 'YOUR_CLIENT_ID')
        throw new Exception('client_id not set');

    // ── Generate unique Order ID & Trace ID ──────────
    // Rules: alphanumeric only, no special chars, min 10, max 35
    $order_id = 'TEST' . strtoupper(bin2hex(random_bytes(8)));   // e.g. TESTA1B2C3D4E5F6G7H8
    $trace_id = 'TRC'  . strtoupper(bin2hex(random_bytes(8)));   // e.g. TRCA1B2C3D4E5F6G7H8

    // ── Timestamp in IST format yyyymmddHHmmss ────────
    $timestamp = date('YmdHis');   // e.g. 20240130105915

    // ── Build payload ─────────────────────────────────
    $payload = [
        'mercid'          => $merchant_id,
        'orderid'         => $order_id,
        'amount'          => '1.00',
        'order_date'      => date('Y-m-d\TH:i:sP'),   // e.g. 2024-01-30T10:59:15+05:30
        'currency'        => '356',                    // 356 = INR
        'ru'              => $return_url,
        'itemcode'        => 'DIRECT',
        'additional_info' => [
            'additional_info1' => 'test'
        ],
        'device'          => [
            'init_channel'  => 'internet',
            'ip'            => $_SERVER['REMOTE_ADDR'],
            'user_agent'    => $_SERVER['HTTP_USER_AGENT'],
            'accept_header' => 'text/html'
        ]
    ];

    // ── Encrypt → Sign ────────────────────────────────
    $body = bd_sign(
                bd_encrypt($payload, $enc_key, $enc_key_id, $client_id),
                $sign_key, $sign_key_id, $client_id
            );

    // ── Call BillDesk API ─────────────────────────────
    $ch = curl_init($api_url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/jose',
            'Accept: application/jose',
            'BD-Traceid: '   . $trace_id,
            'BD-Timestamp: ' . $timestamp   // IST yyyymmddHHmmss
        ]
    ]);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if($curl_err)
        throw new Exception('cURL Error: ' . $curl_err);

    if($http_code === 401)
        throw new Exception('HTTP 401 Unauthorized — Keys not active yet. Wait for BillDesk UAT setup.');

    if($http_code === 400)
        throw new Exception('HTTP 400 Bad Request — Check merchant_id, client_id and payload format.');

    if($http_code !== 200)
        throw new Exception('BillDesk returned HTTP ' . $http_code . ' → ' . $response);

    // ── Verify → Decrypt response ─────────────────────
    $order    = bd_decrypt(bd_verify($response, $sign_key), $enc_key);
    $redirect = null;

    foreach($order['links'] as $link){
        if($link['rel'] === 'redirect'){ $redirect = $link; break; }
    }

    if(!$redirect)
        throw new Exception('No redirect link in response — ' . json_encode($order));

    // ── Return redirect params to frontend ───────────
    echo json_encode([
        'action_url' => $redirect['href'],
        'bdorderid'  => $redirect['parameters']['bdorderid'],
        'merchantid' => $redirect['parameters']['mercid'],
        'rdata'      => $redirect['parameters']['rdata']
    ]);

} catch(Exception $e){
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}