-- ------------------------------------------------------------
-- Migration 005: Add webhook_url and webhook_secret to events
-- ------------------------------------------------------------

ALTER TABLE `events`
ADD COLUMN `webhook_url` VARCHAR(255) NULL AFTER `end_date`,
ADD COLUMN `webhook_secret` VARCHAR(64) NULL AFTER `webhook_url`;

-- Populate live webhook configuration for Dyuti
UPDATE `events` e
JOIN `api_clients` c ON c.id = 5
SET 
    e.webhook_url = c.webhook_url,
    e.webhook_secret = c.webhook_secret
WHERE e.event_id = 'DYUT20260913MU01TMQ67BK';
