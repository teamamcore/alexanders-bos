<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\States\WorkOrder\WorkOrderState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\ModelStates\HasStates;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $job_id
 * @property string|null $number
 * @property string|null $customer_notes
 * @property string|null $internal_notes
 * @property int|null $assigned_to
 * @property Carbon|null $scheduled_date
 * @property string|null $schedule_window
 * @property string|null $work_type
 * @property bool $on_hold
 * @property string|null $hold_reason
 * @property string $priority
 * @property string $day_fraction
 * @property string|null $scope_office
 * @property string|null $work_performed
 * @property int|null $crew_id
 * @property WorkOrderState $status
 */
class WorkOrder extends Model
{
    use Auditable, BelongsToTenant, HasStates;

    protected $guarded = [];

    protected $casts = ['scheduled_date' => 'date', 'labor_hours' => 'decimal:2', 'status' => WorkOrderState::class,
        'on_hold' => 'boolean', 'day_fraction' => 'decimal:2'];

    // SOW §3.3 — a field User sees only work assigned to them.
    protected static function booted(): void
    {
        static::addGlobalScope('assignedToField', function (Builder $builder) {
            if (app()->bound('currentUser')) {
                $user = app('currentUser');
                if ($user->role === Role::Field) {
                    $builder->where('work_orders.assigned_to', $user->id);
                }
            }
        });
    }

    /** @return BelongsTo<Job, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(WorkOrderUpdate::class);
    }

    public function scheduleChanges(): HasMany
    {
        return $this->hasMany(ScheduleChange::class);
    }

    public function gateOverrides(): HasMany
    {
        return $this->hasMany(GateOverride::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    // ---- Design notes §1/§2: parent ↔ children -------------------------------

    /** @return HasMany<WorkOrderNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(WorkOrderNote::class)->latest();
    }

    /** @return HasMany<ScopeRevision, $this> */
    public function scopeRevisions(): HasMany
    {
        return $this->hasMany(ScopeRevision::class)->latest();
    }

    /** @return HasMany<WorkOrderFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(WorkOrderFile::class)->latest();
    }

    /** @return BelongsTo<Crew, $this> */
    public function crew(): BelongsTo
    {
        return $this->belongsTo(Crew::class);
    }

    /** Status pill (design notes §1): inactive → active → billable → closed; Hold is a flag on Active. */
    public function pillStatus(): string
    {
        $name = $this->status->getValue();
        if ($name === 'complete') {
            $total = (float) $this->invoices()->where('status', '!=', 'void')->sum('total');
            $paid = (float) $this->invoices()->where('status', 'paid')->sum('total');

            return $total > 0 && $paid >= $total ? 'closed' : 'billable';
        }

        return $name === 'new' ? 'inactive' : 'active';
    }

    public function isEmergency(): bool
    {
        return $this->priority === 'emergency';
    }

    public function holdReasonLabel(): ?string
    {
        return $this->hold_reason ? (config('batsons.hold_reasons')[$this->hold_reason] ?? ucfirst(str_replace('_', ' ', $this->hold_reason))) : null;
    }

    /** Dispatch lane for the board: all_day | am | pm (from the schedule window). */
    public function lane(): string
    {
        return match ($this->schedule_window) {
            'morning' => 'am',
            'afternoon' => 'pm',
            default => 'all_day',
        };
    }

    public static function windowForLane(string $lane, bool $emergency = false): string
    {
        return match ($lane) {
            'am' => 'morning',
            'pm' => 'afternoon',
            default => $emergency ? 'emergency' : 'all_day',
        };
    }

    /** W.O.S panel category (mirrors the Work Orders module exactly). */
    public function board(): string
    {
        if ($this->on_hold) {
            return 'on_hold';
        }

        return match ($this->pillStatus()) {
            'inactive' => 'unscheduled',
            'active' => 'scheduled',
            'billable' => 'billable',
            default => 'closed',
        };
    }

    // SOW §3.4 — customer texts/emails tied to this work order.
    public function messages(): HasMany
    {
        return $this->hasMany(CustomerMessage::class)->latest();
    }

    // SOW §3.7 — equipment billed on this work order (revenue tracked per item).
    public function equipmentUsages(): HasMany
    {
        return $this->hasMany(EquipmentUsage::class);
    }

    public function billableEquipmentTotal(): float
    {
        return (float) $this->equipmentUsages()->sum('amount');
    }
}
