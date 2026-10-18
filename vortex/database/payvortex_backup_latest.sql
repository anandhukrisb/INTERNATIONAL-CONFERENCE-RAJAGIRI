-- ============================================================
-- PayVortex Database Dump
-- Exported on: 2026-10-14 12:29:40
-- Source Database: icswhmh_db
-- ============================================================

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';

-- ------------------------------------------------------------
-- Table structure for `events`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `events`;
CREATE TABLE `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` varchar(50) NOT NULL,
  `event_name` varchar(255) NOT NULL,
  `department` varchar(100) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `webhook_url` varchar(255) DEFAULT NULL,
  `webhook_secret` varchar(64) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_id` (`event_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `events` (2 rows)
INSERT INTO `events` (`id`, `event_id`, `event_name`, `department`, `start_date`, `end_date`, `webhook_url`, `webhook_secret`, `is_active`, `created_at`, `updated_at`) VALUES ('3', 'DYUT20260913MU01TMQ67BK', 'Dyuti', 'Social work', '2026-09-13 00:00:00', '2026-09-13 23:59:59', 'https://dyuti.in/webhook.php', '95050b2f9741d9cc8493e9d1452a486fa6730ff7c0f1ece0', '1', '2026-09-13 22:19:02', '2026-10-11 00:44:43');
INSERT INTO `events` (`id`, `event_id`, `event_name`, `department`, `start_date`, `end_date`, `webhook_url`, `webhook_secret`, `is_active`, `created_at`, `updated_at`) VALUES ('4', 'ICSW20261011MV3SALAS2FM', 'ICSWHMH', 'Computer science', '2026-10-11 00:00:00', '2026-10-31 23:59:59', 'https://icswhmh.com/backend/vortex_webhook.php', '99efbe6734deac163928c46bca906f4671f431ba8290be56', '1', '2026-10-11 17:43:04', '2026-10-11 17:43:04');

-- ------------------------------------------------------------
-- Table structure for `api_clients`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `api_clients`;
CREATE TABLE `api_clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_name` varchar(150) NOT NULL,
  `api_key` varchar(64) NOT NULL,
  `api_secret` varchar(64) NOT NULL,
  `test_api_key` varchar(64) DEFAULT NULL,
  `test_api_secret` varchar(64) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_key` (`api_key`),
  UNIQUE KEY `api_secret` (`api_secret`),
  UNIQUE KEY `test_api_key` (`test_api_key`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `api_clients` (6 rows)
INSERT INTO `api_clients` (`id`, `client_name`, `api_key`, `api_secret`, `test_api_key`, `test_api_secret`, `is_active`, `created_at`) VALUES ('1', 'Vortex Demo Developer', '94ce1b35051583b7036e6feb28645918', '971e0404236dd20ff0de44fed7e68441', 'test_3cf4b54ea6ed86484d76a5aa52d051cf', 'da5c50180eaef1f0c4cec50e6ef90cc7', '1', '2026-09-13 16:34:01');
INSERT INTO `api_clients` (`id`, `client_name`, `api_key`, `api_secret`, `test_api_key`, `test_api_secret`, `is_active`, `created_at`) VALUES ('2', 'icswhmh', '1bbf421c74cce8e03d5f49a63cabfd4e', 'f6408d7d631210e8d8ab5c0be6110fea', 'test_9bd173fb42afbd6219ade0242398b55c', 'e6e3f76155bd5ced1a3cc7f945843658', '1', '2026-09-13 17:31:40');
INSERT INTO `api_clients` (`id`, `client_name`, `api_key`, `api_secret`, `test_api_key`, `test_api_secret`, `is_active`, `created_at`) VALUES ('3', 'icswhmh', '0969a93d1b3767668c822d0cd8998f10', '254604c04726dcf75af9de4f52e2de88', 'test_5637a9071b9d3d0e476eefd2cc766858', '57c3f0904f24133bae1c559f90f1d1cd', '1', '2026-09-13 22:01:36');
INSERT INTO `api_clients` (`id`, `client_name`, `api_key`, `api_secret`, `test_api_key`, `test_api_secret`, `is_active`, `created_at`) VALUES ('4', 'Dyuti', 'abed8fb455b441017c49ac564ff83309', '81e4661ce69c6aa89e56bfe1275583f9', 'test_6c2560c51cc443612919df226e3cc757', '473dd3a147aee5422663bf8b328cc99c', '1', '2026-09-13 22:19:24');
INSERT INTO `api_clients` (`id`, `client_name`, `api_key`, `api_secret`, `test_api_key`, `test_api_secret`, `is_active`, `created_at`) VALUES ('5', 'Dyuti', '51e60f98b5b217688d0fe537a1a58033', 'e605e71a192a6eba29416f3931e5abf3', 'test_cc69d7b50a831ed60ab57ea5fab1e7fd', '1d09938b691d7a0358145f7b51361e76', '1', '2026-09-13 22:28:06');
INSERT INTO `api_clients` (`id`, `client_name`, `api_key`, `api_secret`, `test_api_key`, `test_api_secret`, `is_active`, `created_at`) VALUES ('7', 'icswhmhOriginal', '839109936a140f77e293b0c17c50173f', '0b48b7bd0f581246fc3f80130ba8c2e9', 'test_ea1b4a6263fc0b0fb0ad8680be4d4087', 'e415495af5e85622c1ab65cf45199165', '1', '2026-10-11 18:22:27');

-- ------------------------------------------------------------
-- Table structure for `transactions`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vortex_transaction_id` varchar(64) NOT NULL,
  `event_id` varchar(50) NOT NULL,
  `api_client_id` int(11) NOT NULL,
  `customer_email` varchar(255) NOT NULL,
  `customer_mobile` varchar(20) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'INR',
  `razorpay_order_id` varchar(100) DEFAULT NULL,
  `razorpay_payment_id` varchar(100) DEFAULT NULL,
  `razorpay_signature` varchar(255) DEFAULT NULL,
  `status` enum('PENDING','SUCCESS','FAILED','REFUNDED','DISPUTED') DEFAULT 'PENDING',
  `is_test_mode` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `vortex_transaction_id` (`vortex_transaction_id`),
  UNIQUE KEY `razorpay_order_id` (`razorpay_order_id`),
  KEY `event_id` (`event_id`),
  KEY `api_client_id` (`api_client_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`api_client_id`) REFERENCES `api_clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=84 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `transactions` (20 rows)
INSERT INTO `transactions` (`id`, `vortex_transaction_id`, `event_id`, `api_client_id`, `customer_email`, `customer_mobile`, `amount`, `currency`, `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`, `status`, `is_test_mode`, `created_at`, `updated_at`) VALUES ('54', 'VTX178957007698476819228', 'DYUT20260913MU01TMQ67BK', '5', 'bbajproject@gmail.com', '7736301573', '1000.00', 'INR', 'order_TckM1APR2snsbW', NULL, NULL, 'PENDING', '0', '2026-09-16 20:17:57', '2026-09-16 20:17:59');
INSERT INTO `transactions` (`id`, `vortex_transaction_id`, `event_id`, `api_client_id`, `customer_email`, `customer_mobile`, `amount`, `currency`, `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`, `status`, `is_test_mode`, `created_at`, `updated_at`) VALUES ('55', 'VTX1789579333330C0B131F2', 'DYUT20260913MU01TMQ67BK', '5', 'delegate@rajagiri.edu', '9876543210', '750.00', 'INR', 'order_TcmyymLLbKvcHu', NULL, NULL, 'PENDING', '0', '2026-09-16 22:52:13', '2026-09-16 22:52:15');
INSERT INTO `transactions` (`id`, `vortex_transaction_id`, `event_id`, `api_client_id`, `customer_email`, `customer_mobile`, `amount`, `currency`, `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`, `status`, `is_test_mode`, `created_at`, `updated_at`) VALUES ('56', 'VTX17896236663684C323F88', 'DYUT20260913MU01TMQ67BK', '5', 'test@mail.com', '8565965478', '750.00', 'INR', 'order_TczZTm3tVuJEOQ', NULL, NULL, 'PENDING', '0', '2026-09-17 11:11:06', '2026-09-17 11:11:08');
INSERT INTO `transactions` (`id`, `vortex_transaction_id`, `event_id`, `api_client_id`, `customer_email`, `customer_mobile`, `amount`, `currency`, `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`, `status`, `is_test_mode`, `created_at`, `updated_at`) VALUES ('66', 'VTX1789722371158F722C1E5', 'DYUT20260913MU01TMQ67BK', '5', 'pavitrasanjay656@gmail.com', '8281106495', '750.00', 'INR', 'order_TdRbEcKENpBnmw', NULL, NULL, 'PENDING', '0', '2026-09-18 14:36:11', '2026-09-18 14:36:13');
INSERT INTO `transactions` (`id`, `vortex_transaction_id`, `event_id`, `api_client_id`, `customer_email`, `customer_mobile`, `amount`, `currency`, `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`, `status`, `is_test_mode`, `created_at`, `updated_at`) VALUES ('67', 'VTX17897519092551EB64601', 'DYUT20260913MU01TMQ67BK', '5', 'sarahjooycey@gmail.com', '7094927583', '750.00', 'INR', 'order_TdZzGf88CphE8q', 'pay_Tda0A6FHnA79JC', '5f4f34142f72c4e11477d98789a5d54e37e9bb9e7065f98e731d5e02081d90b4', 'SUCCESS', '0', '2026-09-18 22:48:29', '2026-09-18 22:49:50');
INSERT INTO `transactions` (`id`, `vortex_transaction_id`, `event_id`, `api_client_id`, `customer_email`, `customer_mobile`, `amount`, `currency`, `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`, `status`, `is_test_mode`, `created_at`, `updated_at`) VALUES ('69', 'VTX1789827292358543CE2D6', 'DYUT20260913MU01TMQ67BK', '5', 'blessem@gmail.com', '7736301573', '750.00', 'INR', 'order_TdvOQjaBkhOmZo', NULL, NULL, 'PENDING', '0', '2026-09-19 19:44:52', '2026-09-19 19:44:54');
INSERT INTO `transactions` (`id`, `vortex_transaction_id`, `event_id`, `api_client_id`, `customer_email`, `customer_mobile`, `amount`, `currency`, `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`, `status`, `is_test_mode`, `created_at`, `updated_at`) VALUES ('70', 'VTX1790180182364B16FE1C4', 'DYUT20260913MU01TMQ67BK', '5', 'blessenpshaju555@gmail.com', '7736301573', '750.00', 'INR', 'order_TfXbFt9NBV3vxZ', NULL, NULL, 'PENDING', '0', '2026-09-23 21:46:22', '2026-09-23 21:46:24');
INSERT INTO `transactions` (`id`, `vortex_transaction_id`, `event_id`, `api_client_id`, `customer_email`, `customer_mobile`, `amount`, `currency`, `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`, `status`, `is_test_mode`, `created_at`, `updated_at`) VALUES ('71', 'VTX179032695596935CBEA33', 'DYUT20260913MU01TMQ67BK', '5', 'prabhasankar8@gmail.com', '9488447390', '750.00', 'INR', 'order_TgDHHySKHmJQ1D', NULL, NULL, 'PENDING', '0', '2026-09-25 14:32:36', '2026-09-25 14:32:37');
INSERT INTO `transactions` (`id`, `vortex_transaction_id`, `event_id`, `api_client_id`, `customer_email`, `customer_mobile`, `amount`, `currency`, `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`, `status`, `is_test_mode`, `created_at`, `updated_at`) VALUES ('72', 'VTX1790338893004E4FEC0F2', 'DYUT20260913MU01TMQ67BK', '5', 'shajup325@gmail.com', '7736301573', '750.00', 'INR', 'order_TgGfRluSr9MFyg', NULL, NULL, 'PENDING', '0', '2026-09-25 17:51:33', '2026-09-25 17:51:34');
INSERT INTO `transactions` (`id`, `vortex_transaction_id`, `event_id`, `api_client_id`, `customer_email`, `customer_mobile`, `amount`, `currency`, `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`, `status`, `is_test_mode`, `created_at`, `updated_at`) VALUES ('73', 'VTX17903589390095D5CDACD', 'DYUT20260913MU01TMQ67BK', '5', 'silpakarthika2024@pondiuni.ac.in', '7356751554', '750.00', 'INR', 'order_TgMMMmSc9F8PjJ', 'pay_TgMNtbygVg1Hp9', '8fff4a3566557d32aaa735c94c6cc464e96bc0cf12c266f57e0c001f64bd052d', 'SUCCESS', '0', '2026-09-25 23:25:39', '2026-09-25 23:27:36');
INSERT INTO `transactions` (`id`, `vortex_transaction_id`, `event_id`, `api_client_id`, `customer_email`, `customer_mobile`, `amount`, `currency`, `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`, `status`, `is_test_mode`, `created_at`, `updated_at`) VALUES ('74', 'VTX1790695459118854763D2', 'DYUT20260913MU01TMQ67BK', '5', 'p.sunny86@gmail.com', '9905499803', '750.00', 'INR', 'order_Thtv0D3Hpy3PtX', 'pay_Thtx7RMogw0TrI', '2f6323b7c0c0f967d9522ab3cbde18b6c076b98a243e6df16518a9a175c5c4c5', 'SUCCESS', '0', '2026-09-29 20:54:20', '2026-09-29 20:56:57');
INSERT INTO `transactions` (`id`, `vortex_transaction_id`, `event_id`, `api_client_id`, `customer_email`, `customer_mobile`, `amount`, `currency`, `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`, `status`, `is_test_mode`, `created_at`, `updated_at`) VALUES ('75', 'VTX17909204420074AC4EA7F', 'DYUT20260913MU01TMQ67BK', '5', 'rohitkanthaliya69@gmail.com', '8962379348', '750.00', 'INR', 'order_TivnwTv9oehyTV', NULL, NULL, 'PENDING', '0', '2026-10-02 11:24:02', '2026-10-02 11:24:03');
INSERT INTO `transactions` (`id`, `vortex_transaction_id`, `event_id`, `api_client_id`, `customer_email`, `customer_mobile`, `amount`, `currency`, `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`, `status`, `is_test_mode`, `created_at`, `updated_at`) VALUES ('76', 'VTX1791175942335D586008C', 'DYUT20260913MU01TMQ67BK', '5', 'abhaiantony10@gmail.com', '9740628859', '750.00', 'INR', 'order_Tk6MAVN1swYEcw', 'pay_Tk6Moyt74QlagR', NULL, 'REFUNDED', '0', '2026-10-05 10:22:22', '2026-10-14 11:39:07');
INSERT INTO `transactions` (`id`, `vortex_transaction_id`, `event_id`, `api_client_id`, `customer_email`, `customer_mobile`, `amount`, `currency`, `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`, `status`, `is_test_mode`, `created_at`, `updated_at`) VALUES ('77', 'VTX179152669448751C2B53D', 'DYUT20260913MU01TMQ67BK', '5', 'mca2510@rajagiri.edu', '8921315216', '750.00', 'INR', 'order_TlhxM8mgYdG3AH', 'pay_TlhyoMU60Zefv1', NULL, 'FAILED', '0', '2026-10-09 11:48:14', '2026-10-14 11:15:36');
INSERT INTO `transactions` (`id`, `vortex_transaction_id`, `event_id`, `api_client_id`, `customer_email`, `customer_mobile`, `amount`, `currency`, `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`, `status`, `is_test_mode`, `created_at`, `updated_at`) VALUES ('78', 'VTX17917232846361BDA891B', 'ICSW20261011MV3SALAS2FM', '7', 'mca2510@rajagiri.edu', '8921315216', '600.00', 'INR', NULL, NULL, NULL, 'FAILED', '1', '2026-10-11 18:24:45', '2026-10-11 18:24:46');
INSERT INTO `transactions` (`id`, `vortex_transaction_id`, `event_id`, `api_client_id`, `customer_email`, `customer_mobile`, `amount`, `currency`, `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`, `status`, `is_test_mode`, `created_at`, `updated_at`) VALUES ('79', 'VTX1791723310476BDBE6D29', 'ICSW20261011MV3SALAS2FM', '7', 'mca2510@rajagiri.edu', '8921315216', '600.00', 'INR', NULL, NULL, NULL, 'FAILED', '1', '2026-10-11 18:25:10', '2026-10-11 18:25:12');
INSERT INTO `transactions` (`id`, `vortex_transaction_id`, `event_id`, `api_client_id`, `customer_email`, `customer_mobile`, `amount`, `currency`, `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`, `status`, `is_test_mode`, `created_at`, `updated_at`) VALUES ('80', 'VTX17917235480779B7E9A85', 'ICSW20261011MV3SALAS2FM', '7', 'mca2510@rajagiri.edu', '8921315216', '600.00', 'INR', NULL, NULL, NULL, 'FAILED', '1', '2026-10-11 18:29:08', '2026-10-11 18:29:09');
INSERT INTO `transactions` (`id`, `vortex_transaction_id`, `event_id`, `api_client_id`, `customer_email`, `customer_mobile`, `amount`, `currency`, `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`, `status`, `is_test_mode`, `created_at`, `updated_at`) VALUES ('81', 'VTX1791723565635A549D689', 'ICSW20261011MV3SALAS2FM', '7', 'mca2510@rajagiri.edu', '8921315216', '600.00', 'INR', NULL, NULL, NULL, 'FAILED', '1', '2026-10-11 18:29:26', '2026-10-11 18:29:27');
INSERT INTO `transactions` (`id`, `vortex_transaction_id`, `event_id`, `api_client_id`, `customer_email`, `customer_mobile`, `amount`, `currency`, `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`, `status`, `is_test_mode`, `created_at`, `updated_at`) VALUES ('82', 'VTX1791723697607E4E7DC52', 'ICSW20261011MV3SALAS2FM', '7', 'test@example.com', '9999999999', '500.00', 'INR', NULL, NULL, NULL, 'FAILED', '1', '2026-10-11 18:31:38', '2026-10-11 18:31:39');
INSERT INTO `transactions` (`id`, `vortex_transaction_id`, `event_id`, `api_client_id`, `customer_email`, `customer_mobile`, `amount`, `currency`, `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`, `status`, `is_test_mode`, `created_at`, `updated_at`) VALUES ('83', 'VTX1791723832526EDF38EA3', 'ICSW20261011MV3SALAS2FM', '7', 'mca2510@rajagiri.edu', '8921315216', '600.00', 'INR', 'order_Tmbw5HtYZjlFis', NULL, NULL, 'PENDING', '1', '2026-10-11 18:33:52', '2026-10-11 18:33:54');

-- ------------------------------------------------------------
-- Table structure for `checkout_sessions`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `checkout_sessions`;
CREATE TABLE `checkout_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_token` varchar(64) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `redirect_url` text NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `transaction_id` (`transaction_id`),
  CONSTRAINT `checkout_sessions_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `checkout_sessions` (1 rows)
INSERT INTO `checkout_sessions` (`id`, `session_token`, `transaction_id`, `redirect_url`, `expires_at`, `created_at`) VALUES ('91', 'd1398bb2dc6ae428eebee94e3a33f24ce68984fe342b6c5a39372e96d2a790ed', '83', 'https://icswhmh.com/backend/verify_vortex_payment.php?reg_id=ICSW261011-RO1UD', '2026-10-11 18:38:54', '2026-10-11 18:33:55');

-- ------------------------------------------------------------
-- Table structure for `admin`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `admin`;
CREATE TABLE `admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(80) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(150) NOT NULL DEFAULT 'Administrator',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `admin` (2 rows)
INSERT INTO `admin` (`id`, `username`, `password_hash`, `full_name`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES ('1', 'admin', '$2y$12$yTkzMQU7fwzAYfjv4g.IDeQ9OpVQdxK1P2ldD/ByTLD23aowHQJQa', 'Vortex Administrator', '1', '2026-10-14 11:05:58', '2026-09-13 21:37:43', '2026-10-14 11:05:58');
INSERT INTO `admin` (`id`, `username`, `password_hash`, `full_name`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES ('2', 'lakshmi', '$2y$12$QhyB.eabycX2TAq08B7YReVU83vqqZ.ffaoTc4tN5kn7VjKg.nJm.', 'Lakshmi', '1', '2026-10-14 10:21:03', '2026-10-09 10:34:56', '2026-10-14 10:21:03');

-- ------------------------------------------------------------
-- Table structure for `refunds`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `refunds`;
CREATE TABLE `refunds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` int(11) NOT NULL,
  `razorpay_refund_id` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('PENDING','PROCESSED','FAILED') DEFAULT 'PENDING',
  `refunded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `razorpay_refund_id` (`razorpay_refund_id`),
  KEY `transaction_id` (`transaction_id`),
  KEY `refunds_admin_fk` (`refunded_by`),
  CONSTRAINT `refunds_admin_fk` FOREIGN KEY (`refunded_by`) REFERENCES `admin` (`id`) ON DELETE SET NULL,
  CONSTRAINT `refunds_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Table structure for `webhooks`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `webhooks`;
CREATE TABLE `webhooks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `razorpay_event_id` varchar(100) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `payment_id` varchar(100) DEFAULT NULL,
  `order_id` varchar(100) DEFAULT NULL,
  `signature` varchar(255) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `status` enum('RECEIVED','PROCESSED','FAILED') DEFAULT 'RECEIVED',
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `razorpay_event_id` (`razorpay_event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Table structure for `transaction_timeline`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `transaction_timeline`;
CREATE TABLE `transaction_timeline` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vortex_transaction_id` varchar(100) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `order_id` varchar(100) DEFAULT NULL,
  `payment_id` varchar(100) DEFAULT NULL,
  `event_type` varchar(100) NOT NULL,
  `status` varchar(50) NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'INR',
  `reason` text DEFAULT NULL,
  `source` varchar(50) DEFAULT 'RAZORPAY_WEBHOOK',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_timeline_vortex` (`vortex_transaction_id`),
  KEY `idx_timeline_tx_id` (`transaction_id`),
  KEY `idx_timeline_order` (`order_id`),
  KEY `idx_timeline_payment` (`payment_id`),
  KEY `idx_timeline_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `transaction_timeline` (25 rows)
INSERT INTO `transaction_timeline` (`id`, `vortex_transaction_id`, `transaction_id`, `order_id`, `payment_id`, `event_type`, `status`, `amount`, `currency`, `reason`, `source`, `metadata`, `created_at`) VALUES ('1', 'VTX179152669448751C2B53D', '77', 'order_TlhxM8mgYdG3AH', 'pay_TlhyoMU60Zefv1', 'PAYMENT_FAILED', 'FAILED', '750.00', 'INR', 'Payment attempt failed: Payment was unsuccessful as you could not complete it in time.', 'MANUAL_SYNC', '{\"method\":\"upi\",\"vpa\":null,\"error_code\":\"BAD_REQUEST_ERROR\",\"error_description\":\"Payment was unsuccessful as you could not complete it in time.\"}', '2026-10-14 12:12:20');
INSERT INTO `transaction_timeline` (`id`, `vortex_transaction_id`, `transaction_id`, `order_id`, `payment_id`, `event_type`, `status`, `amount`, `currency`, `reason`, `source`, `metadata`, `created_at`) VALUES ('2', 'VTX1791175942335D586008C', '76', 'order_Tk6MAVN1swYEcw', 'pay_Tk6Moyt74QlagR', 'REFUND_PROCESSED', 'REFUNDED', '750.00', 'INR', 'Full refund credited back to customer (upi)', 'MANUAL_SYNC', '{\"method\":\"upi\",\"vpa\":\"9740628859@naviaxis\",\"error_code\":null,\"error_description\":null}', '2026-10-14 12:12:21');
INSERT INTO `transaction_timeline` (`id`, `vortex_transaction_id`, `transaction_id`, `order_id`, `payment_id`, `event_type`, `status`, `amount`, `currency`, `reason`, `source`, `metadata`, `created_at`) VALUES ('3', 'VTX1790695459118854763D2', '74', 'order_Thtv0D3Hpy3PtX', 'pay_Thtx7RMogw0TrI', 'PAYMENT_CAPTURED', 'SUCCESS', '750.00', 'INR', 'Payment attempt captured successfully via upi (RRN: 985112920155)', 'MANUAL_SYNC', '{\"method\":\"upi\",\"vpa\":\"p.sunny86@ybl\",\"error_code\":null,\"error_description\":null}', '2026-10-14 12:12:21');
INSERT INTO `transaction_timeline` (`id`, `vortex_transaction_id`, `transaction_id`, `order_id`, `payment_id`, `event_type`, `status`, `amount`, `currency`, `reason`, `source`, `metadata`, `created_at`) VALUES ('4', 'VTX17903589390095D5CDACD', '73', 'order_TgMMMmSc9F8PjJ', 'pay_TgMNtbygVg1Hp9', 'PAYMENT_CAPTURED', 'SUCCESS', '750.00', 'INR', 'Payment attempt captured successfully via upi (RRN: 315523932041)', 'MANUAL_SYNC', '{\"method\":\"upi\",\"vpa\":\"7356751554@ptyes\",\"error_code\":null,\"error_description\":null}', '2026-10-14 12:12:22');
INSERT INTO `transaction_timeline` (`id`, `vortex_transaction_id`, `transaction_id`, `order_id`, `payment_id`, `event_type`, `status`, `amount`, `currency`, `reason`, `source`, `metadata`, `created_at`) VALUES ('5', 'VTX17897519092551EB64601', '67', 'order_TdZzGf88CphE8q', 'pay_Tda0A6FHnA79JC', 'PAYMENT_CAPTURED', 'SUCCESS', '750.00', 'INR', 'Payment attempt captured successfully via upi (RRN: 662790560094)', 'MANUAL_SYNC', '{\"method\":\"upi\",\"vpa\":\"sarahjooycey@okicici\",\"error_code\":null,\"error_description\":null}', '2026-10-14 12:12:24');
INSERT INTO `transaction_timeline` (`id`, `vortex_transaction_id`, `transaction_id`, `order_id`, `payment_id`, `event_type`, `status`, `amount`, `currency`, `reason`, `source`, `metadata`, `created_at`) VALUES ('6', 'VTX178957007698476819228', '54', 'order_TckM1APR2snsbW', NULL, 'ORDER_CREATED', 'PENDING', '1000.00', 'INR', 'Order generated in Vortex; awaiting customer payment attempt', 'GATEWAY_CHECKOUT', NULL, '2026-09-16 20:17:57');
INSERT INTO `transaction_timeline` (`id`, `vortex_transaction_id`, `transaction_id`, `order_id`, `payment_id`, `event_type`, `status`, `amount`, `currency`, `reason`, `source`, `metadata`, `created_at`) VALUES ('7', 'VTX1789579333330C0B131F2', '55', 'order_TcmyymLLbKvcHu', NULL, 'ORDER_CREATED', 'PENDING', '750.00', 'INR', 'Order generated in Vortex; awaiting customer payment attempt', 'GATEWAY_CHECKOUT', NULL, '2026-09-16 22:52:13');
INSERT INTO `transaction_timeline` (`id`, `vortex_transaction_id`, `transaction_id`, `order_id`, `payment_id`, `event_type`, `status`, `amount`, `currency`, `reason`, `source`, `metadata`, `created_at`) VALUES ('8', 'VTX17896236663684C323F88', '56', 'order_TczZTm3tVuJEOQ', NULL, 'ORDER_CREATED', 'PENDING', '750.00', 'INR', 'Order generated in Vortex; awaiting customer payment attempt', 'GATEWAY_CHECKOUT', NULL, '2026-09-17 11:11:06');
INSERT INTO `transaction_timeline` (`id`, `vortex_transaction_id`, `transaction_id`, `order_id`, `payment_id`, `event_type`, `status`, `amount`, `currency`, `reason`, `source`, `metadata`, `created_at`) VALUES ('9', 'VTX1789722371158F722C1E5', '66', 'order_TdRbEcKENpBnmw', NULL, 'ORDER_CREATED', 'PENDING', '750.00', 'INR', 'Order generated in Vortex; awaiting customer payment attempt', 'GATEWAY_CHECKOUT', NULL, '2026-09-18 14:36:11');
INSERT INTO `transaction_timeline` (`id`, `vortex_transaction_id`, `transaction_id`, `order_id`, `payment_id`, `event_type`, `status`, `amount`, `currency`, `reason`, `source`, `metadata`, `created_at`) VALUES ('10', 'VTX1789827292358543CE2D6', '69', 'order_TdvOQjaBkhOmZo', NULL, 'ORDER_CREATED', 'PENDING', '750.00', 'INR', 'Order generated in Vortex; awaiting customer payment attempt', 'GATEWAY_CHECKOUT', NULL, '2026-09-19 19:44:52');
INSERT INTO `transaction_timeline` (`id`, `vortex_transaction_id`, `transaction_id`, `order_id`, `payment_id`, `event_type`, `status`, `amount`, `currency`, `reason`, `source`, `metadata`, `created_at`) VALUES ('11', 'VTX1790180182364B16FE1C4', '70', 'order_TfXbFt9NBV3vxZ', NULL, 'ORDER_CREATED', 'PENDING', '750.00', 'INR', 'Order generated in Vortex; awaiting customer payment attempt', 'GATEWAY_CHECKOUT', NULL, '2026-09-23 21:46:22');
INSERT INTO `transaction_timeline` (`id`, `vortex_transaction_id`, `transaction_id`, `order_id`, `payment_id`, `event_type`, `status`, `amount`, `currency`, `reason`, `source`, `metadata`, `created_at`) VALUES ('12', 'VTX179032695596935CBEA33', '71', 'order_TgDHHySKHmJQ1D', NULL, 'ORDER_CREATED', 'PENDING', '750.00', 'INR', 'Order generated in Vortex; awaiting customer payment attempt', 'GATEWAY_CHECKOUT', NULL, '2026-09-25 14:32:36');
INSERT INTO `transaction_timeline` (`id`, `vortex_transaction_id`, `transaction_id`, `order_id`, `payment_id`, `event_type`, `status`, `amount`, `currency`, `reason`, `source`, `metadata`, `created_at`) VALUES ('13', 'VTX1790338893004E4FEC0F2', '72', 'order_TgGfRluSr9MFyg', NULL, 'ORDER_CREATED', 'PENDING', '750.00', 'INR', 'Order generated in Vortex; awaiting customer payment attempt', 'GATEWAY_CHECKOUT', NULL, '2026-09-25 17:51:33');
INSERT INTO `transaction_timeline` (`id`, `vortex_transaction_id`, `transaction_id`, `order_id`, `payment_id`, `event_type`, `status`, `amount`, `currency`, `reason`, `source`, `metadata`, `created_at`) VALUES ('14', 'VTX17909204420074AC4EA7F', '75', 'order_TivnwTv9oehyTV', NULL, 'ORDER_CREATED', 'PENDING', '750.00', 'INR', 'Order generated in Vortex; awaiting customer payment attempt', 'GATEWAY_CHECKOUT', NULL, '2026-10-02 11:24:02');
INSERT INTO `transaction_timeline` (`id`, `vortex_transaction_id`, `transaction_id`, `order_id`, `payment_id`, `event_type`, `status`, `amount`, `currency`, `reason`, `source`, `metadata`, `created_at`) VALUES ('15', 'VTX17917232846361BDA891B', '78', NULL, NULL, 'ORDER_CREATED', 'PENDING', '600.00', 'INR', 'Order generated in Vortex; awaiting customer payment attempt', 'GATEWAY_CHECKOUT', NULL, '2026-10-11 18:24:45');
INSERT INTO `transaction_timeline` (`id`, `vortex_transaction_id`, `transaction_id`, `order_id`, `payment_id`, `event_type`, `status`, `amount`, `currency`, `reason`, `source`, `metadata`, `created_at`) VALUES ('16', 'VTX17917232846361BDA891B', '78', NULL, NULL, 'PAYMENT_FAILED', 'FAILED', '600.00', 'INR', 'Payment attempt was unsuccessful or expired without completion', 'GATEWAY_CHECKOUT', NULL, '2026-10-11 18:24:46');
INSERT INTO `transaction_timeline` (`id`, `vortex_transaction_id`, `transaction_id`, `order_id`, `payment_id`, `event_type`, `status`, `amount`, `currency`, `reason`, `source`, `metadata`, `created_at`) VALUES ('17', 'VTX1791723310476BDBE6D29', '79', NULL, NULL, 'ORDER_CREATED', 'PENDING', '600.00', 'INR', 'Order generated in Vortex; awaiting customer payment attempt', 'GATEWAY_CHECKOUT', NULL, '2026-10-11 18:25:10');
INSERT INTO `transaction_timeline` (`id`, `vortex_transaction_id`, `transaction_id`, `order_id`, `payment_id`, `event_type`, `status`, `amount`, `currency`, `reason`, `source`, `metadata`, `created_at`) VALUES ('18', 'VTX1791723310476BDBE6D29', '79', NULL, NULL, 'PAYMENT_FAILED', 'FAILED', '600.00', 'INR', 'Payment attempt was unsuccessful or expired without completion', 'GATEWAY_CHECKOUT', NULL, '2026-10-11 18:25:12');
INSERT INTO `transaction_timeline` (`id`, `vortex_transaction_id`, `transaction_id`, `order_id`, `payment_id`, `event_type`, `status`, `amount`, `currency`, `reason`, `source`, `metadata`, `created_at`) VALUES ('19', 'VTX17917235480779B7E9A85', '80', NULL, NULL, 'ORDER_CREATED', 'PENDING', '600.00', 'INR', 'Order generated in Vortex; awaiting customer payment attempt', 'GATEWAY_CHECKOUT', NULL, '2026-10-11 18:29:08');
INSERT INTO `transaction_timeline` (`id`, `vortex_transaction_id`, `transaction_id`, `order_id`, `payment_id`, `event_type`, `status`, `amount`, `currency`, `reason`, `source`, `metadata`, `created_at`) VALUES ('20', 'VTX17917235480779B7E9A85', '80', NULL, NULL, 'PAYMENT_FAILED', 'FAILED', '600.00', 'INR', 'Payment attempt was unsuccessful or expired without completion', 'GATEWAY_CHECKOUT', NULL, '2026-10-11 18:29:09');
INSERT INTO `transaction_timeline` (`id`, `vortex_transaction_id`, `transaction_id`, `order_id`, `payment_id`, `event_type`, `status`, `amount`, `currency`, `reason`, `source`, `metadata`, `created_at`) VALUES ('21', 'VTX1791723565635A549D689', '81', NULL, NULL, 'ORDER_CREATED', 'PENDING', '600.00', 'INR', 'Order generated in Vortex; awaiting customer payment attempt', 'GATEWAY_CHECKOUT', NULL, '2026-10-11 18:29:26');
INSERT INTO `transaction_timeline` (`id`, `vortex_transaction_id`, `transaction_id`, `order_id`, `payment_id`, `event_type`, `status`, `amount`, `currency`, `reason`, `source`, `metadata`, `created_at`) VALUES ('22', 'VTX1791723565635A549D689', '81', NULL, NULL, 'PAYMENT_FAILED', 'FAILED', '600.00', 'INR', 'Payment attempt was unsuccessful or expired without completion', 'GATEWAY_CHECKOUT', NULL, '2026-10-11 18:29:27');
INSERT INTO `transaction_timeline` (`id`, `vortex_transaction_id`, `transaction_id`, `order_id`, `payment_id`, `event_type`, `status`, `amount`, `currency`, `reason`, `source`, `metadata`, `created_at`) VALUES ('23', 'VTX1791723697607E4E7DC52', '82', NULL, NULL, 'ORDER_CREATED', 'PENDING', '500.00', 'INR', 'Order generated in Vortex; awaiting customer payment attempt', 'GATEWAY_CHECKOUT', NULL, '2026-10-11 18:31:38');
INSERT INTO `transaction_timeline` (`id`, `vortex_transaction_id`, `transaction_id`, `order_id`, `payment_id`, `event_type`, `status`, `amount`, `currency`, `reason`, `source`, `metadata`, `created_at`) VALUES ('24', 'VTX1791723697607E4E7DC52', '82', NULL, NULL, 'PAYMENT_FAILED', 'FAILED', '500.00', 'INR', 'Payment attempt was unsuccessful or expired without completion', 'GATEWAY_CHECKOUT', NULL, '2026-10-11 18:31:39');
INSERT INTO `transaction_timeline` (`id`, `vortex_transaction_id`, `transaction_id`, `order_id`, `payment_id`, `event_type`, `status`, `amount`, `currency`, `reason`, `source`, `metadata`, `created_at`) VALUES ('25', 'VTX1791723832526EDF38EA3', '83', 'order_Tmbw5HtYZjlFis', NULL, 'ORDER_CREATED', 'PENDING', '600.00', 'INR', 'Order generated in Vortex; awaiting customer payment attempt', 'GATEWAY_CHECKOUT', NULL, '2026-10-11 18:33:52');

SET FOREIGN_KEY_CHECKS=1;
-- Dump completed.
