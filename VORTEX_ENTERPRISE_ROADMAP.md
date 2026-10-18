# PayVortex — Enterprise-Level Upgrade Roadmap

## Current State Summary
PayVortex is a solid, standalone PHP payment gateway orchestrator sitting on top of Razorpay. It has:
- API Client isolation (live + test key pairs)
- 5-minute checkout sessions
- Webhook ingestion + idempotency
- Refund dispatching
- Transaction timeline
- Admin dashboard
- File-based logging
- Basic `.htaccess` hardening
- A manual test suite

---

## 🔐 1. Security & Authentication

| Gap | What to Add |
|-----|------------|
| Single admin role only | ✅ **RBAC (Role-Based Access Control) [COMPLETED]** — `super_admin`, `finance`, `support`, `read-only` |
| No 2FA in login flow | **TOTP / Google Authenticator 2FA** (schema field exists but not implemented) |
| No rate limiting on API endpoints | **Rate limiter** per `api_key` (e.g. 100 req/min) with 429 responses |
| No IP allowlisting per client | **IP whitelist per API client** stored in `api_clients` table |
| No API key rotation | **Key rotation endpoint** — rotate live/test keys without downtime |
| No request signing beyond webhook | **HMAC request signing** for all inbound API calls (not just webhooks) |
| Session tokens are plain hex | **JWT-based admin sessions** with expiry + refresh tokens |
| `Content-Security-Policy` header missing | Add **CSP, HSTS, Permissions-Policy** headers in `.htaccess` |
| No brute-force lockout on admin login | **Account lockout** after N failed logins + CAPTCHA |
| Secrets hardcoded as fallbacks in Payment.php | Remove ALL fallback hardcoded keys — fail loudly |

---

## 📊 2. Observability & Logging

| Gap | What to Add |
|-----|------------|
| Logger writes to a single flat file | **Structured JSON logging** (log level, context, request ID, client ID) |
| No log rotation | **Log rotation** (daily, size-based) or push to syslog |
| No centralized log sink | Integration with **Sentry / Datadog / ELK** for error monitoring |
| No request tracing | **Correlation/Trace IDs** propagated through every request → log → webhook |
| No performance metrics | **Latency tracking** for Razorpay API calls, DB queries |
| No alerting | **Alert hooks** — email/Slack on failed webhook, failed Razorpay order, refund errors |
| Admin dashboard has no live activity | **Real-time activity feed** (Server-Sent Events or WebSocket) |

---

## 📈 3. Analytics & Reporting

| Gap | What to Add |
|-----|------------|
| Dashboard shows aggregate counts only | **Time-series charts** — revenue by day/week/month per event/client |
| No export capability | **CSV/XLSX export** for transactions, refunds, revenue reports |
| No scheduled reports | **Automated email reports** — daily/weekly revenue summaries |
| No settlement reconciliation | **Settlement reconciliation module** — match Razorpay settlements to transactions |
| No conversion funnel | **Checkout funnel tracking** — sessions initiated vs. completed vs. abandoned |
| No per-client analytics | **Per API-client analytics page** — isolated revenue, success rate, refund rate |

---

## 🔁 4. Webhook & Event Pipeline

| Gap | What to Add |
|-----|------------|
| Webhook delivery is fire-and-forget | ✅ **Outbound webhook retry queue [COMPLETED]** — Exponential backoff intervals (3, 9, 27 min, max 4 attempts), dedicated MySQL schema (`outbound_webhook_queue`), atomic row claiming (`CLAIMED`, `PROCESSING`, `DELIVERED`, `PERMANENTLY_FAILED`), CLI/cron background worker (`/vortex/tools/process_webhook_queue.php`), manual retry capability, automated notification escalation on dead-letter, and 35 automated test assertions passing. |
| No delivery receipts | ✅ **Webhook delivery log [COMPLETED]** — Full HTTP status code, response body, latency, error reason, execution timestamp, and retry attempt tracking per dispatch in `outbound_webhook_queue`. |
| Client must poll for status | **Server-Sent Events (SSE) endpoint** for real-time payment status push |
| Only Razorpay events handled | **Internal event bus** — emit events for refunds, client creation, event activation |
| No dead-letter handling | ✅ **Dead-letter queue & Alert Escalation [COMPLETED]** — Failed webhooks after 4 attempts transition to `PERMANENTLY_FAILED` and automatically escalate or trigger critical alerts in the Admin Notification Center. |

---

## 🏗️ 5. Architecture & Code Quality

| Gap | What to Add |
|-----|------------|
| No dependency injection | **DI container** (e.g. PHP-DI or hand-rolled) — inject DB, Logger into classes |
| Database singleton is global state | **Repository pattern** — `TransactionRepository`, `EventRepository`, etc. |
| No service layer separation | **Service layer** between API controllers and repositories |
| No PSR-4 autoloading | Register classes with **Composer PSR-4 autoload** instead of `require_once` chains |
| No caching layer | **Redis/APCu caching** for active events, API client lookups |
| Migrations are manual SQL files | **Database migration runner** (Phinx or custom) with `up`/`down` support |
| `checkout.php` is 845 lines | Break into **controller + view** (Twig or PHP templates) |
| No API versioning | **Versioned API routes** — `/api/v1/`, `/api/v2/` for backwards compatibility |
| Checkout session is 5 min fixed | Make **session TTL configurable per API client** |
| Single payment gateway (Razorpay) | 📋 **Gateway abstraction layer [ARCHITECTED & PLANNED]** — Complete enterprise architecture plan saved in [`GATEWAY_ABSTRACTION_LAYER_PLAN.md`](file:///home/krisb/Desktop/Projects/INTERNATIONAL-CONFERENCE-RAJAGIRI/INTERNATIONAL-CONFERENCE-RAJAGIRI/GATEWAY_ABSTRACTION_LAYER_PLAN.md). Supports plug-and-play adapters for **Razorpay, Stripe, PayU, CCAvenue, and BillDesk** with zero core rewrites. |

---

## 💳 6. Payment Features

| Gap | What to Add |
|-----|------------|
| Only full refunds | **Partial refunds** — allow refunding a subset of the amount |
| No payment links | **Payment link generation** — sharable links without a checkout session |
| No recurring payments | **Subscription / recurring payment plans** per event |
| No multi-currency display | **Currency conversion display** (show INR equivalent for foreign cards) |
| No UPI/link payment method control | **Payment method restrictions** per event (UPI only, card only, etc.) |
| No discount/promo codes | **Coupon/promo code engine** — percentage or fixed discount per event |
| No tax calculation | **GST/tax line item** on checkout and receipts |
| No payment receipt | **Auto-generated PDF receipt** emailed post-payment |

---

## 👤 7. Multi-Tenancy & Client Management

| Gap | What to Add |
| Client management is basic | ✅ **Client onboarding wizard [COMPLETED]** — 4-step guided onboarding in admin (`/vortex/admin/api_clients/onboarding.php`) with business profile, multi-channel alert subscriptions, live signed HMAC-SHA256 webhook test ping + latency & response validation (`test_webhook_ajax.php`), credentials handoff (live/sandbox keys), quickstart sample, and JSON export |
| No client portal | ✅ **Self-service client portal [COMPLETED]** — clients view their own transactions, download reports, view API keys & quickstarts, manage webhooks, rotate secrets, and customize profiles with strict tenant isolation (`/vortex/portal/`) |
| No usage quotas | **Per-client rate limits and quotas** — max transactions/day, max refund % |
| No audit log per client | ✅ **Audit trail [COMPLETED]** — every admin action on a client is logged with who + when, IP, user agent, state diffs, secret redaction, and client portal transparency (`/vortex/admin/audit_logs.php`) |
| No client notifications | ✅ **Client notifications [COMPLETED]** — Real-time Email (Google Accounts SMTP Mailer / STARTTLS) and SMS alerts (mock, Twilio, Fast2SMS) for payment captures, customer refunds, and payment disputes with custom merchant alert preferences & delivery history (`/vortex/portal/settings.php`) |

---

## 🔧 8. DevOps & Operational Readiness

| Gap | What to Add |
|-----|------------|
| No Docker setup | **Dockerfile + docker-compose** for local dev and production |
| No CI/CD pipeline | **GitHub Actions CI** — run test suite on every PR, lint, SAST scan |
| Test suite requires live server manually | **PHPUnit integration** — proper unit + integration tests, mocked HTTP |
| No health check details | Extend `/api/health_check.php` — report DB latency, Razorpay reachability, queue depth |
| No `.env` validation on boot | **Boot-time env validation** — fail fast if required vars are missing |
| No graceful shutdown | **Webhook queue drain** before shutdown |
| Single log file for everything | **Separate log channels** — `payment.log`, `webhook.log`, `admin.log`, `refund.log` |
| No backup automation | **Automated DB backup script** on cron with offsite push (S3/GCS) |

---

## 🛡️ 9. Compliance & Finance

| Gap | What to Add |
|-----|------------|
| No PCI-DSS notes | Document **PCI-DSS scope** — confirm no card data touches the server |
| No data retention policy | **Data retention rules** — auto-purge/archive transactions older than N years |
| No GDPR/data erasure | **Customer data deletion endpoint** — erase PII on request |
| No dispute/chargeback management | **Dispute tracking** — log `DISPUTED` status, track resolution in timeline |
| Refund authorization is binary | **Refund approval workflow** — finance role approves, super admin executes |

---

## 🖥️ 10. Admin UX

| Gap | What to Add |
|-----|------------|
| No dark mode | ✅ **Google Dark Mode & Theme Toggle [COMPLETED]** — Google-coloured dark mode (#202124 canvas, #292a2d surface, #303134 card, #3c4043 borders, #8ab4f8 blue, #81c995 green, #f28b82 red, #fdd663 yellow) with instant anti-FOUC initialization script, interactive topbar sun/moon morph toggle, admin profile preference selection (`/admin/profile.php`), and floating login page toggle (`/admin/login.php`) |
| No global search | ✅ **Global search [COMPLETED]** — Multi-entity search engine (`GlobalSearch.php`), dedicated results dashboard (`/admin/search.php`), live Omnisearch AJAX autocomplete palette (`/admin/search_ajax.php`), query highlighting, status/date filters, and `Ctrl+K` / `/` keyboard shortcuts |
| No bulk actions | **Bulk refund / bulk export** from transaction list |
| No keyboard shortcuts | Power-user keyboard shortcuts |
| Admin profile page is bare | Full **profile management** — avatar, password change, 2FA toggle |
| No notification center | ✅ **In-app notification center [COMPLETED]** — Real-time topbar bell with pulsing unread badge & interactive dropdown palette, dedicated full-page alerts dashboard (`/admin/notifications.php`), and automated event hooks capturing failed webhooks, customer payment disputes, and high-value transactions (> ₹10,000) with category filters, severity tags, and instant mark-as-read (`/admin/notifications_ajax.php`) |

---

> **Priority Order (Quick Wins → Foundation → Features)**
> 1. Rate limiting + IP whitelist (Security)
> 2. Structured JSON logging + Correlation IDs (Observability)
> 3. Webhook retry queue with delivery log (Reliability)
> 4. PSR-4 autoloading + Repository pattern (Architecture)
> 5. RBAC + 2FA (Security)
> 6. CSV export + Time-series charts (Analytics)
> 7. Docker + GitHub Actions CI (DevOps)
> 8. Partial refunds + PDF receipts (Payment Features)
> 9. Client self-service portal (Multi-tenancy)
> 10. Gateway abstraction layer (Future-proofing)
