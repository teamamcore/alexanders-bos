# Batsons BOS — Technical Spec

Generated from the live codebase. Keep in sync on feature/fix/behavior commits.

## Stack
- Laravel 13 (PHP 8.4), SQLite local / MySQL prod-parity
- Livewire + Volt (server-driven UI), Pest (functional tests)
- Quality loop: **Rector → Pint → Larastan** (`composer quality`)
- Packages: `lorisleiva/laravel-actions`, `spatie/laravel-model-states`

## Architecture — independent, multi-tenant
Batsons is an **independent** app (not a hub satellite). It owns its auth, data,
and deployment. Tenancy is column-based: every Client Data table has `tenant_id`,
auto-filled and query-scoped by `App\Models\Concerns\BelongsToTenant`.

### Ownership boundary (MSA §6.1 / §6.2)
Single deliverable repo, with the IP split expressed by namespace/intent:
- **Client Deliverables** (Client-owned, §6.1): Batsons-specific config, schema,
  Actions, States, and UI under this app.
- **AmCore Technology** (AmCore-owned, reusable, §6.2): the generic patterns —
  tenancy, audit, state-machine scaffolding, prohibited-data guard — reusable for
  other trade clients. Kept generic so it can be lifted into a shared core later.
Code + data are exportable/deliverable to the Client (§2.8, §11.3).

## Where logic lives (engineering standards)
- **Actions** — `app/Actions/{Domain}/`, VerbNoun, `::run()` sync / `::dispatch()`
  queued. e.g. `Intake\CreateJobFromIntake`, `WorkOrders\AddWorkOrder`.
  No `app/Jobs/`.
- **State machines** — `app/States/{Domain}/`. Work-order status:
  `new → scheduled → in_progress → complete` (reschedule steps back). Transitions
  via `transitionTo()`; illegal transitions throw `CouldNotPerformTransition`.
- **Audit trail** — `App\Models\Concerns\Auditable` → `audit_logs` (append-only),
  on `WorkOrder`, `Invoice`, `GateOverride`, `TimeEntry`.
- **Prohibited-data guard** — `App\Rules\NoProhibitedData` (MSA Exhibit B.2).
- **Config** — `config/batsons.php` (roles, equipment types, pay rules, pipeline
  stages, reminder types, service targets, module map).
- New files in Actions/States/Models/Http start with `declare(strict_types=1)`
  (not Volt blades, not applied retroactively).

## Domain model
28 models across SOW modules 3.1–3.14, 3.16 (customers, service locations, jobs,
work orders, gates/overrides, scheduling, proposals, invoices, parts/equipment/
fleet, time, team/qualifications, reminders, vendor portals). See migrations in
`database/migrations/2026_10_16_1000*`.

## Modules vertical-sliced so far
- **3.1 Intake → 3.2 Jobs/Work Orders** — Volt pages `pages/jobs/{index,show}`,
  backed by Actions; work-order status pipeline live.
- **3.3 Scheduling & Dispatch** — dispatch board `pages/schedule/index`;
  `ScheduleWorkOrder` (new → scheduled) and `RescheduleWorkOrder` (records
  `schedule_changes` history); field users already scoped to their own work.

## Testing
Pest, functional style only. Tenant context is set with
`app()->instance('currentTenantId', $id)`; field scoping with `currentUser`.
Key suites: tenant isolation, field scoping, intake/work-order Actions, WO states.

## Non-functional parameters
See `PARAMETERS.md` — security, availability, prohibited data, audit, integrations,
and the ownership model, each cited to its SOW/MSA section.
