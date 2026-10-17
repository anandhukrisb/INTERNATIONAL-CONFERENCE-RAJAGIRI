-- ============================================================
-- Migration 006: Drop webhook_url and webhook_secret from api_clients
-- Webhooks are strictly managed at the event level (events table)
-- Dyuti event DYUT20260913MU01TMQ67BK retains its production webhook
-- ============================================================

-- 1. Ensure Dyuti event has its production webhook URL and secret preserved
UPDATE `events`
SET `webhook_url` = 'https://dyuti.in/webhook.php',
    `webhook_secret` = '95050b2f9741d9cc8493e9d1452a486fa6730ff7c0f1ece0'
WHERE `event_id` = 'DYUT20260913MU01TMQ67BK';

-- 2. Preserve ICSWHMH event webhook if empty
UPDATE `events`
SET `webhook_url` = 'https://icswhmh.com/backend/vortex_webhook.php',
    `webhook_secret` = '806ba73cf5a067c1735d3ac0285b7aac5d4039f3f13ed126'
WHERE `event_id` = 'ICSW20261011MV3SALAS2FM' AND (`webhook_url` IS NULL OR `webhook_url` = '');

-- 3. Drop webhook columns from api_clients
ALTER TABLE `api_clients`
    DROP COLUMN `webhook_url`,
    DROP COLUMN `webhook_secret`;
