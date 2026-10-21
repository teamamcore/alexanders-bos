<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SOW §3.7 — "check on delivery": each delivered item checked against the
 * order, shortage/damage recorded, photo attached, linked to job + item.
 *
 * @property int $qty_ordered
 * @property int $qty_received
 * @property int $qty_damaged
 */
class DeliveryCheck extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return BelongsTo<Part, $this> */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<WorkOrder, $this> */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function shortage(): int
    {
        return max(0, $this->qty_ordered - $this->qty_received);
    }

    public function usable(): int
    {
        return max(0, $this->qty_received - $this->qty_damaged);
    }
}
