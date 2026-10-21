# Architecture — Platform core vs. Client layer

The build is split into two layers so that **any client's deployment can be
cleanly exported** — for handover on exit (MSA §2.8, §11.3, §11.4) *and* as the
fork point for a new customer (reuse, MSA §6.2).

```
┌─────────────────────────────────────────────────────────────┐
│ AmCore Technology — PLATFORM CORE  (retained by AmCore, §6.2)│
│   app/Models, app/Models/Concerns (BelongsToTenant, Auditable)│
│   app/Platform/**        (export engine, shared services)     │
│   config/batsons.php     (platform DEFAULTS)                   │
│   database/migrations    (shared schema)                      │
│   → reusable across every client; NOT part of an export       │
└─────────────────────────────────────────────────────────────┘
                              ▲  merges over
┌─────────────────────────────────────────────────────────────┐
│ Client Deliverables — CLIENT LAYER  (owned by Client, §6.1)   │
│   config/clients/<key>.php   (branding, overrides, pricing)   │
│   app/Client/<Studly>/       (client-specific custom code)    │
│   Client Data                (all tenant_id-scoped rows)       │
│   → THIS is what the export produces                          │
└─────────────────────────────────────────────────────────────┘
```

Each tenant has a stable **client key** (`tenants.settings.client_key`, see
`Tenant::clientKey()`) that resolves its config file, code directory, and export
folder — independent of its display name.

## Export — `php artisan client:export {client}`
Produces `storage/app/exports/<key>-<ts>.zip` containing:
- `data/*.csv` — every Client Data table, tenant-scoped (credentials redacted).
- `config/<key>.php` + `code/<Studly>/` — the Client layer (Client Deliverables).
- `MANIFEST.json` + `README.md` — contents and the explicit **exclusions**
  (AmCore Technology, shared infra, subprocessor creds, other customers' data).

This is sufficient for the Client or a Client-authorized contractor to run the
deployment, while never shipping the reusable platform core.

## Fork — `php artisan client:fork {source} {new name}`
Creates a new tenant from an existing build's **reusable structure** (settings,
gates, equipment catalog) and scaffolds a fresh `config/clients/<key>.php`, with
**no** transactional Client Data. This is the "pivot into a new customer" path.

## Why the seam matters
The 10.16 MSA obligates AmCore to deliver a separable copy of a client's code +
data on exit. A naive multi-tenant SaaS has no such separable unit. Keeping all
client-specific material in the Client layer (config + `app/Client` + tenant
data) makes the obligation satisfiable without ever handing over AmCore
Technology — and makes new-customer forks a one-command operation.

## Boundary rules (keep the seam clean)
- Client-specific code goes in `app/Client/<Studly>/`, never in `app/Models` or `app/Platform`.
- Client-tunable values go in `config/clients/<key>.php` overrides, not hard-coded in core.
- Anything reusable across clients stays in the platform core and is **excluded** from exports.
