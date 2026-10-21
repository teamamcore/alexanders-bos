<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Batsons Business Operating System — Domain Configuration
|--------------------------------------------------------------------------
| Config-driven values from SOW No. 01 and the MSA. Per the SOW, nearly
| everything here is meant to be tenant-configurable (terminology, rates,
| equipment types, pay rules, roles, gates, qualification values). This file
| holds the platform defaults; per-tenant overrides live in the `tenants`
| settings and the module tables.
*/
return [

    // SOW §3.13 / Exhibit B.5 — three access tiers.
    'roles' => ['field', 'office', 'administrator'],

    // SOW §3.13 — configurable terminology + abbreviations. Platform defaults;
    // per-tenant overrides live in tenants.settings.terminology / .abbreviations
    // (Client layer) and are read through term() / abbr().
    'terminology' => [
        'customer' => 'Customer',
        'service_location' => 'Service Location',
        'job' => 'Job',
        'work_order' => 'Work Order',
        'proposal' => 'Proposal',
        'invoice' => 'Invoice',
        'schedule' => 'Schedule',
        'crew' => 'Crew',
    ],
    'abbreviations' => [
        'customer' => 'CUST',
        'service_location' => 'SITE',
        'job' => 'J',
        'work_order' => 'WO',
        'proposal' => 'PR',
        'invoice' => 'INV',
        'schedule' => 'SCH',
        'crew' => 'CRW',
    ],

    // SOW §3.15 / Exhibit A — included seats.
    'included_users' => 20,
    'additional_user_block' => 5,

    // SOW §3.2 — equipment types (tenant-editable).
    'equipment_types' => [
        'excavator', 'dump_truck', 'compactor', 'boring', 'core_drill',
        'concrete_saw', 'hammer_drill', 'sewer_machine', 'jetter',
        'video_inspection', 'sonic_detection',
    ],

    // SOW §3.8 — after-hours pay multipliers (configurable).
    'pay_rules' => [
        'after_hours' => [
            // Mon–Thu after regular hours
            ['days' => ['mon', 'tue', 'wed', 'thu'], 'from' => 'after_hours', 'multiplier' => 1.5],
            // Fri 12:00 PM through Sun
            ['days' => ['fri'], 'from' => '12:00', 'multiplier' => 2.0],
            ['days' => ['sat', 'sun'], 'from' => '00:00', 'multiplier' => 2.0],
        ],
        'time_categories' => ['travel', 'on_site', 'shop'],
    ],

    // SOW §3.11 — work types + qualification levels (set during discovery; tenant-
    // editable). Administrators assign a level per employee per work type; values
    // feed the dispatch board indicators (§3.3).
    'work_types' => [
        'backflow_testing' => 'Backflow testing',
        'sewer' => 'Sewer',
        'camera_inspection' => 'Camera inspection',
        'water_heater' => 'Water heater',
        'excavation' => 'Excavation',
        'gas' => 'Gas',
    ],
    'qualification_levels' => [0 => 'None', 1 => 'Helper', 2 => 'Qualified', 3 => 'Lead'],
    // Certification / licence expiry alerts this many days ahead.
    'certification_alert_days' => 60,
    // Background check: status + date only (MSA Ex. B.2 / B.5(g)).
    'background_check_statuses' => ['not_started', 'pending', 'cleared', 'flagged', 'expired'],

    // SOW §3.4 — customer communication. Platform default templates; tenant
    // overrides live in message_templates (edit under Messages → Templates).
    // Placeholders: {customer} {first_name} {company} {job} {job_number}
    // {work_order} {date} {window} {tech} {address} {eta} {note}
    'message_templates' => [
        'scheduled' => ['name' => 'Scheduled day', 'channel' => 'sms', 'subject' => '{company}: your visit is scheduled for {date}',
            'body' => 'Hi {first_name}, {company} has scheduled your {job} for {date} ({window}). Reply to this message with any questions.'],
        'rescheduled' => ['name' => 'Rescheduled', 'channel' => 'sms', 'subject' => '{company}: your visit has moved to {date}',
            'body' => 'Hi {first_name}, your {company} visit for {job} has moved to {date} ({window}). Sorry for the change — reply with any questions.'],
        'on_the_way' => ['name' => 'On the way', 'channel' => 'sms', 'subject' => '{company}: {tech} is on the way',
            'body' => '{tech} from {company} is on the way to {address}. {eta}'],
        'arrived' => ['name' => 'Arrived', 'channel' => 'sms', 'subject' => '{company}: {tech} has arrived',
            'body' => '{tech} from {company} has arrived at {address}.'],
        'work_started' => ['name' => 'Work started', 'channel' => 'sms', 'subject' => '{company}: work has started',
            'body' => '{company}: work has started on {job} ({work_order}).'],
        'work_complete' => ['name' => 'Work complete', 'channel' => 'sms', 'subject' => '{company}: work complete',
            'body' => '{company}: work on {job} is complete. Thank you! Your invoice will follow.'],
        'status_update' => ['name' => 'Status update', 'channel' => 'sms', 'subject' => '{company}: update on {job}',
            'body' => '{company} update on {job}: {note}'],
        'instructions' => ['name' => 'Instructions', 'channel' => 'sms', 'subject' => '{company}: a note from your technician',
            'body' => '{company}: {note}'],
        'service_reminder' => ['name' => 'Service reminder', 'channel' => 'sms', 'subject' => '{company}: your {service} is coming due',
            'body' => 'Hi {first_name}, {company} here — your {service} is due by {due_date}. Reply to this message or call us to get it on the schedule. {note}'],
    ],
    'message_placeholders' => [
        'customer' => 'Customer name', 'first_name' => 'First word of the customer name', 'company' => 'Your company name',
        'job' => 'Job title', 'job_number' => 'Job number', 'work_order' => 'Work order number', 'date' => 'Scheduled day',
        'window' => 'Scheduling window', 'tech' => 'Assigned technician', 'address' => 'Service address',
        'eta' => 'ETA text (on the way)', 'note' => 'Free text from the sender',
        'service' => 'Recurring service name (reminders)', 'due_date' => 'Service due date (reminders)',
    ],

    // SOW §3.9 — reporting & job costing.
    'payment_terms' => ['due_on_receipt' => 0, 'net_15' => 15, 'net_30' => 30],
    'collection_flag_days' => 30,      // past due beyond this → collection flag
    'labor_burden_pct' => 0.25,        // payroll taxes, insurance, benefits on top of wages
    'default_hourly_rate' => 32.00,    // used when an employee has no rate on file

    // SOW §3.7 — parts, equipment, fleet.
    'stock_adjustment_reasons' => [
        'used_on_job' => 'Used on job',
        'not_used' => 'Not used (returned to stock)',
        'billable_not_used' => 'Billable, not used',
        'breakage' => 'Breakage',
        'received' => 'Received (delivery)',
        'count_correction' => 'Count correction',
    ],
    'equipment_billing_units' => ['hour' => 'hour', 'day' => 'day', 'foot' => 'foot'],
    'equipment_cost_types' => ['maintenance', 'repair', 'parts', 'rental'],
    'vehicle_cost_types' => ['maintenance', 'repair', 'fuel'],
    'vehicle_service_items' => [
        'oil_change' => 'Oil change',
        'tires' => 'Tires / rotation',
        'brakes' => 'Brakes',
        'inspection' => 'State inspection',
    ],
    'renewal_alert_days' => 30,        // registration / inspection / insurance
    'fleet_mileage_stale_days' => 30,  // monthly mileage input reminder

    // SOW §3.3 — scheduling windows.
    'schedule_windows' => ['morning', 'afternoon', 'all_day', 'emergency', 'timed'],

    // SOW §3.5 — proposal pipeline stages.
    'proposal_stages' => ['draft', 'sent', 'follow_up', 'won', 'lost'],

    // SOW §3.10 — recurring reminder types (platform defaults; tenant overrides in
    // tenants.settings.reminder_types via Admin → Reminders). lead_days_max = when
    // the office is alerted / contact starts; lead_days_min = have it scheduled by.
    'reminder_types' => [
        'backflow_test' => ['label' => 'Annual backflow test', 'customer_label' => 'annual backflow device test',
            'interval_months' => 12, 'lead_days_min' => 45, 'lead_days_max' => 60, 'auto_message' => false, 'work_type' => 'backflow_testing'],
        'water_heater_service' => ['label' => 'Water heater service', 'customer_label' => 'water heater service',
            'interval_months' => 12, 'lead_days_min' => 7, 'lead_days_max' => 30, 'auto_message' => true, 'work_type' => 'water_heater'],
    ],

    // SOW §3.14 — vendor portal integrations.
    'vendor_portals' => [
        'included' => 5,
        'known' => ['service_channel', 'paramark'],
        'statuses' => ['planned', 'building', 'live', 'blocked'],
    ],

    // MSA Exhibit B.2 — Prohibited Data. These categories must NEVER be
    // persisted. Enforced by App\Rules\NoProhibitedData and reviewed in code.
    'prohibited_data' => [
        'payment_card_or_bank_numbers',
        'ssn_or_government_id',
        'health_or_medical',
        'biometric_templates', // device biometric sign-in stays on-device only
        'hipaa_or_pci_regulated',
    ],

    // MSA §8 / Exhibit B — operational targets the platform is built against.
    'service_targets' => [
        'availability_pct' => 99.5,
        'backup_interval_hours' => 24,
        'backup_retention_days' => 30,
        'rpo_hours' => 24,
        'restore_target_hours' => 72,
        'breach_notice_hours' => 72,
        'critical_patch_business_days' => 2,
        'data_export_formats' => ['csv', 'pdf'],
        'archive_after_years' => 5,
    ],

    // SOW §3 — module map (build tracker mirrors the Reminders list).
    'modules' => [
        '3.1' => 'Intake & Customer Records',
        '3.2' => 'Jobs & Work Orders',
        '3.3' => 'Scheduling & Dispatch',
        '3.4' => 'Customer Communication',
        '3.5' => 'Proposals & Estimating',
        '3.6' => 'Invoicing & Client Portals',
        '3.7' => 'Parts, Inventory, Equipment & Fleet',
        '3.8' => 'Time & Pay',
        '3.9' => 'Reporting & Job Costing',
        '3.10' => 'Recurring Service & Reminders',
        '3.11' => 'Team Records & Qualifications',
        '3.12' => 'Notifications & Messaging',
        '3.13' => 'Administration & Security',
        '3.14' => 'Vendor Portal Integrations',
        '3.16' => 'AI-Assisted Features',
    ],
];
