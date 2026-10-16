<?php

declare(strict_types=1);

namespace App\Actions\Time;

use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.8 after-hours pay rules (configurable):
 *   • 1.5× Monday–Thursday after hours
 *   • 2×   Friday 12:00 PM through Sunday
 * Regular hours multiply by 1.0. Classified by the entry's start time.
 */
final class CalculatePay
{
    use AsAction;

    public const BUSINESS_START = '07:00';

    public const BUSINESS_END = '17:00';

    /** @return array{multiplier: float, classification: string} */
    public function handle(Carbon $start): array
    {
        $dow = (int) $start->dayOfWeek; // 0=Sun … 5=Fri, 6=Sat
        $time = $start->format('H:i');

        // Weekend premium: Friday noon through Sunday.
        if ($dow === Carbon::FRIDAY && $time >= '12:00') {
            return ['multiplier' => 2.0, 'classification' => 'weekend'];
        }
        if ($dow === Carbon::SATURDAY || $dow === Carbon::SUNDAY) {
            return ['multiplier' => 2.0, 'classification' => 'weekend'];
        }

        // Weekdays (Mon–Thu, and Fri before noon): after-hours if outside business hours.
        if ($time < self::BUSINESS_START || $time >= self::BUSINESS_END) {
            return ['multiplier' => 1.5, 'classification' => 'after_hours'];
        }

        return ['multiplier' => 1.0, 'classification' => 'regular'];
    }
}
