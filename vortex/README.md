# PayVortex - Unified Payment Gateway

PayVortex is a secure, standalone PHP payment gateway orchestrator integrated with Razorpay. It provides merchant API client isolation, dynamic event tracking, temporary checkout sessions, webhook processing, refund dispatching, and a dedicated administrative portal.

---

## Architecture Overview

```
                      +-----------------------------+
                      |   Merchant / Client App     |
                      +-----------------------------+
                                     |
               (API Key / Secret)    | 1. POST /api/create_payment_order.php
                                     v
                      +-----------------------------+
                      |       PayVortex API         |
                      +-----------------------------+
                                     |
     2. Generates Razorpay Order     | 3. Returns 5-Minute Checkout URL
        & Vortex Transaction ID      |
                                     v
                      +-----------------------------+
                      | Customer Checkout Interface |
                      |       (checkout.php)        |
                      +-----------------------------+
                                     |
     4. Razorpay Standard Checkout   | 5. Verifies Signature
        & Network Polling Recovery   |    POST /api/verify_payment.php
                                     v
                      +-----------------------------+
                      |  Merchant Redirect Callback |
                      +-----------------------------+
```

---

## Directory Structure

```
PayVortex/
├── admin/               # Dedicated Admin Dashboard and transaction explorer
│   ├── api_clients/     # Client activation/deactivation handlers
│   ├── assets/css/      # Dashboard stylesheet (admin.css)
│   ├── events/          # Event management handlers
│   ├── includes/        # Header, sidebar, and footer partials
│   ├── payments/        # Transaction list and deep inspection views
│   ├── dashboard.php    # Primary admin dashboard
│   ├── index.php        # Admin entryway redirect
│   ├── login.php        # Secure admin login
│   └── logout.php       # Session termination
├── api/                 # Core Gateway API Endpoints
│   ├── authenticate_user.php          # Merchant credential verification
│   ├── create_payment_order.php       # Transaction order initialization
│   ├── create_refund.php              # Refund issuance
│   ├── get_checkout_payment_status.php# Checkout session polling
│   ├── get_payment_status.php         # Transaction status inquiry
│   ├── health_check.php               # System & DB health probe
│   ├── manage_api_client.php          # API Client CRUD
│   ├── manage_event.php               # Event management
│   ├── process_webhook.php            # Razorpay webhook listener
│   └── verify_payment.php             # Payment verification
├── classes/             # Object-Oriented Business Logic & Services
│   ├── ApiClient.php        # Merchant client management
│   ├── Auth.php             # API Key/Secret authentication
│   ├── CheckoutSession.php  # 5-minute checkout session token provider
│   ├── Database.php         # PDO connection singleton
│   ├── Event.php            # Event lifecycle manager
│   ├── EventIdGenerator.php # Sequential/formatted event ID generator
│   ├── Logger.php           # File logging utility
│   ├── Payment.php          # Razorpay order creator & signature verifier
│   ├── Refund.php           # Razorpay refund handler
│   ├── Response.php         # Standardized JSON response helper
│   ├── Validator.php        # Request validation logic
│   └── Webhook.php          # Webhook signature validation & idempotency
├── config/              # Configuration files
│   └── database.php         # Database and timezone configuration
├── database/            # Database schema and migrations
│   ├── migrations/          # Migration SQL scripts
│   └── schema.sql           # Complete base schema
├── helpers/             # Utility helpers
│   ├── client_helper.php    # API key generation helper
│   └── env_loader.php       # Lightweight .env loader fallback
├── logs/                # Application logs (vortex.log)
├── tests/               # Comprehensive automated test suite
├── .env.example         # Environment variables template
├── .htaccess            # Apache security hardening directives
├── callback_example.php # Reference merchant return page
├── checkout.php         # Production customer payment checkout interface
├── index.php            # Root router (redirects to /admin/)
├── seed_admin.php       # CLI administrator seed utility
└── setup_db.php         # CLI database schema initializer
```

---

## Production Setup & Installation

### 1. Requirements
* PHP 8.0 or higher
* PDO MySQL Extension (`ext-pdo_mysql`)
* cURL Extension (`ext-curl`)
* OpenSSL Extension (`ext-openssl`)
* Composer

### 2. Install Dependencies
```bash
composer install --no-dev --optimize-autoloader
```

### 3. Configure Environment Variables
Copy `.env.example` to `.env` and fill in your production credentials:
```bash
cp .env.example .env
```
Ensure database credentials, Razorpay API keys, and `APP_ENV=production` are configured.

### 4. Initialize Fresh Database
Run the CLI setup script to automatically create all 7 PayVortex tables (and create the database if it doesn't exist):
```bash
php setup_db.php
```

### 5. Export / Backup Data (Optional Migration)
To backup or dump existing PayVortex data from the current database:
```bash
# Exports all 7 tables and their data to a timestamped SQL dump file
php dump_data.php

# Or specify a custom output filename:
php dump_data.php database/my_payvortex_backup.sql
```

To restore/import that dump into a new database:
1. Update `DB_NAME` (and host/user/pass) in `.env`
2. Run:
```bash
php setup_db.php database/my_payvortex_backup.sql
```

### 6. Seed Initial Admin Account
If setting up a fresh database without importing previous data, seed the initial administrator:
```bash
php seed_admin.php
```
> **Security Notice**: Immediately log into the admin portal (`/admin/login.php`) and update the default password.

---

## Automated Test Suite
To verify end-to-end functionality across all endpoints:
```bash
php tests/run_all_tests.php
```
*(Requires a running local server pointing to the project root, e.g. `php -S 0.0.0.0:8000`).*
