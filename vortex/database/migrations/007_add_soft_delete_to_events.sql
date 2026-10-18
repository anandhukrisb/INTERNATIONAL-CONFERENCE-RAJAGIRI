-- ============================================================
-- Migration 007: Add Soft Delete Support to Events Table
-- ============================================================
-- Adds deleted_at column to allow soft deletion if the schema
-- is configured to preserve zero-transaction events in-table.
-- ============================================================

ALTER TABLE `events`
ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL AFTER `is_active`;
