<?php

declare(strict_types=1);

namespace App\Actions\Time;

use App\Models\TimeEntry;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.8 / §3.9: resolve a closed time entry into billable/payable hours,
 * applying the pay-rule multiplier. Feeds payroll summary and job costing.
 */
final class PayForTimeEntry
{
    use AsAction;

    /** @return array{hours: float, multiplier: float, classification: string, pay_hours: float} */
    public function handle(TimeEntry $entry): array
    {
        $hours = ($entry->started_at && $entry->ended_at)
            ? round((float) $entry->started_at->floatDiffInHours($entry->ended_at), 2)
            : 0.0;

        $pay = CalculatePay::run($entry->started_at ?? now());

        return [
            'hours' => $hours,
            'multiplier' => $pay['multiplier'],
            'classification' => $pay['classification'],
            'pay_hours' => round($hours * $pay['multiplier'], 2),
        ];
    }
}
