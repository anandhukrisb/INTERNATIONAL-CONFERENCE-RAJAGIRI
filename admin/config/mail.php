<?php
/**
 * PHPMailer / SMTP Configuration
 * ICSWHMH 2027 Admin Panel — Phase 1
 *
 * Install PHPMailer: composer require phpmailer/phpmailer
 * Or download from: https://github.com/PHPMailer/PHPMailer
 */
declare(strict_types=1);

// ── SMTP Settings — UPDATE BEFORE DEPLOYMENT ─────────────────────────────────
define('MAIL_HOST',       'smtp.gmail.com');   // SMTP server
define('MAIL_PORT',       587);                // 587 (STARTTLS) or 465 (SSL)
define('MAIL_ENCRYPTION', 'STARTTLS');         // 'STARTTLS' or 'SSL'
define('MAIL_USERNAME',   'your-email@gmail.com');   // ← SMTP username
define('MAIL_PASSWORD',   'your-app-password');      // ← App password (not account password)
define('MAIL_FROM',       'noreply@icswhmh2027.com');
define('MAIL_FROM_NAME',  'ICSWHMH 2027 Admin');

// ── For cPanel / Hosting SMTP Example ─────────────────────────────────────────
// define('MAIL_HOST',       'mail.yourdomain.com');
// define('MAIL_PORT',       465);
// define('MAIL_ENCRYPTION', 'SSL');
// define('MAIL_USERNAME',   'noreply@icswhmh2027.com');
// define('MAIL_PASSWORD',   'your-email-password');
