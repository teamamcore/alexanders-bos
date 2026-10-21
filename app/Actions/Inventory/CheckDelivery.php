<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\DeliveryCheck;
use App\Models\Part;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WorkOrder;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.7 — check on delivery: ordered vs received vs damaged, photo,
 * linked to the job and the item. Usable units go into stock as "received".
 */
final class CheckDelivery
{
    use AsAction;

    public function handle(
        Part $part,
        int $ordered,
        int $received,
        int $damaged,
        User $actor,
        ?WorkOrder $workOrder = null,
        ?Supplier $supplier = null,
        ?string $reference = null,
        ?string $notes = null,
        ?string $photoPath = null,
    ): DeliveryCheck {
        if ($ordered < 0 || $received < 0 || $damaged < 0 || $damaged > $received) {
            throw new DomainException('Quantities must be non-negative and damaged cannot exceed received.');
        }

        $check = $part->deliveryChecks()->create([
            'supplier_id' => $supplier instanceof Supplier ? $supplier->id : $part->supplier_id,
            'work_order_id' => $workOrder?->id,
            'reference' => $reference ?: null,
            'qty_ordered' => $ordered,
            'qty_received' => $received,
            'qty_damaged' => $damaged,
            'photo_path' => $photoPath,
            'notes' => $notes ?: null,
            'checked_by' => $actor->id,
        ]);

        // Only usable units enter stock; shortage + damage stay on the check record (item history).
        if ($check->usable() > 0) {
            AdjustStock::run($part, $check->usable(), 'received', $actor, $workOrder,
                'Delivery '.($reference ?: '#'.$check->id).($damaged > 0 ? " ({$damaged} damaged)" : ''));
        }

        return $check;
    }
}
