<?php
require_once 'pay.php'; // reuse helpers

$order_id = $_GET['orderid'] ?? '';
if(empty($order_id)) die('No order ID');

$payload = [
    'mercid'  => $merchant_id,
    'orderid' => $order_id
];

$body = bd_sign(bd_encrypt($payload,$enc_key,$enc_key_id,$client_id),
                $sign_key,$sign_key_id,$client_id);

date_default_timezone_set('Asia/Kolkata');
$ch = curl_init('https://uat1.billdesk.com/u2/payments/ve1_2/transactions/get');
curl_setopt_array($ch,[
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/jose',
        'Accept: application/jose',
        'BD-Traceid: '   . 'TRC' . strtoupper(bin2hex(random_bytes(8))),
        'BD-Timestamp: ' . date('YmdHis')
    ]
]);

$response = curl_exec($ch);
curl_close($ch);

$txn = bd_decrypt(bd_verify($response,$sign_key),$enc_key);
echo json_encode($txn, JSON_PRETTY_PRINT);