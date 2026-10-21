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
- **3.5 Proposals & Estimating** — pipeline draft→sent→follow_up→won/lost;
  `ConvertProposalToJob` creates the job + first work order.
- **3.6 Invoicing** — `GenerateInvoiceFromWorkOrder`, progress payments,
  approve/void/mark-paid, `SyncInvoiceToQuickBooks` (status-only; no card data).
- **3.8 Time & Pay** — `ClockIn`/`ClockOut`, `AdjustTimeEntry`, `CalculatePay`
  pay-rule engine (business hours, OT multipliers from `tenants.settings.pay_rules`).

- **3.13 Administration & Security** — `AdminController` under `/admin`
  (`role:administrator`): user setup/deactivation/role changes (`Actions/Admin/*`,
  seat usage vs. 20 included + blocks of 5), configurable terminology via
  `term()`/`abbr()` (`app/Support/helpers.php`, overrides in `tenants.settings`),
  passkey / device-biometric sign-in (spatie/laravel-passkeys; `Route::passkeys()`),
  and the written security overview (`docs/SECURITY.md`, rendered in-app).
  Deactivated users are refused at login and dropped by `SetCurrentTenant`.

- **3.11 Team Records & Qualifications** — `/team` (`TeamController`): profiles,
  milestones, certifications/licences with expiry alerts (`Certification::expiringWithin`),
  background check status+date (admin-only, no documents), and the admin
  **qualification matrix** (`/admin/qualifications`, `SetQualification`, audited).
  `App\Support\SchedulingIndicators` feeds level + cert health into the dispatch
  board per work order `work_type` (SOW §3.3). Field users see only their own profile.
- **3.7 Parts, Inventory, Equipment & Fleet** — `/parts`, `/equipment`, `/fleet`
  (`PartsController`, `EquipmentController`, `FleetController`; Actions under
  `Inventory/`, `Equipment/`, `Fleet/`, `Assets/`). Parts: nickname/SKU search,
  manual price (audited = item history), CSV supplier price import, one-off items,
  stock adjustments with reasons, delivery checks with shortage/damage/photo,
  low-stock alerts. Equipment: usage billed per work order (`equipment_usages`),
  maintenance by hours/days, check-out/return, issue reports, revenue vs. cost,
  straight-line depreciation (`App\Support\Depreciation`). Fleet: register,
  mileage readings + stale reminder, service intervals by miles/months, renewal
  alerts, costs by type, TCO. Photos go to the `public` disk.
- **Build checklist** — `/progress` mirrors the Notion Build Timeline from
  `config/build.php` (status, dates, notes, and a link to the live screen for each
  shipped module). Update it alongside Notion when a module ships.

## Platform / Client seam
`php artisan client:export {client}` and `client:fork {source} {name}` — see
`ARCHITECTURE.md`. Everything client-owned lives in `config/clients/<key>.php`,
`app/Client/<Studly>/` and tenant-scoped rows; the platform core is never exported.

## Testing
Pest, functional style only. Tenant context is set with
`app()->instance('currentTenantId', $id)`; field scoping with `currentUser`.
Key suites: tenant isolation, field scoping, intake/work-order Actions, WO states.

## Non-functional parameters
See `PARAMETERS.md` — security, availability, prohibited data, audit, integrations,
and the ownership model, each cited to its SOW/MSA section.
