<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SOW §3.12 — an email that arrived at the Client's inbound address and was
 * routed (or is waiting to be routed) to a customer / job.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $from_email
 * @property string|null $from_name
 * @property string|null $to_email
 * @property string|null $subject
 * @property string|null $body
 * @property int|null $customer_id
 * @property int|null $job_id
 * @property int|null $work_order_id
 * @property int|null $customer_message_id
 * @property string $status
 * @property string|null $routed_by_rule
 * @property Carbon|null $received_at
 */
class InboundEmail extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['received_at' => 'datetime'];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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

    /** @param  Builder<InboundEmail>  $q */
    public function scopeUnrouted(Builder $q): void
    {
        $q->where('status', 'new');
    }
}
