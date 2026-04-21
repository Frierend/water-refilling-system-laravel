# IMPLEMENTATION_CHECKLIST

## Phase 1 Implementation Checklist
- [x] **GC-01 Authentication Enforcement**: Protected routes are under `auth` middleware.
- [x] **GC-02 Authorization Controls**: Role middleware is applied on restricted modules.
- [x] **GC-03 Input Validation**: Report/dashboard/filter and transactional inputs use allowlisted validation.
- [x] **GC-04 Export Safety**: Export format handling is constrained; unsupported inventory PDF returns safe fallback.
- [x] **GC-05 Data Integrity**: Order and inventory write paths use DB transactions with rollback on failure.

## Phase 2 Implementation Checklist
- [x] **GC-06 Audit Logging**: Dedicated `audit` log channel configured; security-relevant actions log structured entries.
- [x] **GC-07 Security Headers**: Global middleware adds `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, and conditional HSTS.
- [x] **GC-08 CORS Hardening**: CORS configured with explicit environment-driven origins/methods/headers settings.
- [x] **GC-09 Security Regression Tests**: Security feature tests for input validation and export endpoint behavior are present and passing.
- [x] **GC-10 Migration Safety**: Enum migration updated to MariaDB-safe raw SQL without `->change()`, with SQLite-safe skip and idempotent guards in both `up()` and `down()`.

## Phase 2.5 Account-Security Implementation Checklist
- [x] Login attempts + temporary lockout implemented (`failed_attempts`, `locked_until`).
- [x] Owner-only user creation routes implemented.
- [x] Assignable roles constrained to `delivery` and `helper`.
- [x] Server-generated temporary password implemented.
- [x] Temporary password stored as hash only (no plaintext DB persistence).
- [x] One-time temporary password display via flash/session implemented.
- [x] Forced password change on first login enforced.
- [x] `EnsurePasswordIsChanged` middleware blocks normal protected routes while flagged.
- [x] Expired temporary password safe fallback implemented (`contact administrator` message).
- [x] Password complexity policy enforced from shared `config/security.php`.
- [x] Temporary password non-reuse enforced.
- [x] Lifecycle fields implemented and used:
  - [x] `must_change_password`
  - [x] `password_changed_at`
  - [x] `temp_password_expires_at`
- [x] Authentication/account lifecycle audit events implemented:
  - [x] `auth.login.failed`
  - [x] `auth.account.locked`
  - [x] `auth.login.locked_blocked`
  - [x] `security.user.created_by_owner`
  - [x] `security.temporary_password.issued`
  - [x] `security.forced_password_change.triggered`
  - [x] `security.password.changed.success`

## Verification Checklist (Current Run)
- [x] `php artisan migrate --pretend` passed.
- [x] `php artisan test` passed (`29` tests, `158` assertions).

## Deferred / Future Security Checklist (Not Yet Implemented)
- [ ] Forgot password via Laravel broker + SMTP routes and flow.
- [ ] Email verification enforcement flow.
- [ ] Google reCAPTCHA v2 login protection.
- [ ] MFA.
- [ ] Split logging channels into dedicated `security` and `system` channels.
- [ ] Add MySQL/MariaDB integration coverage for DB-specific behavior.

## Evidence Pointers for Evaluators
- Middleware and headers: `app/Http/Kernel.php`, `app/Http/Middleware/SecurityHeaders.php`
- CORS and logging config: `config/cors.php`, `config/logging.php`, `.env.example`
- Security policy config: `config/security.php`
- Validation and transaction guards: `app/Http/Controllers/DashboardController.php`, `app/Http/Controllers/ReportController.php`, `app/Http/Controllers/OrderController.php`, `app/Http/Controllers/InventoryController.php`
- Account lifecycle and auth enforcement: `app/Http/Controllers/Auth/LoginController.php`, `app/Http/Controllers/UserManagementController.php`, `app/Http/Controllers/Auth/ForcedPasswordChangeController.php`, `app/Http/Middleware/EnsurePasswordIsChanged.php`
- Audit event emission: `app/Http/Controllers/Auth/LoginController.php`, `app/Http/Controllers/UserManagementController.php`, `app/Http/Controllers/Auth/ForcedPasswordChangeController.php`, `app/Http/Controllers/OrderController.php`, `app/Http/Controllers/InventoryController.php`, `app/Http/Controllers/DeliveryController.php`
- Security tests: `tests/Feature/Security/ReportInputValidationTest.php`, `tests/Feature/Security/ExportEndpointsTest.php`, `tests/Feature/Security/LoginLockoutTest.php`, `tests/Feature/Security/AdminUserCreationTest.php`, `tests/Feature/Security/ForcedPasswordChangeEnforcementTest.php`
- Migration stabilization: `database/migrations/2026_04_21_000001_add_empty_type_to_inventory_items_enum.php`
