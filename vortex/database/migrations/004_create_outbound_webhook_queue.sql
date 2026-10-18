-- Migration 004: Create Outbound Webhook Queue for Exponential Backoff Retries
-- Intervals: 3 min, 9 min, 27 min (Max 4 attempts total)

CREATE TABLE IF NOT EXISTS `outbound_webhook_queue` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `vortex_transaction_id` VARCHAR(64) NOT NULL,
    `api_client_id` INT NOT NULL,
    `webhook_url` VARCHAR(512) NOT NULL,
    `event_type` VARCHAR(50) NOT NULL DEFAULT 'payment.success',
    `payload` TEXT NOT NULL,
    `signature` VARCHAR(255) NOT NULL,
    `attempts` INT NOT NULL DEFAULT 0,
    `max_attempts` INT NOT NULL DEFAULT 4,
    `status` ENUM('PENDING', 'PROCESSING', 'DELIVERED', 'FAILED', 'PERMANENTLY_FAILED') NOT NULL DEFAULT 'PENDING',
    `last_attempt_at` DATETIME NULL,
    `next_attempt_at` DATETIME NOT NULL,
    `last_http_code` INT NULL,
    `last_error` TEXT NULL,
    `delivered_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_queue_status_next` (`status`, `next_attempt_at`),
    INDEX `idx_queue_txn` (`vortex_transaction_id`),
    INDEX `idx_queue_client` (`api_client_id`),
    FOREIGN KEY (`api_client_id`) REFERENCES `api_clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
