# Batsons BOS — Design decisions from the Oct 7–8, 2026 planning meetings

Context document for anyone (human or Claude session) working on the prototype.
Distilled from three meeting-note docs in the shared Drive folder "Batson's Meeting
Notes" plus the whiteboard photos saved alongside this file in `docs/design/`.
**Read the whiteboard photos for layout** — they are the wireframes:

| Photo | What it shows |
|---|---|
| `docs/design/work-order.jpeg` | Office (desktop) Work Order Parent layout + tech mobile view (right side) |
| `docs/design/scheduling.jpeg` | Dispatch day view: roster row, All-Day/AM/PM lanes, W.O.S side panel |
| `docs/design/contacts.jpeg` | Batsons Contacts (business/customer) page layout |
| `docs/design/crm.jpeg`, `crm-mind-map.jpeg` | **AmCore-internal CRM concept — NOT a Batsons module** (see §7) |
| `docs/design/valve-icon.jpeg`, `toilet-paper-icon.jpeg` | Plumbing-themed icon concepts |

Source docs (Google Drive): "10-07 Work Order UI/UX, Workflow, and Data
Architecture", "10-07 Work Order Completion Flow, Time Tracking, Plumbing-Themed
UI", "10-08 Scheduling Module and Dispatch System Design".

---

## 1. Work Order module — the "Work Order Parent" (SOW §3.2, §3.6)

### Data-architecture vocabulary (used throughout the meetings)
- **Entity / Parent / Child** (+ "sibling"/"cousin" for summary cards). The work
  order *parent* holds **everything** about the job; *children* are
  purpose-specific views that pull from the parent (scheduling child, hours
  child, contact-on-a-job child). Information is never sourced from multiple
  places — it flows parent → child.
- The invoice is a child of the work order: `WO123` becomes `INV123`; every
  invoice field (amount, cost, contact, tagged conversation excerpts,
  description) sources from the WO parent.
- **Two back behaviors**: a back button that goes one tier up the hierarchy, AND
  history-based return (e.g., jump from Inventory → a WO popover → back to
  Inventory). Related entities open as **pop-up portal modals** by default, with
  a standard trio of affordances on every reference chip: pop-out (modal),
  go-to-it link (navigate), and a tertiary stat (e.g., times used).
- **Semantic continuity**: once a concept has a position (conversation left,
  description right), it keeps that position on every screen, forever. Modal
  and navigation behavior must be identical across work orders, inventory,
  contacts.
- A stage-dynamic layout (intake vs. scheduled vs. active reordering the page)
  was floated and **rejected** — the parent view always looks the same.

### Page chrome (all desktop pages)
Persistent top bar: logo, date/time, current page name, user/profile, settings.
Collapsible left sidebar nav. Back button top-left. No hamburger menus.

### Office/desktop layout (see work-order.jpeg)
- **Header**: status as a sliding pill — `Inactive → Active → Billable →
  Paid/Closed`, with **Hold** ("waiting for parts" etc.) as a sub-state of
  Active. Office controls billing transitions. WO number. **Invoice button with
  a running cost tally** always visible (dollar amounts admin-only). "Book Now"
  CTA.
- **Customer block** (top-left): business parent + specific location (e.g.,
  Publix @ 123 McBee — many Publixes, the location disambiguates), primary
  contact + secondary contact (corporate POC vs. on-site manager; child
  inherits from customer-card parent).
- **Comms panel**: iMessage-style thread, collapsed to last 3–4 messages,
  expandable popover. **Two streams: internal comms and external (customer)
  comms.** Conversations are tagged per job, so a contact card can filter
  threads to one job.
- **Activity log**: last system event ("work started"), expandable — the office
  glances here first; movement in cost/activity signals when to drill in.
- **Description / Scope of Work**, three layers:
  1. customer-stated problem (office intake wrote it),
  2. field findings/discovery notes with timestamps,
  3. office-updated scope — **only the office updates the SOW**; additions are
     highlighted redline-style with a reference link to the note/conversation
     that authorized them.
- **Billables section** (hours · materials · equipment grouped as one zone):
  running time cards (who/when, regular + OT, collapsible for multi-week jobs),
  running materials list, and **equipment as its own category distinct from
  materials** (search/dropdown + hours used + billed amount). Each is a summary
  card that pops out to its own child view.
- **Notes** (bottom): tech note stream with three actions — `Save note`,
  `Send scope update` (pings the office dashboard), `Cancel`. Tech notes never
  reach the invoice or customer.
- **Redundant invoice triggers** on purpose: header tally, billables section,
  bottom of page — different office workflows, same action.

### Tech (mobile/field) view — deliberately minimal
Banner: Batsons logo, date/time, **status dropdown** (Active / On hold–parts /
Complete — the only states techs toggle). Stack: WO# + customer + location,
contact with chat bubble, read-only description/SOW, Notes box, Hours (clock
in/out), Materials & Equipment (search + quantity), icons for files / photos /
camera / send-update. **Techs never see pricing, billing, running dollar
amounts, or approvals.** "The work order feels like its own little website" —
not one long scroll.

### Completion flow (decided)
Questionnaire-style submission at job end, **pre-filled by anything logged
along the way**: walks category by category (notes → materials → equipment →
hours) asking "anything more?"; flags overtime; supports both working styles
(log-as-you-go breezes through; fill-at-the-end does it all there).

### Field↔office process truth (from Gus — field SME, drives permissions)
- Initial discovery is done by the **office** on the intake call → becomes the
  WO description.
- Tech investigates on site, talks to the customer about findings, but **never
  discusses pricing** (company policy). All billing/admin stays office-side.
- **Extra work requires office authorization before it starts**, even if the
  customer says "don't care what it costs, do it." Tech reports findings
  (`Send scope update` ping) → office prices it, verifies with customer,
  updates the SOW.
- The office needs a dashboard view of "actions needed" (scope-update pings).
- Per Gus: the old paper WO has distinct "description of issue" and "work
  performed" sections — mirror that; "scope of work" alone is too broad.
- Design bar: "make it so Colin can use it" (Colin is seven).

## 2. Scheduling & Dispatch (SOW §3.3) — see scheduling.jpeg

- **Day view**: top **Roster row** (team slots, dropdown-built: pick members →
  that's a team; ~5 crews now, 7–8 max), then lanes: **All Day (thin), AM, PM**;
  one column per team; WO cards show customer/location/WO#.
- **Right panel "W.O.S"**: the work-order list with `+WO` quick-create;
  categories **Unscheduled / On Hold / Scheduled** — must mirror the Work
  Orders module *exactly* (continuity). Card snapshot header: customer, WO#,
  location, scheduled state. Click = summary popover; button = full modal.
  Drag-and-drop from panel onto the schedule.
- **Half/multi-day**: days counter on the card (e.g., `0.5`); multi-day
  schedules auto-populate each day; multi-day items stay visible ("Scheduled
  Friday") in the list.
- **Emergency flow**: Emergency control at top + on each card ("adjust"). An
  emergency WO defaults to **All Day**, gets a **red frame**; the team's
  current job grays out and auto-moves to On Hold/reassign. Creating an
  emergency opens a **dispatch center**: available crews with capability tier +
  availability, and a map — red pin = emergency, blue = active jobs, yellow =
  next scheduled jobs, with driving distances. (Map is a stretch goal — "we
  won't promise it." Note: meeting said Mapbox; the later integrations decision
  locked **Google Maps** — use Google Maps.)
- **Assignment hierarchy**: 1) capability (Eric defines tier 1/2/3 per work
  type; WOs tagged with required tier — *still owed by Eric*), 2) availability
  (job priority + load), 3) distance (left to dispatcher judgment, no
  auto-assignment).
- **Availability & queueing**: completing a WO frees the rest of the team's
  day; support a priority-1/priority-2 queue per team/day — finishing P1
  activates P2.
- **Week/Month views**: same structure simplified; probably no crew lanes
  (explicitly left to our discretion). Vacation time should surface on the
  month view.
- On-hold needs a **reasons taxonomy** (waiting for parts, customer approval,
  discovery follow-up) and **part-arrival triggers**: when a tracked part
  arrives, tag "parts are here" → WO moves to ready-to-schedule and dispatch
  gets notified (ties into the Inventory module).

## 3. Contacts module (SOW §3.1) — see contacts.jpeg

Business page: corp-info header (business name, segment tag, corp POC, contact
owner, **Paid $ YTD**), **Locations list** where each location pairs with its
local contact (123 McBee — Bob; 456 Woodruff Rd — Bill), **Comms** panel
(threads, filterable per location/job), and an **AR/Report** mini chart
(revenue over time). Right rail icons: alerts, notes, events, contacts,
network, billing, messaging. Same parent-child logic: business = parent,
location contacts = children, a job's contact card = child filtered to that
job's threads.

## 4. Iconography & UI identity

Two complete, client-selectable icon sets: **playful plumbing-themed** and
**standard** — Eric picks. Decided playful concepts:
- Contacts = men/women restroom symbols split by a line (team favorite)
- Administration = toilet paper roll (see toilet-paper-icon.jpeg)
- Invoices = water meter with $ and digits
- Reports = pressure gauge (see valve-icon.jpeg)
- Schedule = peel-away calendar on a toilet-paper roll, filled banner per
  sheet, **no words in icons**
- Alerts = leaking pipe/valve with a drip (or chat-bubble spurt)
- Notifications = sprinkler head (red badge)
- Proposals/Bids = handshake (alt: literal pipeline)
- Work orders = wrench
- Unresolved: Inventory (idea: empty TP holder with "!"), final Admin pick.

**Settings vs. Administration** (open): user-level settings (profile) vs.
business-level Administration (users, rates, fully-burdened labor numbers).
Leaning two separate areas; possibly admin nested inside settings. Needs a
decision before the nav is final.

## 5. Time tracking (SOW §3.8)

Start/stop clock in/out preferred — minute-accurate pay including OT rather
than rounded hours. Geofencing is a possible layer (clock in only on site, or
auto "traveling" status) — **open, needs Eric/office input on worker
pushback** (owner: Eric). Flags for anomalies (e.g., no lunch stop) that the
office can question. Travel time is its own category (already in SOW §3.8).

## 6. Cross-cutting rules

- "As complicated as it needs to be, as simple as you want it to be" — every
  view must zoom out to a summary and zoom in to everything.
- High-redundancy iconography: every symbol instantly identifiable, low
  friction, reused identically everywhere.
- Internal vs. external is **office vs. field**, not company vs. client — the
  office verifies what the client said; the field reports what it found.
- Alpha plan: finish Alpha from this material → full workflow walkthrough with
  Eric **before** beta (validate operational flow, not UI). A comprehensive
  **workflow document** is still owed (assigned "Claude" in the meeting).
- Deposit not yet received — third-party integrations are gated on it
  (Exhibit A: deposit due before kickoff).

## 7. AmCore-internal CRM (crm.jpeg, crm-mind-map.jpeg) — out of scope here

These two boards sketch **AmCore's own CRM** for tracking its clients
(Batsons etc.): client card with service type, phone/email/address/website,
tabs (Dash / Opp / Contacts / Events / Support), live-services links (client's
OS, website, CRM), metrics (uptime, tickets, revenue, events), active contacts
(Eric, Dany, Gus), opportunities, historical data, cross-business referral
network. **Do not build this into the Batsons repo** — separate product.
Captured here only so the photos make sense.

## 8. Reconciliation checklist vs. already-built modules

Modules 3.1/3.2/3.3/3.8 are marked shipped in the Build Timeline, but these
meetings specify details to verify against the implementation:

- [ ] Completion questionnaire (pre-filled, category-by-category) on WO submit
- [ ] Status pill taxonomy incl. Hold sub-state + on-hold reasons taxonomy
- [ ] Techs cannot see pricing/billing anywhere in field views
- [ ] Internal vs. external notes/comms split; tech notes never on invoices
- [ ] `Send scope update` ping + office "actions needed" dashboard
- [ ] Office-only SOW editing with redline-style authorized additions
- [ ] Equipment as its own billable category (not materials)
- [ ] Redundant invoice buttons on the office WO page
- [ ] Scheduling day view: roster row + All Day/AM/PM lanes, drag-and-drop
- [ ] W.O.S panel categories mirror Work Orders module exactly
- [ ] Half-day counter, multi-day population, P1/P2 queueing
- [ ] Emergency flow (red frame, All-Day default, auto-hold of current job)
- [ ] Capability tiers on employees + required tier on WOs (needs Eric's tiers)
- [ ] Parts-arrival trigger → ready-to-schedule + dispatch notification
- [ ] Contacts: locations-with-local-contact list, Paid $ YTD, AR mini chart
- [ ] Two icon sets, client-selectable
