<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** SOW §3.7 — odometer reading reported for a vehicle. */
class MileageReading extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['recorded_at' => 'date'];

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
