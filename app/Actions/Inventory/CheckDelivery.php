<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Actions\WorkOrders\ReleaseHold;
use App\Models\DeliveryCheck;
use App\Models\Part;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WorkOrder;
use App\Platform\Notifications\Notifier;
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

        // Design notes §2 — part-arrival trigger: a WO waiting on parts goes back to ready-to-schedule.
        if ($workOrder && $workOrder->on_hold && $workOrder->hold_reason === 'waiting_parts' && $check->usable() > 0) {
            ReleaseHold::run($workOrder, $actor, "parts are here ({$part->name})");
        }

        // SOW §3.12 — office hears about shortages / damage on delivery.
        if ($check->shortage() > 0 || $damaged > 0) {
            Notifier::send(Notifier::office($part->tenant_id, $actor->id), 'delivery', "Delivery issue: {$part->name}",
                trim(($check->shortage() ? "short {$check->shortage()}" : '').($check->shortage() && $damaged ? ', ' : '').($damaged ? "{$damaged} damaged" : '')).($reference ? " ({$reference})" : ''),
                route('parts.show', $part), ['delivery_check_id' => $check->id]);
        }

        return $check;
    }
}
