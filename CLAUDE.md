# Batsons BOS — agent instructions

**Start by reading `docs/HANDOFF.md`** (one page: status, how David works, gotchas), then
`README.md` for launch steps. Deeper detail only when needed: `docs/spec.md` (per-module
engineering notes), `docs/design-notes-2026-10.md` + `docs/design/` (UI source of truth),
`docs/PARAMETERS.md` (contract-derived parameters), `docs/SECURITY.md`.

Rules that are not optional:
- Quality loop green before every commit: `composer rector` → `composer lint` →
  `PAO_DISABLE=1 composer stan` → `php artisan test --compact`.
- Logic lives in `app/Actions/*` (lorisleiva actions) and `app/Platform/*`; views stay thin.
- Every Client Data table is tenant-scoped (`BelongsToTenant`); techs never see pricing.
- When a module/stage ships: commit, push to `teamamcore/batsons-prototype`, flip the
  Notion Build Timeline status, update `config/build.php` and `docs/spec.md`.
- Heritage Paintworks (`~/heritage-paintworks/`) is a different, live project — never mix.
- Dev server is port 8010 (8000 is another project). Don't use `rm`; move files instead.
