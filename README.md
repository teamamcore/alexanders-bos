# Batsons Business Operating System — Prototype

Laravel prototype for the **Batsons Business Operating System** described in
AmCore's **SOW No. 01** and governed by the **MSA**. This scaffold stands up the
multi-tenant data model and the contractual guardrails; feature logic and UI
are the next layer.

## What's in place
- **Multi-tenancy** — `tenant_id` on all Client Data + `BelongsToTenant` global scope.
- **Roles** — field / office / administrator (`App\Enums\Role`).
- **Audit trail** — append-only `audit_logs` via `Auditable`, plus explicit audit
  columns for time adjustments, gate overrides, and schedule changes.
- **Prohibited-data guardrail** — `App\Rules\NoProhibitedData` (MSA Exhibit B.2).
- **Domain config** — `config/batsons.php` (equipment types, pay rules, pipeline
  stages, reminder types, service targets, module map).
- **Schema** — migrations for modules 3.1–3.14, 3.16 (customers, jobs/work orders,
  gates, scheduling, proposals, invoices, parts/equipment/fleet, time, team,
  reminders, vendor portals).
- **Seed data** — Batsons tenant, admin user, equipment catalog, a sample job/WO.

See **`docs/PARAMETERS.md`** for the full list of contract-derived parameters and
where each is implemented.

## Local setup

This prototype runs on a no-sudo toolchain (static PHP + Composer in `~/.local/bin`)
with **SQLite**. Standard Laravel setup also works (Herd, `php.new`, etc.).

```bash
# with PHP + Composer on PATH:
composer install
cp .env.example .env            # already present; SQLite is the default
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve               # http://127.0.0.1:8000
```

Seeded admin login: `eric@batsonsplumbing.com` / `password`.

## Tech notes
- Laravel 13, PHP 8.4, SQLite (local). MySQL for production-parity if needed.
- `database/database.sqlite`, `.env`, and `vendor/` are git-ignored.

## Roadmap
Business logic, Blade/Livewire UI, QuickBooks + texting + AI + vendor-portal
integrations, and tests per module. Team convention is `--livewire --pest`
(not yet added to this scaffold).
