-- Vortex Unified Payment Gateway Database Schema

-- 1. Events Table
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(50) NOT NULL UNIQUE,
    event_name VARCHAR(255) NOT NULL,
    department VARCHAR(100) NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. API Clients Table (External apps using this gateway)
CREATE TABLE IF NOT EXISTS `api_clients` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `client_name` VARCHAR(150) NOT NULL,
  `api_key` VARCHAR(64) NOT NULL UNIQUE,
  `api_secret` VARCHAR(64) NOT NULL UNIQUE,
  `test_api_key` VARCHAR(64) UNIQUE NULL,
  `test_api_secret` VARCHAR(64) NULL,
  `webhook_url` VARCHAR(255) NULL,
  `webhook_secret` VARCHAR(64) NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Transactions Table (Razorpay order & payment records)
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vortex_transaction_id VARCHAR(64) NOT NULL UNIQUE,
    event_id VARCHAR(50) NOT NULL,
    api_client_id INT NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    customer_mobile VARCHAR(20) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'INR',
    razorpay_order_id VARCHAR(100) DEFAULT NULL UNIQUE,
    razorpay_payment_id VARCHAR(100) DEFAULT NULL,
    razorpay_signature VARCHAR(255) DEFAULT NULL,
    status ENUM('PENDING', 'SUCCESS', 'FAILED', 'REFUNDED', 'DISPUTED') DEFAULT 'PENDING',
    is_test_mode TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    FOREIGN KEY (api_client_id) REFERENCES api_clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Checkout Sessions Table (5-minute temporary customer payment sessions)
CREATE TABLE IF NOT EXISTS checkout_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_token VARCHAR(64) NOT NULL UNIQUE,
    transaction_id INT NOT NULL,
    redirect_url TEXT NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id)
        REFERENCES transactions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Admin Panel Users (Dedicated gateway admin logins)
CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL DEFAULT 'Administrator',
    email VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    role VARCHAR(50) DEFAULT 'Super Administrator',
    avatar_color VARCHAR(20) DEFAULT '#1a73e8',
    is_active TINYINT(1) DEFAULT 1,
    two_factor_enabled TINYINT(1) DEFAULT 0,
    last_login TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Refunds Table
CREATE TABLE IF NOT EXISTS refunds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    razorpay_refund_id VARCHAR(100) NOT NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    status ENUM('PENDING', 'PROCESSED', 'FAILED') DEFAULT 'PENDING',
    refunded_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id)
        REFERENCES transactions(id)
        ON DELETE CASCADE,
    FOREIGN KEY (refunded_by)
        REFERENCES admin(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,

    razorpay_event_id VARCHAR(100) NOT NULL UNIQUE,

    event_type VARCHAR(100) NOT NULL,

    payment_id VARCHAR(100) DEFAULT NULL,

    order_id VARCHAR(100) DEFAULT NULL,

    signature VARCHAR(255) NOT NULL,

    payload JSON NOT NULL,

    status ENUM(
        'RECEIVED',
        'PROCESSED',
        'FAILED'
    ) DEFAULT 'RECEIVED',

    processed_at TIMESTAMP NULL DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transaction_timeline (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vortex_transaction_id VARCHAR(100) NOT NULL,
    transaction_id INT NULL,
    order_id VARCHAR(100) DEFAULT NULL,
    payment_id VARCHAR(100) DEFAULT NULL,
    event_type VARCHAR(100) NOT NULL,
    status VARCHAR(50) NOT NULL,
    amount DECIMAL(10, 2) DEFAULT NULL,
    currency VARCHAR(10) DEFAULT 'INR',
    reason TEXT DEFAULT NULL,
    source VARCHAR(50) DEFAULT 'RAZORPAY_WEBHOOK',
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_timeline_vortex (vortex_transaction_id),
    INDEX idx_timeline_tx_id (transaction_id),
    INDEX idx_timeline_order (order_id),
    INDEX idx_timeline_payment (payment_id),
    INDEX idx_timeline_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;