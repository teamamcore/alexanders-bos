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
- **3.4 Customer Communication** — `/messages` (`MessagesController`, office/admin):
  monitor of every text/email, compose, templates editor, auto-notify switch;
  `/messages/calls` for call transcripts (office + field). `Platform/Messaging/*`
  holds the swappable `SmsDriver` (log by default), `Messenger`, and
  `TemplateRenderer` (config defaults + `message_templates` overrides, `{placeholders}`).
  `NotifyCustomer` fires on schedule / reschedule / `StartWorkOrder` /
  `CompleteWorkOrder`; field users send on-the-way, arrived and free text from the
  job page. Inbound replies arrive at `POST /webhooks/sms` (shared token).
- **3.12 Notifications & Messaging** — Laravel database notifications via
  `Platform/Notifications/Notifier` + `Notifications/InApp` (kind/title/body/url);
  bell + `/notifications`. Fired from ScheduleWorkOrder, RescheduleWorkOrder,
  RecordInboundMessage, ReportAssetIssue, CheckDelivery, SendUserMessage,
  IngestInboundEmail, and the `notifications:digest` command (07:00 daily, once
  per topic per day). `/inbox` is user-to-user messaging (`user_messages`);
  `/inbox/email` is the Client inbound address queue (`inbound_emails`, fed by
  `POST /webhooks/email`, routed by WO/job number in subject → customer email → contact email, else manual).
- **3.10 Recurring Service & Reminders** — `recurring_services` (schedule per
  customer/site) + `reminders` (one worked cycle). `OnWorkOrderCompleted` (from
  `CompleteWorkOrder`) starts a schedule for a matching `work_type` or closes the
  reminder's job and rolls the schedule. `reminders:run` (06:30 daily →
  `RunReminders`) opens cycles, flips pending→notified when `remind_at` arrives,
  notifies the office, and sends the `service_reminder` template when
  `auto_message`. `/reminders` works the queue (text / email / call / schedule job /
  skip); `/admin/reminder-types` edits `ReminderTypes` (config defaults + tenant
  overrides: interval, 45–60 d style window, auto-message, triggering work type).
- **3.9 Reporting & Job Costing** — `Platform/Reporting/JobCosting` (labor =
  hours × pay-rule multiplier × `employees.hourly_rate` × (1 + `labor_burden_pct`);
  materials = `used_on_job` adjustments at part cost; equipment = usage amount;
  revenue = approved/sent/paid invoices) and `Reports` (A/R aging buckets + terms +
  collection flags from `invoices.issued_at/due_at/terms`, profitability by
  `work_type`, monthly trends, equipment/fleet economics). `/reports` dashboard
  (inline SVG, validated 2-series palette), `/reports/ar`, drill-down
  `/reports/work-orders?month|type|customer|from|to`, cost sheet `/reports/jobs/{job}`.
  `ApproveInvoice` stamps issued/due dates; `MarkInvoicePaid` stamps paid_at.
- **3.14 Vendor Portal Integrations** — `Platform/Portals/*`: `PortalAdapter`
  contract (pull requests, push status, push invoice, pull payment) with
  `SandboxAdapter` (fixtures in `config/portals.php`), `ServiceChannelAdapter` /
  `ParamarkAdapter` (guarded by `PortalNotConnected` until provider credentials,
  stored `encrypted:array`), and `ManualAdapter` (no-API portals). Actions:
  `ImportPortalWorkOrder` (customer/site/job/typed WO, idempotent), `PollPortal`
  (`portals:poll` every 15 min), `PushPortalStatus` (from Schedule/Start/Complete),
  `PushPortalInvoice` (from ApproveInvoice; NTE flag), `RecordPortalPayment`,
  `UpdateIntegration`. `POST /webhooks/portals/{integration}` with per-portal token.
  `/portals` shows the five-included usage (blocked excluded), requests pipeline, sync log.
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
