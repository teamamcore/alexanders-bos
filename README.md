# Batsons BOS — Business Operating System (prototype)

Laravel application for the **Batsons Business Operating System** built by AmCore
Systems under **SOW No. 01 / MSA v6** for Batsons Plumbing. Every SOW module
(§3.1–3.14, §3.16) plus the §4 data-migration tooling is built and running as a
prototype. The build is tracked in Notion and mirrored in-app at `/progress`.

Design source of truth: `docs/design-notes-2026-10.md` and the whiteboard photos in
`docs/design/`. Engineering notes: `docs/spec.md`. Contract-derived parameters:
`docs/PARAMETERS.md`. Security overview: `docs/SECURITY.md`.

---

## Launch it locally

**Requirements:** PHP 8.4 with the sqlite3 extension, Composer, Node 20+ with npm.
No database server is needed — local runs use SQLite.

```bash
git clone https://github.com/teamamcore/batsons-prototype.git
cd batsons-prototype

composer install
npm install
cp .env.example .env            # SQLite is the default
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed   # schema + Batsons demo data
php artisan storage:link           # photos / uploads
npm run build                      # compile Tailwind + Vite assets

php artisan serve --port=8010      # http://127.0.0.1:8010
```

Open **http://127.0.0.1:8010** and sign in with one of the seeded accounts:

| Role | Email | Password | What you see |
|---|---|---|---|
| Administrator (AmCore) | `david@amcoresystems.com` | `password` | Everything, incl. Administration |
| Administrator (Batsons) | `eric@batsonsplumbing.com` | `password` | Everything, incl. Administration |
| Office | — | — | Create one under Administration → Users |
| Field tech | `marcus@batsonsplumbing.com` | `password` | Tech view: no pricing, field work order flow |
| Field tech | `dana@batsonsplumbing.com` | `password` | Tech view |

Email verification is disabled for the prototype. Self-registration is off.

### Day-to-day while developing

```bash
php artisan serve --port=8010     # app
npm run dev                       # hot-reload CSS/JS (optional; `npm run build` otherwise)
php artisan schedule:work         # runs reminders (06:30), digest (07:00), portal polling (15 min)
php artisan migrate:fresh --seed  # reset the demo data at any time
```

### Quality loop (must be green before a commit)

```bash
composer rector
composer lint
PAO_DISABLE=1 composer stan
php artisan test --compact
```

---

## Where things are in the app

| Area | URL | SOW |
|---|---|---|
| Dashboard | `/dashboard` | — |
| Work orders (parent record) and jobs | `/work-orders`, `/jobs` | §3.1, §3.2 |
| Dispatch board | `/schedule` | §3.3 |
| Contacts (customers, sites, A/R) | `/contacts` | §3.1 |
| Messages, templates, call transcripts | `/messages`, `/messages/calls` | §3.4 |
| Proposals | `/proposals` | §3.5 |
| Invoices | `/invoices` | §3.6 |
| Parts, equipment, fleet | `/parts`, `/equipment`, `/fleet` | §3.7 |
| Time & pay | `/time` | §3.8 |
| Reports and job costing | `/reports` | §3.9 |
| Recurring service and reminders | `/reminders` | §3.10 |
| Team records and qualifications | `/team` | §3.11 |
| Notifications and inbox | `/notifications`, `/inbox` | §3.12 |
| Administration (users, terminology, security, AI usage, data import) | `/admin/users` | §3.13, §3.16, §4 |
| Vendor portals | `/portals` | §3.14 |
| Search everything / in-app help | `/search`, `/help` (top bar) | §3.16 |
| Build checklist | `/progress` | — |

---

## Optional integrations (environment variables)

The app runs fully without any of these. Each one switches a module from its
offline/sandbox mode to the live service.

| Feature | Variables | Without it |
|---|---|---|
| AI search, tags, help, call summaries | `ANTHROPIC_API_KEY` (optional `AI_MODEL`, `AI_EFFORT`) | Offline keyword assistant; usage still metered |
| Texting / calls (Twilio) | see `config/messaging.php` | SMS logged, not sent; A2P 10DLC + phone port pending |
| Transactional email (Postmark) | `POSTMARK_API_KEY`, `MAIL_*` | Mail goes to the log |
| QuickBooks Online live pull | `QBO_CLIENT_ID`, `QBO_CLIENT_SECRET`, `QBO_REALM_ID`, `QBO_REFRESH_TOKEN` | Import exported CSV files (same result) |
| Import window | `QB_IMPORT_YEARS` (default 8) | — |
| Vendor portals (ServiceChannel, Paramark) | per-portal credentials in Administration → Portals | Sandbox adapter with fixtures |
| Call audio transcription | `AI_TRANSCRIPTION_DRIVER` | Transcripts typed/pasted; audio marked pending |

---

## Data migration (SOW §4)

Administration → **Data import** (`/admin/migration`) takes CSV exports from
QuickBooks Online, QuickBooks Desktop, or a spreadsheet: customers, items, invoices
(open A/R + paid history), and the work-order log. Every upload is a dry run first and
produces the "records that could not be imported" report; one click commits. Sample
files are in `database/migration-samples/`. Bulk from the shell:

```bash
php artisan import:quickbooks path/to/exports            # dry run
php artisan import:quickbooks path/to/exports --commit
```

## Client data export (MSA §6)

```bash
php artisan client:export          # zip of all Client Data for the tenant
```

---

## Stack

Laravel 13 · PHP 8.4 · Livewire 3 / Volt · Tailwind 3 · Pest · Larastan · Rector ·
SQLite locally (MySQL/Postgres for production) · spatie/laravel-model-states ·
spatie/laravel-passkeys · lorisleiva/laravel-actions · anthropic-ai/sdk.

Multi-tenant from the start: every Client Data table carries `tenant_id` with a
global scope; one codebase, one tenant per client (`php artisan client:fork` seeds a
new one).

## Repository

`main` on `github.com/teamamcore/batsons-prototype` (private). Heritage
Paintworks is a separate project and must never be mixed into this repo.
