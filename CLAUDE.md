# BSMP Migration - Project Context

## What This Project Does

A **Laravel 12 ETL (Extract-Transform-Load) utility** that migrates a business expense management platform from a legacy MySQL database to a new cloud-hosted schema. It is not a production application — it is a one-off data migration tool.

The migration reads from an old local MySQL database (`mysql` connection), transforms the data (UUID generation, status mapping, schema normalization), and writes to a new DigitalOcean cloud MySQL database (`mysql2` connection).

---

## Architecture

- **Framework**: Laravel 12, PHP 8.2+
- **Frontend**: Vite + Tailwind CSS (minimal, welcome view only)
- **Trigger mechanism**: HTTP GET endpoints — hit an endpoint to run a migration step
- **No Eloquent models** for migration logic — all queries use `DB::table()` (raw query builder)
- **Migration logic lives in**: `app/Http/Controllers/UserController.php`
  - Note: `MigrationController.php` also exists with identical content — routes currently point to `UserController`

---

## Database Connections

Configured in `config/database.php`, values from `.env`:

| Connection | Purpose | Env vars |
|---|---|---|
| `mysql` | Source (legacy, local) | `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| `mysql2` | Target (new, DigitalOcean cloud) | `DB_HOST2`, `DB_DATABASE2`, `DB_USERNAME2`, `DB_PASSWORD2` |

The hardcoded company being migrated: `$company_id = 258`, `$new_company = '6a8a17a3-f895-4964-b10a-1eac52b88f8e'`

---

## Migration Endpoints (routes/api.php)

All routes are unauthenticated GET requests under `/api/`:

| Endpoint | Method | What it migrates |
|---|---|---|
| `/migrate-company` | `migrateCompany()` | Company profile, accounts, preferences, currencies, roles, permissions |
| `/migrate-user` | `moveUsers()` | Users and staff records with role assignments |
| `/migrate-payee` | `migratePayee()` | Suppliers/payees |
| `/migrate-groups` | `migrateGroups()` | Employee/staff groups |
| `/migrate-approver-circle` | `moveApprovalCircle()` | Approval workflow circles and approvers |
| `/migrate-expense-category` | `moveExpenseCategories()` | Budget categories and subcategories |
| `/migrate-payments` | `movePayments()` | Payment requests, cost centers, approvers, activity logs, request queue |
| `/migrate-purchases` | `movePurchases()` | Purchase orders with budget tracking |
| `/migrate-wallet` | `moveWallet()` | Wallet webhook data |
| `/migrate-billing` | `moveBilling()` | Billing records |
| `/migrate-transactions` | `moveTransactions()` | Transaction history with mode tracking |

**Run order matters** — company and users must be migrated first since other steps reference `old_company_id` and `old_user_id` lookups.

---

## Key Transformation Patterns

- **UUID generation**: New schema uses UUIDs (`Str::uuid()` / `Ramsey\Uuid`); old schema uses integer IDs
- **Old ID preservation**: Records are inserted with `old_*_id` columns (e.g., `old_company_id`, `old_user_id`, `old_purchase_id`) to allow cross-referencing during migration
- **Invalid date handling**: `0000-00-00 00:00:00` timestamps are normalized to `null`
- **Status mapping**: Legacy string statuses are mapped to new schema values
- **JSON field parsing**: Some legacy fields (e.g., `state`) are stored as JSON and decoded before insertion
- **Lookup pattern**: When migrating child records, the new parent UUID is looked up via `old_*_id` on the `mysql2` connection

---

## Target Schema Tables (created in mysql2)

| Category | Tables |
|---|---|
| Company | `companies`, `companies_accounts`, `companies_preferences`, `companies_currencies` |
| Users | `staffs`, `users`, `roles`, `roles_permissions` |
| Payees | `payees` |
| Groups | `groups` |
| Approvals | `approval_circles`, `approval_circle_approvers` |
| Budget | `budget_categories`, `budget_sub_categories` |
| Payments | `payment_requests`, `payment_requests_cc`, `request_activities`, `request_queue` |
| Purchases | `purchases` |
| Wallet | `webhooks` |
| Billing | `billings` |
| Transactions | `transactions` |

---

## Key Files

```
app/Http/Controllers/UserController.php   # All migration logic (11 methods)
app/Http/Controllers/MigrationController.php  # Duplicate — not currently routed
routes/api.php                            # All migration endpoints
config/database.php                       # mysql + mysql2 connection config
```

---

## Running a Migration

```bash
# Start the Laravel dev server
php artisan serve

# Hit each endpoint in order
curl http://localhost:8000/api/migrate-company
curl http://localhost:8000/api/migrate-user
curl http://localhost:8000/api/migrate-payee
curl http://localhost:8000/api/migrate-groups
curl http://localhost:8000/api/migrate-approver-circle
curl http://localhost:8000/api/migrate-expense-category
curl http://localhost:8000/api/migrate-payments
curl http://localhost:8000/api/migrate-purchases
curl http://localhost:8000/api/migrate-wallet
curl http://localhost:8000/api/migrate-billing
curl http://localhost:8000/api/migrate-transactions
```

---

## Important Notes

- Migration endpoints are **not idempotent** — running them twice will insert duplicate records. Clear the target tables before re-running.
- The `$company_id` and `$new_company` properties in `UserController` are hardcoded — change them to migrate a different company.
- No authentication guards on API routes — do not expose this app publicly.
- Logging is enabled at debug level (`Log::debug(...)` calls throughout migration methods).
