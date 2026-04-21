# Sales and Inventory System for Mi-Gail Water Refilling Station

## Overview
This project is a Laravel-based business system built for **Mi-Gail Water Refilling Station** to digitize daily operations that were previously recorded manually in notebooks.
It centralizes sales recording, delivery tracking, customer management, inventory monitoring, and reporting.

The system reflects the real business flow described in the project documentation, while this README prioritizes what is currently implemented in the codebase.

## Background (Company Profile)
Mi-Gail Water Refilling Station is a community-based water business in **Purok 9-B, Tacunan, Tugbok District, Davao City**.
Based on the final documentation, the business:
- serves walk-in and delivery customers
- was established in **July 2023**
- is owned/managed by **Mary Jane Orpeza**
- typically serves nearby households in the local area
- originally handled transactions and reports through manual notebook records

## Problem Statement
From the project documentation, the core business problems were:
- manual and time-consuming sales recording (walk-in and delivery)
- delays and errors in sales report preparation
- slow and error-prone inventory tracking
- limited visibility of customer history and recurring orders

## System Objectives
Based on the documentation, the system objectives are to:
- automate walk-in and delivery sales recording
- simplify inventory updates after transactions
- manage customer records and order history
- generate daily/weekly/monthly reports faster
- provide controlled access through login credentials

## Tech Stack (From Codebase)
### Backend
- PHP `^8.1` (tested locally here with PHP `8.3.30`)
- Laravel `10.48.29`
- MySQL (default in `.env.example`)
- Eloquent ORM + Laravel migrations/seeders
- Session-based authentication with custom login controller
- Custom role middleware (`role` alias -> `CheckRole`)

### Frontend
- Blade templates
- Bootstrap `5.3.2` (CDN)
- Bootstrap Icons (CDN)
- Chart.js (CDN, used in reports)
- Custom styling in:
  - `public/css/custom.css`
  - `public/css/mi-gail-theme.css`

### Build / Tooling
- Vite `^5`
- laravel-vite-plugin
- Axios

### Key Packages
- `laravel/sanctum`
- `laravel/ui`
- `doctrine/dbal`
- `guzzlehttp/guzzle`

### Testing
- PHPUnit 10 (currently basic example tests only)

## How the Real Business Process Was Digitized
The notebook-based workflow was translated into system modules:
- sales entries became structured order records
- delivery updates became trackable statuses
- stock usage became transaction-based inventory movements
- customer records became searchable and filterable
- manual reports became filterable dashboard/report pages with print-ready views

## System Modules
### Dashboard
- Sales metrics (daily/weekly/monthly)
- Pending deliveries count
- Low-stock alerts
- Recent orders list with filters/search/pagination
- Quick actions for orders, walk-in sales, customers, inventory

### Sales
- Order management (`orders` resource)
- Walk-in sales form (`/walkin`)
- Payment status and payment method tracking
- Order completion/cancellation
- Automatic inventory transactions during order processing

### Delivery
- Delivery list (pending/completed/all)
- Delivery details page
- Mark delivery complete (with optional payment capture for unpaid orders)
- Delivery cancellation (controller restricts to owner/admin logic)

### Inventory
- Inventory item CRUD
- Stock adjustment with audit notes
- Transaction history view
- Low-stock view
- Inventory filters by type and stock level
- CSV export for inventory items
- PDF export route currently returns safe fallback notice (CSV recommended)

### Customers
- Customer CRUD
- Search/filter/sort
- Customer profile with order history
- AJAX customer search endpoint (`/api/customers/search`)

### Reports
- Sales report
- Delivery report
- Customer report
- Inventory report
- Filters (period/date range/status/type)
- Print-ready views and charting
- CSV export for sales, delivery, customer, and inventory reports
- PDF export for sales, delivery, and customer reports (DOMPDF)
- Inventory report PDF currently returns safe fallback notice (CSV recommended)

## Detailed Features (Implemented in Routes/Controllers)
### Authentication and Access
- Login/logout
- Role-based post-login redirect:
  - delivery -> deliveries page
  - helper -> order creation page
  - others -> dashboard
- Protected routes under `auth` middleware

### Order and Payment Logic
- Delivery fee and water pricing are computed in backend
- Payment statuses: `paid` / `unpaid`
- Payment methods: `cash` / `gcash` / `none`
- Delivery unpaid orders can be collected and marked paid on completion
- Order cancellation can reverse associated inventory transactions

### Inventory Tracking Logic
- `InventoryTransaction` records are created per stock movement
- Sales deduct water and packaging materials (caps/seals; containers for delivery)
- Manual adjustments add/remove quantity with reason notes
- Low-stock is based on `quantity <= threshold`

### Customer Management Logic
- Regular vs non-regular tagging
- Search by name, phone, address
- Sort options include newest/oldest/name/order volume
- Customer deletion is blocked when related orders exist

### Reporting Logic
- Reports support daily/weekly/monthly/custom periods
- Metrics and tables are generated from actual order/inventory data
- Sales report includes chart dataset generation
- Print view support is implemented in report pages

## Database Structure
| Table | Purpose |
|---|---|
| `users` | System accounts and role-based access (`owner`, `delivery`, `helper`) |
| `customers` | Customer master records |
| `orders` | Sales transactions (walk-in and delivery), payment and status data |
| `inventory_items` | Stock master list (water/container/cap/seal/other) |
| `inventory_transactions` | Inventory movement log per action/order/adjustment |
| `password_reset_tokens` | Default Laravel password reset storage |
| `failed_jobs` | Default Laravel failed queue jobs |
| `personal_access_tokens` | Default Laravel Sanctum tokens |

## Workflow Explanation

### Walk-in Sales Workflow
Based on documentation and mapped to current implementation:
1. Staff opens **Walk-in Sale** (`/walkin`).
2. Staff enters customer name, quantity, and payment method.
3. System creates order as completed walk-in sale.
4. System deducts inventory for water/caps/seals.
5. System attempts to record empty container returns when applicable.
6. Transaction is visible in order lists and reports.

Implementation note:
- The business process in documentation emphasizes empty container handling.
- In code, empty-return logic exists, but inventory type support for `empty` is not fully aligned with current migration enums.

### Delivery Sales Workflow
Based on documentation and mapped to current implementation:
1. Staff creates order in **Sales** with delivery enabled.
2. Delivery personnel can be assigned during order creation/edit.
3. Pending delivery appears in **Deliveries** module.
4. Delivery personnel/admin views delivery details.
5. Delivery is marked completed; unpaid orders can be marked paid with method/reference.
6. Delivery/order data flows into reports and dashboard metrics.

Implementation note:
- Documentation mentions richer delivery status flow and assignment workflow; current code primarily uses `pending`, `completed`, `cancelled`.

## Installation Guide
1. Clone the repository.
2. Install PHP dependencies:
   ```bash
   composer install
   ```
3. Install frontend dependencies:
   ```bash
   npm install
   ```
4. Create environment file:
   ```bash
   cp .env.example .env
   ```
   PowerShell:
   ```powershell
   Copy-Item .env.example .env
   ```
5. Configure DB credentials in `.env`.
6. Generate app key:
   ```bash
   php artisan key:generate
   ```
7. Run migrations and seeders:
   ```bash
   php artisan migrate --seed
   ```
8. Start app:
   ```bash
   php artisan serve
   ```
9. Optional frontend dev server:
   ```bash
   npm run dev
   ```

If `php` is not in PATH (common in some Laragon setups), use your PHP executable directly, for example:
```powershell
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe artisan serve
```

## Usage Guide (Based on User Manual + Actual UI)
1. Open `/login` and sign in.
2. Review dashboard metrics and alerts.
3. For regular/delivery orders:
   - Go to **Sales** -> create order
   - select customer, quantity, payment status/method
   - enable delivery if needed
4. For walk-in sales:
   - Go to **Walk-in Sale**
   - enter customer name and quantity
   - complete payment details
5. Manage customer records in **Customers**.
6. Monitor and adjust stock in **Inventory**.
7. Track delivery progress in **Deliveries**.
8. Generate and print reports in **Reports**.

For fresh local seeded data, default users include:
- `owner@migail.com` / `password`
- `delivery@migail.com` / `password`
- `helper@migail.com` / `password`

## Security Runbook (Phase 2)
Use this quick checklist before release or major handoff:
1. Run dependency audit:
   ```bash
   composer audit:composer
   ```
2. Run security-focused tests:
   ```bash
   composer audit:security
   ```
3. Run Phase 2 regression checks:
   ```bash
   composer audit:phase2
   ```
4. Verify audit log output:
   - check `storage/logs/audit-*.log` for structured events (orders, inventory, deliveries)
5. Confirm CORS settings in `.env`:
   - `CORS_ALLOWED_ORIGINS`
   - `CORS_ALLOWED_METHODS`
   - `CORS_SUPPORTS_CREDENTIALS`
6. Confirm response headers are present:
   - `X-Frame-Options`
   - `X-Content-Type-Options`
   - `Referrer-Policy`
   - `Permissions-Policy`

For disclosure/reporting process, see `SECURITY.md`.

## Documentation vs Code Notes (Important)
The following are **described in documentation but may not be fully implemented** in the current codebase:
- Administration module features (full user admin, backup/config suite)
- Full Excel export implementation (CSV and selected PDF exports are implemented)
- Some delivery workflow details from manual (for example richer status flow)
- Login by "username" wording in documentation (code uses email/password validation)
- `admin` role behavior (controllers/middleware reference admin, but current `users.role` enum does not include `admin`)
- Customer extra fields in docs/manual (for example email) not present in current customer schema
- Empty container stock flow is partially coded but not fully aligned with current inventory item type enum

Additional implementation gaps observed in code:
- `orders.pay` is referenced in a view but no matching route exists
- `orders.destroy` route exists via resource routing but no `destroy` method in `OrderController`
- Customer `notes` are used in forms/controller validation but no `customers.notes` column exists in current migrations

## Future Improvements
1. Add true Excel (`.xlsx`) exports (currently CSV + selected PDF are implemented).
2. Resolve role model consistency (`owner/delivery/helper/admin`) across migration, model, middleware, and UI.
3. Implement or remove undefined routes/actions (`orders.pay`, `orders.destroy`) for stability.
4. Align database schema with forms and documented fields (for example customer notes/email if required).
5. Fully align empty-container inventory workflow with schema and business rules.
6. Expand delivery workflow states and assignment controls to match documented operations.
7. Add comprehensive automated tests for sales, delivery, inventory, and reporting flows.
8. Add audit logs and stricter validation for high-impact inventory and cancellation actions.

