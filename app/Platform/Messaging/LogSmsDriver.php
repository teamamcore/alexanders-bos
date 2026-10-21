<?php

declare(strict_types=1);

namespace App\Platform\Messaging;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Development / prototype driver: the text goes to the log, nowhere else. */
final class LogSmsDriver implements SmsDriver
{
    public function send(string $to, string $body, string $from): string
    {
        $ref = 'log-'.Str::lower(Str::random(10));
        Log::info("SMS [{$ref}] {$from} → {$to}: {$body}");

        return $ref;
    }
}
