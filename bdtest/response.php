<?php
$sign_key = 'YOUR_SIGNING_KEY';
$enc_key  = 'YOUR_ENCRYPTION_KEY';

function b64e($d){return rtrim(strtr(base64_encode($d),'+/','-_'),'=');}
function b64d($d){return base64_decode(strtr($d,'-_','+/').str_repeat('=',3-(3+strlen($d))%4));}

$raw = $_POST['transaction_response'] ?? '';
if(empty($raw)) die('<h2>No response received</h2>');

try {
    // Verify + Decrypt
    [$h,$p,$s] = explode('.',$raw);
    if(!hash_equals(b64e(hash_hmac('sha256',"$h.$p",$sign_key,true)),$s))
        throw new Exception('Signature mismatch');

    [$hb64,,$iv64,$ct64,$tag64] = explode('.',b64d($p));
    $txn = json_decode(openssl_decrypt(b64d($ct64),'aes-256-gcm',$enc_key,
           OPENSSL_RAW_DATA,b64d($iv64),b64d($tag64),$hb64),true);

    $auth_status = $txn['auth_status'] ?? 'NOT RECEIVED';

    switch($auth_status) {

        case '0300':
            // ✅ SUCCESS
            // TODO: mark order as paid in your DB
            $msg   = '✅ Payment Successful!';
            $color = 'green';
            $note  = 'Do not allow another payment for this order.';
            break;

        case '0399':
            // ❌ FAILURE
            $msg   = '❌ Payment Failed';
            $color = 'red';
            $note  = 'Reason: ' . ($txn['transaction_error_desc'] ?? 'Unknown');
            // TODO: allow fresh payment
            break;

        case '0002':
            // ⏳ PENDING
            $msg   = '⏳ Payment Pending';
            $color = 'orange';
            $note  = 'Money may be debited. Please check back after 60 minutes.
                      We will verify using Retrieve Transaction API.';
            // TODO: store order as pending, run retrieve API after 60 mins
            break;

        default:
            // NOT RECEIVED
            $msg   = '⏳ Response Not Received';
            $color = 'orange';
            $note  = 'Please check back after 60 minutes.';
            // TODO: same as 0002
            break;
    }

    echo "
    <div style='font-family:sans-serif; padding:40px; text-align:center'>
        <h2 style='color:$color'>$msg</h2>
        <p>Order ID: <b>{$txn['orderid']}</b></p>
        <p>Transaction ID: <b>{$txn['transactionid']}</b></p>
        <p>Amount: <b>₹{$txn['amount']}</b></p>
        <p>Auth Status: <b>$auth_status</b></p>
        <p style='color:gray'>$note</p>
        <a href='/payment_test.html'>← Back</a>
    </div>";

} catch(Exception $e){
    echo "<h2 style='color:red'>Error: ".$e->getMessage()."</h2>";
}