<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * SOW §3.14 — a work request issued by a customer in its portal, imported as
 * a job + work order here, with the portal's view of status kept in sync.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $integration_id
 * @property string $external_id
 * @property string|null $external_number
 * @property string|null $external_status
 * @property int|null $customer_id
 * @property int|null $service_location_id
 * @property int|null $job_id
 * @property int|null $work_order_id
 * @property int|null $invoice_id
 * @property string|null $customer_name
 * @property string|null $location_name
 * @property string|null $address
 * @property string|null $trade
 * @property string|null $priority
 * @property string|null $nte
 * @property string|null $description
 * @property Carbon|null $scheduled_by
 * @property string $status
 * @property array<string, mixed>|null $raw
 * @property Carbon|null $imported_at
 * @property Carbon|null $last_synced_at
 */
class PortalWorkOrder extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['raw' => 'array', 'nte' => 'decimal:2', 'scheduled_by' => 'date', 'imported_at' => 'datetime', 'last_synced_at' => 'datetime'];

    public const OPEN = ['imported', 'scheduled', 'in_progress', 'completed', 'invoiced'];

    /** @return BelongsTo<VendorPortalIntegration, $this> */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(VendorPortalIntegration::class, 'integration_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Job, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /** @return BelongsTo<WorkOrder, $this> */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return HasMany<PortalSyncEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(PortalSyncEvent::class)->latest();
    }

    /** @param  Builder<PortalWorkOrder>  $q */
    public function scopeOpen(Builder $q): void
    {
        $q->whereIn('status', self::OPEN);
    }

    public function isPastNte(): bool
    {
        return $this->nte !== null && $this->invoice && (float) $this->invoice->total > (float) $this->nte;
    }
}
