<?php
/**
 * ------------------------------------------------------------
 * AuditLogger.php – Enterprise Audit Trail & Governance System
 * ------------------------------------------------------------
 * Project : PayVortex Unified Payment Gateway
 * ------------------------------------------------------------
 * Tracks and records every administrative and client governance
 * action with actor attribution (who), timestamps (when), IP,
 * user agent, and before/after state diffs (what changed).
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Logger.php';

class AuditLogger
{
    // Canonical Action Constants
    public const CLIENT_CREATED          = 'client.created';
    public const CLIENT_ACTIVATED        = 'client.activated';
    public const CLIENT_DEACTIVATED      = 'client.deactivated';
    public const CLIENT_WEBHOOK_UPDATED  = 'client.webhook_updated';
    public const CLIENT_SECRET_ROTATED   = 'client.secret_rotated';
    public const CLIENT_PROFILE_UPDATED  = 'client.profile_updated';
    public const CLIENT_PASSWORD_UPDATED = 'client.password_updated';

    public const ADMIN_CREATED           = 'admin.created';
    public const ADMIN_ROLE_UPDATED      = 'admin.role_updated';
    public const ADMIN_STATUS_TOGGLED    = 'admin.status_toggled';
    public const ADMIN_LOGIN_SUCCESS     = 'admin.login_success';
    public const ADMIN_LOGIN_FAILED      = 'admin.login_failed';

    public const EVENT_CREATED           = 'event.created';
    public const EVENT_EDITED            = 'event.edited';
    public const EVENT_WEBHOOK_UPDATED   = 'event.webhook_updated';
    public const EVENT_ACTIVATED         = 'event.activated';
    public const EVENT_DEACTIVATED       = 'event.deactivated';
    public const EVENT_DELETED           = 'event.deleted';

    public const REFUND_PROCESSED        = 'refund.processed';

    /**
     * Record an audit event into database
     *
     * @param string      $entityType     'api_client', 'event', 'admin_user', 'refund', 'system'
     * @param string|null $entityId       ID of target entity (e.g. client ID)
     * @param string      $action         Canonical action code (e.g. 'client.activated')
     * @param array|null  $oldValues      Prior state before mutation
     * @param array|null  $newValues      New state after mutation
     * @param string|null $description    Human-readable explanation
     * @param array|null  $metadata       Extra contextual metadata
     * @param array|null  $actorOverride  Manual actor specifications
     * @return bool
     */
    public static function log(
        string $entityType,
        ?string $entityId,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        ?array $metadata = null,
        ?array $actorOverride = null
    ): bool {
        try {
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                session_start();
            }

            // Determine Actor
            $actorType     = $actorOverride['actor_type'] ?? 'system';
            $actorId       = $actorOverride['actor_id'] ?? null;
            $actorUsername = $actorOverride['actor_username'] ?? 'SYSTEM';
            $actorRole     = $actorOverride['actor_role'] ?? 'system';

            if ($actorOverride === null) {
                if (!empty($_SESSION['vortex_admin_id'])) {
                    $actorType     = 'admin';
                    $actorId       = (int)$_SESSION['vortex_admin_id'];
                    $actorUsername = $_SESSION['vortex_admin_username'] ?? 'admin';
                    $actorRole     = $_SESSION['vortex_admin_role'] ?? 'super_admin';
                } elseif (!empty($_SESSION['vortex_client_id'])) {
                    $actorType     = 'client';
                    $actorId       = (int)$_SESSION['vortex_client_id'];
                    $actorUsername = $_SESSION['vortex_client_name'] ?? ('Client #' . $actorId);
                    $actorRole     = 'merchant';
                }
            }

            // Extract Network Context
            $ipAddress = self::getClientIp();
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI/System', 0, 255);

            // Generate default description if omitted
            if (empty($description)) {
                $description = self::formatDefaultDescription($action, $entityType, $entityId, $actorUsername);
            }

            // Sanitize secrets from stored diffs
            $cleanOld = self::sanitizeSensitiveValues($oldValues);
            $cleanNew = self::sanitizeSensitiveValues($newValues);

            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO audit_logs (
                    entity_type, entity_id, action,
                    actor_type, actor_id, actor_username, actor_role,
                    ip_address, user_agent,
                    old_values, new_values, description, metadata
                ) VALUES (
                    ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?,
                    ?, ?, ?, ?
                )
            ");

            $stmt->execute([
                $entityType,
                $entityId !== null ? (string)$entityId : null,
                $action,
                $actorType,
                $actorId,
                $actorUsername,
                $actorRole,
                $ipAddress,
                $userAgent,
                $cleanOld !== null ? json_encode($cleanOld) : null,
                $cleanNew !== null ? json_encode($cleanNew) : null,
                $description,
                $metadata !== null ? json_encode($metadata) : null,
            ]);

            Logger::info("AUDIT: [{$action}] on [{$entityType}:{$entityId}] by [{$actorUsername} ({$actorRole})]");
            return true;

        } catch (\Throwable $e) {
            Logger::error("Audit logging failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Helper to log actions on an API Client
     */
    public static function logClientAction(
        int $clientId,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        ?array $metadata = null
    ): bool {
        return self::log('api_client', (string)$clientId, $action, $oldValues, $newValues, $description, $metadata);
    }

    /**
     * Get audit logs for a specific client (for client portal and admin client drawers)
     */
    public static function getClientLogs(int $clientId, int $limit = 50, int $offset = 0): array
    {
        return self::getLogsForEntity('api_client', (string)$clientId, $limit, $offset);
    }

    /**
     * Get audit logs for a specific entity
     */
    public static function getLogsForEntity(string $entityType, string $entityId, int $limit = 50, int $offset = 0): array
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT * FROM audit_logs 
                WHERE entity_type = ? AND entity_id = ? 
                ORDER BY id DESC 
                LIMIT ? OFFSET ?
            ");
            $stmt->bindValue(1, $entityType, PDO::PARAM_STR);
            $stmt->bindValue(2, $entityId, PDO::PARAM_STR);
            $stmt->bindValue(3, max(1, min($limit, 200)), PDO::PARAM_INT);
            $stmt->bindValue(4, max(0, $offset), PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            Logger::error("Failed to query entity audit logs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Query recent audit logs with flexible filtering
     */
    public static function getRecentLogs(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        try {
            $db = Database::getInstance()->getConnection();
            $where = ["1=1"];
            $params = [];

            if (!empty($filters['entity_type'])) {
                $where[] = "entity_type = :entity_type";
                $params[':entity_type'] = $filters['entity_type'];
            }

            if (!empty($filters['entity_id'])) {
                $where[] = "entity_id = :entity_id";
                $params[':entity_id'] = (string)$filters['entity_id'];
            }

            if (!empty($filters['action'])) {
                $where[] = "action = :action";
                $params[':action'] = $filters['action'];
            }

            if (!empty($filters['actor_type'])) {
                $where[] = "actor_type = :actor_type";
                $params[':actor_type'] = $filters['actor_type'];
            }

            if (!empty($filters['actor_id'])) {
                $where[] = "actor_id = :actor_id";
                $params[':actor_id'] = (int)$filters['actor_id'];
            }

            if (!empty($filters['search'])) {
                $search = '%' . trim($filters['search']) . '%';
                $where[] = "(actor_username LIKE :s1 OR description LIKE :s2 OR action LIKE :s3 OR ip_address LIKE :s4)";
                $params[':s1'] = $search;
                $params[':s2'] = $search;
                $params[':s3'] = $search;
                $params[':s4'] = $search;
            }

            if (!empty($filters['from_date'])) {
                $where[] = "created_at >= :from_date";
                $params[':from_date'] = $filters['from_date'] . ' 00:00:00';
            }

            if (!empty($filters['to_date'])) {
                $where[] = "created_at <= :to_date";
                $params[':to_date'] = $filters['to_date'] . ' 23:59:59';
            }

            $whereSql = implode(' AND ', $where);
            $limitInt = max(1, min($limit, 500));
            $offsetInt = max(0, $offset);

            $sql = "SELECT * FROM audit_logs WHERE {$whereSql} ORDER BY created_at DESC LIMIT {$limitInt} OFFSET {$offsetInt}";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (\Throwable $e) {
            Logger::error("Failed to query audit logs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Count matching audit logs
     */
    public static function countLogs(array $filters = []): int
    {
        try {
            $db = Database::getInstance()->getConnection();
            $where = ["1=1"];
            $params = [];

            if (!empty($filters['entity_type'])) {
                $where[] = "entity_type = :entity_type";
                $params[':entity_type'] = $filters['entity_type'];
            }

            if (!empty($filters['entity_id'])) {
                $where[] = "entity_id = :entity_id";
                $params[':entity_id'] = (string)$filters['entity_id'];
            }

            if (!empty($filters['action'])) {
                $where[] = "action = :action";
                $params[':action'] = $filters['action'];
            }

            if (!empty($filters['actor_type'])) {
                $where[] = "actor_type = :actor_type";
                $params[':actor_type'] = $filters['actor_type'];
            }

            if (!empty($filters['actor_id'])) {
                $where[] = "actor_id = :actor_id";
                $params[':actor_id'] = (int)$filters['actor_id'];
            }

            if (!empty($filters['search'])) {
                $search = '%' . trim($filters['search']) . '%';
                $where[] = "(actor_username LIKE :s1 OR description LIKE :s2 OR action LIKE :s3 OR ip_address LIKE :s4)";
                $params[':s1'] = $search;
                $params[':s2'] = $search;
                $params[':s3'] = $search;
                $params[':s4'] = $search;
            }

            if (!empty($filters['from_date'])) {
                $where[] = "created_at >= :from_date";
                $params[':from_date'] = $filters['from_date'] . ' 00:00:00';
            }

            if (!empty($filters['to_date'])) {
                $where[] = "created_at <= :to_date";
                $params[':to_date'] = $filters['to_date'] . ' 23:59:59';
            }

            $whereSql = implode(' AND ', $where);
            $stmt = $db->prepare("SELECT COUNT(*) FROM audit_logs WHERE {$whereSql}");
            $stmt->execute($params);

            return (int)$stmt->fetchColumn();

        } catch (\Throwable $e) {
            Logger::error("Failed to count audit logs: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Render an HTML badge for an action
     */
    public static function getActionBadge(string $action): string
    {
        $style = 'background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;';
        
        if (str_contains($action, 'created') || str_contains($action, 'activated') || str_contains($action, 'success')) {
            $style = 'background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;';
        } elseif (str_contains($action, 'deactivated') || str_contains($action, 'failed') || str_contains($action, 'delete')) {
            $style = 'background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca;';
        } elseif (str_contains($action, 'updated') || str_contains($action, 'rotated') || str_contains($action, 'toggled')) {
            $style = 'background: #fef3c7; color: #b45309; border: 1px solid #fde68a;';
        } elseif (str_contains($action, 'refund')) {
            $style = 'background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff;';
        }

        return sprintf(
            '<span style="display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:12px; font-size:0.74rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; %s">%s</span>',
            $style,
            htmlspecialchars(strtoupper($action))
        );
    }

    /**
     * Extract client IP with proxy awareness
     */
    private static function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                $ips = explode(',', $_SERVER[$h]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '127.0.0.1';
    }

    /**
     * Redact sensitive secrets from audit diffs
     */
    private static function sanitizeSensitiveValues(?array $values): ?array
    {
        if ($values === null) return null;

        $sensitiveKeys = ['password', 'password_hash', 'api_secret', 'test_api_secret', 'webhook_secret'];
        $clean = [];

        foreach ($values as $key => $val) {
            if (in_array(strtolower($key), $sensitiveKeys, true)) {
                $clean[$key] = '[REDACTED]';
            } elseif (is_array($val)) {
                $clean[$key] = self::sanitizeSensitiveValues($val);
            } else {
                $clean[$key] = $val;
            }
        }

        return $clean;
    }

    /**
     * Default human-readable audit description
     */
    private static function formatDefaultDescription(string $action, string $entityType, ?string $entityId, string $actor): string
    {
        $idStr = $entityId !== null ? "#{$entityId}" : '';
        switch ($action) {
            case self::CLIENT_CREATED:
                return "{$actor} registered a new API Client {$idStr}";
            case self::CLIENT_ACTIVATED:
                return "{$actor} activated API Client {$idStr}";
            case self::CLIENT_DEACTIVATED:
                return "{$actor} deactivated/suspended API Client {$idStr}";
            case self::CLIENT_WEBHOOK_UPDATED:
                return "{$actor} updated webhook configuration for API Client {$idStr}";
            case self::CLIENT_SECRET_ROTATED:
                return "{$actor} rotated cryptographic secret for API Client {$idStr}";
            case self::CLIENT_PROFILE_UPDATED:
                return "{$actor} updated profile details for API Client {$idStr}";
            case self::CLIENT_PASSWORD_UPDATED:
                return "{$actor} updated account password for API Client {$idStr}";
            case self::ADMIN_CREATED:
                return "{$actor} created new admin account {$idStr}";
            case self::ADMIN_ROLE_UPDATED:
                return "{$actor} modified role permissions for admin {$idStr}";
            case self::ADMIN_STATUS_TOGGLED:
                return "{$actor} toggled active status for admin {$idStr}";
            case self::REFUND_PROCESSED:
                return "{$actor} authorized a refund for transaction {$idStr}";
            case self::EVENT_DELETED:
                return "{$actor} deleted event {$idStr}";
            default:
                return "{$actor} executed {$action} on {$entityType} {$idStr}";
        }
    }
}
