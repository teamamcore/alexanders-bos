<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Internal note on a work order (design notes §1): tech notes and field
 * findings never reach the invoice or the customer; a `scope_update` note is
 * the "Send scope update" ping the office must acknowledge before extra work.
 *
 * @property int $id
 * @property int $work_order_id
 * @property int|null $user_id
 * @property string $kind
 * @property string $body
 * @property Carbon|null $acknowledged_at
 * @property int|null $acknowledged_by
 * @property Carbon|null $created_at
 */
class WorkOrderNote extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['acknowledged_at' => 'datetime'];

    public const KINDS = ['tech_note' => 'Tech note', 'field_finding' => 'Field finding', 'scope_update' => 'Scope update', 'office_note' => 'Office note', 'system' => 'System'];

    /** @return BelongsTo<WorkOrder, $this> */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @param  Builder<WorkOrderNote>  $q */
    public function scopePendingScopeUpdates(Builder $q): void
    {
        $q->where('kind', 'scope_update')->whereNull('acknowledged_at');
    }
}
