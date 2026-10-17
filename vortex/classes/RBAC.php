<?php
/**
 * ------------------------------------------------------------
 * RBAC.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 * Purpose : Enterprise Role-Based Access Control (RBAC) System
 *
 * Supported Roles:
 * 1. super_admin : Unrestricted gateway governance, keys, events, and user management.
 * 2. finance     : Financial transactions, refunds, dispute resolution, reporting, and reconciliations.
 * 3. support     : Customer payment inquiry, transaction timeline inspection, search orders (read-only).
 * 4. read_only   : Audit-only access across payments, events, and client lists with zero write capability.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/AuditLogger.php';

class RBAC
{
    // Canonical Role Constants
    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_FINANCE     = 'finance';
    public const ROLE_SUPPORT     = 'support';
    public const ROLE_READ_ONLY   = 'read_only';

    /**
     * Canonical Role Configuration & Metadata
     */
    private static array $roles = [
        self::ROLE_SUPER_ADMIN => [
            'key'         => self::ROLE_SUPER_ADMIN,
            'label'       => 'Super Administrator',
            'short_label' => 'Super Admin',
            'description' => 'Unrestricted governance across all payment gateways, event creation, client API keys, and team roles.',
            'color'       => '#1e40af', // Deep Blue
            'bg_color'    => '#dbeafe',
            'badge_class' => 'badge-super-admin',
        ],
        self::ROLE_FINANCE => [
            'key'         => self::ROLE_FINANCE,
            'label'       => 'Finance Manager',
            'short_label' => 'Finance',
            'description' => 'Direct payment refunds, dispute management, transaction reconciliations, and financial report exports.',
            'color'       => '#065f46', // Emerald Green
            'bg_color'    => '#d1fae5',
            'badge_class' => 'badge-finance',
        ],
        self::ROLE_SUPPORT => [
            'key'         => self::ROLE_SUPPORT,
            'label'       => 'Support Specialist',
            'short_label' => 'Support',
            'description' => 'Customer payment verification, transaction search, and lifecycle timeline inspection (Read-Only).',
            'color'       => '#92400e', // Amber / Orange
            'bg_color'    => '#fef3c7',
            'badge_class' => 'badge-support',
        ],
        self::ROLE_READ_ONLY => [
            'key'         => self::ROLE_READ_ONLY,
            'label'       => 'Auditor / Read-Only',
            'short_label' => 'Read Only',
            'description' => 'Audit visibility across dashboard, payments, events, and clients without modification privileges.',
            'color'       => '#475569', // Slate Gray
            'bg_color'    => '#f1f5f9',
            'badge_class' => 'badge-read-only',
        ],
    ];

    /**
     * Complete Enterprise Permissions Matrix
     */
    private static array $permissions = [
        // Dashboard & Analytics
        'dashboard.view' => [
            'label'       => 'View Dashboard Overview',
            'description' => 'Access executive overview metrics, transaction statistics, and revenue totals.',
            'roles'       => [self::ROLE_SUPER_ADMIN, self::ROLE_FINANCE, self::ROLE_SUPPORT, self::ROLE_READ_ONLY],
        ],

        // Payments & Transactions
        'payments.view' => [
            'label'       => 'View Transactions',
            'description' => 'Search and inspect incoming payment records, customer logs, and Razorpay order IDs.',
            'roles'       => [self::ROLE_SUPER_ADMIN, self::ROLE_FINANCE, self::ROLE_SUPPORT, self::ROLE_READ_ONLY],
        ],
        'payments.refund' => [
            'label'       => 'Process Refunds',
            'description' => 'Authorize and execute instant customer refunds via Razorpay with audit trace recording.',
            'roles'       => [self::ROLE_SUPER_ADMIN, self::ROLE_FINANCE],
        ],
        'payments.sync' => [
            'label'       => 'Sync Transactions',
            'description' => 'Poll Razorpay to synchronize orphaned transactions and resolve pending orders.',
            'roles'       => [self::ROLE_SUPER_ADMIN, self::ROLE_FINANCE],
        ],
        'payments.export' => [
            'label'       => 'Export Reports',
            'description' => 'Download CSV, Excel, or tabular financial reports and settlement data.',
            'roles'       => [self::ROLE_SUPER_ADMIN, self::ROLE_FINANCE, self::ROLE_READ_ONLY],
        ],

        // Events Management
        'events.view' => [
            'label'       => 'View Events',
            'description' => 'Access list of registered university/college departments and events.',
            'roles'       => [self::ROLE_SUPER_ADMIN, self::ROLE_FINANCE, self::ROLE_SUPPORT, self::ROLE_READ_ONLY],
        ],
        'events.create' => [
            'label'       => 'Create Events',
            'description' => 'Create new institutional events and configure date intervals.',
            'roles'       => [self::ROLE_SUPER_ADMIN],
        ],
        'events.edit' => [
            'label'       => 'Activate / Deactivate Events',
            'description' => 'Change event status, extend deadlines, and update active state.',
            'roles'       => [self::ROLE_SUPER_ADMIN],
        ],

        // API Clients & Credentials
        'clients.view' => [
            'label'       => 'View API Clients',
            'description' => 'View list of integrated client applications and webhook configurations.',
            'roles'       => [self::ROLE_SUPER_ADMIN, self::ROLE_READ_ONLY],
        ],
        'clients.create' => [
            'label'       => 'Create API Clients',
            'description' => 'Provision new merchant API client applications with live & test key pairs.',
            'roles'       => [self::ROLE_SUPER_ADMIN],
        ],
        'clients.edit' => [
            'label'       => 'Manage API Clients & Webhooks',
            'description' => 'Activate/deactivate clients and configure Layer 2 webhook target endpoints.',
            'roles'       => [self::ROLE_SUPER_ADMIN],
        ],
        'clients.view_secret' => [
            'label'       => 'View Client API Secrets',
            'description' => 'Display unmasked client API secrets and webhook signing keys.',
            'roles'       => [self::ROLE_SUPER_ADMIN],
        ],

        // Admin Team Governance
        'admins.manage' => [
            'label'       => 'Manage Admin Team & Roles',
            'description' => 'Create new administrative accounts, reassign RBAC roles, and toggle access.',
            'roles'       => [self::ROLE_SUPER_ADMIN],
        ],

        // Audit Trail
        'audit.view' => [
            'label'       => 'View Audit Trail',
            'description' => 'Inspect administrative login records, session origins, and system logs.',
            'roles'       => [self::ROLE_SUPER_ADMIN, self::ROLE_FINANCE, self::ROLE_READ_ONLY],
        ],
    ];

    /**
     * Normalize any role string (including legacy database names) to canonical key.
     */
    public static function normalizeRole(?string $role): string
    {
        if (empty($role)) {
            return self::ROLE_READ_ONLY;
        }

        $cleaned = strtolower(trim($role));
        $cleaned = str_replace(['-', ' '], '_', $cleaned);

        return match ($cleaned) {
            'super_admin', 'super_administrator', 'superadmin', 'admin' => self::ROLE_SUPER_ADMIN,
            'finance', 'finance_manager', 'finance_admin', 'accounts'    => self::ROLE_FINANCE,
            'support', 'support_specialist', 'customer_support', 'agent' => self::ROLE_SUPPORT,
            'read_only', 'readonly', 'auditor', 'viewer'                 => self::ROLE_READ_ONLY,
            default                                                      => self::ROLE_READ_ONLY,
        };
    }

    /**
     * Get the active session admin's normalized role.
     */
    public static function getCurrentRole(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $rawRole = $_SESSION['vortex_admin_role'] ?? null;
        return self::normalizeRole($rawRole);
    }

    /**
     * Check if a specific role (or the current user) has permission.
     */
    public static function can(string $permission, ?string $role = null): bool
    {
        $roleKey = $role ? self::normalizeRole($role) : self::getCurrentRole();

        // Super Admin always has full access to all permissions
        if ($roleKey === self::ROLE_SUPER_ADMIN) {
            return true;
        }

        if (!isset(self::$permissions[$permission])) {
            return false;
        }

        return in_array($roleKey, self::$permissions[$permission]['roles'], true);
    }

    /**
     * Enforce a permission requirement. If unauthorized, halts or redirects.
     */
    public static function require(string $permission, ?string $role = null, ?string $redirectUrl = null): void
    {
        if (!self::can($permission, $role)) {
            $currentRoleLabel = self::getRoleLabel($role);
            Logger::error("RBAC Access Denied: User role '{$currentRoleLabel}' lacks permission '{$permission}'");

            // Handle API / JSON endpoints
            $isJson = (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
                   || (!empty($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'))
                   || str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')
                   || str_contains($_SERVER['SCRIPT_NAME'] ?? '', 'process_refund.php');

            if ($isJson) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'success'    => false,
                    'error_code' => 403,
                    'message'    => "Access Denied: Your assigned role ({$currentRoleLabel}) does not have permission to perform this action.",
                    'required'   => $permission,
                ]);
                exit;
            }

            // HTML fallback
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['flash_error'] = "Access Denied: Your assigned role ({$currentRoleLabel}) does not have permission for that operation.";

            $target = $redirectUrl ?? ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php');
            header("Location: {$target}");
            exit;
        }
    }

    /**
     * Check if user has any of the specified roles.
     */
    public static function hasRole(string|array $roles, ?string $role = null): bool
    {
        $currentRole = $role ? self::normalizeRole($role) : self::getCurrentRole();
        $rolesArray = is_array($roles) ? $roles : [$roles];

        foreach ($rolesArray as $r) {
            if ($currentRole === self::normalizeRole($r)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get human-readable label for a role.
     */
    public static function getRoleLabel(?string $role = null): string
    {
        $normalized = self::normalizeRole($role ?? self::getCurrentRole());
        return self::$roles[$normalized]['label'] ?? 'Unknown Role';
    }

    /**
     * Get short label for a role.
     */
    public static function getShortRoleLabel(?string $role = null): string
    {
        $normalized = self::normalizeRole($role ?? self::getCurrentRole());
        return self::$roles[$normalized]['short_label'] ?? 'Unknown';
    }

    /**
     * Render an HTML role badge with tailored colors.
     */
    public static function getRoleBadge(?string $role = null): string
    {
        $normalized = self::normalizeRole($role ?? self::getCurrentRole());
        $meta = self::$roles[$normalized] ?? self::$roles[self::ROLE_READ_ONLY];

        $style = "display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:12px; font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; background:{$meta['bg_color']}; color:{$meta['color']}; border:1px solid rgba(0,0,0,0.06);";

        $icon = match ($normalized) {
            self::ROLE_SUPER_ADMIN => '⚡',
            self::ROLE_FINANCE     => '💳',
            self::ROLE_SUPPORT     => '🎧',
            self::ROLE_READ_ONLY   => '👁️',
        };

        return "<span class=\"vortex-role-badge {$meta['badge_class']}\" style=\"{$style}\"><span>{$icon}</span><span>" . htmlspecialchars($meta['short_label']) . "</span></span>";
    }

    /**
     * Return all available roles and metadata.
     */
    public static function getAllRoles(): array
    {
        return self::$roles;
    }

    /**
     * Return full permissions list with descriptions.
     */
    public static function getPermissions(): array
    {
        return self::$permissions;
    }

    /**
     * Get permission matrix for a given role (or current user).
     */
    public static function getPermissionsForRole(?string $role = null): array
    {
        $roleKey = $role ? self::normalizeRole($role) : self::getCurrentRole();
        $result = [];

        foreach (self::$permissions as $permKey => $perm) {
            $isGranted = ($roleKey === self::ROLE_SUPER_ADMIN) || in_array($roleKey, $perm['roles'], true);
            $result[$permKey] = [
                'label'       => $perm['label'],
                'description' => $perm['description'],
                'granted'     => $isGranted,
            ];
        }

        return $result;
    }

    /**
     * Fetch all admin users from database.
     */
    public static function getAllAdmins(): array
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("SELECT id, username, full_name, email, phone, role, avatar_color, is_active, last_login, created_at FROM admin ORDER BY id ASC");
            $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($admins as &$adm) {
                $adm['normalized_role'] = self::normalizeRole($adm['role']);
                $adm['role_label']      = self::getRoleLabel($adm['role']);
                $adm['badge_html']       = self::getRoleBadge($adm['role']);
            }
            unset($adm);

            return $admins;
        } catch (\Throwable $e) {
            Logger::error("Failed to fetch admin users: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create a new administrative user with assigned role (Super Admin only).
     */
    public static function createAdminUser(array $data): array
    {
        self::require('admins.manage');

        $username = trim($data['username'] ?? '');
        $password = trim($data['password'] ?? '');
        $fullName = trim($data['full_name'] ?? '');
        $email    = trim($data['email'] ?? '');
        $phone    = trim($data['phone'] ?? '');
        $role     = self::normalizeRole($data['role'] ?? self::ROLE_SUPPORT);

        if (empty($username) || strlen($username) < 3) {
            return ['success' => false, 'message' => 'Username must be at least 3 characters.'];
        }

        if (empty($password) || strlen($password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters.'];
        }

        if (empty($fullName)) {
            $fullName = ucfirst($username);
        }

        // Color palette for new user avatars
        $palette = ['#1a73e8', '#0d9488', '#8b5cf6', '#e11d48', '#d97706', '#059669', '#4f46e5'];
        $avatarColor = $palette[array_rand($palette)];

        try {
            $db = Database::getInstance()->getConnection();

            // Check username uniqueness
            $check = $db->prepare("SELECT id FROM admin WHERE username = :u LIMIT 1");
            $check->execute([':u' => $username]);
            if ($check->fetch()) {
                return ['success' => false, 'message' => "Username '@{$username}' is already taken."];
            }

            $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $roleLabel = self::$roles[$role]['label'];

            $stmt = $db->prepare("INSERT INTO admin (username, password_hash, full_name, email, phone, role, avatar_color, is_active) VALUES (:u, :p, :f, :e, :ph, :r, :a, 1)");
            $stmt->execute([
                ':u'  => $username,
                ':p'  => $passwordHash,
                ':f'  => $fullName,
                ':e'  => $email ?: null,
                ':ph' => $phone ?: null,
                ':r'  => $roleLabel,
                ':a'  => $avatarColor,
            ]);

            $newId = (int)$db->lastInsertId();
            Logger::info("Admin user created: {$username} with role {$roleLabel} (ID: {$newId}) by " . ($_SESSION['vortex_admin_username'] ?? 'Super Admin'));

            AuditLogger::log(
                'admin_user',
                (string)$newId,
                AuditLogger::ADMIN_CREATED,
                null,
                ['username' => $username, 'role' => $roleLabel, 'full_name' => $fullName],
                "Admin user @{$username} created with role '{$roleLabel}'"
            );

            return [
                'success' => true,
                'message' => "Admin user '{$fullName}' (@{$username}) created successfully with role '{$roleLabel}'.",
                'admin_id' => $newId,
            ];
        } catch (\Throwable $e) {
            Logger::error("Failed to create admin user: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Update an admin user's role (Super Admin only).
     */
    public static function updateAdminRole(int $adminId, string $newRole): array
    {
        self::require('admins.manage');

        $normalized = self::normalizeRole($newRole);
        $roleLabel = self::$roles[$normalized]['label'];

        if ($adminId <= 0) {
            return ['success' => false, 'message' => 'Invalid administrator ID.'];
        }

        // Prevent Super Admin from demoting themselves if they are the only Super Admin
        $currentAdminId = (int)($_SESSION['vortex_admin_id'] ?? 0);
        if ($adminId === $currentAdminId && $normalized !== self::ROLE_SUPER_ADMIN) {
            try {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->query("SELECT COUNT(*) FROM admin WHERE is_active = 1 AND (role LIKE '%Super%' OR role = 'super_admin')");
                $superCount = (int)$stmt->fetchColumn();
                if ($superCount <= 1) {
                    return ['success' => false, 'message' => 'Action blocked: You cannot demote yourself as the sole active Super Administrator.'];
                }
            } catch (\Throwable $e) {}
        }

        try {
            $db = Database::getInstance()->getConnection();

            // Fetch current role before updating for audit diff
            $targetStmt = $db->prepare("SELECT role, username FROM admin WHERE id = :id LIMIT 1");
            $targetStmt->execute([':id' => $adminId]);
            $targetAdmin = $targetStmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("UPDATE admin SET role = :r WHERE id = :id LIMIT 1");
            $stmt->execute([':r' => $roleLabel, ':id' => $adminId]);

            // If updating current user's role, sync session
            if ($adminId === $currentAdminId) {
                $_SESSION['vortex_admin_role'] = $roleLabel;
            }

            Logger::info("Admin ID #{$adminId} role updated to '{$roleLabel}' by " . ($_SESSION['vortex_admin_username'] ?? 'Super Admin'));

            AuditLogger::log(
                'admin_user',
                (string)$adminId,
                AuditLogger::ADMIN_ROLE_UPDATED,
                ['role' => $targetAdmin['role'] ?? null],
                ['role' => $roleLabel],
                "Admin @{$targetAdmin['username']} (#{$adminId}) role updated to '{$roleLabel}'"
            );

            return ['success' => true, 'message' => "Admin role successfully updated to '{$roleLabel}'."];
        } catch (\Throwable $e) {
            Logger::error("Failed to update admin role: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Toggle admin active status (Super Admin only).
     */
    public static function toggleAdminStatus(int $adminId, int $isActive): array
    {
        self::require('admins.manage');

        $currentAdminId = (int)($_SESSION['vortex_admin_id'] ?? 0);
        if ($adminId === $currentAdminId && $isActive === 0) {
            return ['success' => false, 'message' => 'Action blocked: You cannot deactivate your own active session account.'];
        }

        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE admin SET is_active = :a WHERE id = :id LIMIT 1");
            $stmt->execute([':a' => $isActive ? 1 : 0, ':id' => $adminId]);

            $statusText = $isActive ? 'activated' : 'deactivated';
            Logger::info("Admin ID #{$adminId} was {$statusText} by " . ($_SESSION['vortex_admin_username'] ?? 'Super Admin'));

            AuditLogger::log(
                'admin_user',
                (string)$adminId,
                AuditLogger::ADMIN_STATUS_TOGGLED,
                ['is_active' => $isActive ? 0 : 1],
                ['is_active' => $isActive ? 1 : 0],
                "Admin account #{$adminId} was {$statusText}"
            );

            return ['success' => true, 'message' => "Administrator account has been {$statusText}."];
        } catch (\Throwable $e) {
            Logger::error("Failed to toggle admin status: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}
