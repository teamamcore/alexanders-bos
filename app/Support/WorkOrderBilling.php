<?php

declare(strict_types=1);

namespace App\Support;

use App\Actions\Time\CalculatePay;
use App\Models\EquipmentUsage;
use App\Models\Invoice;
use App\Models\StockAdjustment;
use App\Models\TimeEntry;
use App\Models\WorkOrder;

/**
 * The running billable tally shown in the work-order header (design notes §1):
 * hours (regular / OT) at the customer labor rate, materials at part price,
 * equipment at the billed usage, against what has already been invoiced/paid.
 * Dollar amounts are for office/admin eyes only — techs never see them.
 *
 * @phpstan-type Tally array{regular_hours: float, ot_hours: float, hours: float, labor: float, materials: float, materials_qty: int, equipment: float, equipment_qty: int, total: float, invoiced: float, paid: float, uninvoiced: float}
 */
final class WorkOrderBilling
{
    /** @return Tally */
    public static function tally(WorkOrder $wo): array
    {
        $rate = (float) config('batsons.billing.labor_rate', 125);
        $otRate = (float) config('batsons.billing.ot_labor_rate', $rate * 1.5);

        $regular = 0.0;
        $ot = 0.0;
        foreach (TimeEntry::query()->where('work_order_id', $wo->id)->where('billable', true)->whereNotNull('ended_at')->get() as $e) {
            $h = (float) $e->started_at->diffInHours($e->ended_at);
            if (CalculatePay::run($e->started_at)['multiplier'] > 1) {
                $ot += $h;
            } else {
                $regular += $h;
            }
        }

        $materials = 0.0;
        $materialsQty = 0;
        foreach (StockAdjustment::query()->where('work_order_id', $wo->id)->where('reason', 'used_on_job')->with('part')->get() as $a) {
            $materials += abs($a->quantity) * (float) ($a->part->price ?? 0);
            $materialsQty += abs($a->quantity);
        }

        $equipment = (float) EquipmentUsage::query()->where('work_order_id', $wo->id)->sum('amount');
        $equipmentQty = EquipmentUsage::query()->where('work_order_id', $wo->id)->count();

        $labor = $regular * $rate + $ot * $otRate;
        $invoiced = (float) Invoice::query()->where('work_order_id', $wo->id)->where('status', '!=', 'void')->sum('total');
        $paid = (float) Invoice::query()->where('work_order_id', $wo->id)->where('status', 'paid')->sum('total');
        $total = round($labor + $materials + $equipment, 2);

        return [
            'regular_hours' => round($regular, 2),
            'ot_hours' => round($ot, 2),
            'hours' => round($regular + $ot, 2),
            'labor' => round($labor, 2),
            'materials' => round($materials, 2),
            'materials_qty' => $materialsQty,
            'equipment' => round($equipment, 2),
            'equipment_qty' => $equipmentQty,
            'total' => $total,
            'invoiced' => round($invoiced, 2),
            'paid' => round($paid, 2),
            'uninvoiced' => round(max(0, $total - $invoiced), 2),
        ];
    }
}
