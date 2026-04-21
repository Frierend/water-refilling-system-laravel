# Mi-Gail Water System

Laravel-based sales, delivery, inventory, customer, reporting, and account-security system.

## Authoritative Documentation

Use **`MI_GAIL_MASTER_SOURCE_OF_TRUTH.md`** as the single source of truth for:
- verified implementation status,
- security/account-lifecycle policies,
- audit logging taxonomy (`security` vs `system`), and
- known gaps/deferred items.

## Overlapping Documentation Status

- `AUDIT_REPORT.md` — deprecated/archive-ready
- `IMPLEMENTATION_CHECKLIST.md` — deprecated/archive-ready
- `SECURITY_DOCUMENTATION.md` — deprecated/archive-ready

Any overlapping checklist/security/audit narrative outside the master file is pointer-only.

## Quick Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```
