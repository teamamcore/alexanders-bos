<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CLIENT LAYER — Batsons  ("Client Deliverables", MSA §6.1)
|--------------------------------------------------------------------------
| This file is part of the CLIENT layer, not the platform core. It holds the
| client-specific overrides that make this deployment "Batsons" — branding,
| terminology, rates, and any values tuned during discovery. Everything here
| is owned by the Client and travels in the export bundle. Platform defaults
| live in config/batsons.php (AmCore Technology, §6.2) and are NOT exported.
*/

return [
    'key' => 'batsons',
    'display_name' => 'Batsons Plumbing, Inc.',
    'trade' => 'Plumbing — commercial, industrial, institutional, residential (T&M)',

    // Client-specific overrides of platform defaults (merged over config/batsons.php).
    'overrides' => [
        // e.g. 'equipment_types' => [...], 'pay_rules' => [...], 'reminder_types' => [...]
    ],

    // Branding / terminology (Client-owned).
    'branding' => [
        'primary_color' => '#1f6feb',
        'terminology' => [
            // 'work_order' => 'Ticket', etc.
        ],
    ],
];
