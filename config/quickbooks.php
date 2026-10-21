<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| QuickBooks connection + data migration (SOW §4)
|--------------------------------------------------------------------------
| Two ways in: (1) files the Client exports from QuickBooks (Online or
| Desktop) or from their spreadsheets — CSV, parsed by the Platform importer;
| (2) a direct QuickBooks Online connection (OAuth 2.0) once the Client grants
| administrative access. Either way every row is recorded, every skipped row
| gets a reason, and re-running a file updates instead of duplicating.
*/
return [
    // How far back the included import reaches (SOW §4: up to eight years; §9: Client supplies four).
    'years' => (int) env('QB_IMPORT_YEARS', 8),

    // QuickBooks Online OAuth 2.0 app credentials — set all four to enable the live connection.
    'online' => [
        'client_id' => env('QBO_CLIENT_ID'),
        'client_secret' => env('QBO_CLIENT_SECRET'),
        'realm_id' => env('QBO_REALM_ID'),
        'refresh_token' => env('QBO_REFRESH_TOKEN'),
        'environment' => env('QBO_ENVIRONMENT', 'production'), // production|sandbox
    ],

    // Where uploaded exports are kept (local disk, never public).
    'upload_dir' => 'migrations',

    // Cap on issues stored per batch (counts are always complete).
    'max_issues' => 500,
];
