<?php

declare(strict_types=1);

namespace App\Actions\Equipment;

use App\Models\Equipment;
use App\Models\EquipmentUsage;
use App\Models\User;
use App\Models\WorkOrder;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.7 — equipment used on a job is billed on the work order and linked
 * to the equipment record. Rate is snapshotted; hour-billed items accrue
 * hours_used, which drives maintenance-by-hours alerts.
 */
final class LogEquipmentUsage
{
    use AsAction;

    public function handle(Equipment $equipment, WorkOrder $workOrder, float $quantity, User $actor, ?string $usedOn = null): EquipmentUsage
    {
        if ($quantity <= 0) {
            throw new DomainException('Quantity must be greater than zero.');
        }

        $rate = (float) $equipment->billing_rate;
        $usage = $equipment->usages()->create([
            'work_order_id' => $workOrder->id,
            'quantity' => $quantity,
            'unit' => $equipment->billing_unit,
            'rate' => $rate,
            'amount' => round($quantity * $rate, 2),
            'used_on' => $usedOn ?: today(),
            'logged_by' => $actor->id,
        ]);

        if ($equipment->billing_unit === 'hour') {
            $equipment->forceFill(['hours_used' => (float) $equipment->hours_used + $quantity])->save();
        }

        return $usage;
    }
}
