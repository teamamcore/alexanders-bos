<?php

declare(strict_types=1);

namespace App\Platform\Messaging;

/** Outbound SMS transport (platform core — provider swappable, MSA §2.3). */
interface SmsDriver
{
    /** @return string provider message reference */
    public function send(string $to, string $body, string $from): string;
}
