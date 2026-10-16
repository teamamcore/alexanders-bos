<?php
namespace App\Models;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TimeEntry extends Model
{
    use BelongsToTenant, Auditable;
    protected $guarded = [];
    protected $casts = ['prior_value' => 'array', 'started_at' => 'datetime', 'ended_at' => 'datetime', 'adjusted_at' => 'datetime', 'billable' => 'boolean'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function job(): BelongsTo { return $this->belongsTo(Job::class); }
    public function workOrder(): BelongsTo { return $this->belongsTo(WorkOrder::class); }
}
