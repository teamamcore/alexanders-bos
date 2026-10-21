<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Build checklist — mirrors the Notion "Batsons BOS — Project Plan" Build
| Timeline, simplified and rendered in-app at /progress so the team can see
| what is done, what is in progress, and open the live screen for each
| shipped module. Keep this in sync with Notion whenever a status changes.
|
| status: done | in_progress | not_started
| link:   a named route (with optional params) that demonstrates the module
|--------------------------------------------------------------------------
*/
return [
    'as_of' => '2026-10-21',
    'day_one' => '2026-10-20', // tentative Day 1 (shift all dates to the real kickoff)

    'phases' => [
        'Kickoff' => 'Days 1',
        'Build' => 'Days 1–28 · Oct 20 – Nov 16',
        'Test' => 'Days 29–35 · Nov 17 – Nov 23',
        'Launch' => 'Days 36–42 · Nov 24 – Nov 30',
        'Turnover' => 'Day 42 · ~Nov 30',
        'Support' => 'Days 43–72 · Dec 1 – Dec 30',
    ],

    'items' => [
        ['ref' => 'Day 1', 'phase' => 'Kickoff', 'name' => 'Kickoff', 'status' => 'done',
            'target' => ['2026-10-21', null], 'notes' => 'Within 5 business days of signing + deposit.'],

        ['ref' => 'SOW §3.1', 'phase' => 'Build', 'name' => '3.1 Intake & Customer Records', 'status' => 'done',
            'target' => ['2026-10-21', '2026-10-23'], 'notes' => 'Intake → customer/job; Volt page + Actions.',
            'link' => ['route' => 'jobs.index', 'label' => 'Open Jobs']],
        ['ref' => 'SOW §3.2', 'phase' => 'Build', 'name' => '3.2 Jobs & Work Orders', 'status' => 'done',
            'target' => ['2026-10-22', '2026-10-26'], 'notes' => 'Work orders + status state machine + gates schema.',
            'link' => ['route' => 'jobs.index', 'label' => 'Open Jobs']],
        ['ref' => 'SOW §3.3', 'phase' => 'Build', 'name' => '3.3 Scheduling & Dispatch', 'status' => 'done',
            'target' => ['2026-10-25', '2026-10-29'], 'notes' => 'Dispatch board; schedule + reschedule history; qualification indicators.',
            'link' => ['route' => 'schedule.index', 'label' => 'Open Dispatch board']],
        ['ref' => 'SOW §3.4', 'phase' => 'Build', 'name' => '3.4 Customer Communication', 'status' => 'done',
            'target' => ['2026-10-28', '2026-10-30'], 'notes' => 'Scheduled/rescheduled/started/complete updates by text or email, on-the-way + arrived + free text from the field, office monitor, editable templates, inbound replies webhook, call transcripts.',
            'link' => ['route' => 'messages.index', 'label' => 'Open Messages']],
        ['ref' => 'SOW §3.5', 'phase' => 'Build', 'name' => '3.5 Proposals & Estimating', 'status' => 'done',
            'target' => ['2026-10-26', '2026-10-28'], 'notes' => 'Pipeline draft→won; convert to job.',
            'link' => ['route' => 'proposals.index', 'label' => 'Open Proposals']],
        ['ref' => 'SOW §3.6', 'phase' => 'Build', 'name' => '3.6 Invoicing & Client Portals', 'status' => 'done',
            'target' => ['2026-10-29', '2026-11-01'], 'notes' => 'Invoice per WO; progress payments; QuickBooks sync (status only).',
            'link' => ['route' => 'invoices.index', 'label' => 'Open Invoicing']],
        ['ref' => 'SOW §3.7', 'phase' => 'Build', 'name' => '3.7 Parts, Inventory, Equipment & Fleet', 'status' => 'done',
            'target' => ['2026-10-24', '2026-10-28'], 'notes' => 'Catalog + nickname search, price import, delivery checks, stock reasons; equipment billing/maintenance/check-out/depreciation; fleet mileage, service, renewals, TCO.',
            'link' => ['route' => 'parts.index', 'label' => 'Open Assets']],
        ['ref' => 'SOW §3.8', 'phase' => 'Build', 'name' => '3.8 Time & Pay', 'status' => 'done',
            'target' => ['2026-10-27', '2026-10-30'], 'notes' => 'Clock in/out, pay rules, QB payroll summary.',
            'link' => ['route' => 'time.index', 'label' => 'Open Time & Pay']],
        ['ref' => 'SOW §3.9', 'phase' => 'Build', 'name' => '3.9 Reporting & Job Costing', 'status' => 'done',
            'target' => ['2026-10-31', '2026-11-03'], 'notes' => 'A/R aging by terms with collection flags; job costing (labor + burden, materials, equipment); profitability by job type; equipment + fleet cost reports; trends; drill-down to work orders and per-job cost sheet.',
            'link' => ['route' => 'reports.index', 'label' => 'Open Reports']],
        ['ref' => 'SOW §3.10', 'phase' => 'Build', 'name' => '3.10 Recurring Service & Reminders', 'status' => 'done',
            'target' => ['2026-10-30', '2026-11-01'], 'notes' => 'Completed backflow / water-heater jobs start a recurring schedule; office alerted 60→45 days out (configurable), optional auto text, text/email/call, one-click job, closes + rolls on completion; admin-editable reminder types.',
            'link' => ['route' => 'reminders.index', 'label' => 'Open Reminders']],
        ['ref' => 'SOW §3.11', 'phase' => 'Build', 'name' => '3.11 Team Records & Qualifications', 'status' => 'done',
            'target' => ['2026-10-22', '2026-10-24'], 'notes' => 'Profiles, certs with expiry alerts, background check status, qualification matrix → dispatch indicators.',
            'link' => ['route' => 'team.index', 'label' => 'Open Team']],
        ['ref' => 'SOW §3.12', 'phase' => 'Build', 'name' => '3.12 Notifications & Messaging', 'status' => 'done',
            'target' => ['2026-10-29', '2026-10-31'], 'notes' => 'Event-based in-app notifications (assignments, schedule changes, replies, issues, deliveries, daily digest), user-to-user inbox, inbound email routing by job number / sender.',
            'link' => ['route' => 'inbox.index', 'label' => 'Open Inbox']],
        ['ref' => 'SOW §3.13', 'phase' => 'Build', 'name' => '3.13 Administration & Security', 'status' => 'done',
            'target' => ['2026-10-21', '2026-10-22'], 'notes' => 'Roles, passkeys, user setup/deactivation, terminology, security overview.',
            'link' => ['route' => 'admin.users', 'label' => 'Open Admin', 'admin' => true]],
        ['ref' => 'SOW §3.14', 'phase' => 'Build', 'name' => '3.14 Vendor Portal Integrations', 'status' => 'done',
            'target' => ['2026-10-28', '2026-11-03'], 'notes' => 'Portal framework: import → job/WO → status + invoice back → payment; sandbox adapter walks the flow; ServiceChannel + Paramark adapters wired behind provider credentials; manual/email path; blocked portals don\'t count toward 5.',
            'link' => ['route' => 'portals.index', 'label' => 'Open Portals']],
        ['ref' => 'SOW §3.16', 'phase' => 'Build', 'name' => '3.16 AI-Assisted Features', 'status' => 'done', 'link' => ['route' => 'search', 'label' => 'Open Search'],
            'target' => ['2026-11-01', '2026-11-03'], 'notes' => 'One search box for everything (AI-planned), tag suggestions on work orders/calls, in-app help (?) grounded in the guide, call summaries + action items, metered usage (Admin → AI usage). Claude via ANTHROPIC_API_KEY; offline fallback without it. Audio transcription waits on an STT provider.'],
        ['ref' => 'SOW §3.4 / Ex. A', 'phase' => 'Build', 'name' => 'Twilio A2P 10DLC + phone lines (port & connect) — kick off', 'status' => 'not_started',
            'target' => ['2026-10-26', '2026-11-20'], 'notes' => 'TIME-SENSITIVE: A2P brand + campaign approval and number porting take 1–4 weeks; start Mon 10/26 so texting, call transcription and the inbound email address are live before Turnover.'],
        ['ref' => 'SOW §4', 'phase' => 'Build', 'name' => 'Data migration (QuickBooks, 4 yrs)', 'status' => 'not_started',
            'target' => ['2026-10-28', '2026-11-17'], 'notes' => 'Import customers, items, open A/R, WO records.'],

        ['ref' => 'Week 5', 'phase' => 'Test', 'name' => 'Walkthrough + field test', 'status' => 'not_started',
            'target' => ['2026-11-18', '2026-11-24'], 'notes' => 'Up to 2 revision rounds; live field test.'],
        ['ref' => 'Week 6', 'phase' => 'Launch', 'name' => 'Training + company-wide cutover', 'status' => 'not_started',
            'target' => ['2026-11-25', '2026-12-01'], 'notes' => '15 hrs role-based training.'],
        ['ref' => 'Day 42', 'phase' => 'Turnover', 'name' => 'Turnover / Go-Live', 'status' => 'not_started',
            'target' => ['2026-12-01', null], 'notes' => 'Production use; starts monthly fee + support window.'],
        ['ref' => 'Days 43–72', 'phase' => 'Support', 'name' => 'Priority support (30 days)', 'status' => 'not_started',
            'target' => ['2026-12-02', '2026-12-31'], 'notes' => 'Priority support after Turnover.'],
    ],

    'notion_url' => 'https://app.notion.com/p/3fb9ddb42a0381f9aaf0de03787698ab',
];
