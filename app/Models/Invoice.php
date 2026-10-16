<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use Auditable, BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['qb_synced_at' => 'datetime', 'voided_at' => 'datetime', 'total' => 'decimal:2'];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
