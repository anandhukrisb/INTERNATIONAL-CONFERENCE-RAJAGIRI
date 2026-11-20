-- Migration: Add designation field to user_registrations table
-- Database: icswhmh_db

-- ============================================================================
-- 1. UP MIGRATION (Apply Change)
-- ============================================================================
ALTER TABLE `user_registrations`
  ADD COLUMN `designation` VARCHAR(100) NOT NULL DEFAULT '' AFTER `organization`;

-- If your environment also has the legacy 'registrations' table, uncomment the following:
-- ALTER TABLE `registrations`
--   ADD COLUMN `designation` VARCHAR(100) NOT NULL DEFAULT '' AFTER `organization`;

-- ============================================================================
-- 2. DOWN MIGRATION (Rollback)
-- ============================================================================
-- ALTER TABLE `user_registrations`
--   DROP COLUMN `designation`;

-- If rolled back on legacy 'registrations' table:
-- ALTER TABLE `registrations`
--   DROP COLUMN `designation`;

