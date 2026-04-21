# Mi-Gail Water System — Master Source of Truth

Last verified against repository code: **April 21, 2026 (UTC)**.

## 1) Authority and Terminology

This file is the single authoritative documentation source for this repository.

Canonical role model:
- Administrative authority: **owner**
- Operational roles: **delivery**, **helper**

If `admin` appears in legacy names (for example `AdminUserCreationTest`), it is treated as legacy naming; the verified runtime authority model is `owner` + `delivery` + `helper`.

---

## 2) Verified Implementation Matrix (A–J)

Legend:
- **Implemented** = verified in routes/controllers/config/migrations, with tests where present.
- **Partial** = core code exists, but there is a verified gap or mismatch.

### A. Owner-only user creation with temporary password lifecycle
**Status:** Implemented.

Verified:
- Owner-only create/store user routes (`role:owner`) exist.
- Owner can assign only `delivery` and `helper` in controller validation.
- Temporary password is generated/accepted, hashed, expiry timestamped, and `must_change_password` is set.
- Security audit events are emitted for owner-created users and temp-password issuance.
- Migration fields exist: `must_change_password`, `password_changed_at`, `temp_password_expires_at`, lifecycle lock fields.
- Tests cover owner access restrictions and lifecycle field behavior.

### B. Password policy enforcement
**Status:** Implemented.

Verified:
- Central policy is defined in `config/security.php`.
- Forced change and reset flows use `PasswordPolicy::rules()`.
- Tests verify reset enforces configured minimum length and successful reset clears lifecycle flags.

### C. Forced first-login password change + lifecycle lock behavior
**Status:** Implemented.

Verified:
- Post-login redirect to forced change when `must_change_password` is true.
- Middleware blocks normal access until forced change is completed.
- Expired temporary passwords trigger lifecycle lock and redirect to forgot-password recovery.
- Tests cover redirect behavior, expired temp-password handling, and lifecycle lock messaging.

### D. Forgot-password and reset-password recovery
**Status:** Implemented.

Verified:
- Guest routes for forgot/reset endpoints exist.
- Forgot flow uses Laravel broker reset-link send.
- Reset flow updates password, rotates remember token, clears lifecycle lock and temporary-password flags.
- Tests cover forgot status response and lifecycle reset behavior.

### E. Email verification flow (SMTP-dependent)
**Status:** Implemented.

Verified:
- `User` implements `MustVerifyEmail`.
- Verification notice, signed verify endpoint, and resend endpoint exist.
- Verified middleware is part of protected business route chain.
- Owner-created users are sent verification notification when created.
- Tests cover verified-middleware redirect for unverified users.

### F. Login lockout controls
**Status:** Implemented.

Verified:
- Configurable thresholds (`auth.login_lockout.max_attempts`, `lock_minutes`).
- Failed attempts and lock window persisted in `users.failed_attempts` and `users.locked_until`.
- Security events logged for failures and lockouts.
- Migration adds lockout fields; tests verify increment/lock/reset behavior.

### G. reCAPTCHA on login
**Status:** Implemented (feature-toggle dependent).

Verified:
- Login view posts `g-recaptcha-response`.
- Login controller invokes `RecaptchaService` before auth attempt.
- Service returns allow-all when `RECAPTCHA_ENABLED=false`; verifies token via Google endpoint when enabled.

Conflict note:
- No dedicated feature test was found that asserts reCAPTCHA failure/success paths.

### H. MFA (TOTP) challenge/setup/disable
**Status:** Implemented.

Verified:
- Setup, enable, challenge, verify, and disable routes/controllers exist.
- `mfa` middleware blocks guarded routes unless challenge is passed.
- TOTP secret stored encrypted; session tracks challenge pass.
- Migration adds `mfa_secret` and `mfa_enabled`.
- Tests cover MFA lifecycle and challenge gating behavior.

### I. Audit log categories (`security`, `system`)
**Status:** Implemented.

Verified:
- `logging.php` defines `security`, `system`, and `audit` (stack) channels.
- Security-sensitive controllers/middleware log to `security`.
- Operational controllers (orders/inventory/delivery/customer) log to `system`.
- Tests include security/system log emission checks.

### J. Mobile verification OTP lifecycle
**Status:** Partial (implemented in runtime code; one stale test remains).

Verified runtime implementation:
- Routes exist: `mobile.verification.notice`, `.send`, `.verify`.
- OTP records persisted in `mobile_verification_otps` with hash, attempts, expiry, consumed timestamp.
- User fields `mobile_number`, `mobile_verified_at` are persisted on successful verification.
- Migration and model support present; feature tests validate send/verify/wrong-OTP flows.

Conflict explicitly recorded:
- `tests/Feature/Security/OtpMobileVerificationLifecycleTest.php` still states mobile OTP routes are not implemented and marks itself skipped using old route names (`mobile.otp.send`, `mobile.otp.verify`). This test is inconsistent with current routes and current implementation.

---

## 3) SMTP Status and Boundaries

### 3.1 App logic correctness (verified in code)
- Mailer defaults to SMTP in `config/mail.php`.
- Forgot-password and reset use Laravel password broker mail flow.
- Email verification notice/send/fulfill routes are wired.
- Owner-created users call `sendEmailVerificationNotification()`.

Conclusion: application mail-dependent logic is wired correctly for Laravel SMTP usage.

### 3.2 PHP/OpenSSL/CA-bundle environment issues (operational risk outside app logic)
These issues are environment/runtime concerns, not route/controller correctness:
- PHP OpenSSL extension missing/disabled.
- Outdated CA trust store causing TLS certificate validation failures.
- Local machine clock skew causing TLS handshake/certificate validity issues.

When these occur, SMTP failures can happen even when code and `.env` values are correct.

### 3.3 Gmail-provider constraints (external provider behavior)
When using Gmail SMTP (`smtp.gmail.com`):
- Standard account password is not sufficient; app password is required.
- 2-step verification must be enabled in the Gmail account.
- Provider-side anti-abuse/rate policies can block or defer sends.

Conclusion: Gmail-specific failures are provider-constraint failures, separate from app logic correctness.

---

## 4) Local Setup Checklist (`.env`)

### 4.1 Required baseline app/runtime keys
- `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `SESSION_DRIVER`, `CACHE_DRIVER`, `QUEUE_CONNECTION`

### 4.2 Required for account lifecycle + security policy
- `LOGIN_MAX_ATTEMPTS`
- `LOGIN_LOCK_MINUTES`
- `TEMP_PASSWORD_EXPIRES_HOURS`
- `SECURITY_PASSWORD_MIN_LENGTH`
- `SECURITY_PASSWORD_REQUIRE_UPPERCASE`
- `SECURITY_PASSWORD_REQUIRE_LOWERCASE`
- `SECURITY_PASSWORD_REQUIRE_NUMBERS`
- `SECURITY_PASSWORD_REQUIRE_SYMBOLS`
- `OWNER_EMAIL` (needed for owner-contact messaging and default mail-from fallback)
- `OWNER_NAME`

### 4.3 Required for SMTP-dependent features (email verification + forgot/reset links)
- `MAIL_MAILER` (expected `smtp`)
- `MAIL_HOST`
- `MAIL_PORT`
- `MAIL_USERNAME`
- `MAIL_PASSWORD`
- `MAIL_ENCRYPTION`
- `MAIL_FROM_ADDRESS`
- `MAIL_FROM_NAME`

### 4.4 Optional / feature-toggle keys
- reCAPTCHA: `RECAPTCHA_ENABLED`, `RECAPTCHA_SITE_KEY`, `RECAPTCHA_SECRET_KEY`, `RECAPTCHA_VERIFY_URL`
- MFA tuning: `MFA_ISSUER`, `MFA_TIME_WINDOW`
- Audit retention/levels: `SECURITY_LOG_LEVEL`, `SECURITY_LOG_DAYS`, `SYSTEM_LOG_LEVEL`, `SYSTEM_LOG_DAYS`
- `MAIL_EHLO_DOMAIN` (optional SMTP local domain override)

---

## 5) Known Limitations and Deferred Items

1. **No telecom SMS provider integration for mobile OTP delivery.**
   OTP generation/verification storage exists, and local OTP preview is shown in `local` environment, but no Twilio/Semaphore/other SMS gateway dispatch is implemented.

2. **Test suite mismatch for one legacy OTP test.**
   `OtpMobileVerificationLifecycleTest` is stale and skipped with old route names; it conflicts with current implemented OTP routes and separate passing OTP tests.

3. **No explicit reCAPTCHA feature test coverage.**
   Runtime logic exists, but no dedicated test currently asserts `RECAPTCHA_ENABLED=true` verification failure/success behavior.

4. **Legacy naming persists in some test/file names (`Admin...`).**
   Runtime authorization model is still correctly owner-centric, but naming cleanup is deferred.

5. **SMTP production readiness depends on host environment and provider account policy.**
   Application logic is wired, but successful send in production is contingent on runtime TLS/CA health and Gmail/provider configuration.
