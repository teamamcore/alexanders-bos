<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * SOW §3.14 — one vendor portal the Client's customers require (ServiceChannel,
 * Paramark, …). Up to five included; blocked portals don't count.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string|null $key
 * @property string $name
 * @property string $adapter
 * @property array<string, mixed>|null $credentials
 * @property string|null $webhook_token
 * @property int|null $default_customer_id
 * @property string $status
 * @property Carbon|null $go_live_at
 * @property bool $counts_toward_included
 * @property string|null $blocked_reason
 * @property Carbon|null $last_polled_at
 * @property string|null $last_error
 * @property string|null $notes
 */
class VendorPortalIntegration extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'go_live_at' => 'date', 'counts_toward_included' => 'boolean', 'last_polled_at' => 'datetime',
        'credentials' => 'encrypted:array', // never stored in clear; column is not named `connection` (Eloquent reserves that)
    ];

    protected $hidden = ['credentials', 'webhook_token'];

    /** @return HasMany<PortalWorkOrder, $this> */
    public function workOrders(): HasMany
    {
        return $this->hasMany(PortalWorkOrder::class, 'integration_id')->latest('imported_at');
    }

    /** @return HasMany<PortalSyncEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(PortalSyncEvent::class, 'integration_id')->latest();
    }

    /** @return BelongsTo<Customer, $this> */
    public function defaultCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'default_customer_id');
    }

    /** @param  Builder<VendorPortalIntegration>  $q */
    public function scopeLive(Builder $q): void
    {
        $q->where('status', 'live');
    }

    public function isLive(): bool
    {
        return $this->status === 'live';
    }

    public function adapterLabel(): string
    {
        return (string) (config("portals.adapters.{$this->adapter}.label") ?? ucfirst($this->adapter));
    }

    /** True when every connection field the adapter needs has a value. */
    public function isConfigured(): bool
    {
        $fields = config("portals.adapters.{$this->adapter}.connection_fields", []);
        foreach ($fields as $f) {
            if (trim((string) ($this->credentials[$f] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }
}
