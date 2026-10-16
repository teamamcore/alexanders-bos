# Batsons BOS — Build Parameters

Non-functional and cross-cutting requirements pulled from **SOW No. 01** and the
**Master Service Agreement (MSA)**, incl. Exhibit A (Managed Hosting Schedule)
and Exhibit B (Data Handling Policy). These shape the architecture beyond the
16 feature modules. Citations are to the source sections.

## 1. Architecture
- **Multi-tenant SaaS**, fully hosted/managed (MSA §2.2). AmCore owns the Platform —
  configs, workflows, templates (MSA §6.2); Client owns Client Data (MSA §6.1).
  Components are reused across clients (MSA §6.3) → **tenant isolation from day one**.
  _Implemented:_ `tenant_id` on every Client Data table + `BelongsToTenant` global scope.
- **Config-driven**: terminology, field labels, rates, equipment types, pay rules,
  roles, gates, qualification values, reminder types, message templates are all
  tenant-configurable (SOW §3.13, §4, §3.2). _Implemented:_ `config/batsons.php` +
  `tenants.settings` JSON.
- AmCore may change frameworks/hosts/subprocessors freely (MSA §2.3) → keep infra
  swappable; no hard vendor lock-in in app code.

## 2. Roles & Access
- Three tiers: **field / office / administrator** (SOW §3.13, Ex. B.5b).
  _Implemented:_ `App\Enums\Role`, `users.role`.
- **Field users see only their assigned work** (SOW §3.3). _TODO:_ query scope on
  work orders / schedule for `role = field`.
- **Passkey + device biometric** sign-in; biometric stays on device (Ex. B.5b, B.2d).
- **MFA** on administrative systems (Ex. B.5c).
- User setup/deactivation; deactivate departed users promptly (MSA §5.1).
- **20 users** included; blocks of **5** (Ex. A; SOW §3.15).

## 3. 🔴 Prohibited Data (hard constraint — MSA Exhibit B.2)
Never persist any of:
- Payment card / bank account numbers
- SSNs / government ID numbers
- Health / medical information
- Biometric templates (device biometric stays on-device)
- Anything under HIPAA / PCI DSS

**Design implications:**
- **No card/bank fields anywhere.** Invoicing stores **payment status only**;
  money movement stays in QuickBooks. _Implemented:_ `invoices` has no card columns.
- Background checks = **status + date only**; supporting docs admin-restricted,
  not stored in-app (Ex. B.5g). _Implemented:_ `employees.background_check_status/date`.
- _Implemented guardrail:_ `App\Rules\NoProhibitedData` (Luhn + SSN detection) for
  free-text inputs. Guardrail, not a guarantee — client remains responsible (Ex. B.2).

## 4. Data Protection & Operations (MSA §8, Exhibit B)
| Parameter | Value | Source |
|---|---|---|
| Availability target | 99.5% / month | §8.1 |
| Downtime credit | 5% of monthly fee per excess hour, cap 50% | §8.2 |
| Backups | daily, separate storage, **≥30-day** retention, restore-tested yearly | §8.6, Ex. B.8 |
| RPO / restore target | ≤24h data / ≤72h service | §8.6 |
| Breach notification | ≤72h after confirmation | §7.3, Ex. B.9 |
| Critical security patches | ≤2 business days | §8.7 |
| Encryption | in transit **and** at rest | Ex. B.5a |
| Subprocessors | must hold SOC 2 Type II; 30-day notice for new ones | Ex. B.3–B.4 |
| Data export | CSV + PDF (with photos/docs); 30-day window post-termination | §11.3, Ex. B.8 |
| Archival | records >5 years may move to archival storage | Ex. B.8 |

**Audit trails required** (append-only): time-entry adjustments — who / prior value /
when (SOW §3.8); gate overrides — who / when / why (SOW §3.2); schedule/reschedule
history (SOW §3.3); plus general logging (Ex. B.5d).
_Implemented:_ `App\Models\Concerns\Auditable` → `audit_logs`, plus explicit columns
on `time_entries`, `gate_overrides`, `schedule_changes`.

## 5. Support Tiers (MSA §8.8)
| Severity | Definition | Response target |
|---|---|---|
| 1 — Critical | Platform / WO / scheduling / invoicing unusable, no workaround | 2h, 24×7 |
| 2 — High | Major function impaired for some, or workaround exists | 4 business hours |
| 3 — Normal | Minor defects, how-to, config requests | 1 business day |
Client-caused Sev 1 billed at **$100/hr, 1h min** (Ex. A, §8.9).

## 6. Integrations
- **QuickBooks** — invoicing, payroll summary, job costing (SOW §3.6, §3.8, §3.9).
  Supports **Desktop (local) and Online**; remains the accounting/payroll
  **system of record** (Ex. B.10d). _Modeled:_ `invoices.qb_synced_at`.
- **Texting platform** — usage-billed; customer consent + registration are Client's
  responsibility (SOW §3.4, MSA §5.6, Ex. B.7e).
- **AI subprocessors** — search/tagging/help + call transcription. No training on
  Client Data; call audio deleted ≤30 days; output must be human-verified
  (SOW §3.16, Ex. B.7). 
- **Vendor portals** — up to **5** included (Service Channel, Paramark, …), via API;
  blocked portals don't count toward 5; each goes live independently (SOW §3.14).
  _Modeled:_ `vendor_portal_integrations`.

## 7. Field / Mobile
- Mobile-first (phone/tablet, current OS); **offline capture + background sync**
  (SOW §3.2); voice-to-text notes; on-image photo markup; map screenshot + markup.
- Devices/data plans are Client's responsibility (SOW §9, MSA §5.2).

## 8. Domain Constants
- **Pay rules:** 1.5× Mon–Thu after-hours; 2× Fri 12:00pm–Sun (configurable) — SOW §3.8.
- **Time categories:** travel / on-site / shop — SOW §3.8.
- **Equipment types:** excavator, dump truck, compactor, boring, core drill,
  concrete saw, hammer drill, sewer machine, jetter, video inspection, sonic
  detection — SOW §3.2 (`config/batsons.php`).
- **Proposal stages:** draft / sent / follow-up / won / lost — SOW §3.5.
- **Schedule windows:** morning / afternoon / all-day / emergency / timed — SOW §3.3.
- **Invoice numbering paired with work orders**; progress payments + voids — SOW §3.6.
- **Reminders:** backflow test 45–60 days before due; water-heater service +12 months — SOW §3.10.

## 9. Out of Scope (don't build — SOW §10)
Batsons University training content; location-based time verification; vendor
portals beyond 5; devices/data plans; third-party licenses (QuickBooks); payroll
processing; legal/tax/accounting advice; data migration beyond 4 years / paper records.

## 10. Status
Prototype scaffold covers the **data model + guardrails** for modules 3.1–3.14, 3.16.
Business logic, UI, and integrations are stubs/TODO. See `config/batsons.php`
`modules` map and the "Batsons SOW" tracker.
