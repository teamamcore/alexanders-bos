<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Equipment extends Model
{
    use BelongsToTenant;
    protected $table = 'equipment';
    protected $guarded = [];
    protected $casts = ['maintenance_schedule' => 'array', 'billing_rate' => 'decimal:2'];
    public function maintenance(): HasMany { return $this->hasMany(EquipmentMaintenance::class); }
}
