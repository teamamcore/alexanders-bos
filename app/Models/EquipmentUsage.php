<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** SOW §3.7 — equipment billed on a work order; usage + revenue per item. */
class EquipmentUsage extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['quantity' => 'decimal:2', 'rate' => 'decimal:2', 'amount' => 'decimal:2', 'used_on' => 'date'];

    /** @return BelongsTo<Equipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /** @return BelongsTo<WorkOrder, $this> */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
