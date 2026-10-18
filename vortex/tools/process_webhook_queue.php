<?php

/**
 * ------------------------------------------------------------
 * process_webhook_queue.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 *
 * Purpose:
 * Background CLI & Cron worker that scans for due pending outbound
 * webhooks and dispatches them with exponential backoff:
 *   - Retry 1: 3 min
 *   - Retry 2: 9 min
 *   - Retry 3: 27 min
 *
 * Usage:
 *   CLI:  php vortex/tools/process_webhook_queue.php [--limit=50]
 *   Cron: * * * * * /usr/bin/php /path/to/vortex/tools/process_webhook_queue.php >> /path/to/vortex/logs/webhook_worker.log 2>&1
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Logger.php';
require_once __DIR__ . '/../classes/WebhookQueueService.php';

$isCli = (php_sapi_name() === 'cli');

// Parse options
$limit = 50;
if ($isCli) {
    $options = getopt('', ['limit::', 'verbose::']);
    if (isset($options['limit']) && is_numeric($options['limit'])) {
        $limit = (int) $options['limit'];
    }
} else {
    // Basic protection if called via HTTP
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
}

$startTime = microtime(true);
$queueService = new WebhookQueueService();
$results = $queueService->processPendingRetries($limit);
$elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

$response = [
    'timestamp'          => date('Y-m-d H:i:s'),
    'scanned'            => $results['scanned'],
    'delivered'          => $results['delivered'],
    'rescheduled'        => $results['rescheduled'],
    'permanently_failed' => $results['permanently_failed'],
    'elapsed_ms'         => $elapsedMs,
    'items'              => $results['items']
];

if ($isCli) {
    echo "========================================\n";
    echo " VORTEX OUTBOUND WEBHOOK RETRY WORKER\n";
    echo "========================================\n";
    echo "Timestamp:          " . $response['timestamp'] . "\n";
    echo "Scanned Due Items:  " . $response['scanned'] . "\n";
    echo "Delivered:          " . $response['delivered'] . "\n";
    echo "Rescheduled:        " . $response['rescheduled'] . "\n";
    echo "Permanently Failed: " . $response['permanently_failed'] . "\n";
    echo "Execution Time:     " . $elapsedMs . " ms\n";
    echo "----------------------------------------\n";
    if (!empty($results['items'])) {
        foreach ($results['items'] as $item) {
            echo "Queue #{$item['queue_id']}: Status={$item['status']} Attempts={$item['attempts']}\n";
        }
    } else {
        echo "No pending webhook retries were due.\n";
    }
} else {
    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT);
}
