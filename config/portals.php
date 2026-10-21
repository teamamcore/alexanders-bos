<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Vendor portal integrations (SOW §3.14)
|--------------------------------------------------------------------------
| Up to five portals are included. Each one is built through the portal's API
| or another supported connection method; a portal that refuses access is
| marked blocked and does not count toward the five. The adapters below share
| one interface so the import → job → status → invoice → payment flow is the
| same regardless of portal.
*/
return [
    'included' => 5,

    'adapters' => [
        'sandbox' => [
            'label' => 'Sandbox (demo portal)',
            'docs' => null,
            'connection_fields' => [],
            'description' => 'Built-in simulated portal: emits sample work requests and acknowledges pushes. Use it to walk the full flow before real credentials arrive.',
        ],
        'service_channel' => [
            'label' => 'ServiceChannel',
            'docs' => 'https://developer.servicechannel.com/',
            'connection_fields' => ['base_url', 'client_id', 'client_secret', 'username', 'password', 'subscriber_id'],
            'description' => 'OAuth2 client credentials against the ServiceChannel API (work orders, notes/status, proposals, invoices). Provider approval is required before go-live.',
        ],
        'paramark' => [
            'label' => 'Paramark',
            'docs' => null,
            'connection_fields' => ['base_url', 'api_key', 'vendor_id'],
            'description' => 'Paramark vendor API (work requests, status updates, invoice submission). Access is granted by the portal provider.',
        ],
        'manual' => [
            'label' => 'Manual / email (no API)',
            'docs' => null,
            'connection_fields' => ['portal_url', 'login_hint'],
            'description' => 'For portals without an integration path: requests are keyed in from the portal email or screen; status and invoices are entered on the portal by hand and recorded here.',
        ],
    ],

    // Portal "trade" / category → our work_type (drives qualification indicators, reminders, reporting).
    'trade_map' => [
        'PLUMBING' => 'sewer',
        'DRAIN' => 'sewer',
        'SEWER' => 'sewer',
        'BACKFLOW' => 'backflow_testing',
        'WATER HEATER' => 'water_heater',
        'CAMERA' => 'camera_inspection',
        'EXCAVATION' => 'excavation',
        'GAS' => 'gas',
    ],

    // Our work-order state → status the portal expects (per adapter; sandbox uses these too).
    'status_map' => [
        'scheduled' => 'SCHEDULED',
        'in_progress' => 'IN_PROGRESS',
        'complete' => 'COMPLETED',
    ],

    // Sandbox fixtures: what the demo portal "issues" on the next poll.
    'sandbox_requests' => [
        ['external_id' => 'SC-88213', 'external_number' => '88213', 'customer_name' => 'National Retail Co.', 'location_name' => 'Store #0417', 'address' => '2100 Commerce Pkwy',
            'trade' => 'PLUMBING', 'priority' => 'P2 — 48 hr', 'nte' => 750.00, 'description' => 'Restroom floor drain backing up; customers complaining of odor.', 'scheduled_by' => '+2 days'],
        ['external_id' => 'SC-88240', 'external_number' => '88240', 'customer_name' => 'National Retail Co.', 'location_name' => 'Store #0422', 'address' => '905 Ridge Rd',
            'trade' => 'BACKFLOW', 'priority' => 'P3 — 7 day', 'nte' => 450.00, 'description' => 'Annual backflow certification due; submit test report to city.', 'scheduled_by' => '+7 days'],
    ],
];
