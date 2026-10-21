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

    // SOW §3.3 — scheduling windows.
    'schedule_windows' => ['morning', 'afternoon', 'all_day', 'emergency', 'timed'],

    // SOW §3.5 — proposal pipeline stages.
    'proposal_stages' => ['draft', 'sent', 'follow_up', 'won', 'lost'],

    // SOW §3.10 — recurring reminder types (tenant-configurable).
    'reminder_types' => [
        'backflow_test' => ['lead_days_min' => 45, 'lead_days_max' => 60],
        'water_heater_service' => ['interval_months' => 12],
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
