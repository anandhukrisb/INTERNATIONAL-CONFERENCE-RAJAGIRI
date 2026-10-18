-- Migration: 002_add_refunded_disputed_statuses.sql
-- Description: Expand transactions.status ENUM to support REFUNDED and DISPUTED.

ALTER TABLE transactions 
    MODIFY COLUMN status ENUM('PENDING', 'SUCCESS', 'FAILED', 'REFUNDED', 'DISPUTED') DEFAULT 'PENDING';
