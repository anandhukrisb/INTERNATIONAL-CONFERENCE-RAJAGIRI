<?php
/**
 * backend/vortex_webhook.php
 *
 * Vortex Payment Gateway Webhook Receiver endpoint wrapper.
 * Directly routes to root webhook.php for centralized handling and idempotency.
 */

require_once __DIR__ . '/../webhook.php';
