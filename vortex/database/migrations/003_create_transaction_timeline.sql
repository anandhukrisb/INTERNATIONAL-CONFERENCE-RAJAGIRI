-- Migration: 003_create_transaction_timeline.sql
-- Purpose: Adds transaction_timeline table to track full chronological status changes,
--          payment attempts, failures, chargebacks/disputes, and refunds.

CREATE TABLE IF NOT EXISTS transaction_timeline (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vortex_transaction_id VARCHAR(100) NOT NULL,
    transaction_id INT NULL,
    order_id VARCHAR(100) DEFAULT NULL,
    payment_id VARCHAR(100) DEFAULT NULL,
    event_type VARCHAR(100) NOT NULL,
    status VARCHAR(50) NOT NULL,
    amount DECIMAL(10, 2) DEFAULT NULL,
    currency VARCHAR(10) DEFAULT 'INR',
    reason TEXT DEFAULT NULL,
    source VARCHAR(50) DEFAULT 'RAZORPAY_WEBHOOK',
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_timeline_vortex (vortex_transaction_id),
    INDEX idx_timeline_tx_id (transaction_id),
    INDEX idx_timeline_order (order_id),
    INDEX idx_timeline_payment (payment_id),
    INDEX idx_timeline_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
