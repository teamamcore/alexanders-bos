<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class EquipmentMaintenance extends Model
{
    use BelongsToTenant;
    protected $table = 'equipment_maintenance';
    protected $guarded = [];
    protected $casts = ['performed_at' => 'date', 'cost' => 'decimal:2'];
    public function equipment(): BelongsTo { return $this->belongsTo(Equipment::class); }
}
