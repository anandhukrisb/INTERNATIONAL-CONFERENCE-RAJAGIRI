-- Migration: Move designation column to first position (after primary key)
-- Database: icswhmh_db
-- Table: user_registrations
--
-- ⚠ IMPORTANT: Take a database backup before running this migration.
--   phpMyAdmin → Export → Quick → Go
--   OR: mysqldump -u <user> -p icswhmh_db > icswhmh_db_backup_$(date +%Y%m%d).sql
--
-- ============================================================================
-- UP MIGRATION (Apply Change)
-- ============================================================================
-- The `id` column is the AUTO_INCREMENT primary key and must stay first.
-- We place `designation` immediately after `id`.
--
-- Case 1: Column already exists (column was added by add_designation.sql) — MOVE it:
ALTER TABLE `user_registrations`
  MODIFY COLUMN `designation` VARCHAR(100) NOT NULL DEFAULT '' AFTER `id`;

-- Case 2: Column does NOT exist yet — ADD it in position:
-- (Uncomment the block below and comment out the MODIFY above if the column is missing)
-- ALTER TABLE `user_registrations`
--   ADD COLUMN `designation` VARCHAR(100) NOT NULL DEFAULT '' AFTER `id`;

-- ============================================================================
-- DOWN MIGRATION (Rollback — moves designation back to after organization)
-- ============================================================================
-- ALTER TABLE `user_registrations`
--   MODIFY COLUMN `designation` VARCHAR(100) NOT NULL DEFAULT '' AFTER `organization`;

-- ============================================================================
-- How to run
-- ============================================================================
-- Via MySQL CLI:
--   mysql -u <username> -p icswhmh_db < migrations/designation_first.sql
--
-- Via phpMyAdmin:
--   1. Open phpMyAdmin → select icswhmh_db
--   2. Click the SQL tab
--   3. Paste the ALTER TABLE statement above and click Go
--
-- To roll back:
--   Run the ALTER TABLE in the DOWN MIGRATION section above.

