<?php

/**
 * ------------------------------------------------------------
 * NotificationCenter.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 *
 * Purpose : In-app Notification Center service for administrators.
 *           Captures, aggregates, and manages real-time security,
 *           operational, and financial gateway alerts:
 *           - Failed Webhooks (Layer 2 merchant dispatch failures)
 *           - New Disputes & Chargebacks (Razorpay dispute events)
 *           - High-Value Transactions (AML/fraud vigilance)
 *           - System & Security Alerts
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Logger.php';

class NotificationCenter
{
    public const TYPE_FAILED_WEBHOOK    = 'failed_webhook';
    public const TYPE_NEW_DISPUTE       = 'new_dispute';
    public const TYPE_HIGH_VALUE_TXN    = 'high_value_transaction';
    public const TYPE_SYSTEM_ALERT      = 'system_alert';

    public const SEVERITY_INFO          = 'info';
    public const SEVERITY_WARNING       = 'warning';
    public const SEVERITY_CRITICAL      = 'critical';

    // Default threshold for high-value transaction alerts (INR)
    public const DEFAULT_HIGH_VALUE_THRESHOLD = 10000.00;

    /**
     * Ensure database table exists
     */
    public static function ensureTableExists(): void
    {
        try {
            $db = Database::getInstance()->getConnection();
            $db->exec("
                CREATE TABLE IF NOT EXISTS admin_notifications (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    admin_id INT NULL,
                    type VARCHAR(50) NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    severity ENUM('info', 'warning', 'critical') DEFAULT 'info',
                    reference_id VARCHAR(100) NULL,
                    reference_type VARCHAR(50) NULL,
                    link VARCHAR(255) NULL,
                    is_read TINYINT(1) DEFAULT 0,
                    read_at DATETIME NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_admin_notif_admin (admin_id),
                    INDEX idx_admin_notif_type (type),
                    INDEX idx_admin_notif_unread (admin_id, is_read),
                    INDEX idx_admin_notif_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        } catch (\Throwable $e) {
            Logger::error("NotificationCenter::ensureTableExists failed: " . $e->getMessage());
        }
    }

    /**
     * Create an in-app administrative notification
     *
     * @param string      $type          Notification type (failed_webhook, new_dispute, etc.)
     * @param string      $title         Short summary title
     * @param string      $message       Detailed alert content
     * @param string      $severity      info | warning | critical
     * @param string|null $link          Optional direct admin route to inspect source
     * @param string|null $referenceId   Unique transaction/webhook ID for reference & deduplication
     * @param string|null $referenceType Entity type ('transaction', 'webhook', 'api_client', etc.)
     * @param int|null    $adminId       Target specific admin (NULL = broadcast to all admins)
     * @return int Created notification ID
     */
    public static function create(
        string $type,
        string $title,
        string $message,
        string $severity = self::SEVERITY_INFO,
        ?string $link = null,
        ?string $referenceId = null,
        ?string $referenceType = null,
        ?int $adminId = null
    ): int {
        self::ensureTableExists();
        $db = Database::getInstance()->getConnection();

        // Deduplication: prevent duplicate alerts for the same event within 30 minutes, but escalate to critical if needed
        if (!empty($referenceId)) {
            $checkStmt = $db->prepare("
                SELECT id, severity FROM admin_notifications 
                WHERE type = ? AND reference_id = ? 
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                LIMIT 1
            ");
            $checkStmt->execute([$type, $referenceId]);
            $existing = $checkStmt->fetch(\PDO::FETCH_ASSOC);
            if ($existing) {
                if ($severity === 'critical' && ($existing['severity'] ?? '') !== 'critical') {
                    $db->prepare("UPDATE admin_notifications SET severity = 'critical', title = ?, message = ?, is_read = 0 WHERE id = ?")
                       ->execute([$title, $message, $existing['id']]);
                }
                return (int)$existing['id'];
            }
        }

        $stmt = $db->prepare("
            INSERT INTO admin_notifications 
                (admin_id, type, title, message, severity, reference_id, reference_type, link, is_read, created_at)
            VALUES 
                (:admin_id, :type, :title, :message, :severity, :reference_id, :reference_type, :link, 0, NOW())
        ");

        $stmt->execute([
            ':admin_id'       => $adminId,
            ':type'           => $type,
            ':title'          => $title,
            ':message'        => $message,
            ':severity'       => $severity,
            ':reference_id'   => $referenceId,
            ':reference_type' => $referenceType,
            ':link'           => $link
        ]);

        $id = (int)$db->lastInsertId();

        Logger::info("NotificationCenter created [{$severity}] {$type} alert #{$id}: {$title}");
        return $id;
    }

    /**
     * Get unread notifications count
     */
    public static function getUnreadCount(?int $adminId = null): int
    {
        self::ensureTableExists();
        $db = Database::getInstance()->getConnection();

        if ($adminId !== null) {
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM admin_notifications 
                WHERE (admin_id = ? OR admin_id IS NULL) AND is_read = 0
            ");
            $stmt->execute([$adminId]);
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0");
            $stmt->execute();
        }

        return (int)$stmt->fetchColumn();
    }

    /**
     * Fetch list of notifications with filtering and pagination
     */
    public static function getNotifications(
        ?int $adminId = null,
        int $limit = 20,
        int $offset = 0,
        array $filters = []
    ): array {
        self::ensureTableExists();
        $db = Database::getInstance()->getConnection();

        $where = ["1=1"];
        $params = [];

        if ($adminId !== null) {
            $where[] = "(admin_id = :admin_id OR admin_id IS NULL)";
            $params[':admin_id'] = $adminId;
        }

        if (!empty($filters['unread_only'])) {
            $where[] = "is_read = 0";
        } elseif (isset($filters['is_read']) && $filters['is_read'] !== '') {
            $where[] = "is_read = :is_read";
            $params[':is_read'] = (int)$filters['is_read'];
        }

        if (!empty($filters['type']) && $filters['type'] !== 'all') {
            $where[] = "type = :type";
            $params[':type'] = $filters['type'];
        }

        if (!empty($filters['severity']) && $filters['severity'] !== 'all') {
            $where[] = "severity = :severity";
            $params[':severity'] = $filters['severity'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(title LIKE :q OR message LIKE :q OR reference_id LIKE :q)";
            $params[':q'] = '%' . trim($filters['search']) . '%';
        }

        $whereClause = implode(" AND ", $where);

        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $sql = "
            SELECT * FROM admin_notifications 
            WHERE {$whereClause} 
            ORDER BY created_at DESC 
            LIMIT {$limit} OFFSET {$offset}
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get aggregate statistics for notification center header
     */
    public static function getStats(?int $adminId = null): array
    {
        self::ensureTableExists();
        $db = Database::getInstance()->getConnection();

        $adminClause = $adminId !== null ? "AND (admin_id = ? OR admin_id IS NULL)" : "";
        $params = $adminId !== null ? [$adminId] : [];

        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_count,
                SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_count,
                SUM(CASE WHEN type = 'failed_webhook' THEN 1 ELSE 0 END) as failed_webhooks,
                SUM(CASE WHEN type = 'new_dispute' THEN 1 ELSE 0 END) as disputes,
                SUM(CASE WHEN type = 'high_value_transaction' THEN 1 ELSE 0 END) as high_value,
                SUM(CASE WHEN severity = 'critical' AND is_read = 0 THEN 1 ELSE 0 END) as critical_unread
            FROM admin_notifications
            WHERE 1=1 {$adminClause}
        ");
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        return [
            'total'           => (int)($row['total_count'] ?? 0),
            'unread'          => (int)($row['unread_count'] ?? 0),
            'failed_webhooks' => (int)($row['failed_webhooks'] ?? 0),
            'disputes'        => (int)($row['disputes'] ?? 0),
            'high_value'      => (int)($row['high_value'] ?? 0),
            'critical_unread' => (int)($row['critical_unread'] ?? 0)
        ];
    }

    /**
     * Mark a single notification as read
     */
    public static function markAsRead(int $notificationId, ?int $adminId = null): bool
    {
        self::ensureTableExists();
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            UPDATE admin_notifications 
            SET is_read = 1, read_at = NOW() 
            WHERE id = ?
        ");
        return $stmt->execute([$notificationId]);
    }

    /**
     * Mark all notifications as read
     */
    public static function markAllAsRead(?int $adminId = null): bool
    {
        self::ensureTableExists();
        $db = Database::getInstance()->getConnection();

        if ($adminId !== null) {
            $stmt = $db->prepare("
                UPDATE admin_notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE (admin_id = ? OR admin_id IS NULL) AND is_read = 0
            ");
            return $stmt->execute([$adminId]);
        }

        $stmt = $db->prepare("UPDATE admin_notifications SET is_read = 1, read_at = NOW() WHERE is_read = 0");
        return $stmt->execute();
    }

    /**
     * Delete notification
     */
    public static function delete(int $id): bool
    {
        self::ensureTableExists();
        $db = Database::getInstance()->getConnection();
        return $db->prepare("DELETE FROM admin_notifications WHERE id = ?")->execute([$id]);
    }

    // ------------------------------------------------------------
    // Automated Domain Trigger Helpers
    // ------------------------------------------------------------

    /**
     * Trigger Failed Webhook Alert
     */
    public static function triggerFailedWebhookAlert(
        int $clientId,
        string $webhookUrl,
        int $httpCode,
        string $errorDetails,
        ?string $txnId = null
    ): int {
        $db = Database::getInstance()->getConnection();
        $clientName = "Client #{$clientId}";
        try {
            $stmt = $db->prepare("SELECT client_name FROM api_clients WHERE id = ?");
            $stmt->execute([$clientId]);
            $client = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($client && !empty($client['client_name'])) {
                $clientName = $client['client_name'];
            }
        } catch (\Throwable $e) {}

        $title = "Outbound Webhook Failed: {$clientName}";
        $message = "Delivery to endpoint '{$webhookUrl}' failed with HTTP {$httpCode}. " . ($errorDetails ? "Details: {$errorDetails}" : "");
        $link = "api_clients/index.php?client_id={$clientId}";

        if ($txnId) {
            $message .= " (Transaction: {$txnId})";
            $link = "payments/payment_details.php?id={$txnId}";
        }

        return self::create(
            self::TYPE_FAILED_WEBHOOK,
            $title,
            $message,
            self::SEVERITY_CRITICAL,
            $link,
            $txnId ?: "wh_{$clientId}_" . time(),
            'webhook'
        );
    }

    /**
     * Trigger Payment Dispute / Chargeback Alert
     */
    public static function triggerDisputeAlert(
        string $txnId,
        string $customerEmail,
        float $amount,
        string $reason
    ): int {
        $formattedAmount = '₹' . number_format($amount, 2);
        $title = "New Payment Dispute: {$txnId}";
        $message = "A customer dispute of {$formattedAmount} was filed for {$customerEmail}. Reason: {$reason}";
        $link = "payments/payment_details.php?id={$txnId}";

        return self::create(
            self::TYPE_NEW_DISPUTE,
            $title,
            $message,
            self::SEVERITY_CRITICAL,
            $link,
            "dispute_{$txnId}",
            'transaction'
        );
    }

    /**
     * Trigger High-Value Transaction Alert (> ₹10,000 threshold)
     */
    public static function triggerHighValueTxnAlert(
        string $txnId,
        float $amount,
        string $customerEmail,
        string $clientName = '',
        ?int $clientId = null
    ): int {
        $threshold = (float)($_ENV['HIGH_VALUE_THRESHOLD'] ?? getenv('HIGH_VALUE_THRESHOLD') ?: self::DEFAULT_HIGH_VALUE_THRESHOLD);
        if ($amount < $threshold) {
            return 0; // Not a high value transaction
        }

        $formattedAmount = '₹' . number_format($amount, 2);
        $merchantPart = !empty($clientName) ? " via {$clientName}" : "";
        $title = "High-Value Transaction: {$formattedAmount}";
        $message = "Received significant payment of {$formattedAmount} from {$customerEmail}{$merchantPart}. (Vortex ID: {$txnId})";
        $link = "payments/payment_details.php?id={$txnId}";

        return self::create(
            self::TYPE_HIGH_VALUE_TXN,
            $title,
            $message,
            self::SEVERITY_WARNING,
            $link,
            "highval_{$txnId}",
            'transaction'
        );
    }

    /**
     * Trigger Generic System / Security Alert
     */
    public static function triggerSystemAlert(
        string $title,
        string $message,
        string $severity = self::SEVERITY_WARNING,
        ?string $link = null,
        ?string $refId = null
    ): int {
        return self::create(
            self::TYPE_SYSTEM_ALERT,
            $title,
            $message,
            $severity,
            $link,
            $refId ?: 'sys_' . time(),
            'system'
        );
    }

    /**
     * Sync and inspect database records for any pending alerts
     * (e.g. historical high-value transactions, disputes, or webhook errors)
     */
    public static function syncGatewayAlerts(): int
    {
        self::ensureTableExists();
        $db = Database::getInstance()->getConnection();
        $created = 0;

        // 1. High-Value Transactions check
        try {
            $threshold = (float)($_ENV['HIGH_VALUE_THRESHOLD'] ?? getenv('HIGH_VALUE_THRESHOLD') ?: self::DEFAULT_HIGH_VALUE_THRESHOLD);
            $stmt = $db->prepare("
                SELECT t.vortex_transaction_id, t.amount, t.customer_email, c.client_name, t.api_client_id 
                FROM transactions t
                LEFT JOIN api_clients c ON t.api_client_id = c.id
                WHERE t.status = 'SUCCESS' AND t.amount >= :threshold
                ORDER BY t.created_at DESC LIMIT 15
            ");
            $stmt->execute([':threshold' => $threshold]);
            $highValRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($highValRows as $row) {
                $ref = "highval_" . $row['vortex_transaction_id'];
                $check = $db->prepare("SELECT id FROM admin_notifications WHERE reference_id = ? LIMIT 1");
                $check->execute([$ref]);
                if (!$check->fetch()) {
                    self::triggerHighValueTxnAlert(
                        $row['vortex_transaction_id'],
                        (float)$row['amount'],
                        $row['customer_email'],
                        $row['client_name'] ?? '',
                        (int)$row['api_client_id']
                    );
                    $created++;
                }
            }
        } catch (\Throwable $e) {}

        // 2. Disputed transactions check
        try {
            $stmt = $db->query("
                SELECT vortex_transaction_id, customer_email, amount 
                FROM transactions 
                WHERE status = 'DISPUTED' 
                ORDER BY created_at DESC LIMIT 10
            ");
            $disputeRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($disputeRows as $row) {
                $ref = "dispute_" . $row['vortex_transaction_id'];
                $check = $db->prepare("SELECT id FROM admin_notifications WHERE reference_id = ? LIMIT 1");
                $check->execute([$ref]);
                if (!$check->fetch()) {
                    self::triggerDisputeAlert(
                        $row['vortex_transaction_id'],
                        $row['customer_email'],
                        (float)$row['amount'],
                        'Payment flagged as DISPUTED on gateway ledger'
                    );
                    $created++;
                }
            }
        } catch (\Throwable $e) {}

        // 3. Failed notification log check
        try {
            $stmt = $db->query("
                SELECT n.id, n.client_id, n.recipient, n.error_message, c.client_name, c.webhook_url
                FROM notification_logs n
                LEFT JOIN api_clients c ON n.client_id = c.id
                WHERE n.status = 'FAILED'
                ORDER BY n.created_at DESC LIMIT 10
            ");
            $failedLogs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($failedLogs as $row) {
                $ref = "log_fail_" . $row['id'];
                $check = $db->prepare("SELECT id FROM admin_notifications WHERE reference_id = ? LIMIT 1");
                $check->execute([$ref]);
                if (!$check->fetch()) {
                    self::create(
                        self::TYPE_FAILED_WEBHOOK,
                        "Failed Alert Dispatch: " . ($row['client_name'] ?? 'Merchant'),
                        "Outbound notification to {$row['recipient']} failed: " . ($row['error_message'] ?? 'Delivery failure'),
                        self::SEVERITY_WARNING,
                        "api_clients/index.php?client_id=" . ($row['client_id'] ?? 0),
                        $ref,
                        'webhook'
                    );
                    $created++;
                }
            }
        } catch (\Throwable $e) {}

        return $created;
    }

    /**
     * Format time relative (e.g. '2m ago', '1h ago', 'yesterday')
     */
    public static function timeAgo(string $datetime): string
    {
        $time = strtotime($datetime);
        $diff = time() - $time;

        if ($diff < 60) return "Just now";
        if ($diff < 3600) return floor($diff / 60) . "m ago";
        if ($diff < 86400) return floor($diff / 3600) . "h ago";
        if ($diff < 172800) return "Yesterday";
        return date('M j, Y', $time);
    }
}
