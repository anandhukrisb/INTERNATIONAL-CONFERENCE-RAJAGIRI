<?php

/**
 * ------------------------------------------------------------
 * WebhookQueueService.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 *
 * Purpose:
 * Outbound webhook delivery engine with guaranteed at-least-once
 * delivery, automated retry queue, and exponential backoff:
 *   - Initial Attempt (t = 0)
 *   - Retry 1: +3 minutes
 *   - Retry 2: +9 minutes
 *   - Retry 3: +27 minutes
 * Total maximum attempts: 4 (1 initial + 3 retries).
 * If all retries fail, status transitions to PERMANENTLY_FAILED
 * and a critical alert is triggered in the Notification Center.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Logger.php';

class WebhookQueueService
{
    /**
     * Exponential backoff delays in minutes.
     * Index represents the retry number:
     *   Retry 1 -> 3 minutes
     *   Retry 2 -> 9 minutes
     *   Retry 3 -> 27 minutes
     */
    public const BACKOFF_INTERVALS_MINUTES = [
        1 => 3,   // 1st retry: 3m
        2 => 9,   // 2nd retry: 9m
        3 => 27   // 3rd retry: 27m
    ];

    /**
     * Total maximum attempts allowed (1 initial + 3 retries)
     */
    public const MAX_ATTEMPTS = 4;

    /**
     * Database connection.
     * @var \PDO
     */
    private $db;

    public function __construct(?\PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    /**
     * Dispatch an outbound webhook immediately, or enqueue for retry on failure.
     *
     * @param string      $vortexTxnId
     * @param string      $status
     * @param int         $apiClientId
     * @param string|null $eventType
     * @param array|null  $extraData
     * @return array
     */
    public function dispatchOrEnqueue(
        string $vortexTxnId,
        string $status,
        int $apiClientId,
        ?string $eventType = null,
        ?array $extraData = null
    ): array {
        try {
            // 1. Fetch merchant webhook credentials
            $stmt = $this->db->prepare("SELECT client_name, webhook_url, webhook_secret FROM api_clients WHERE id = ? LIMIT 1");
            $stmt->execute([$apiClientId]);
            $client = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$client || empty($client['webhook_url']) || empty($client['webhook_secret'])) {
                Logger::info("WebhookQueueService: Client #{$apiClientId} has no webhook_url or webhook_secret. Skipped.");
                return [
                    'success' => false,
                    'delivered' => false,
                    'enqueued' => false,
                    'message' => 'No webhook URL or secret configured for client.'
                ];
            }

            $webhookUrl    = trim($client['webhook_url']);
            $webhookSecret = trim($client['webhook_secret']);
            $resolvedEvent = $eventType ?? ('payment.' . strtolower($status));

            // 2. Prepare payload & cryptographic HMAC signature
            $payloadData = [
                'event'                 => $resolvedEvent,
                'vortex_transaction_id' => $vortexTxnId,
                'status'                => $status,
                'timestamp'             => date('Y-m-d H:i:s')
            ];

            if (!empty($extraData)) {
                $payloadData['data'] = $extraData;
            }

            $payloadJson = json_encode($payloadData, JSON_UNESCAPED_SLASHES);
            $signature   = hash_hmac('sha256', $payloadJson, $webhookSecret);

            // 3. Attempt immediate delivery (Attempt 1)
            $deliveryResult = $this->executeHttpDelivery($webhookUrl, $payloadJson, $signature);

            $attempts    = 1;
            $httpCode    = $deliveryResult['http_code'];
            $curlError   = $deliveryResult['curl_error'];
            $isDelivered = $deliveryResult['success'];

            if ($isDelivered) {
                // Immediate success!
                $stmtInsert = $this->db->prepare("
                    INSERT INTO outbound_webhook_queue (
                        vortex_transaction_id, api_client_id, webhook_url, event_type,
                        payload, signature, attempts, max_attempts, status,
                        last_attempt_at, next_attempt_at, last_http_code, delivered_at
                    ) VALUES (
                        ?, ?, ?, ?,
                        ?, ?, ?, ?, 'DELIVERED',
                        NOW(), NOW(), ?, NOW()
                    )
                ");
                $stmtInsert->execute([
                    $vortexTxnId, $apiClientId, $webhookUrl, $resolvedEvent,
                    $payloadJson, $signature, $attempts, self::MAX_ATTEMPTS,
                    $httpCode
                ]);
                $queueId = (int) $this->db->lastInsertId();

                Logger::info("WebhookQueueService: Webhook delivered immediately to {$webhookUrl} (HTTP {$httpCode}, Queue #{$queueId}).");

                return [
                    'success'   => true,
                    'delivered' => true,
                    'enqueued'  => false,
                    'queue_id'  => $queueId,
                    'attempts'  => 1,
                    'http_code' => $httpCode,
                    'message'   => 'Webhook delivered immediately.'
                ];
            }

            // Delivery failed: schedule 1st retry with 3-minute backoff!
            $delayMinutes = self::BACKOFF_INTERVALS_MINUTES[1]; // 3 min
            $lastError    = $curlError !== '' ? "cURL error: {$curlError}" : "HTTP {$httpCode}: " . substr((string) ($deliveryResult['response'] ?? ''), 0, 200);

            $stmtInsert = $this->db->prepare("
                INSERT INTO outbound_webhook_queue (
                    vortex_transaction_id, api_client_id, webhook_url, event_type,
                    payload, signature, attempts, max_attempts, status,
                    last_attempt_at, next_attempt_at, last_http_code, last_error
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, 'PENDING',
                    NOW(), DATE_ADD(NOW(), INTERVAL ? MINUTE), ?, ?
                )
            ");
            $stmtInsert->execute([
                $vortexTxnId, $apiClientId, $webhookUrl, $resolvedEvent,
                $payloadJson, $signature, $attempts, self::MAX_ATTEMPTS,
                $delayMinutes, $httpCode, $lastError
            ]);
            $queueId = (int) $this->db->lastInsertId();

            Logger::warning(
                "WebhookQueueService: Immediate delivery failed for Txn {$vortexTxnId} to {$webhookUrl} "
                . "({$lastError}). Enqueued for Retry 1 in {$delayMinutes}m (Queue #{$queueId})."
            );

            // Trigger Notification Center warning alert
            $this->triggerNotificationAlert(
                $apiClientId,
                $webhookUrl,
                $httpCode,
                "Initial webhook failed ({$lastError}). Scheduled Retry 1 in {$delayMinutes} minutes.",
                $vortexTxnId,
                'warning'
            );

            return [
                'success'          => false,
                'delivered'        => false,
                'queued_for_retry' => true,
                'queue_id'         => $queueId,
                'attempts'         => 1,
                'retry_delay_min'  => $delayMinutes,
                'http_code'        => $httpCode,
                'error'            => $lastError,
                'message'          => "Initial delivery failed. Enqueued for Retry 1 in {$delayMinutes} minutes."
            ];

        } catch (\Throwable $e) {
            Logger::error("WebhookQueueService dispatchOrEnqueue error: " . $e->getMessage());
            return [
                'success'   => false,
                'delivered' => false,
                'enqueued'  => false,
                'error'     => $e->getMessage()
            ];
        }
    }

    /**
     * Process all pending retries whose next_attempt_at has elapsed.
     *
     * @param int         $limit
     * @param string|null $simulatedNow Optional datetime string for deterministic testing
     * @return array Summary of processed items
     */
    public function processPendingRetries(int $limit = 50, ?string $simulatedNow = null): array
    {
        $now = $simulatedNow ?? date('Y-m-d H:i:s');
        $summary = [
            'scanned'            => 0,
            'delivered'          => 0,
            'rescheduled'        => 0,
            'permanently_failed' => 0,
            'items'              => []
        ];

        try {
            // Find due pending items
            $stmt = $this->db->prepare("
                SELECT id, vortex_transaction_id, api_client_id, webhook_url,
                       event_type, payload, signature, attempts, max_attempts
                FROM outbound_webhook_queue
                WHERE status = 'PENDING' AND next_attempt_at <= ?
                ORDER BY next_attempt_at ASC
                LIMIT ?
            ");
            $stmt->bindValue(1, $now, \PDO::PARAM_STR);
            $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $pendingItems = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $summary['scanned'] = count($pendingItems);

            foreach ($pendingItems as $item) {
                $queueId = (int) $item['id'];

                // Atomically claim the item
                $claimStmt = $this->db->prepare("UPDATE outbound_webhook_queue SET status = 'PROCESSING', updated_at = NOW() WHERE id = ? AND status = 'PENDING'");
                $claimStmt->execute([$queueId]);
                if ($claimStmt->rowCount() === 0) {
                    continue; // claimed by another worker
                }

                // Execute attempt
                $deliveryResult = $this->executeHttpDelivery(
                    $item['webhook_url'],
                    $item['payload'],
                    $item['signature']
                );

                $newAttempts = (int) $item['attempts'] + 1;
                $httpCode    = $deliveryResult['http_code'];
                $curlError   = $deliveryResult['curl_error'];
                $isDelivered = $deliveryResult['success'];

                if ($isDelivered) {
                    // Success!
                    $upd = $this->db->prepare("
                        UPDATE outbound_webhook_queue
                        SET status = 'DELIVERED',
                            attempts = ?,
                            last_attempt_at = NOW(),
                            delivered_at = NOW(),
                            last_http_code = ?,
                            last_error = NULL
                        WHERE id = ?
                    ");
                    $upd->execute([$newAttempts, $httpCode, $queueId]);

                    // Update transactions table if transaction exists
                    $this->updateTransactionNotifiedStatus($item['vortex_transaction_id']);

                    Logger::info("WebhookQueueService: Retry #{$newAttempts} succeeded for Queue #{$queueId} (HTTP {$httpCode}).");
                    $summary['delivered']++;
                    $summary['items'][] = [
                        'queue_id' => $queueId,
                        'status'   => 'DELIVERED',
                        'attempts' => $newAttempts
                    ];
                    continue;
                }

                // Failed attempt
                $lastError = $curlError !== '' ? "cURL error: {$curlError}" : "HTTP {$httpCode}: " . substr((string) ($deliveryResult['response'] ?? ''), 0, 200);

                if ($newAttempts >= (int) $item['max_attempts']) {
                    // Exhausted all retries (Total 4 attempts: Initial + 3, 9, 27 min)
                    $upd = $this->db->prepare("
                        UPDATE outbound_webhook_queue
                        SET status = 'PERMANENTLY_FAILED',
                            attempts = ?,
                            last_attempt_at = NOW(),
                            last_http_code = ?,
                            last_error = ?
                        WHERE id = ?
                    ");
                    $upd->execute([$newAttempts, $httpCode, $lastError, $queueId]);

                    Logger::error("WebhookQueueService: Queue #{$queueId} PERMANENTLY FAILED after {$newAttempts} attempts ({$lastError}).");

                    // Trigger Critical Alert
                    $this->triggerNotificationAlert(
                        (int) $item['api_client_id'],
                        $item['webhook_url'],
                        $httpCode,
                        "Webhook permanently failed after {$newAttempts} attempts ({$lastError}). Manual intervention required.",
                        $item['vortex_transaction_id'],
                        'critical'
                    );

                    $summary['permanently_failed']++;
                    $summary['items'][] = [
                        'queue_id' => $queueId,
                        'status'   => 'PERMANENTLY_FAILED',
                        'attempts' => $newAttempts,
                        'error'    => $lastError
                    ];

                } else {
                    // Compute next exponential backoff delay
                    // $newAttempts = 2 -> Retry 1 just failed -> schedule Retry 2 in 9 min
                    // $newAttempts = 3 -> Retry 2 just failed -> schedule Retry 3 in 27 min
                    $retryIndex = $newAttempts; // 2 -> 9m, 3 -> 27m
                    $delayMinutes = self::BACKOFF_INTERVALS_MINUTES[$retryIndex] ?? 27;

                    $upd = $this->db->prepare("
                        UPDATE outbound_webhook_queue
                        SET status = 'PENDING',
                            attempts = ?,
                            last_attempt_at = NOW(),
                            next_attempt_at = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                            last_http_code = ?,
                            last_error = ?
                        WHERE id = ?
                    ");
                    $upd->execute([$newAttempts, $delayMinutes, $httpCode, $lastError, $queueId]);

                    Logger::warning("WebhookQueueService: Queue #{$queueId} attempt #{$newAttempts} failed ({$lastError}). Next retry in {$delayMinutes}m.");

                    $summary['rescheduled']++;
                    $summary['items'][] = [
                        'queue_id'         => $queueId,
                        'status'           => 'PENDING',
                        'attempts'         => $newAttempts,
                        'next_delay_min'   => $delayMinutes,
                        'error'            => $lastError
                    ];
                }
            }

        } catch (\Throwable $e) {
            Logger::error("WebhookQueueService processPendingRetries error: " . $e->getMessage());
        }

        return $summary;
    }

    /**
     * Manually trigger an immediate retry on any queue item (Admin / Merchant action).
     *
     * @param int $queueId
     * @return array
     */
    public function retryManually(int $queueId): array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM outbound_webhook_queue WHERE id = ? LIMIT 1");
            $stmt->execute([$queueId]);
            $item = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$item) {
                return ['success' => false, 'message' => 'Queue item not found.'];
            }

            $deliveryResult = $this->executeHttpDelivery(
                $item['webhook_url'],
                $item['payload'],
                $item['signature']
            );

            $newAttempts = (int) $item['attempts'] + 1;
            $httpCode    = $deliveryResult['http_code'];
            $curlError   = $deliveryResult['curl_error'];
            $isDelivered = $deliveryResult['success'];

            if ($isDelivered) {
                $this->db->prepare("
                    UPDATE outbound_webhook_queue
                    SET status = 'DELIVERED',
                        attempts = ?,
                        last_attempt_at = NOW(),
                        delivered_at = NOW(),
                        last_http_code = ?,
                        last_error = NULL
                    WHERE id = ?
                ")->execute([$newAttempts, $httpCode, $queueId]);

                $this->updateTransactionNotifiedStatus($item['vortex_transaction_id']);

                return [
                    'success'   => true,
                    'delivered' => true,
                    'http_code' => $httpCode,
                    'message'   => "Manual delivery successful (HTTP {$httpCode})."
                ];
            }

            $lastError = $curlError !== '' ? "cURL error: {$curlError}" : "HTTP {$httpCode}: " . substr((string) ($deliveryResult['response'] ?? ''), 0, 200);

            $this->db->prepare("
                UPDATE outbound_webhook_queue
                SET attempts = ?,
                    last_attempt_at = NOW(),
                    last_http_code = ?,
                    last_error = ?
                WHERE id = ?
            ")->execute([$newAttempts, $httpCode, $lastError, $queueId]);

            return [
                'success'   => false,
                'delivered' => false,
                'http_code' => $httpCode,
                'error'     => $lastError,
                'message'   => "Manual retry failed: {$lastError}"
            ];

        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get queue statistics summary.
     *
     * @param int|null $apiClientId
     * @return array
     */
    public function getStats(?int $apiClientId = null): array
    {
        $where = $apiClientId !== null ? "WHERE api_client_id = " . (int)$apiClientId : "";
        $stats = [
            'total'              => 0,
            'delivered'          => 0,
            'pending_retry'      => 0,
            'permanently_failed' => 0,
            'success_rate'       => 100.0
        ];

        try {
            $stmt = $this->db->query("
                SELECT status, COUNT(*) as cnt
                FROM outbound_webhook_queue
                {$where}
                GROUP BY status
            ");
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $st = strtoupper($row['status']);
                $cnt = (int)$row['cnt'];
                $stats['total'] += $cnt;
                if ($st === 'DELIVERED') {
                    $stats['delivered'] += $cnt;
                } elseif ($st === 'PENDING' || $st === 'PROCESSING') {
                    $stats['pending_retry'] += $cnt;
                } elseif ($st === 'PERMANENTLY_FAILED' || $st === 'FAILED') {
                    $stats['permanently_failed'] += $cnt;
                }
            }

            if ($stats['total'] > 0) {
                $stats['success_rate'] = round(($stats['delivered'] / $stats['total']) * 100, 1);
            }
        } catch (\Throwable $e) {
            Logger::error("WebhookQueueService getStats error: " . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Get recent queue records for display in Admin or Merchant Portal.
     *
     * @param int         $limit
     * @param int|null    $apiClientId
     * @param string|null $statusFilter
     * @return array
     */
    public function getQueueItems(int $limit = 50, ?int $apiClientId = null, ?string $statusFilter = null): array
    {
        try {
            $sql = "
                SELECT q.*, c.client_name, c.api_key as merchant_identifier
                FROM outbound_webhook_queue q
                LEFT JOIN api_clients c ON q.api_client_id = c.id
                WHERE 1=1
            ";
            $params = [];

            if ($apiClientId !== null) {
                $sql .= " AND q.api_client_id = ?";
                $params[] = $apiClientId;
            }

            if (!empty($statusFilter) && $statusFilter !== 'ALL') {
                $sql .= " AND q.status = ?";
                $params[] = strtoupper($statusFilter);
            }

            $sql .= " ORDER BY q.id DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $this->db->prepare($sql);
            foreach ($params as $idx => $val) {
                $type = is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
                $stmt->bindValue($idx + 1, $val, $type);
            }
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            Logger::error("WebhookQueueService getQueueItems error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Low-level cURL HTTP execution.
     *
     * @param string $url
     * @param string $payload
     * @param string $signature
     * @return array
     */
    private function executeHttpDelivery(string $url, string $payload, string $signature): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Vortex-Webhook-Dispatcher/2.0 (Retry-Queue)');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Vortex-Signature: ' . $signature
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (in_array($host, ['localhost', '127.0.0.1'], true)) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        $response  = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $isSuccess = ($curlError === '' && $httpCode >= 200 && $httpCode < 300);

        return [
            'success'    => $isSuccess,
            'http_code'  => $httpCode,
            'curl_error' => $curlError,
            'response'   => $response
        ];
    }

    /**
     * Helper to update client_notified_status on transactions table.
     */
    private function updateTransactionNotifiedStatus(string $vortexTxnId): void
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE transactions
                SET client_notified_status = status,
                    client_notified_at = NOW()
                WHERE vortex_transaction_id = ?
            ");
            $stmt->execute([$vortexTxnId]);
        } catch (\Throwable $e) {
            // Ignore if column or transaction doesn't exist
        }
    }

    /**
     * Helper to trigger In-App Notification Center alert.
     */
    private function triggerNotificationAlert(
        int $apiClientId,
        string $url,
        int $httpCode,
        string $message,
        string $txnId,
        string $severity = 'warning'
    ): void {
        try {
            require_once __DIR__ . '/NotificationCenter.php';
            NotificationCenter::create(
                'failed_webhook',
                $severity === 'critical' ? 'Outbound Webhook Delivery Permanently Failed' : 'Outbound Webhook Scheduled for Retry',
                $message,
                $severity,
                "/vortex/admin/search.php?q=" . urlencode($txnId),
                $txnId,
                'transaction',
                null
            );
        } catch (\Throwable $e) {
            // Non-blocking
        }
    }
}
