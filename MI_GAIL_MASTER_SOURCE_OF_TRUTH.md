# Mi-Gail Water System — Master Source of Truth

Last verified against repository code and tests: **April 21, 2026**.

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

## 9) Tests: Verified Coverage Snapshot

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

Any overlapping security/audit/checklist/project-status sections in other docs (including `README.md`) are secondary context only; this master file is authoritative.

