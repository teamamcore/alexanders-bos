<?php
namespace App\Models;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class WorkOrder extends Model
{
    use BelongsToTenant, Auditable;
    protected $guarded = [];
    protected $casts = ['scheduled_date' => 'date', 'labor_hours' => 'decimal:2'];
    public function job(): BelongsTo { return $this->belongsTo(Job::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function updates(): HasMany { return $this->hasMany(WorkOrderUpdate::class); }
    public function scheduleChanges(): HasMany { return $this->hasMany(ScheduleChange::class); }
    public function gateOverrides(): HasMany { return $this->hasMany(GateOverride::class); }
}
