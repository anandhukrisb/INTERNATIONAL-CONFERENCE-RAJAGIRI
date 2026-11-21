-- ============================================================
-- Migration: create payment_logs table
-- Project  : Vortex Unified Payment Gateway
-- Purpose  : Full audit log for every payment lifecycle event
--            (order creation, verification, webhook, 3DS, etc.)
--            Records request payloads, response payloads, error
--            details, and customer context for BOTH success and
--            failure events.
-- ============================================================

CREATE TABLE IF NOT EXISTS `payment_logs` (

    -- --------------------------------------------------------
    -- Primary Key
    -- --------------------------------------------------------
    `id`                    INT UNSIGNED        NOT NULL AUTO_INCREMENT,

    -- --------------------------------------------------------
    -- Event Classification
    -- --------------------------------------------------------
    `event_type`            VARCHAR(50)         NOT NULL
        COMMENT 'order.created | order.failed | verify.success | verify.failed | webhook.captured | webhook.failed | webhook.refunded | 3ds.timeout | payment.failed.js',

    -- --------------------------------------------------------
    -- Link to transactions table (nullable — log even before TX exists)
    -- --------------------------------------------------------
    `transaction_id`        INT UNSIGNED        NULL
        COMMENT 'FK → transactions.id',
    `vortex_transaction_id` VARCHAR(64)         NULL
        COMMENT 'VTX... identifier',
    `api_client_id`         INT UNSIGNED        NULL
        COMMENT 'FK → api_clients.id',

    -- --------------------------------------------------------
    -- Razorpay Identifiers
    -- --------------------------------------------------------
    `razorpay_order_id`     VARCHAR(100)        NULL,
    `razorpay_payment_id`   VARCHAR(100)        NULL,

    -- --------------------------------------------------------
    -- Payment Financials
    -- --------------------------------------------------------
    `currency`              VARCHAR(10)         NULL
        COMMENT 'INR | USD | EUR | GBP …',
    `is_international`      TINYINT(1)          NOT NULL DEFAULT 0
        COMMENT '0 = INR (domestic), 1 = all other currencies',
    `amount`                DECIMAL(10, 2)      NULL
        COMMENT 'Human-readable amount (not paise)',
    `payment_status`        VARCHAR(20)         NULL
        COMMENT 'created | authorized | captured | failed | refunded',

    -- --------------------------------------------------------
    -- Payment Method Details
    -- --------------------------------------------------------
    `payment_method`        VARCHAR(50)         NULL
        COMMENT 'card | upi | netbanking | wallet | emi',
    `card_network`          VARCHAR(30)         NULL
        COMMENT 'Visa | Mastercard | RuPay | Amex | Diners',
    `card_last4`            CHAR(4)             NULL,
    `card_type`             VARCHAR(20)         NULL
        COMMENT 'credit | debit | prepaid',

    -- --------------------------------------------------------
    -- Full Payloads (JSON)
    -- --------------------------------------------------------
    `request_payload`       LONGTEXT            NULL
        COMMENT 'JSON: what was sent TO Razorpay (or to our verify endpoint)',
    `response_payload`      LONGTEXT            NULL
        COMMENT 'JSON: what Razorpay returned (order object, webhook body, error response)',

    -- --------------------------------------------------------
    -- Razorpay Error Fields (populated on failure)
    -- --------------------------------------------------------
    `error_code`            VARCHAR(100)        NULL
        COMMENT 'e.g. BAD_REQUEST_ERROR | GATEWAY_ERROR',
    `error_description`     TEXT                NULL
        COMMENT 'Human-readable error message from Razorpay',
    `error_reason`          VARCHAR(100)        NULL
        COMMENT 'e.g. payment_timed_out | payment_authentication | insufficient_funds',
    `error_step`            VARCHAR(100)        NULL
        COMMENT 'e.g. payment_authentication | payment_authorization',
    `error_source`          VARCHAR(50)         NULL
        COMMENT 'customer | gateway | bank | business',

    -- --------------------------------------------------------
    -- Customer Context
    -- --------------------------------------------------------
    `customer_email`        VARCHAR(255)        NULL,
    `customer_mobile`       VARCHAR(30)         NULL,
    `ip_address`            VARCHAR(45)         NULL
        COMMENT 'IPv4 or IPv6, respects X-Forwarded-For / CF-Connecting-IP',
    `user_agent`            VARCHAR(500)        NULL,

    -- --------------------------------------------------------
    -- Timestamp
    -- --------------------------------------------------------
    `logged_at`             TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- --------------------------------------------------------
    -- Constraints & Indexes
    -- --------------------------------------------------------
    PRIMARY KEY (`id`),

    INDEX `idx_pl_vortex_tx`        (`vortex_transaction_id`),
    INDEX `idx_pl_rzp_order`        (`razorpay_order_id`),
    INDEX `idx_pl_rzp_payment`      (`razorpay_payment_id`),
    INDEX `idx_pl_event_type`       (`event_type`),
    INDEX `idx_pl_currency`         (`currency`),
    INDEX `idx_pl_is_international` (`is_international`),
    INDEX `idx_pl_payment_status`   (`payment_status`),
    INDEX `idx_pl_customer_email`   (`customer_email`(100)),
    INDEX `idx_pl_logged_at`        (`logged_at`),
    INDEX `idx_pl_api_client`       (`api_client_id`),
    INDEX `idx_pl_transaction`      (`transaction_id`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Full audit log of every payment event (success + failure) with request/response payloads';
