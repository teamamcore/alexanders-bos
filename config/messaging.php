<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Customer messaging (SOW §3.4)
|--------------------------------------------------------------------------
| Texting runs on AmCore's texting platform (monthly usage charge, Exhibit A).
| The SMS driver is swappable (MSA §2.3): "log" writes to the application log
| and the customer_messages table so the prototype is fully reviewable without
| a carrier account; a provider driver drops in behind the same interface.
*/
return [
    'sms_driver' => env('MESSAGING_SMS_DRIVER', 'log'),
    'sms_from' => env('MESSAGING_SMS_FROM', '+15555550100'),

    // Shared secret the texting platform sends with inbound webhooks (POST /webhooks/sms).
    'inbound_token' => env('MESSAGING_INBOUND_TOKEN', 'change-me'),

    // SOW §3.12 — the Client's one inbound email address; the mail provider
    // forwards it to POST /webhooks/email with the shared token above.
    'inbound_email_address' => env('MESSAGING_INBOUND_EMAIL', 'office@batsonsplumbing.com'),

    // Appended to outbound texts where required (opt-out language).
    'sms_footer' => env('MESSAGING_SMS_FOOTER', 'Reply STOP to opt out.'),
];
