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
