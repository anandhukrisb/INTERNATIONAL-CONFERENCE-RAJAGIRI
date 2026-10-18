<?php
/**
 * api/vortex_webhook.php
 *
 * Vortex Payment Gateway Webhook Receiver endpoint wrapper for /api route.
 * Directly routes to root webhook.php for centralized handling and idempotency.
 */

require_once __DIR__ . '/../webhook.php';
