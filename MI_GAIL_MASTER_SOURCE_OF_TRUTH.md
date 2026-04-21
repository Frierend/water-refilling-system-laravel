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
- Recovery path is explicitly used when a temporary password has expired, including for the `owner` account:
  - Expired temporary-password users are logged out and redirected to `password.request` (Forgot Password)
  - Recovery requires broker reset + standard post-login controls (email verification middleware and MFA middleware where enabled)
  - No permanent bypass route is granted for lifecycle flags
- No backup administrative role is introduced; canonical authority remains `owner`

Evidence:
- `routes/web.php`
- `app/Http/Controllers/Auth/LoginController.php`
- `app/Http/Controllers/Auth/ForcedPasswordChangeController.php`
- `app/Http/Middleware/EnsurePasswordIsChanged.php`
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

### 2.10 Mobile number verification via OTP challenge storage
Implemented with user-level mobile verification fields and dedicated OTP challenge storage:
- `users.mobile_number` and `users.mobile_verified_at`
- `mobile_verification_otps` table with hashed OTP, expiry, attempts, and consumption timestamp
- OTP flow endpoints:
  - `GET /mobile/verify`
  - `POST /mobile/verify/send`
  - `POST /mobile/verify/confirm`
- OTP delivery behavior:
  - security log event is written when OTP is generated
  - local-only OTP preview is displayed in UI when `APP_ENV=local`

Important implementation boundary:
- **Telecom-grade SMS delivery is not enabled in verified code.**
- No real SMS gateway/provider integration (Twilio, Semaphore, etc.) is implemented here.
- OTP presentation is currently local-development oriented and/or audit-log oriented only.

Evidence:
- `database/migrations/2026_04_21_000005_add_mobile_verification_fields_to_users_table.php`
- `database/migrations/2026_04_21_000006_create_mobile_verification_otps_table.php`
- `app/Models/MobileVerificationOtp.php`
- `app/Http/Controllers/Auth/MobileVerificationController.php`
- `resources/views/auth/mobile-verification.blade.php`
- `routes/web.php`

## 3) Audit Logging Taxonomy

### `security` category
Includes:
- authentication failures/lockouts
- blocked login attempts during active lockout windows
- account lifecycle expiry locks (temporary password expired)
- forced-password-change trigger and completion
- forced-password-change failure paths
- forgot/reset password request and result events
- owner account lifecycle actions
- authorization denials
- email verification notice/send/fulfillment
- MFA challenge outcomes and enable/disable actions
- OTP setup lifecycle (issued/expired/verification failure)
- reCAPTCHA-related gate failures

### `system` category
Includes:
- order activity (created/updated/completed/cancelled/walk-in)
- inventory item and stock adjustment activity
- delivery completion/cancellation events
- customer operations (create/update/delete/delete-blocked)

### 3.1 Logging Event Matrix (Controller/Middleware Coverage)

| Category | Event family | Event names (implemented) | Primary implementation points |
|---|---|---|---|
| security | Failed logins / lockouts / blocked locked-period logins | `auth.login.failed`, `auth.account.locked`, `auth.login.locked_blocked` | `app/Http/Controllers/Auth/LoginController.php` |
| security | Lifecycle expiry locks | `security.account.lifecycle.expiry.locked` | `app/Http/Controllers/Auth/LoginController.php`, `app/Http/Controllers/Auth/ForcedPasswordChangeController.php`, `app/Http/Middleware/EnsurePasswordIsChanged.php` |
| security | Forced change trigger/success/failure | `security.forced_password_change.triggered`, `security.password.changed.success`, `security.forced_password_change.failed` | `app/Http/Controllers/Auth/LoginController.php`, `app/Http/Middleware/EnsurePasswordIsChanged.php`, `app/Http/Controllers/Auth/ForcedPasswordChangeController.php` |
| security | Forgot/reset requests and results | `security.password.forgot.requested`, `security.password.forgot.sent`, `security.password.forgot.failed`, `security.password.reset.success`, `security.password.reset.failed` | `app/Http/Controllers/Auth/ForgotPasswordController.php`, `app/Http/Controllers/Auth/ResetPasswordController.php` |
| security | Email verification send/fulfill/notice | `security.email.verification.notice.viewed`, `security.email.verification.sent`, `security.email.verification.fulfilled` | `routes/web.php` |
| security | MFA enable/challenge/disable | `security.mfa.challenge.requested`, `security.mfa.challenge.passed`, `security.mfa.challenge.failed`, `security.mfa.challenge.required`, `security.mfa.enabled`, `security.mfa.disabled`, `security.mfa.disable.failed` | `app/Http/Controllers/Auth/MfaController.php`, `app/Http/Middleware/EnsureMfaIsVerified.php` |
| security | OTP lifecycle | `security.otp.setup.issued`, `security.otp.setup.expired`, `security.otp.setup.verification.failed` | `app/Http/Controllers/Auth/MfaController.php` |
| security | Unauthorized access | `security.authorization.denied` | `app/Http/Middleware/CheckRole.php`, `app/Http/Controllers/OrderController.php`, `app/Http/Controllers/InventoryController.php`, `app/Http/Controllers/DeliveryController.php` |
| system | User creation (operational) | `security.user.created_by_owner`, `security.temporary_password.issued` (security-classified lifecycle events for operational user onboarding) | `app/Http/Controllers/UserManagementController.php` |
| system | Orders | `order.created`, `order.updated`, `order.walkin.created`, `order.completed`, `order.cancelled` | `app/Http/Controllers/OrderController.php` |
| system | Inventory | `inventory.item.created`, `inventory.item.adjusted`, `inventory.item.deleted` | `app/Http/Controllers/InventoryController.php` |
| system | Deliveries | `delivery.completed`, `delivery.cancelled` | `app/Http/Controllers/DeliveryController.php` |
| system | Customer operations | `customer.created`, `customer.updated`, `customer.deleted`, `customer.delete.blocked.has_orders` | `app/Http/Controllers/CustomerController.php` |

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

## 1) Documentation Authority

This is the single authoritative documentation file for the **Mi-Gail Water System**.

All overlapping implementation/audit/security/checklist documentation is deprecated/archive-ready and should be treated as pointer-only material. Use this file as the source for:
- verified implementation status,
- security and account-lifecycle behavior,
- logging taxonomy (`security` vs `system`), and
- known gaps/deferred work.

## 2) Canonical Terminology

- Project: **Mi-Gail Water System**
- Administrative authority: **owner**
- Operational roles: **delivery**, **helper**

Legacy `admin` references in code or tests are compatibility naming only; the effective administrative authority is `owner`.

## 3) Verification Method

Claims below were verified from Laravel routes, controllers/services/middleware, config, migrations, and security feature tests in this repository.

If a claim cannot be verified in code/tests, it is documented as **not implemented** or **operational assumption**.

---

## 4) Verified Security and Account-Lifecycle Controls

### 4.1 Password policy (explicit)

**Implemented and configurable** via `config/security.php`:
- minimum length (default 8),
- uppercase requirement,
- lowercase requirement,
- number requirement,
- symbol requirement.

**Enforced in code paths:**
- forced password change flow (`ForcedPasswordChangeController::passwordRules()`),
- forgot-password reset flow (`ResetPasswordController`, minimum length via `config/security.php`).

Note: reset flow currently enforces configured minimum length; character-class regex enforcement is implemented in forced-change flow.

### 4.2 Lockout policy (explicit)

**Implemented account lockout policy** in `config/auth.php` and `LoginController`:
- `max_attempts` default: 5 failed attempts,
- `lock_minutes` default: 5 minutes,
- tracked with `users.failed_attempts` and `users.locked_until`.

Behavior:
- failed attempts increment per known account,
- account is blocked while `locked_until` is in the future,
- counters reset after successful login or expired lock window.

### 4.3 Lifecycle expiry policy (explicit)

**Implemented temporary-password lifecycle model**:
- owner-created users are issued temporary passwords,
- account flagged `must_change_password = true`,
- expiry timestamp stored in `temp_password_expires_at`,
- expiry window configured by `security.temporary_password.expires_hours` (default 24h),
- expired temporary-password users are denied login and told to contact owner.

### 4.4 Recovery model (explicit)

**Implemented recovery path:** Laravel broker-based email reset:
- `GET /forgot-password`, `POST /forgot-password`,
- `GET /reset-password/{token}`, `POST /reset-password`.

On successful reset:
- password updated,
- remember token rotated,
- lifecycle flags cleared (`must_change_password = false`, `temp_password_expires_at = null`, `password_changed_at = now()`).

### 4.5 SMTP setup (explicit)

Email-dependent flows (verification + reset links) rely on Laravel mail config:
- default mailer is SMTP,
- SMTP host/port/encryption/credentials are environment-driven.

**Operational requirement:** production `.env` must provide valid mail transport values (`MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_*`).

### 4.6 Email verification (explicit)

**Implemented Laravel verification flow**:
- `User` implements `MustVerifyEmail`,
- verification notice route,
- signed/throttled verify endpoint,
- resend verification endpoint,
- owner-created users are sent verification notifications.

Protected business routes are gated by `verified` middleware.

### 4.7 MFA (explicit)

**Implemented TOTP MFA (Google Authenticator compatible)**:
- setup route generates secret + provisioning URI (`otpauth://...`),
- challenge verifies 6-digit TOTP,
- enabled state stored per user (`mfa_enabled`, encrypted `mfa_secret`),
- `mfa` middleware blocks protected routes until challenge is passed for session,
- disable flow requires current password.

### 4.8 OTP/mobile behavior (explicit)

**Implemented OTP behavior:** app-based 6-digit TOTP for authenticator applications (e.g., Google Authenticator).

**Not implemented:** SMS OTP, phone-number delivery OTP, or mobile carrier-based verification flows. No mobile-number model fields or SMS provider integration are present.

### 4.9 reCAPTCHA (related login anti-abuse)

Login supports optional Google reCAPTCHA token verification:
- enabled via `services.recaptcha.enabled`,
- server-side verification via configured Google verify URL.

If disabled, login proceeds without captcha enforcement.

---

## 5) Audit Logging Taxonomy (`security` vs `system`) (explicit)

### 5.1 Channel design

`config/logging.php` defines:
- `security` daily log file (`storage/logs/security.log`, retention env-configurable),
- `system` daily log file (`storage/logs/system.log`, retention env-configurable),
- `audit` stack channel combining both for compatibility.

### 5.2 Security-log category

`security` log events include (verified examples):
- failed logins and lockouts,
- blocked logins for locked accounts,
- forced-password-change trigger and completion,
- owner user creation and temporary-password issuance metadata,
- authorization denials,
- MFA challenge failures/success and enable/disable actions.

### 5.3 System-log category

`system` log events include (verified examples):
- order create/update/walk-in/complete/cancel,
- inventory item create/delete/adjust,
- delivery complete/cancel.

---

## 6) Backup and Retention (explicit)

### 6.1 Verified in-code retention controls

- Log-file retention is explicitly configured on daily channels:
  - `SECURITY_LOG_DAYS` (default 30),
  - `SYSTEM_LOG_DAYS` (default 30),
  - default Laravel daily log channel retention is 14 days.

### 6.2 Backup status

- **No dedicated database/file backup scheduler or restoration automation is present in this repository code.**
- Backup cadence, media, encryption-at-rest, offsite copy, and restore testing are deployment/operations responsibilities and should be handled outside this codebase unless future code adds them.

---

## 7) Incident Response (explicit)

### 7.1 In-code incident-support capabilities

Implemented support signals:
- structured security events in `security` log channel,
- account lockout controls,
- forced password reset/change and verification paths,
- authorization-denial logging.

### 7.2 Documented minimum response model

For this repository scope, minimum process is:
1. Detect suspicious activity from `security` logs.
2. Contain affected accounts (owner action: reset credentials, disable MFA if recovery needed, rotate secrets as applicable).
3. Eradicate root cause (credential/config/access issue).
4. Recover service (verify auth and core business flows).
5. Record post-incident findings and corrective actions.

**Gap:** No separate automated incident runbook system exists in-code.

---

## 8) Local Presentation Assumptions (explicit)

The application is implemented as a **server-rendered web UI** (Blade templates), not a native mobile app.

Assumptions for local/dev presentation:
- users access via browser sessions,
- MFA OTP entry occurs on web forms,
- email verification/reset links are consumed in browser,
- role-specific navigation/redirects are web-route based.

No in-code assumptions for native mobile push, SMS inbox parsing, or mobile-specific OTP UX are implemented.

---

## 15) Local Owner Identity + SMTP Setup (Gmail App Password)

This section is the canonical local setup for account lifecycle and SMTP email flows (verification + password reset), verified against:
- `config/security.php` (`OWNER_EMAIL`, optional `OWNER_NAME`)
- `config/mail.php` (`MAIL_*`, fallback usage with owner identity)
- Auth flows that surface owner-contact guidance when temporary passwords expire

### 15.1 `.env` keys to set locally

Edit your local `.env` (not `.env.example`) and set:

```dotenv
OWNER_EMAIL=owner@example.com
OWNER_NAME="Mi-Gail Owner"

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_gmail_address@gmail.com
MAIL_PASSWORD=your_16_char_gmail_app_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your_gmail_address@gmail.com
MAIL_FROM_NAME="Mi-Gail Water System"
```

Notes:
- `OWNER_NAME` is optional; leave blank if you only want email-based owner contact.
- Keep placeholder values in `.env.example`; never commit real credentials.

### 15.2 Step-by-step Gmail app-password setup (local development)

1. Sign in to the Gmail account you will use as SMTP sender (`MAIL_USERNAME` / `MAIL_FROM_ADDRESS`).
2. Enable Google 2-Step Verification on that account (required before app passwords are available).
3. Open **Google Account → Security → App passwords**.
4. Create an app password (choose **Mail** + your device, or a custom label like `Mi-Gail Local SMTP`).
5. Copy the generated 16-character password immediately (Google only shows it once).
6. Paste that value into `MAIL_PASSWORD` in your local `.env`.
7. Set `MAIL_ENCRYPTION=tls` and `MAIL_PORT=587` for Gmail SMTP submission.
8. Run `php artisan config:clear` after editing `.env` so Laravel reloads settings.
9. Test with email verification or forgot-password flow from a local account.

### 15.3 Security guardrails

- Do not commit `.env`.
- Do not replace placeholders in `.env.example` with real secrets.
- Rotate the Gmail app password immediately if it is exposed.

---

## 15) Consolidation / Archival Guidance for Legacy Docs

Security tests currently present and aligned to implemented behavior include:
- owner-only user creation, lifecycle flags, and secure logging expectations,
- forced password change enforcement and lifecycle handling,
- login lockout increment/block/reset behavior,
- additional report/export validation tests.

Coverage confirms key policies above, but not all possible flows (e.g., full end-to-end SMTP delivery success in external infrastructure).

---

## 10) Deprecated / Archive-Ready Documentation Index

The following files are deprecated/archive-ready and now pointer-only:
- `AUDIT_REPORT.md`
- `IMPLEMENTATION_CHECKLIST.md`
- `SECURITY_DOCUMENTATION.md`

Where conflicts existed, this file preserved code-verified behavior and marked discrepancies.
