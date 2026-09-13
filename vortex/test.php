<?php

$url = "http://localhost:8000/api/create_payment_order.php";

$data = [
    "api_key" => "f4bcb154a50ce3f2555bdbc2fe01b47f",
    "api_secret" => "96becb36438bf570c6f6e8a203f824d2",
    "event_id" => "NATI20261015MTMHTO3W5RK",
    "email" => "test@example.com",
    "mobile" => "9876543210",
    "amount" => 500,
    "currency" => "INR",
    "redirect_url" => "http://localhost:9000/Vortex/payment-result.php"
];

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Accept: application/json"
]);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);

if ($response === false) {
    echo "cURL Error: " . curl_error($ch);
    curl_close($ch);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

echo "HTTP Status: " . $httpCode . "<br><br>";

$result = json_decode($response, true);

echo "<pre>";
print_r($result);
echo "</pre>";

if (
    isset($result["status"]) &&
    $result["status"] === "success" &&
    isset($result["data"]["payment_url"])
) {
    echo "<h3>Payment created successfully!</h3>";

    echo '<a href="' .
         htmlspecialchars($result["data"]["payment_url"]) .
         '">Open Checkout</a>';
}
?>