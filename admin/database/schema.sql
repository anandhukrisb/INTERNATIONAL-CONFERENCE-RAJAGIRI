-- ============================================================
--  ICSWHMH 2027 — Admin Authentication Database Schema
--  Phase 1: Auth System Only
--  MySQL 8.0+ | InnoDB | UTF-8mb4
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+05:30';
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
--  1. ADMIN USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id`                  INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `full_name`           VARCHAR(150)     NOT NULL,
    `email`               VARCHAR(255)     NOT NULL UNIQUE,
    `password_hash`       VARCHAR(255)     NOT NULL COMMENT 'Argon2ID hash',
    `role`                ENUM('super_admin','admin') NOT NULL DEFAULT 'admin',
    `status`              ENUM('active','inactive','locked') NOT NULL DEFAULT 'active',
    `failed_attempts`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until`        DATETIME         NULL,
    `last_login`          DATETIME         NULL,
    `last_login_ip`       VARCHAR(45)      NULL,
    `password_changed_at` DATETIME         NULL,
    `created_at`          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email`  (`email`),
    KEY `idx_status`       (`status`),
    KEY `idx_role`         (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  2. PASSWORD RESETS
-- ============================================================
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED  NOT NULL,
    `token_hash` VARCHAR(255)  NOT NULL UNIQUE COMMENT 'SHA-256 hash of token',
    `expires_at` DATETIME      NOT NULL,
    `used_at`    DATETIME      NULL,
    `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_token_hash` (`token_hash`),
    KEY `idx_user_id`    (`user_id`),
    KEY `idx_expires`    (`expires_at`),
    CONSTRAINT `fk_pr_user`
        FOREIGN KEY (`user_id`) REFERENCES `admin_users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  3. LOGIN ATTEMPTS  (brute-force tracking)
-- ============================================================
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`        VARCHAR(255)    NOT NULL,
    `ip_address`   VARCHAR(45)     NOT NULL,
    `user_agent`   VARCHAR(500)    NULL,
    `success`      TINYINT(1)      NOT NULL DEFAULT 0,
    `attempted_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_email_ip`  (`email`(100), `ip_address`),
    KEY `idx_attempted` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  4. REMEMBER ME TOKENS
-- ============================================================
CREATE TABLE IF NOT EXISTS `remember_tokens` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED  NOT NULL,
    `selector`      VARCHAR(24)   NOT NULL UNIQUE COMMENT 'Random selector (public)',
    `token_hash`    VARCHAR(255)  NOT NULL COMMENT 'SHA-256 hash of validator',
    `expires_at`    DATETIME      NOT NULL,
    `ip_address`    VARCHAR(45)   NULL,
    `user_agent`    VARCHAR(500)  NULL,
    `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_selector`  (`selector`),
    KEY `idx_user_id`   (`user_id`),
    KEY `idx_expires`   (`expires_at`),
    CONSTRAINT `fk_rt_user`
        FOREIGN KEY (`user_id`) REFERENCES `admin_users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  5. AUDIT LOGS
-- ============================================================
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED    NULL,
    `user_email`  VARCHAR(255)    NULL COMMENT 'Snapshot in case user deleted',
    `action`      VARCHAR(100)    NOT NULL,
    `details`     TEXT            NULL,
    `ip_address`  VARCHAR(45)     NOT NULL,
    `user_agent`  VARCHAR(500)    NULL,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id`  (`user_id`),
    KEY `idx_action`   (`action`),
    KEY `idx_created`  (`created_at`),
    CONSTRAINT `fk_al_user`
        FOREIGN KEY (`user_id`) REFERENCES `admin_users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  SEED — Default Admin Accounts
--  Super Admin: admin@icswhmh2027.com  / Admin@2027!Secure#
--  Admin:       manager@icswhmh2027.com / Manager@2027!Secure#
--  CHANGE BOTH PASSWORDS IMMEDIATELY AFTER FIRST LOGIN.
-- ============================================================
INSERT INTO `admin_users`
    (`full_name`, `email`, `password_hash`, `role`, `status`, `password_changed_at`)
VALUES
    (
        'Super Administrator',
        'admin@icswhmh2027.com',
        '$argon2id$v=19$m=65536,t=4,p=1$c29tZXJhbmRvbXNhbHQ$RdescudoJ0sZQUZMkM8GBmEFqYn1IxJqHLoxvJEjPPA',
        'super_admin',
        'active',
        NOW()
    ),
    (
        'Conference Manager',
        'manager@icswhmh2027.com',
        '$argon2id$v=19$m=65536,t=4,p=1$c29tZXJhbmRvbXNhbHQ$RdescudoJ0sZQUZMkM8GBmEFqYn1IxJqHLoxvJEjPPA',
        'admin',
        'active',
        NOW()
    );

SET FOREIGN_KEY_CHECKS = 1;
