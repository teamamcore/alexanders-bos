<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\ReminderTypes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * SOW §3.10 — a recurring service a customer (or site) is on: annual backflow
 * test, water-heater service after one year, or any interval the client sets.
 * Each cycle produces one Reminder; completing the follow-up work rolls the
 * schedule forward.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $customer_id
 * @property int|null $service_location_id
 * @property string $type
 * @property string|null $label
 * @property int $interval_months
 * @property int $lead_days_min
 * @property int $lead_days_max
 * @property bool $auto_message
 * @property bool $is_active
 * @property Carbon|null $last_serviced_at
 * @property Carbon $next_due_at
 * @property int|null $source_work_order_id
 * @property string|null $notes
 */
class RecurringService extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'auto_message' => 'boolean', 'is_active' => 'boolean',
        'last_serviced_at' => 'date', 'next_due_at' => 'date',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<ServiceLocation, $this> */
    public function serviceLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class);
    }

    /** @return HasMany<Reminder, $this> */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class)->latest('due_at');
    }

    /** @param  Builder<RecurringService>  $q */
    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true);
    }

    public function typeLabel(): string
    {
        return ReminderTypes::label($this->type);
    }

    public function displayLabel(): string
    {
        return $this->label ?: $this->typeLabel().($this->serviceLocation ? ' — '.($this->serviceLocation->label ?: $this->serviceLocation->address) : '');
    }

    /** The open reminder for the current cycle, if any. */
    public function currentReminder(): ?Reminder
    {
        return $this->reminders()->whereNotIn('status', ['done', 'skipped'])->first();
    }

    /** Window in which the office should be contacting the customer for the next due date. */
    public function remindAt(): Carbon
    {
        return $this->next_due_at->copy()->subDays($this->lead_days_max);
    }

    public function contactBy(): Carbon
    {
        return $this->next_due_at->copy()->subDays($this->lead_days_min);
    }

    /** Roll the schedule after a completed service. */
    public function rollForward(?Carbon $servicedOn = null): self
    {
        $servicedOn ??= today();
        $this->forceFill([
            'last_serviced_at' => $servicedOn,
            'next_due_at' => $servicedOn->copy()->addMonths($this->interval_months),
        ])->save();

        return $this;
    }
}
