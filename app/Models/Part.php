<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Part extends Model
{
    use BelongsToTenant;
    protected $guarded = [];
    protected $casts = ['nicknames' => 'array', 'price' => 'decimal:2'];
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function stockAdjustments(): HasMany { return $this->hasMany(StockAdjustment::class); }
}
