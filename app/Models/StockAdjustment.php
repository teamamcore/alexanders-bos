<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class StockAdjustment extends Model
{
    use BelongsToTenant;
    protected $guarded = [];
    public function part(): BelongsTo { return $this->belongsTo(Part::class); }
}
