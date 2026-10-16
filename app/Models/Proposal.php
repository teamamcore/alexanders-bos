<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Proposal extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['line_items' => 'array', 'follow_up_at' => 'datetime', 'total' => 'decimal:2'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function convertedJob(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'converted_job_id');
    }
}
