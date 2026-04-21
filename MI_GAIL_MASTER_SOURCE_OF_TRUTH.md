# Mi-Gail Water System — Master Source of Truth

Last verified against repository code: **April 21, 2026**.

## 1) Authority
This is the single authoritative documentation file for project/evaluator/security status in this repository.

## 2) Verified Final Account-Security Scope (Implemented)

### 2.1 Owner-only user creation with temporary password
Implemented via owner-restricted routes and controller logic:
- `GET /users/create`, `POST /users` with `role:owner`
- Assignable roles restricted to `delivery` and `helper`
- Temporary password generated server-side and stored hashed
- New accounts flagged for forced password change (`must_change_password`, `temp_password_expires_at`)

Evidence:
- `routes/web.php`
- `app/Http/Controllers/UserManagementController.php`
- `app/Http/Middleware/CheckRole.php`
- `database/migrations/2026_04_21_000003_add_account_lifecycle_fields_to_users_table.php`

### 2.2 Password complexity enforcement
Implemented in both:
- Forced first-login password change flow
- Forgot-password reset flow (`password.update`)

Complexity policy source:
- `config/security.php`

Evidence:
- `app/Http/Controllers/Auth/ForcedPasswordChangeController.php`
- `app/Http/Controllers/Auth/ResetPasswordController.php`
- `app/Http/Middleware/EnsurePasswordIsChanged.php`

### 2.3 Forced first-login password change
Implemented and enforced before normal module access.

Evidence:
- `app/Http/Controllers/Auth/LoginController.php`
- `app/Http/Middleware/EnsurePasswordIsChanged.php`
- `routes/web.php`

### 2.4 Email verification (SMTP-based Laravel flow)
Implemented with verification notice, signed verification endpoint, and resend endpoint.
Owner-created users are sent verification notifications.

Evidence:
- `routes/web.php`
- `app/Models/User.php` (`MustVerifyEmail`)
- `app/Http/Controllers/UserManagementController.php`
- `resources/views/auth/verify-email.blade.php`
- `database/migrations/2026_04_21_000004_add_email_verification_and_mfa_fields_to_users_table.php`

### 2.5 Forgot-password via SMTP
Implemented using Laravel password broker routes + controllers + views:
- `/forgot-password`
- `/reset-password/{token}`
- `POST /reset-password`

Evidence:
- `routes/web.php`
- `app/Http/Controllers/Auth/ForgotPasswordController.php`
- `app/Http/Controllers/Auth/ResetPasswordController.php`
- `resources/views/auth/forgot-password.blade.php`
- `resources/views/auth/reset-password.blade.php`

### 2.6 Login attempt limiting and account locking
Implemented account lock behavior with:
- `failed_attempts`
- `locked_until`
- configurable thresholds (`config/auth.php`)

Evidence:
- `app/Http/Controllers/Auth/LoginController.php`
- `config/auth.php`
- `database/migrations/2026_04_21_000002_add_login_lock_fields_to_users_table.php`

### 2.7 reCAPTCHA checkbox on login
Implemented with server-side verification and optional enable flag:
- Login form has checkbox widget when enabled
- Backend verifies Google response token

Evidence:
- `resources/views/auth/login.blade.php`
- `app/Services/RecaptchaService.php`
- `app/Http/Controllers/Auth/LoginController.php`
- `config/services.php`

### 2.8 MFA with Google Authenticator (TOTP)
Implemented with setup + challenge + verify + disable flow:
- Secret generation and provisioning URI
- TOTP code validation
- Challenge gating middleware for MFA-enabled users

Evidence:
- `app/Services/TotpService.php`
- `app/Http/Controllers/Auth/MfaController.php`
- `app/Http/Middleware/EnsureMfaIsVerified.php`
- `routes/web.php`
- `resources/views/auth/mfa-setup.blade.php`
- `resources/views/auth/mfa-challenge.blade.php`
- `database/migrations/2026_04_21_000004_add_email_verification_and_mfa_fields_to_users_table.php`

### 2.9 Split audit logs into `security` and `system`
Implemented log channels:
- `security` (auth, lifecycle, authorization/MFA/reCAPTCHA-sensitive events)
- `system` (orders, inventory, deliveries)
- `audit` retained as stack compatibility channel

Evidence:
- `config/logging.php`
- `app/Http/Controllers/Auth/*`
- `app/Http/Controllers/UserManagementController.php`
- `app/Http/Middleware/CheckRole.php`
- `app/Http/Controllers/OrderController.php`
- `app/Http/Controllers/InventoryController.php`
- `app/Http/Controllers/DeliveryController.php`

## 3) Audit Logging Taxonomy

### `security` category
Includes:
- authentication failures/lockouts
- forced-password-change trigger and completion
- owner account lifecycle actions
- authorization denials
- MFA challenge outcomes and enable/disable actions
- reCAPTCHA-related gate failures

### `system` category
Includes:
- order activity (created/updated/completed/cancelled/walk-in)
- inventory item and stock adjustment activity
- delivery completion/cancellation events

## 4) Access Control Terminology (Canonical)
- Project: **Mi-Gail Water System**
- Administrative authority: **owner**
- Operational roles: **delivery**, **helper**

Legacy `admin` references in code are compatibility checks; effective administrative authority remains `owner`.

## 5) Security Middleware and Route Protection
Protected business routes are enforced with middleware chain:
- `auth`
- `password.changed`
- `verified`
- `mfa`

Security-specific flows remain reachable where appropriate:
- forced password change routes
- email verification notice/verify/resend
- MFA challenge/verification

Evidence:
- `routes/web.php`
- `app/Http/Kernel.php`

## 6) Verification Evidence
Security tests present under:
> Canonical project documentation for the **Mi-Gail Water System**.  
> Date of verification (static repository inspection): **April 21, 2026**.

---

## 1) Document Authority and Scope

This file is the single authoritative documentation source for this repository. It consolidates and supersedes overlapping project/evaluator documentation content from:

- `README.md`
- `AUDIT_REPORT.md`
- `IMPLEMENTATION_CHECKLIST.md`
- `SECURITY_DOCUMENTATION.md`
- `SECURITY.md` (where applicable)
- `Project Criteria.docx` (rubric)
- `Final Docs (IT12).docx` (project documentation template/basis)

If any of the above documents conflict with verified code behavior, this master file prefers **verified code** and records the mismatch.

---

## 2) Project Overview

- **Project name:** Mi-Gail Water System
- **Domain:** Sales, delivery, inventory, customer management, and reporting for a water-refilling business
- **Framework:** Laravel 10 (`laravel/framework` `^10.10` via `composer.json`)
- **Language/runtime:** PHP 8.1+
- **Core modules:**
  - Authentication and account lifecycle security
  - Dashboard
  - Customers
  - Orders (including walk-in)
  - Deliveries
  - Inventory + inventory transactions
  - Reports + exports

---

## 3) System Description (Verified)

### 3.1 Route map (web)
Verified from `routes/web.php`:

- Public:
  - `GET /` welcome
  - `GET /login`, `POST /login`, `POST /logout`
- Protected group with `auth` + `password.changed` middleware:
  - Forced password change routes: `GET/POST /force-password-change`
  - Owner-only user creation: `GET /users/create`, `POST /users`
  - Dashboard: `GET /dashboard`
  - Customers: resource routes + `GET /api/customers/search`
  - Orders: resource routes, walk-in routes, complete/cancel routes
  - Deliveries: list/show/complete/cancel routes
  - Inventory: CRUD + adjust + low-stock + export
  - Reports: sales/delivery/customer/inventory + export endpoints

### 3.2 Middleware and request-security chain
Verified from `app/Http/Kernel.php` and middleware classes:

- Global middleware includes:
  - `SecurityHeaders` (custom)
  - CORS handler
  - CSRF in web group
- Custom aliases:
  - `role` → `CheckRole`
  - `password.changed` → `EnsurePasswordIsChanged`

### 3.3 Primary business entities
Verified from models + migrations:

- `users`: role-based accounts (`owner`, `delivery`, `helper`)
- `customers`
- `orders`
- `inventory_items`
- `inventory_transactions`
- Laravel defaults: `password_reset_tokens`, `failed_jobs`, `personal_access_tokens`

### 3.4 Business process implementation status

Implemented in code:
- Sales order capture and management (delivery + walk-in)
- Delivery workflow (`pending/completed/cancelled`)
- Inventory deductions/additions through transaction records
- Customer CRUD with search/filter/sort
- Report pages (sales, delivery, customer, inventory)
- CSV and selected PDF exports

Partially/Deferred:
- Full password-reset flow (token/email routes not implemented)
- MFA, CAPTCHA, and email-verification enforcement not implemented
- Dedicated split of audit channel into `security` and `system` channels not implemented (currently single `audit` channel)

---

## 4) Platform and Technologies Used

Verified from `composer.json`, `package.json`, and code:

- Backend: Laravel 10, Eloquent ORM, Blade templates
- DB: MySQL/MariaDB-oriented migrations; SQLite guarded handling in enum migration
- Frontend: Blade + Bootstrap + Chart.js + Vite
- Export/PDF: `barryvdh/laravel-dompdf`
- Auth/session: Laravel session auth with custom login/account-lifecycle logic
- Tests: PHPUnit feature tests under `tests/Feature/Security/*`

---

## 5) Access Control / RBAC

### 5.1 Role model (authoritative terminology)

- **Administrative authority:** `owner`
- **Operational roles:** `delivery`, `helper`

### 5.2 Legacy `admin` references

Legacy `admin` references still appear in code (e.g., middleware role strings and `isAdmin()` helper). In this codebase, `isAdmin()` resolves to `isOwner()`, so `owner` is the effective administrative authority.

### 5.3 Enforcement examples

- Owner-only user creation routes use `role:owner`
- Reports use `role:owner,admin` (legacy compatibility in middleware argument)
- Inventory/customer restrictions deny certain operations for `delivery`

---

## 6) Security Policies and Controls (Verified)

### 6.1 Authentication and lockout

Verified from `LoginController`, `config/auth.php`, migrations:
- Login lockout controls:
  - `max_attempts` (default 5)
  - `lock_minutes` (default 5)
- Tracks and enforces:
  - `failed_attempts`
  - `locked_until`

### 6.2 Account lifecycle / forced password change

Verified from `UserManagementController`, `ForcedPasswordChangeController`, `EnsurePasswordIsChanged`, `config/security.php`:
- Owner creates `delivery`/`helper` user with generated temporary password
- Temporary password is stored hashed; shown once via session flash
- New user flagged with:
  - `must_change_password = true`
  - `temp_password_expires_at`
- Flagged users are redirected to forced-change flow
- Password policy driven by config:
  - min length, upper/lower/number/symbol requirements
- Reuse prevention: new password cannot equal current hash

### 6.3 Header hardening

Verified from `SecurityHeaders` middleware:
- `X-Frame-Options: SAMEORIGIN`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy` restrictions
- Conditional HSTS on secure requests

### 6.4 Input validation / allowlisting

Controllers consistently validate critical inputs for:
- login/authentication paths
- account creation + password change
- report filters (period/status/driver/type/per_page)
- export format allowlists
- order/inventory/customer mutation inputs

### 6.5 Transaction safety

Write-heavy operations in orders and inventory are transaction-wrapped with rollback paths.

---

## 7) Incident Response Plan (Current + Operational Guidance)

### 7.1 Current in-code incident-support capabilities

- Structured audit events in `audit` channel (`storage/logs/audit*.log`)
- Account lockout telemetry (`auth.login.failed`, `auth.account.locked`, `auth.login.locked_blocked`)
- Account lifecycle events (`security.*` events)
- Business operation events for order/delivery/inventory actions

### 7.2 Minimum incident response procedure (documented process)

1. **Detect:** Monitor `audit` channel for high-risk patterns (repeated failed logins, lockouts, unauthorized attempts).
2. **Triage:** Identify actor, IP, user-agent, timestamp, and affected account/module.
3. **Contain:** Disable/rotate affected credentials, force password reset by owner workflow when needed.
4. **Eradicate/Recover:** Fix root cause (misconfiguration/access issue), verify normal login/business operations.
5. **Post-incident review:** Record timeline, root cause, corrective action, and prevention tasks.

### 7.3 Gaps / deferred IR enhancements

- No documented automated alerting pipeline (e.g., SIEM/webhook) in repository.
- No dedicated incident runbook file beyond this master documentation.

---

## 8) Audit Logging (Required Categories)

The project must document two categories: **`security`** and **`system`**.

### 8.1 Current implementation state

- Logging channel configured in `config/logging.php`: **single `audit` daily channel**.
- Event naming inside this channel already naturally separates categories by prefix.

### 8.2 Category map (authoritative)

#### A) `security` audit events (implemented in event naming)

Includes, and verified in controllers:
- authentication attempts and failures (`auth.login.failed`)
- lockout events (`auth.account.locked`, `auth.login.locked_blocked`)
- forced password change trigger (`security.forced_password_change.triggered`)
- password change success (`security.password.changed.success`)
- owner-created account lifecycle events (`security.user.created_by_owner`, `security.temporary_password.issued`)

Unauthorized-access attempts:
- Unauthorized role access currently redirects/aborts, but explicit structured unauthorized-attempt logging is limited and should be expanded.

#### B) `system` audit events (implemented in event naming)

Includes, and verified in controllers:
- order activity (`order.created`, `order.updated`, `order.completed`, `order.cancelled`, `order.walkin.created`)
- inventory changes (`inventory.item.created`, `inventory.item.adjusted`, `inventory.item.deleted`)
- delivery updates (`delivery.completed`, `delivery.cancelled`)
- customer operational events: customer CRUD exists; explicit customer audit events are limited and can be expanded.

### 8.3 Required improvement (deferred)

Implement dedicated channels (or stacks) for `security` and `system` while preserving current event taxonomy for backward compatibility.

---

## 9) Verification and Testing Evidence

### 9.1 Verified test coverage (from repository tests)

Security-focused feature tests are present for:
- login lockout behavior
- forced password change enforcement and completion
- owner-only user creation and lifecycle controls
- report input validation
- export endpoint behavior

Files:
- `tests/Feature/Security/LoginLockoutTest.php`
- `tests/Feature/Security/ForcedPasswordChangeEnforcementTest.php`
- `tests/Feature/Security/AdminUserCreationTest.php`
- `tests/Feature/Security/ReportInputValidationTest.php`
- `tests/Feature/Security/ExportEndpointsTest.php`

## 7) Deferred / Known Limits
- External dependency correctness (SMTP, Google reCAPTCHA endpoints) depends on deployment env variables and network reachability.
- MFA UX currently exposes provisioning URI + secret text; teams may optionally add in-app QR image rendering.

## 8) Legacy Docs Status
The following files remain as legacy/deprecated pointers and should not override this document:
- `AUDIT_REPORT.md`
- `IMPLEMENTATION_CHECKLIST.md`
- `SECURITY_DOCUMENTATION.md`
- `README.md` (quick entry only)
### 9.2 Evidence provenance note

Prior docs claim `php artisan test` and migration preflight passes on **April 21, 2026**. In this update cycle, documentation was verified by static code inspection; no new runtime execution evidence is added here.

---

## 10) Criteria Alignment (Rubric-Based)

Based on `Project Criteria.docx` and verified code evidence:

1. **Secure coding practices:** generally present (env-driven configs, validation, no plaintext password storage in DB).
2. **Authentication system:** implemented with hashing and lockout; MFA/CAPTCHA not implemented.
3. **Authorization/RBAC:** implemented with role middleware and role helpers; legacy `admin` compatibility needs cleanup.
4. **Data protection:** hashing for passwords; TLS/HSTS depends on deployment transport and secure requests.
5. **Input validation:** implemented on critical controllers/routes.
6. **Audit/accountability:** strong event coverage, but channel split (`security` vs `system`) still pending.
7. **HTTP hardening:** security headers middleware enabled globally.
8. **CORS hardening:** explicit allowlist-based config present.
9. **Automated security verification:** targeted feature tests included.
10. **Migration safety:** enum migration includes DB-driver guards and idempotent behavior.

---

## 11) Code-vs-Documentation Mismatches

1. **Role naming mismatch (legacy admin references):**
   - Schema roles are `owner`, `delivery`, `helper`.
   - Some checks/middleware still reference `admin` for compatibility.
   - Canonical administrative term is **owner**.

2. **Inventory `empty` type evolution:**
   - Base inventory migration initially lacked `empty` enum value.
   - Later migration adds `empty` with driver-safe/idempotent approach.
   - Code uses `empty` item logic in order flows.

3. **Audit channel taxonomy:**
   - Documentation requirement asks `security` and `system` categories.
   - Code currently logs to one `audit` channel with category-like event names.

4. **Feature claims from historical docs:**
   - Some historical docs mention future/deferred controls; this master file marks deferred items explicitly as **not implemented**.

---

## 12) Deferred Items

Not implemented in verified code:

- Forgot-password broker/email reset flow
- Email verification enforcement
- CAPTCHA (e.g., reCAPTCHA)
- MFA
- Dedicated split log channels for `security` and `system`
- Broader structured logging of unauthorized-access attempts and customer CRUD operations

---

## 13) Known Limitations

- Test suite includes strong security-focused feature coverage but no broad integration matrix for all DB-engine edge cases.
- Legacy compatibility references to `admin` can cause conceptual confusion for maintainers/evaluators.
- Some capabilities are present in workflow but not yet fully normalized in policy/log channel separation.

---

## 14) Evidence Pointers (Quick Index)

- Routes: `routes/web.php`
- Kernel/middleware: `app/Http/Kernel.php`, `app/Http/Middleware/*`
- Auth/account lifecycle:
  - `app/Http/Controllers/Auth/LoginController.php`
  - `app/Http/Controllers/Auth/ForcedPasswordChangeController.php`
  - `app/Http/Controllers/UserManagementController.php`
  - `app/Http/Middleware/EnsurePasswordIsChanged.php`
  - `config/security.php`, `config/auth.php`
- RBAC and role model: `app/Http/Middleware/CheckRole.php`, `app/Models/User.php`
- Business modules:
  - `app/Http/Controllers/OrderController.php`
  - `app/Http/Controllers/DeliveryController.php`
  - `app/Http/Controllers/InventoryController.php`
  - `app/Http/Controllers/CustomerController.php`
  - `app/Http/Controllers/ReportController.php`
- Logging config: `config/logging.php`
- Schema/migrations:
  - `database/migrations/2025_05_01_140313_create_users_table.php`
  - `database/migrations/2026_04_21_000002_add_login_lock_fields_to_users_table.php`
  - `database/migrations/2026_04_21_000003_add_account_lifecycle_fields_to_users_table.php`
  - `database/migrations/2025_05_01_140315_create_inventory_items_table.php`
  - `database/migrations/2026_04_21_000001_add_empty_type_to_inventory_items_enum.php`
- Security verification tests:
  - `tests/Feature/Security/LoginLockoutTest.php`
  - `tests/Feature/Security/ForcedPasswordChangeEnforcementTest.php`
  - `tests/Feature/Security/AdminUserCreationTest.php`
  - `tests/Feature/Security/ReportInputValidationTest.php`
  - `tests/Feature/Security/ExportEndpointsTest.php`

---

## 15) Consolidation / Archival Guidance for Legacy Docs

Recommended post-consolidation document status:

- `AUDIT_REPORT.md` → **Deprecated** (replace with pointer to this master file)
- `IMPLEMENTATION_CHECKLIST.md` → **Deprecated** (pointer)
- `SECURITY_DOCUMENTATION.md` → **Deprecated** (pointer)
- `README.md` → **Keep concise** with quickstart + pointer to this master file

---

## 16) Consolidation Summary (What was merged and what was verified)

Merged from existing docs:
- Prior audit conclusions and criteria mapping
- Security and implementation checklist status items
- Project/business context summary

Re-verified from code:
- Route/middleware protections
- Role and lifecycle enforcement
- Logging configuration + event emission points
- Migration-backed fields and role enum schema
- Security-focused test presence and scope

Where conflicts existed, this file preserved code-verified behavior and marked discrepancies.


---

## 17) Local Backup and Retention Automation (Verified)

### 17.1 Implemented Artisan commands

The project now includes dedicated local backup commands:

- `php artisan backup:run-local`
  - Creates a timestamped ZIP archive under `storage/app/backups`
  - Includes a database dump (`database.sql` for MySQL/PostgreSQL; SQLite file copy when using SQLite)
  - Includes critical logs matched by patterns:
    - `security*.log`
    - `system*.log`
    - `laravel*.log`
  - Supports optional storage artifact inclusion via either:
    - `--include-storage` flag, or
    - `LOCAL_BACKUP_INCLUDE_STORAGE=true`
  - Generates archive checksum file (`.sha256`) and a manifest with per-file SHA-256 entries

- `php artisan backup:prune-local --days=7`
  - Deletes `.zip` and `.zip.sha256` backup artifacts older than the specified retention period
  - Cleans stale temporary backup working directories

### 17.2 Storage path and naming

- Root backup path: `storage/app/backups` (configurable with `LOCAL_BACKUP_PATH`)
- Archive naming: `backup_YYYYmmdd_HHMMSS.zip`
- Checksum naming: `backup_YYYYmmdd_HHMMSS.zip.sha256`

### 17.3 Scheduler configuration (app/Console/Kernel.php)

Configured schedule:

- `backup:run-local` every 15 minutes
- `backup:prune-local --days=7` daily at `01:00`

### 17.4 Windows/XAMPP/Laragon scheduler setup

Use Windows Task Scheduler to run Laravel's scheduler continuously through `schedule:run`.

1. Open **Task Scheduler** → **Create Task...**
2. **General** tab:
   - Name: `Mi-Gail Laravel Scheduler`
   - Choose **Run whether user is logged on or not**
3. **Triggers** tab:
   - New trigger: **Daily**
   - Repeat task every: **1 minute**
   - Duration: **Indefinitely**
4. **Actions** tab:
   - Action: **Start a program**
   - Program/script: path to PHP executable
     - Example (XAMPP): `C:\xampp\php\php.exe`
     - Example (Laragon): `C:\laragon\bin\php\php-8.x.x\php.exe`
   - Add arguments:
     - `artisan schedule:run`
   - Start in:
     - `<project-path>` (example: `C:\xampp\htdocs\water-refilling-system-laravel`)
5. **Conditions** tab:
   - Uncheck **Start the task only if the computer is on AC power** (optional for laptops)
6. **Settings** tab:
   - Enable **Allow task to be run on demand**
   - Enable **If the task fails, restart every** (recommended)

#### Optional direct tasks (fallback)

If Task Scheduler cannot run every minute in a given environment, create explicit recurring tasks for:

- `php artisan backup:run-local` (every 15 minutes)
- `php artisan backup:prune-local --days=7` (daily)

Preferred approach remains `schedule:run` every minute so all schedule definitions stay centralized in `app/Console/Kernel.php`.

### 17.5 Backup-related env/config knobs

- `LOCAL_BACKUP_PATH`
- `LOCAL_BACKUP_RETENTION_DAYS`
- `LOCAL_BACKUP_INCLUDE_STORAGE`
- `LOCAL_BACKUP_STORAGE_PATHS` (comma-separated paths under `storage/app`)
- `LOCAL_BACKUP_DUMP_TIMEOUT_SECONDS`
- `BACKUP_MYSQLDUMP_BINARY`
- `BACKUP_PG_DUMP_BINARY`

