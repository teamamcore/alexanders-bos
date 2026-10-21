# Batsons BOS — Reference

Commands, structure, and conventions.

## Commands
```bash
# run
php artisan serve
php artisan migrate:fresh --seed     # Batsons tenant + admin + sample data

# quality loop (engineering standards: Rector -> Pint -> Larastan -> Pest)
composer rector-dry      # preview Rector changes
composer rector          # apply
composer lint            # Pint (formatting only)
composer stan            # Larastan (set PAO_DISABLE=1 under agents)
composer test            # Pest
composer quality         # the whole loop
```
Seeded admin: `eric@batsonsplumbing.com` / `password`.

## Pre-push gate (local only; don't push to test CI)
`composer test` · `composer lint` · `composer stan` · `composer audit --locked`
(+ `npm run build` when views/CSS change). Push only when asked.

## Layout
```
app/Actions/{Domain}/     business operations (VerbNoun, ::run()/::dispatch())
app/States/WorkOrder/     work-order status pipeline (spatie/model-states)
app/Models/               Eloquent models (+ Concerns: BelongsToTenant, Auditable)
app/Rules/                NoProhibitedData (MSA Exhibit B.2)
app/Support/helpers.php   term() / abbr() — tenant terminology (SOW §3.13)
app/Platform/             export engine (platform core, never exported)
config/clients/<key>.php  client layer overrides (exported with the client)
docs/SECURITY.md          written security overview (rendered under /admin/security)
app/Enums/                Role (field/office/administrator)
config/batsons.php        domain configuration
database/migrations/      schema (2026_10_16_1000*)
resources/views/livewire/pages/   Volt pages
docs/                     overview.md, spec.md, reference.md, PARAMETERS.md
tests/Feature/            Pest tests
```

## Conventions
- **Commits:** conventional — `type(scope): description` (`feat`, `fix`, `chore`,
  `test`, `refactor`, `docs`, `ci`, `style`). Branch `feat/…`, merge to local
  `main`, delete branch. Commit locally; push only when asked.
- **Tests:** Pest functional (`it()`/`test()`), no class-based PHPUnit. Write the
  failing test first.
- **Logic:** Actions, not controllers/Volt. No `app/Jobs/` (queued Actions).
- **Status with 3+ states + rules:** Model States in `app/States/`; never assign
  the status string directly — use `transitionTo()`.
- **Public IDs:** when customer-facing/portal routes are added (SOW §3.6/§3.14),
  use UUID/ULID/token + `getRouteKeyName()`, not sequential IDs. Internal
  authenticated routes may use integer IDs.
- **Prohibited Data (MSA Exhibit B.2):** never persist card/bank numbers, SSNs,
  health data, or biometric templates.
- **Strict types:** new Actions/States/Models/Http files start with
  `declare(strict_types=1);` (not Volt blades; not retroactive).
