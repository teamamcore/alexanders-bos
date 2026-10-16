<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ServiceLocation extends Model
{
    use BelongsToTenant;
    protected $guarded = [];
    protected $casts = ['site_requirements' => 'array'];
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}
