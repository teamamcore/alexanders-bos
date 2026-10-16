# Batsons BOS — Overview

Plain-language summary of what this app is and how it's built.

## What it is
The **Batsons Business Operating System** — a field-service operations platform
AmCore is building for Batsons Plumbing under SOW No. 01. It replaces paper work
orders and double entry (into Excel + QuickBooks) with one system that captures a
job once, in the field, and carries it through scheduling, invoicing, and follow-up.

## Who uses it
Three kinds of users (SOW §3.13):
- **Field** crews — see only their assigned work, capture photos/notes/time.
- **Office** staff — intake, scheduling, invoicing, reporting.
- **Administrators** — configuration, users, security.

## How it's built (short version)
- **Laravel** (server-driven), **Livewire + Volt** pages, **Pest** tests.
- **One self-contained, Client-owned repository** — the deliverable itself.
- **Multi-tenant**: every record carries a `tenant_id`, so one client's data is
  isolated. (AmCore reuses the core for other trade clients — MSA §6.2/§6.3.)
- Business operations are **Actions** (`app/Actions`); work-order status is a
  **state machine** (`app/States`); changes are **audited** (`audit_logs`).

## Ownership (new — MSA v6, 10/16/2026)
A **commissioned-software** model: on full payment, the **Client owns the
Batsons-specific deliverables and their source code** (§6.1), while **AmCore owns
the reusable core ("AmCore Technology")** and licenses it to the Client perpetually
(§6.2). The code is deliverable/exportable to the Client (§2.8, §11.3).

See `spec.md` for the technical detail and `reference.md` for commands/conventions.
