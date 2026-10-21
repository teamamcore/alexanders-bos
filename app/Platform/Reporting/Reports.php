<?php

declare(strict_types=1);

namespace App\Platform\Reporting;

use App\Actions\Time\CalculatePay;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\EquipmentUsage;
use App\Models\Invoice;
use App\Models\StockAdjustment;
use App\Models\TimeEntry;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\States\WorkOrder\Completed;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * SOW §3.9 — the report queries. Every total carries a drill-down filter so
 * the UI can link to the underlying work orders / invoices.
 */
final class Reports
{
    public const array AGING_BUCKETS = ['current' => [null, 0], '1_30' => [1, 30], '31_60' => [31, 60], '61_90' => [61, 90], '90_plus' => [91, null]];

    /**
     * Accounts receivable by aging bucket and payment terms, with collection flags.
     *
     * @return array{open: Collection<int, Invoice>, buckets: array<string, array{label: string, total: float, count: int}>, by_terms: array<string, array{label: string, total: float, count: int, overdue: float}>, total: float, overdue: float, flagged: int}
     */
    public static function ar(): array
    {
        $flagDays = (int) config('batsons.collection_flag_days', 30);
        $open = Invoice::query()->with(['customer', 'workOrder.job'])
            ->whereIn('status', ['approved', 'sent'])->orderBy('due_at')->get();

        $buckets = [];
        foreach (self::AGING_BUCKETS as $key => [$min, $max]) {
            $buckets[$key] = ['label' => self::bucketLabel($key), 'total' => 0.0, 'count' => 0];
        }
        $byTerms = [];
        foreach (config('batsons.payment_terms', []) as $key => $days) {
            $byTerms[$key] = ['label' => self::termsLabel($key), 'total' => 0.0, 'count' => 0, 'overdue' => 0.0];
        }
        $overdue = 0.0;
        $flagged = 0;

        foreach ($open as $inv) {
            $days = self::daysPastDue($inv);
            $bucket = self::bucketFor($days);
            $buckets[$bucket]['total'] += (float) $inv->total;
            $buckets[$bucket]['count']++;
            $terms = $inv->terms ?: 'net_30';
            $byTerms[$terms] ??= ['label' => self::termsLabel($terms), 'total' => 0.0, 'count' => 0, 'overdue' => 0.0];
            $byTerms[$terms]['total'] += (float) $inv->total;
            $byTerms[$terms]['count']++;
            if ($days > 0) {
                $overdue += (float) $inv->total;
                $byTerms[$terms]['overdue'] += (float) $inv->total;
            }
            if ($days > $flagDays) {
                $flagged++;
            }
        }

        return [
            'open' => $open,
            'buckets' => $buckets,
            'by_terms' => $byTerms,
            'total' => round((float) $open->sum('total'), 2),
            'overdue' => round($overdue, 2),
            'flagged' => $flagged,
        ];
    }

    public static function daysPastDue(Invoice $inv): int
    {
        if (! $inv->due_at) {
            return 0;
        }

        return max(0, (int) $inv->due_at->diffInDays(today(), false));
    }

    public static function bucketFor(int $daysPastDue): string
    {
        foreach (self::AGING_BUCKETS as $key => [$min, $max]) {
            if (($min === null || $daysPastDue >= $min) && ($max === null || $daysPastDue <= $max)) {
                return $key;
            }
        }

        return '90_plus';
    }

    /** Collection flag: none | overdue | collection (past the configured threshold). */
    public static function collectionFlag(Invoice $inv): string
    {
        $days = self::daysPastDue($inv);
        if ($days > (int) config('batsons.collection_flag_days', 30)) {
            return 'collection';
        }

        return $days > 0 ? 'overdue' : 'none';
    }

    public static function bucketLabel(string $key): string
    {
        return ['current' => 'Current', '1_30' => '1–30 days', '31_60' => '31–60 days', '61_90' => '61–90 days', '90_plus' => '90+ days'][$key] ?? $key;
    }

    public static function termsLabel(string $key): string
    {
        return ['due_on_receipt' => 'Due on receipt', 'net_15' => 'Net 15', 'net_30' => 'Net 30'][$key] ?? ucwords(str_replace('_', ' ', $key));
    }

    /**
     * Profitability by job type (work order work_type), over work orders that have
     * cost or revenue in the period.
     *
     * @return Collection<array-key, array<string, mixed>> type, label, count + the JobCosting cost shape
     */
    public static function profitabilityByType(?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $wos = self::workOrdersInPeriod($from, $to);
        $labels = config('batsons.work_types', []);

        return $wos->groupBy(fn (WorkOrder $wo) => $wo->work_type ?: 'untyped')
            ->map(function (Collection $group, string $type) use ($labels) {
                $t = JobCosting::total($group->map(fn (WorkOrder $wo) => JobCosting::forWorkOrder($wo)));

                return ['type' => $type, 'label' => $labels[$type] ?? ($type === 'untyped' ? 'Untyped' : ucwords(str_replace('_', ' ', $type))), 'count' => $group->count()] + $t;
            })
            ->sortByDesc('profit');
    }

    /**
     * Monthly series for the trends dashboard (last N months, oldest first).
     *
     * @return list<array{month: string, label: string, invoiced: float, collected: float, cost: float, completed: int}>
     */
    public static function trends(int $months = 12): array
    {
        $out = [];
        $start = today()->startOfMonth()->subMonths($months - 1);
        for ($i = 0; $i < $months; $i++) {
            $m = $start->copy()->addMonths($i);
            $mEnd = $m->copy()->endOfMonth();
            $out[] = [
                'month' => $m->format('Y-m'),
                'label' => $m->format('M'),
                'invoiced' => round((float) Invoice::query()->whereIn('status', JobCosting::REVENUE_STATUSES)->whereBetween('issued_at', [$m, $mEnd])->sum('total'), 2),
                'collected' => round((float) Invoice::query()->where('status', 'paid')->whereBetween('paid_at', [$m, $mEnd])->sum('total'), 2),
                'cost' => round(self::costInMonth($m, $mEnd), 2),
                'completed' => WorkOrder::withoutGlobalScope('assignedToField')->whereState('status', Completed::class)->whereBetween('updated_at', [$m, $mEnd->copy()->endOfDay()])->count(),
            ];
        }

        return $out;
    }

    /** Per-item equipment economics (SOW §3.7 report surfaced in §3.9). */
    public static function equipment(): Collection
    {
        return Equipment::query()->orderBy('name')->get()->map(fn (Equipment $e) => [
            'item' => $e,
            'revenue' => $e->revenue(),
            'maintenance' => $e->maintenanceCost(),
            'depreciation' => $e->depreciation()['to_date'],
            'net' => $e->netContribution(),
            'hours' => (float) $e->hours_used,
        ])->sortByDesc('net')->values();
    }

    /** Per-vehicle cost of ownership. */
    public static function fleet(): Collection
    {
        return Vehicle::query()->orderBy('name')->get()->map(fn (Vehicle $v) => [
            'vehicle' => $v,
            'by_type' => $v->costByType(),
            'cost' => $v->costTotal(),
            'depreciation' => $v->depreciation()['to_date'],
            'tco' => $v->totalCostOfOwnership(),
            'mileage' => $v->mileage,
        ])->values();
    }

    /**
     * Work orders with cost or revenue activity in a period (default: everything).
     *
     * @return Collection<int, WorkOrder>
     */
    public static function workOrdersInPeriod(?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $q = WorkOrder::withoutGlobalScope('assignedToField')->with(['job.customer', 'assignee']);
        if ($from || $to) {
            $from ??= Carbon::parse('2000-01-01');
            $to ??= today()->endOfDay();
            $ids = collect()
                ->merge(Invoice::query()->whereBetween('issued_at', [$from, $to])->pluck('work_order_id'))
                ->merge(TimeEntry::query()->whereBetween('started_at', [$from, $to])->pluck('work_order_id'))
                ->merge(StockAdjustment::query()->whereBetween('created_at', [$from, $to])->pluck('work_order_id'))
                ->merge(EquipmentUsage::query()->whereBetween('used_on', [$from, $to])->pluck('work_order_id'))
                ->filter()->unique();
            $q->whereIn('id', $ids);
        }

        return $q->get();
    }

    private static function costInMonth(Carbon $from, Carbon $to): float
    {
        $burden = 1 + (float) config('batsons.labor_burden_pct', 0.25);
        $default = (float) config('batsons.default_hourly_rate', 32);
        $rates = Employee::query()->whereNotNull('user_id')->whereNotNull('hourly_rate')->pluck('hourly_rate', 'user_id')->map(fn ($r) => (float) $r)->all();

        $labor = 0.0;
        foreach (TimeEntry::query()->whereNotNull('ended_at')->whereBetween('started_at', [$from, $to->copy()->endOfDay()])->get() as $e) {
            $labor += (float) $e->started_at->diffInHours($e->ended_at) * CalculatePay::run($e->started_at)['multiplier'] * (float) ($rates[$e->user_id] ?? $default) * $burden;
        }
        $materials = 0.0;
        foreach (StockAdjustment::query()->where('reason', 'used_on_job')->whereBetween('created_at', [$from, $to->copy()->endOfDay()])->with('part')->get() as $a) {
            $materials += abs($a->quantity) * (float) ($a->part->cost ?? $a->part->price ?? 0);
        }
        $equipment = (float) EquipmentUsage::query()->whereBetween('used_on', [$from, $to])->sum('amount');

        return $labor + $materials + $equipment;
    }
}
