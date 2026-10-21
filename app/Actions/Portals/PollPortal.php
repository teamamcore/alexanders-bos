<?php

declare(strict_types=1);

namespace App\Actions\Portals;

use App\Models\PortalSyncEvent;
use App\Models\PortalWorkOrder;
use App\Models\VendorPortalIntegration;
use App\Platform\Portals\PortalManager;
use App\Platform\Portals\PortalNotConnected;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * SOW §3.14 — pull new work requests and payment updates from one portal.
 * A portal that isn't connected yet is recorded, not fatal.
 *
 * @return array{imported: int, paid: int, error: string|null}
 */
final class PollPortal
{
    use AsAction;

    /** @return array{imported: int, paid: int, error: string|null} */
    public function handle(VendorPortalIntegration $integration): array
    {
        $result = ['imported' => 0, 'paid' => 0, 'error' => null];
        $adapter = PortalManager::for($integration);

        try {
            foreach ($adapter->pullWorkRequests($integration) as $req) {
                $before = PortalWorkOrder::query()->where('integration_id', $integration->id)->where('external_id', $req->externalId)->exists();
                ImportPortalWorkOrder::run($integration, $req);
                if (! $before) {
                    $result['imported']++;
                }
            }

            foreach (PortalWorkOrder::query()->where('integration_id', $integration->id)->where('status', 'invoiced')->get() as $pwo) {
                $payment = $adapter->pullPaymentStatus($integration, $pwo);
                if ($payment && $payment['paid']) {
                    RecordPortalPayment::run($pwo, $payment['paid_at'], $payment['reference']);
                    $result['paid']++;
                }
            }

            $integration->forceFill(['last_polled_at' => now(), 'last_error' => null])->save();
            PortalSyncEvent::log($integration, 'inbound', 'poll', 'ok', "Polled: {$result['imported']} imported, {$result['paid']} paid");
        } catch (PortalNotConnected $e) {
            $result['error'] = $e->getMessage();
            $integration->forceFill(['last_polled_at' => now(), 'last_error' => $e->getMessage()])->save();
            PortalSyncEvent::log($integration, 'inbound', 'poll', 'failed', $e->getMessage());
        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();
            $integration->forceFill(['last_polled_at' => now(), 'last_error' => mb_substr($e->getMessage(), 0, 500)])->save();
            PortalSyncEvent::log($integration, 'inbound', 'error', 'failed', $e->getMessage());
        }

        return $result;
    }
}
