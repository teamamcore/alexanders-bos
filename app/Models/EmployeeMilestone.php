<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** SOW §3.11 — a dated milestone on an employee profile (hire, promotion, award…). */
class EmployeeMilestone extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['occurred_at' => 'date'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
