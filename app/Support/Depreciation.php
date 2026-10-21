<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/** SOW §3.7 — straight-line depreciation for equipment and vehicles. */
final class Depreciation
{
    /**
     * @return array{annual: float, to_date: float, book_value: float, pct: float}
     */
    public static function straightLine(?float $cost, ?float $salvage, ?int $years, ?Carbon $purchasedAt, ?Carbon $asOf = null): array
    {
        $cost = (float) $cost;
        $salvage = (float) $salvage;
        if ($cost <= 0 || ! $years || $years <= 0 || ! $purchasedAt) {
            return ['annual' => 0.0, 'to_date' => 0.0, 'book_value' => $cost, 'pct' => 0.0];
        }

        $annual = max(0.0, ($cost - $salvage) / $years);
        $elapsedYears = min((float) $years, max(0.0, (float) $purchasedAt->diffInYears($asOf ?? now())));
        $toDate = round($annual * $elapsedYears, 2);

        return [
            'annual' => round($annual, 2),
            'to_date' => $toDate,
            'book_value' => round(max($salvage, $cost - $toDate), 2),
            'pct' => $cost > 0 ? round($toDate / $cost * 100, 1) : 0.0,
        ];
    }
}
