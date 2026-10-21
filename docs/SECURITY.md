# Security Overview

**Batsons Business Operating System** — written security overview required by SOW No. 01 §3.13. It describes hosting, encryption, backups and access controls as the platform is built and operated under the Master Service Agreement (MSA v6, 10/16/2026) §8 and Exhibit B. Where a value is a contractual target it is marked as such; the live posture panel shows what is in effect right now.

## Hosting

- Single-purpose, isolated deployment for Batsons: the application and its database serve this company only. Every record carries a company key and every query is filtered by it at the framework level (MSA §2.2, §6.1).
- Hosted on a managed US cloud provider under AmCore's account. AmCore may change provider or sub-processor without reducing the protections below (MSA §2.3). The provider in effect at Turnover is listed in the Turnover notice.
- Access to servers is limited to AmCore engineers, via key-based SSH with MFA on the provider console (Ex. B.5c). No shared accounts.
- Application traffic is served only over HTTPS; plain HTTP redirects.

## Encryption

- **In transit:** TLS 1.2 or newer for every connection — browser, mobile device, QuickBooks and vendor-portal integrations.
- **At rest:** provider disk encryption (AES-256) on database volumes and backups. Secrets and integration tokens are stored encrypted in the application's secret store, never in source control.
- **Passwords** are stored as one-way bcrypt hashes. Passkeys store only a public key; the private key and any biometric stay on the user's device (Ex. B.2(d), B.5(b)).

## Backups and recovery

| Control | Target (MSA §8 / Ex. B) |
|---|---|
| Automated backup | every 24 hours |
| Backup retention | 30 days |
| Recovery point (max data loss) | 24 hours |
| Restore target | 72 hours |
| Availability | 99.5% monthly, excluding announced maintenance |

- Backups are encrypted and stored in a separate location from the live database.
- A restore drill is performed before Turnover and at least annually.
- Client Data and Client Deliverables can be exported at any time as CSV (and PDF for documents) with `client:export`, and are provided on exit per MSA §11.3 and §11.4.

## Access controls

- **Three roles** (SOW §3.13, Ex. B.5(b)): *field* users see only work assigned to them; *office* users see all company operations; *administrators* also manage users, terminology and security.
- **Sign-in:** email + password, or **passkey / device biometric** (Face ID, Touch ID, Windows Hello, security key). Passkeys are phishing-resistant and recommended for everyone.
- **Lockout:** five failed sign-in attempts throttle the account and address.
- **Deactivation:** administrators deactivate departed users from *Administration → Users*. Deactivation ends live sessions immediately and blocks all sign-in methods; history is retained (MSA §5.1).
- **Least privilege for AmCore:** AmCore staff access production only for support, maintenance or at Client's request, and that access is logged (Ex. B.5).

## Logging and monitoring

- An append-only audit trail records who changed what and when for user administration, time adjustments, gate overrides, schedule changes and invoice status (Ex. B.5(d)).
- Application errors and uptime are monitored; critical security patches are applied within 2 business days of release.

## Prohibited data (hard constraint)

The platform is not designed to hold regulated data and must never store: payment card or bank account numbers, Social Security or government ID numbers, health or medical information, or biometric templates (MSA Ex. B.2). Payments stay in QuickBooks; invoices carry status only. Free-text fields are screened for card and SSN patterns as a guardrail.

## Incident response

If AmCore becomes aware of a breach affecting Client Data it notifies Client within 72 hours with what is known, what was affected and the steps being taken (MSA §8, Ex. B).

## Vendor and third-party services

QuickBooks, vendor portals (e.g. Service Channel, Paramark), SMS/email delivery and AI features are Third-Party Services under the MSA. Each connection uses the provider's supported API with scoped credentials that can be revoked from the integration's settings.

---
*Maintained in the repository at `docs/SECURITY.md` and rendered in-app under Administration → Security. Review at each release and at least annually.*
