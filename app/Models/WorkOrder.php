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
use Spatie\ModelStates\HasStates;

class WorkOrder extends Model
{
    use Auditable, BelongsToTenant, HasStates;

    protected $guarded = [];

    protected $casts = ['scheduled_date' => 'date', 'labor_hours' => 'decimal:2', 'status' => WorkOrderState::class];

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

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

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
