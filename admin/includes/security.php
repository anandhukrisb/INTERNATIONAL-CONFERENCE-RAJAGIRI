<?php
/**
 * Audit Logging
 * ICSWHMH 2027 Admin Panel — Phase 1
 */
declare(strict_types=1);

/**
 * Write an entry to the audit_logs table.
 *
 * @param int|null    $userId  Admin user ID (null for anonymous actions)
 * @param string|null $email   Admin email snapshot
 * @param string      $action  Action identifier (e.g. 'login', 'failed_login')
 * @param string|null $details Additional context
 * @param string      $ip      Client IP address
 */
function audit_log(
    ?int    $userId,
    ?string $email,
    string  $action,
    ?string $details = null,
    string  $ip = '0.0.0.0'
): void {
    try {
        $pdo  = DB::get();
        $ua   = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (user_id, user_email, action, details, ip_address, user_agent)
             VALUES (:uid, :email, :action, :details, :ip, :ua)'
        );
        $stmt->execute([
            ':uid'     => $userId,
            ':email'   => $email,
            ':action'  => $action,
            ':details' => $details,
            ':ip'      => $ip,
            ':ua'      => $ua,
        ]);
    } catch (Throwable $e) {
        // Log to file — audit logging must never crash the app
        error_log('[AUDIT_LOG_ERROR] ' . $e->getMessage());
    }
}

/**
 * Get recent audit log entries.
 *
 * @param int $limit  Number of rows to return
 * @param int|null $userId  Filter by user ID (null = all users)
 */
function get_audit_logs(int $limit = 20, ?int $userId = null): array
{
    try {
        $pdo  = DB::get();
        if ($userId !== null) {
            $stmt = $pdo->prepare(
                'SELECT al.*, au.full_name
                 FROM audit_logs al
                 LEFT JOIN admin_users au ON au.id = al.user_id
                 WHERE al.user_id = :uid
                 ORDER BY al.created_at DESC
                 LIMIT :lim'
            );
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        } else {
            $stmt = $pdo->prepare(
                'SELECT al.*, au.full_name
                 FROM audit_logs al
                 LEFT JOIN admin_users au ON au.id = al.user_id
                 ORDER BY al.created_at DESC
                 LIMIT :lim'
            );
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('[AUDIT_FETCH_ERROR] ' . $e->getMessage());
        return [];
    }
}
