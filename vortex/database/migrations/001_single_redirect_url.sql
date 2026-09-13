-- Migration: 001_single_redirect_url.sql
-- Description: Transition checkout_sessions table to single redirect_url architecture.

-- 1. Add redirect_url column
ALTER TABLE checkout_sessions 
    ADD COLUMN redirect_url TEXT AFTER transaction_id;

-- 2. Backfill redirect_url from existing historical records
UPDATE checkout_sessions 
    SET redirect_url = COALESCE(success_url, failure_url, '') 
    WHERE redirect_url IS NULL;

-- 3. Enforce NOT NULL constraint on redirect_url
ALTER TABLE checkout_sessions 
    MODIFY COLUMN redirect_url TEXT NOT NULL;

-- 4. Drop legacy success_url and failure_url columns
ALTER TABLE checkout_sessions 
    DROP COLUMN success_url,
    DROP COLUMN failure_url;
