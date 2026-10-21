<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SOW §3.4 — a text or email to (or from) a customer, tied to the job / work
 * order. Office users monitor all of them; field users send from the job.
 *
 * @property int $id
 * @property int|null $customer_id
 * @property int|null $contact_id
 * @property int|null $job_id
 * @property int|null $work_order_id
 * @property string $direction
 * @property string $channel
 * @property string|null $to
 * @property string|null $from
 * @property string|null $subject
 * @property string $body
 * @property string|null $template_key
 * @property string $status
 * @property string|null $provider_ref
 * @property string|null $error
 * @property int|null $sent_by
 * @property Carbon|null $sent_at
 */
class CustomerMessage extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['sent_at' => 'datetime'];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
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

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /** @param  Builder<CustomerMessage>  $q */
    public function scopeOutbound(Builder $q): void
    {
        $q->where('direction', 'outbound');
    }

    /** @param  Builder<CustomerMessage>  $q */
    public function scopeInbound(Builder $q): void
    {
        $q->where('direction', 'inbound');
    }
}
