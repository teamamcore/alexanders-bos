<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class WorkOrderUpdate extends Model
{
    use BelongsToTenant;
    protected $guarded = [];
    protected $casts = ['update_date' => 'date'];
    public function workOrder(): BelongsTo { return $this->belongsTo(WorkOrder::class); }
}
