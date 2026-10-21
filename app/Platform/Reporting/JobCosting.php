<?php

declare(strict_types=1);

namespace App\Platform\Reporting;

use App\Actions\Time\CalculatePay;
use App\Models\Employee;
use App\Models\EquipmentUsage;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\StockAdjustment;
use App\Models\TimeEntry;
use App\Models\WorkOrder;

/**
 * SOW §3.9 — job cost for a work order / job:
 *   labor     = hours × pay-rule multiplier × employee hourly rate × (1 + labor burden)
 *   materials = parts used on the job (stock adjustments "used_on_job") at part cost
 *   equipment = equipment usage billed to the work order (internal rate)
 *   revenue   = approved / sent / paid invoices on the work order (voids excluded)
 *
 * @phpstan-type Cost array{labor_hours: float, labor: float, materials: float, equipment: float, cost: float, revenue: float, profit: float, margin: float|null}
 */
final class JobCosting
{
    public const array REVENUE_STATUSES = ['approved', 'sent', 'paid'];

    /** @return Cost */
    public static function forWorkOrder(WorkOrder $wo): array
    {
        $rates = self::rates();
        $burden = 1 + (float) config('batsons.labor_burden_pct', 0.25);
        $default = (float) config('batsons.default_hourly_rate', 32);

        $laborHours = 0.0;
        $labor = 0.0;
        foreach (TimeEntry::query()->where('work_order_id', $wo->id)->whereNotNull('ended_at')->get() as $entry) {
            $hours = (float) $entry->started_at->diffInHours($entry->ended_at);
            $multiplier = CalculatePay::run($entry->started_at)['multiplier'];
            $rate = (float) ($rates[$entry->user_id] ?? $default);
            $laborHours += $hours;
            $labor += $hours * $multiplier * $rate * $burden;
        }

        $materials = 0.0;
        foreach (StockAdjustment::query()->where('work_order_id', $wo->id)->where('reason', 'used_on_job')->with('part')->get() as $adj) {
            $unit = (float) ($adj->part->cost ?? $adj->part->price ?? 0);
            $materials += abs($adj->quantity) * $unit;
        }

        $equipment = (float) EquipmentUsage::query()->where('work_order_id', $wo->id)->sum('amount');
        $revenue = (float) Invoice::query()->where('work_order_id', $wo->id)->whereIn('status', self::REVENUE_STATUSES)->sum('total');

        return self::pack($laborHours, $labor, $materials, $equipment, $revenue);
    }

    /** @return Cost */
    public static function forJob(Job $job): array
    {
        $sum = ['labor_hours' => 0.0, 'labor' => 0.0, 'materials' => 0.0, 'equipment' => 0.0, 'revenue' => 0.0];
        foreach (WorkOrder::withoutGlobalScope('assignedToField')->where('job_id', $job->id)->get() as $wo) {
            $c = self::forWorkOrder($wo);
            foreach (array_keys($sum) as $k) {
                $sum[$k] += $c[$k];
            }
        }

        return self::pack($sum['labor_hours'], $sum['labor'], $sum['materials'], $sum['equipment'], $sum['revenue']);
    }

    /**
     * Sum a set of cost rows.
     *
     * @param  iterable<array-key, array<string, mixed>>  $rows  cost-shaped rows (extra keys ignored)
     * @return Cost
     */
    public static function total(iterable $rows): array
    {
        $sum = ['labor_hours' => 0.0, 'labor' => 0.0, 'materials' => 0.0, 'equipment' => 0.0, 'revenue' => 0.0];
        foreach ($rows as $c) {
            foreach (array_keys($sum) as $k) {
                $sum[$k] += (float) $c[$k];
            }
        }

        return self::pack($sum['labor_hours'], $sum['labor'], $sum['materials'], $sum['equipment'], $sum['revenue']);
    }

    /** @return array<int, float> user_id => hourly rate */
    private static function rates(): array
    {
        return Employee::query()->whereNotNull('user_id')->whereNotNull('hourly_rate')->pluck('hourly_rate', 'user_id')
            ->map(fn ($r) => (float) $r)->all();
    }

    /** @return Cost */
    private static function pack(float $hours, float $labor, float $materials, float $equipment, float $revenue): array
    {
        $cost = round($labor + $materials + $equipment, 2);
        $profit = round($revenue - $cost, 2);

        return [
            'labor_hours' => round($hours, 2),
            'labor' => round($labor, 2),
            'materials' => round($materials, 2),
            'equipment' => round($equipment, 2),
            'cost' => $cost,
            'revenue' => round($revenue, 2),
            'profit' => $profit,
            'margin' => $revenue > 0 ? round($profit / $revenue * 100, 1) : null,
        ];
    }
}
