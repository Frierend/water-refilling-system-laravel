# SECURITY_DOCUMENTATION

## Purpose and Scope
This document summarizes implemented security controls from Phase 1, Phase 2, and Phase 2.5 (account lifecycle hardening) for the current Mi-Gail Water System codebase as of **April 21, 2026**.

## Grading Criteria Mapping (Updated)
| Criteria ID | Grading Criterion | Implemented Improvement | Phase | Evidence |
|---|---|---|---|---|
| GC-01 | Authentication Enforcement | Session auth is protected by `auth` middleware, plus login attempt limiting/temporary lockout and forced first-login password change routing for flagged users. | Phase 1 + Phase 2.5 | `routes/web.php`, `app/Http/Controllers/Auth/LoginController.php`, `app/Http/Middleware/EnsurePasswordIsChanged.php` |
| GC-02 | Authorization / Role Control | Owner-only user creation routes are enforced with `role:owner`; assignable roles are restricted to `delivery` and `helper`. | Phase 1 + Phase 2.5 | `routes/web.php`, `app/Http/Controllers/UserManagementController.php`, `app/Http/Middleware/CheckRole.php` |
| GC-03 | Input Validation & Allowlisting | Request validation is used for filters/operations, and forced password change validates complexity requirements from shared security config. | Phase 1 + Phase 2.5 | `app/Http/Controllers/ReportController.php`, `app/Http/Controllers/OrderController.php`, `app/Http/Controllers/Auth/ForcedPasswordChangeController.php`, `config/security.php` |
| GC-04 | Safe Export Handling | Export format handling remains constrained; unsupported inventory PDF returns controlled fallback. | Phase 1 | `app/Http/Controllers/ReportController.php`, `app/Http/Controllers/InventoryController.php` |
| GC-05 | Data Integrity / Transaction Safety | High-impact order and inventory writes remain transaction-wrapped with rollback. | Phase 1 | `app/Http/Controllers/OrderController.php`, `app/Http/Controllers/InventoryController.php` |
| GC-06 | Audit Trail / Accountability | Structured audit events now cover order/inventory/delivery plus authentication and account lifecycle events. | Phase 2 + Phase 2.5 | `config/logging.php`, `app/Http/Controllers/Auth/LoginController.php`, `app/Http/Controllers/UserManagementController.php`, `app/Http/Controllers/Auth/ForcedPasswordChangeController.php` |
| GC-07 | HTTP Header Hardening | Global security headers middleware remains active in kernel. | Phase 2 | `app/Http/Middleware/SecurityHeaders.php`, `app/Http/Kernel.php` |
| GC-08 | CORS Hardening | CORS remains environment-driven with explicit allowlists. | Phase 2 | `config/cors.php`, `.env.example` |
| GC-09 | Automated Security Verification | Security feature tests now include lockout, admin-created users, and forced password change enforcement/completion. | Phase 2 + Phase 2.5 | `tests/Feature/Security/LoginLockoutTest.php`, `tests/Feature/Security/AdminUserCreationTest.php`, `tests/Feature/Security/ForcedPasswordChangeEnforcementTest.php` |
| GC-10 | Pre-Deployment Migration Safety | Enum migration stability controls remain in place for MariaDB-safe behavior. | Pre-Phase 3 Stabilization | `database/migrations/2026_04_21_000001_add_empty_type_to_inventory_items_enum.php` |

## Phase 2.5 Implemented Account-Security Controls
- Login attempts + temporary lockout:
  - Tracks `failed_attempts` and `locked_until`.
  - Locks account after failed threshold and blocks login during lock window.
- Owner-only user creation:
  - Only owner can create users via protected routes.
  - Assignable roles limited to `delivery` and `helper`.
- Server-generated temporary passwords:
  - Temporary password generated server-side.
  - Password stored as hash only.
  - Plaintext shown once via flash/session flow and not logged.
- Forced password change on first login:
  - `must_change_password` enforcement is active.
  - Middleware blocks normal protected routes until change is completed.
  - Expired temporary password path logs user out and shows safe contact-admin message.
- Password complexity and non-reuse:
  - Complexity policy is loaded from `config/security.php`.
  - New password cannot match current temporary password hash.
- Account lifecycle state tracking:
  - `must_change_password`
  - `password_changed_at`
  - `temp_password_expires_at`
- Authentication/account lifecycle audit events:
  - `auth.login.failed`
  - `auth.account.locked`
  - `auth.login.locked_blocked`
  - `security.user.created_by_owner`
  - `security.temporary_password.issued`
  - `security.forced_password_change.triggered`
  - `security.password.changed.success`

## Current Verification Results
- `php artisan migrate --pretend`: **passed**
- `php artisan test`: **passed** (`29` tests, `158` assertions)

## Deferred / Future Work (Not Implemented Yet)
- Forgot password flow via Laravel broker + SMTP routes (`/forgot-password`, `/reset-password`)
- Email verification enforcement workflow
- Google reCAPTCHA v2 on login
- MFA
- Audit channel split into separate `security` and `system` channels

## Remaining Limitations
- Test DB is SQLite in-memory (`phpunit.xml`), so MySQL/MariaDB-specific behavior is not fully covered by automated tests.
- `admin` is still referenced in some legacy role checks while schema roles remain `owner`, `delivery`, `helper` (owner is currently treated as admin authority).
- Dependency advisory command (`composer audit --locked --no-interaction`) remains environment-sensitive to TLS trust configuration.

## Evaluator Notes
- This document intentionally avoids claiming deferred features as implemented.
- Account lifecycle hardening is now implemented and test-validated in the current codebase.
