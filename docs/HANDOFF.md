# Batsons BOS — Handoff (read this first)

One-page state of the project for a fresh session. Last updated 2026-10-21.

## Who / what
- Client: **Batsons Plumbing**. Builder: **AmCore Systems** (David Conn, david@amcoresystems.com).
- Contract: **SOW No. 01 / MSA v6**. Modules §3.1–3.14, §3.16 + §4 data migration. There is no §3.15.
- Repo: `~/batsons-prototype` → GitHub **teamamcore/batsons-prototype** (private, `main`). `gh` active account is `teamamcore`; no switching needed.
- **Heritage Paintworks is a separate live site in `~/heritage-paintworks/`** — never touch it from here.
- Dev server: `php artisan serve --port=8010` → http://127.0.0.1:8010 (port 8000 belongs to amcore-site).
- Logins (all password `password`): `david@amcoresystems.com` admin · `eric@batsonsplumbing.com` admin · `marcus@` / `dana@batsonsplumbing.com` field techs. Email verification and self-registration are off.

## Status
**Every Build-phase item is shipped as a prototype** (3.1–3.14, 3.16, Data migration) — 157 Pest tests green, Larastan clean. See `/progress` in the app or `config/build.php`.

Remaining timeline (Notion "Batsons BOS — Project Plan"):
1. **Mon 2026-10-26 — Twilio A2P 10DLC + phone-line port kickoff** (time-sensitive, 1–4 week lead).
2. Week 5 (Nov 18) walkthrough + field test → Launch (Nov 25) → Turnover (Dec 1) → 30-day support.

Waiting on outside parties (app works in offline/sandbox mode meanwhile): Twilio (texting/calls/inbound email), ServiceChannel + Paramark API creds (portals), `ANTHROPIC_API_KEY` (AI features run as an offline keyword assistant without it), QuickBooks Online admin access + Batsons' exports (the real 4-year import), Google Maps key.

## How David works (do this)
- Ship one module/stage at a time, then: run the quality loop → commit → push → **flip Notion Build Timeline status** → update `config/build.php` (`status`, `link`, `as_of`) → `docs/spec.md`. Memory file `batsons-notion-timeline.md` (in Claude memory) has every Notion page id.
- David play-tests himself; hand him a running build, don't simulate users.
- UI source of truth: `docs/design-notes-2026-10.md` + whiteboard photos in `docs/design/`. Work Order is the parent record; techs never see pricing; only office edits scope (redlined); Hold is a sub-state of Active.
- Quality loop, all green before commit: `composer rector` → `composer lint` → `PAO_DISABLE=1 composer stan` → `php artisan test --compact`. Larastan has no ignores; fix the nit.
- Commit messages: conventional (`feat(scope): …`), end with `Co-Authored-By: Claude …`.

## Stack & shape
Laravel 13 · PHP 8.4 · Livewire 3 / Volt pages · Tailwind 3 (brand navy `brand-*`, copper `accent-*`) · Pest · SQLite dev DB · spatie model-states (WO: new → scheduled → in_progress → complete; `Completed::$name = 'complete'`) · lorisleiva actions (`VerbNoun::run()`) · spatie passkeys · anthropic-ai/sdk.

```
app/Actions/{Domain}/          business operations (the only place logic lives)
app/Platform/{AI,Messaging,Portals,Migration,Export,Reporting,Notifications}/
app/Models/ (+Concerns: BelongsToTenant, Auditable, Taggable)
app/Support/helpers.php        term()/abbr() tenant terminology, bos_nav(), bos_page_title()
resources/views/livewire/pages/   Volt pages (work-orders, jobs, schedule…)
resources/views/ops/              Blade pages per module (admin, messages, reports…)
config/batsons.php             domain config · config/build.php timeline mirror · config/ai.php · config/quickbooks.php
database/seeders/BatsonsSeeder.php   demo tenant, users, 4 months of history
docs/                          spec.md (engineering notes per module), PARAMETERS.md, SECURITY.md, design-notes
```

## Gotchas learned the hard way
- Multi-tenancy: `BelongsToTenant` global scope + `SetCurrentTenant` middleware (runs before route-model binding). Console commands must `app()->instance('currentTenantId', $id)`.
- Blade: a directive glued to a word (`mo@endif`, `h@if`) is not parsed — always a space before `@if/@endif`.
- Eloquent reserves `$connection` → that portal column is `credentials` (`encrypted:array`).
- Fresh models don't carry DB defaults (`payment_terms`, etc.) — set explicitly or `refresh()`.
- Carbon 3: no `floatDiffIn*`; use `diffInHours/diffInYears`.
- `/build` collides with Vite's `public/build` → the checklist lives at `/progress`.
- Tests seed `BatsonsSeeder`; customer names "Riverside Dental", "Acme Industrial Park", part nickname "the big wrench", WO-1001 are relied on. Use `latest('id')` not `latest()` when rows share a timestamp.
- `rm` is blocked by the agent sandbox — move files instead. Pushing/repointing remotes may need David to run the command.

## Where each module lives (URL → SOW)
`/work-orders` `/jobs` §3.1–3.2 · `/schedule` §3.3 · `/contacts` §3.1 · `/messages` `/messages/calls` §3.4 · `/proposals` §3.5 · `/invoices` §3.6 · `/parts` `/equipment` `/fleet` §3.7 · `/time` §3.8 · `/reports` §3.9 · `/reminders` §3.10 · `/team` §3.11 · `/notifications` `/inbox` §3.12 · `/admin/*` §3.13 · `/portals` §3.14 · `/search` `/help` `/admin/ai-usage` §3.16 · `/admin/migration` §4 · `/progress` checklist.

Full launch instructions: `README.md`. Module-by-module engineering notes: `docs/spec.md`.
