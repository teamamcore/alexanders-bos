<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SOW §3.14 — one message exchanged with a portal (import, status push,
 * invoice push, payment update, poll, error), kept as the integration audit log.
 *
 * @property string $direction
 * @property string $type
 * @property string $status
 * @property string|null $message
 * @property array<string, mixed>|null $payload
 */
class PortalSyncEvent extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['payload' => 'array'];

    /** @return BelongsTo<VendorPortalIntegration, $this> */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(VendorPortalIntegration::class, 'integration_id');
    }

    /** @return BelongsTo<PortalWorkOrder, $this> */
    public function portalWorkOrder(): BelongsTo
    {
        return $this->belongsTo(PortalWorkOrder::class);
    }

    /** @param  array<string, mixed>|null  $payload */
    public static function log(VendorPortalIntegration $integration, string $direction, string $type, string $status, ?string $message = null, ?PortalWorkOrder $pwo = null, ?array $payload = null): self
    {
        return self::create([
            'tenant_id' => $integration->tenant_id,
            'integration_id' => $integration->id,
            'portal_work_order_id' => $pwo?->id,
            'direction' => $direction,
            'type' => $type,
            'status' => $status,
            'message' => $message ? mb_substr($message, 0, 250) : null,
            'payload' => $payload,
        ]);
    }
}
