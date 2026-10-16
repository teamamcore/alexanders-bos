<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Reminder extends Model
{
    use BelongsToTenant;
    protected $guarded = [];
    protected $casts = ['due_at' => 'date', 'auto_message' => 'boolean', 'completed_at' => 'datetime'];
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}
