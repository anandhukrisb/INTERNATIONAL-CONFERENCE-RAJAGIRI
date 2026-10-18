<?php

/**
 * ------------------------------------------------------------
 * redispatch_client_webhooks.php  (CLI only)
 * ------------------------------------------------------------
 * Re-sends the Layer 2 notification (Vortex -> client app) for
 * transactions whose client was never told the current status.
 *
 * Usage (from the vortex/ folder):
 *   php tools/redispatch_client_webhooks.php VTX1792014584591429724F4
 *   php tools/redispatch_client_webhooks.php --all
 *
 * Needs the new Webhook.php (dispatchClientWebhook returns true/false)
 * and the columns client_notified_status / client_notified_at.
 * ------------------------------------------------------------
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

require_once __DIR__ . '/../classes/Webhook.php';
require_once __DIR__ . '/../classes/Logger.php';

$arg = $argv[1] ?? '';
if ($arg === '') {
    fwrite(STDERR, "Usage: php tools/redispatch_client_webhooks.php <VORTEX_TXN_ID | --all>\n");
    exit(1);
}

$db = Database::getInstance()->getConnection();

if ($arg === '--all') {
    $stmt = $db->query("
        SELECT id, vortex_transaction_id, status, api_client_id
        FROM transactions
        WHERE status IN ('SUCCESS','FAILED','REFUNDED','DISPUTED')
          AND (client_notified_status IS NULL OR client_notified_status <> status)
        ORDER BY id ASC
        LIMIT 200
    ");
} else {
    $stmt = $db->prepare("
        SELECT id, vortex_transaction_id, status, api_client_id
        FROM transactions
        WHERE vortex_transaction_id = ?
        LIMIT 1
    ");
    $stmt->execute([$arg]);
}

$rows = $stmt->fetchAll();

if (!$rows) {
    echo "No matching transactions found.\n";
    exit(0);
}

$webhook = new Webhook();

foreach ($rows as $tx) {
    echo "{$tx['vortex_transaction_id']} ({$tx['status']}) ... ";

    $ok = $webhook->dispatchClientWebhook(
        $tx['vortex_transaction_id'],
        strtoupper((string) $tx['status']),
        $tx['api_client_id']
    );

    if ($ok === true) {
        $db->prepare(
            "UPDATE transactions SET client_notified_status = ?, client_notified_at = NOW() WHERE id = ?"
        )->execute([strtoupper((string) $tx['status']), $tx['id']]);
        echo "DELIVERED\n";
    } else {
        echo "FAILED (see the Vortex log for the exact reason)\n";
    }
}