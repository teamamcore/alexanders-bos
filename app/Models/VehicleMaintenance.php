<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class VehicleMaintenance extends Model
{
    use BelongsToTenant;
    protected $guarded = [];
    protected $casts = ['performed_at' => 'date', 'cost' => 'decimal:2'];
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
}
