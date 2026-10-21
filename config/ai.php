<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| AI-assisted features (SOW §3.16)
|--------------------------------------------------------------------------
| Claude (Anthropic API) powers search planning + answers, tag suggestions,
| in-app help, and call summaries. Without an API key the platform falls back
| to a deterministic offline assistant so every feature still works (keyword
| search, rule-based tags, doc-section help) — it just isn't "smart".
| Usage is metered per call (ai_usages) because Exhibit A bills it monthly.
*/
return [
    'api_key' => env('ANTHROPIC_API_KEY'),
    'driver' => env('AI_DRIVER', env('ANTHROPIC_API_KEY') ? 'claude' : 'offline'), // claude|offline
    'model' => env('AI_MODEL', 'claude-opus-5-5'),
    'effort' => env('AI_EFFORT', 'low'),       // search planning / tagging / help are routine → low
    'max_tokens' => 4000,
    'timeout' => 45,

    // $ per million tokens — for the usage meter (Exhibit A pass-through). Keep in sync with pricing.
    'pricing' => [
        'claude-opus-5-5' => ['input' => 4.00, 'output' => 20.00, 'cache_read' => 0.20],
        'claude-sonnet-5-5' => ['input' => 2.00, 'output' => 10.00, 'cache_read' => 0.20],
        'claude-haiku-5-5' => ['input' => 0.10, 'output' => 0.50, 'cache_read' => 0.01],
    ],

    // Controlled vocabulary the tagger prefers (it may add a few free tags).
    'tag_vocabulary' => [
        'backflow', 'sewer', 'drain', 'water-heater', 'gas', 'leak', 'camera', 'excavation', 'repipe', 'fixture',
        'emergency', 'warranty', 'callback', 'permit', 'inspection', 'commercial', 'residential', 'recurring',
        'parts-needed', 'quote-requested', 'customer-approval', 'after-hours', 'safety',
    ],

    // Docs the help assistant grounds on (relative to base_path()).
    'help_sources' => ['docs/overview.md', 'docs/spec.md', 'docs/SECURITY.md', 'docs/PARAMETERS.md', 'docs/design-notes-2026-10.md'],

    // Speech-to-text for uploaded call audio. Claude does not take audio; plug a
    // provider in here (none = transcripts are typed/pasted; summaries still use Claude).
    'transcription_driver' => env('AI_TRANSCRIPTION_DRIVER', 'none'),
];
