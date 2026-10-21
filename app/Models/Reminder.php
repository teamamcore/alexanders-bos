<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\ReminderTypes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SOW §3.10 — one cycle of a recurring service, worked by the office:
 * pending → notified (office alerted when the window opens) → contacted
 * (customer messaged / called) → scheduled (job + work order created) → done.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $customer_id
 * @property int|null $recurring_service_id
 * @property int|null $service_location_id
 * @property string $type
 * @property Carbon $due_at
 * @property Carbon|null $remind_at
 * @property Carbon|null $contact_by
 * @property string $status
 * @property bool $auto_message
 * @property Carbon|null $notified_at
 * @property Carbon|null $contacted_at
 * @property string|null $contact_method
 * @property int|null $job_id
 * @property int|null $work_order_id
 * @property string|null $notes
 * @property Carbon|null $completed_at
 */
class Reminder extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'due_at' => 'date', 'remind_at' => 'date', 'contact_by' => 'date',
        'auto_message' => 'boolean', 'notified_at' => 'datetime', 'contacted_at' => 'datetime', 'completed_at' => 'datetime',
    ];

    public const OPEN = ['pending', 'notified', 'contacted', 'scheduled'];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<RecurringService, $this> */
    public function recurringService(): BelongsTo
    {
        return $this->belongsTo(RecurringService::class);
    }

    /** @return BelongsTo<ServiceLocation, $this> */
    public function serviceLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class);
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

    /** @param  Builder<Reminder>  $q */
    public function scopeOpen(Builder $q): void
    {
        $q->whereIn('status', self::OPEN);
    }

    /** Reminders whose contact window has opened and the office hasn't been told yet. */
    public function scopeDueToNotify(Builder $q): void
    {
        $q->where('status', 'pending')->whereDate('remind_at', '<=', today());
    }

    public function typeLabel(): string
    {
        return ReminderTypes::label($this->type);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, self::OPEN, true) && $this->due_at->isBefore(today());
    }

    /** Past the "contact by" date without a scheduled job. */
    public function isLate(): bool
    {
        return in_array($this->status, ['pending', 'notified', 'contacted'], true) && $this->contact_by && $this->contact_by->isBefore(today());
    }
}
