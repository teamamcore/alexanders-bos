<?php

declare(strict_types=1);

namespace App\Actions\Portals;

use App\Models\PortalSyncEvent;
use App\Models\PortalWorkOrder;
use App\Models\WorkOrder;
use App\Platform\Portals\PortalManager;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * SOW §3.14 — "Work performed → status sent back to the portal". Called from
 * the work-order state changes; no-op for work orders that didn't come from a
 * portal. Never throws into the operational flow.
 */
final class PushPortalStatus
{
    use AsAction;

    public function handle(WorkOrder $workOrder, string $state, ?string $note = null): ?PortalSyncEvent
    {
        $pwo = PortalWorkOrder::query()->where('work_order_id', $workOrder->id)->with('integration')->first();
        if (! $pwo instanceof PortalWorkOrder || ! $pwo->integration) {
            return null;
        }

        $integration = $pwo->integration;
        $portalStatus = (string) (config('portals.status_map')[$state] ?? strtoupper($state));
        $localStatus = match ($state) {
            'scheduled' => 'scheduled', 'in_progress' => 'in_progress', 'complete' => 'completed', default => $pwo->status
        };

        try {
            $ref = PortalManager::for($integration)->pushStatus($integration, $pwo, $portalStatus, $note);
            $pwo->forceFill(['status' => $localStatus, 'external_status' => $portalStatus, 'last_synced_at' => now()])->save();

            return PortalSyncEvent::log($integration, 'outbound', 'status', $integration->adapter === 'manual' ? 'queued' : 'ok', "{$portalStatus} → {$ref}", $pwo, ['status' => $portalStatus, 'note' => $note]);
        } catch (Throwable $e) { // includes PortalNotConnected
            $pwo->forceFill(['status' => $localStatus])->save();

            return PortalSyncEvent::log($integration, 'outbound', 'status', 'failed', $e->getMessage(), $pwo, ['status' => $portalStatus]);
        }
    }
}
