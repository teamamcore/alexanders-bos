<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['service_intervals' => 'array', 'registration_due' => 'date', 'inspection_due' => 'date', 'insurance_due' => 'date'];

    public function maintenance(): HasMany
    {
        return $this->hasMany(VehicleMaintenance::class);
    }
}
