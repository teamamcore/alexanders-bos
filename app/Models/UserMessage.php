<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SOW §3.12 — a direct message between two Users (replaces internal email).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $sender_id
 * @property int $recipient_id
 * @property string $body
 * @property int|null $job_id
 * @property int|null $work_order_id
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 */
class UserMessage extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['read_at' => 'datetime'];

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /** @return BelongsTo<Job, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /** @return BelongsTo<WorkOrder, $this> */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /** @param  Builder<UserMessage>  $q */
    public function scopeBetween(Builder $q, int $a, int $b): void
    {
        $q->where(fn (Builder $w) => $w
            ->where(fn (Builder $x) => $x->where('sender_id', $a)->where('recipient_id', $b))
            ->orWhere(fn (Builder $x) => $x->where('sender_id', $b)->where('recipient_id', $a)));
    }
}
