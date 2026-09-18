<?php

require_once __DIR__ . '/classes/ApiClient.php';

$apiClient = new ApiClient();

$result = $apiClient->createClient(
    'Vortex Demo Developer'
);

print_r($result);?>