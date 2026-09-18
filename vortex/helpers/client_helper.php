<?php
// Helper function to generate secure API keys and secrets for external clients

function generateApiClientKeys() {
    return [
        'api_key'    => 'vtx_live_' . bin2hex(random_bytes(16)), // e.g. vtx_live_3f8a92b1c4d...
        'api_secret' => 'vtx_sec_'  . bin2hex(random_bytes(24))  // e.g. vtx_sec_99a8b7c6d...
    ];
}
