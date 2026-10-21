<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Part;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\WorkOrder;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.7 — stock adjustment with a reason (not used, billable not used,
 * breakage, used on job, received, count correction). Signed quantity; the
 * part's on-hand count moves with it and the row is the item history.
 */
final class AdjustStock
{
    use AsAction;

    public function handle(Part $part, int $quantity, string $reason, User $actor, ?WorkOrder $workOrder = null, ?string $notes = null): StockAdjustment
    {
        if (! array_key_exists($reason, config('batsons.stock_adjustment_reasons', []))) {
            throw new DomainException("Unknown adjustment reason: {$reason}");
        }
        if ($quantity === 0) {
            throw new DomainException('Quantity cannot be zero.');
        }

        $part->stock_qty += $quantity;
        $part->save();

        return $part->stockAdjustments()->create([
            'quantity' => $quantity,
            'reason' => $reason,
            'notes' => $notes ?: null,
            'work_order_id' => $workOrder?->id,
            'created_by' => $actor->id,
        ]);
    }
}
