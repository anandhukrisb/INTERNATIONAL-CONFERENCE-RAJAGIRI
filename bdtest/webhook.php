<?php
$sign_key = 'YOUR_SIGNING_KEY';
$enc_key  = 'YOUR_ENCRYPTION_KEY';

function b64e($d){return rtrim(strtr(base64_encode($d),'+/','-_'),'=');}
function b64d($d){return base64_decode(strtr($d,'-_','+/').str_repeat('=',3-(3+strlen($d))%4));}

$raw = $_POST['transaction_response'] ?? '';
if(empty($raw)) { http_response_code(400); exit; }

try {
    [$h,$p,$s] = explode('.',$raw);
    if(!hash_equals(b64e(hash_hmac('sha256',"$h.$p",$sign_key,true)),$s))
        throw new Exception('Bad signature');

    [$hb64,,$iv64,$ct64,$tag64] = explode('.',b64d($p));
    $txn = json_decode(openssl_decrypt(b64d($ct64),'aes-256-gcm',$enc_key,
           OPENSSL_RAW_DATA,b64d($iv64),b64d($tag64),$hb64),true);

    // Log it to a file (for debugging)
    $log = date('Y-m-d H:i:s') . ' | '
         . 'Order:'  . $txn['orderid'] . ' | '
         . 'Status:' . $txn['auth_status'] . ' | '
         . 'TxnID:'  . $txn['transactionid'] . "\n";

    file_put_contents(__DIR__ . '/webhook_log.txt', $log, FILE_APPEND);

    // TODO: update your DB here based on auth_status

    http_response_code(200);
    echo 'OK';

} catch(Exception $e){
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}