<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $work_order_id
 * @property int|null $customer_id
 * @property string|null $number
 * @property string $status
 * @property string $total
 * @property string $terms
 * @property bool $is_progress_payment
 * @property Carbon|null $issued_at
 * @property Carbon|null $due_at
 * @property Carbon|null $paid_at
 */
class Invoice extends Model
{
    use Auditable, BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['qb_synced_at' => 'datetime', 'voided_at' => 'datetime', 'total' => 'decimal:2',
        'issued_at' => 'date', 'due_at' => 'date', 'paid_at' => 'date', 'is_progress_payment' => 'boolean'];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
