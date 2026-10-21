<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $part_id
 * @property int|null $work_order_id
 * @property int $quantity
 * @property string $reason
 * @property string|null $notes
 */
class StockAdjustment extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return BelongsTo<Part, $this> */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
