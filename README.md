# Mi-Gail Water System

Laravel-based sales, delivery, inventory, customer, reporting, and account-security system.

## Authoritative Documentation
See: **`MI_GAIL_MASTER_SOURCE_OF_TRUTH.md`**

## Security Scope (Implemented)
- owner-only user creation with auto-generated temporary password
- forced first-login password change with policy checks
- email verification flow
- forgot/reset password flow via Laravel password broker
- login attempt limiting and account lockout
- optional Google reCAPTCHA checkbox on login
- Google Authenticator-compatible MFA (TOTP)
- split audit logging channels: `security` and `system`

## Quick Setup
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Configure SMTP and optional reCAPTCHA/MFA environment variables in `.env`.
