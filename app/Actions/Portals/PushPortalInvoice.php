<?php

declare(strict_types=1);

namespace App\Actions\Portals;

use App\Models\Invoice;
use App\Models\PortalSyncEvent;
use App\Models\PortalWorkOrder;
use App\Platform\Notifications\Notifier;
use App\Platform\Portals\PortalManager;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * SOW §3.14 / §3.6 — "invoice sent back to the portal". Called when an invoice
 * on a portal work order is approved. Flags invoices over the NTE.
 */
final class PushPortalInvoice
{
    use AsAction;

    public function handle(Invoice $invoice): ?PortalSyncEvent
    {
        $pwo = PortalWorkOrder::query()->where('work_order_id', $invoice->work_order_id)->with('integration')->first();
        if (! $pwo instanceof PortalWorkOrder || ! $pwo->integration) {
            return null;
        }
        $integration = $pwo->integration;

        if ($pwo->nte !== null && (float) $invoice->total > (float) $pwo->nte) {
            Notifier::send(Notifier::office($integration->tenant_id), 'portal_nte', "{$integration->name} #{$pwo->external_number}: invoice over NTE",
                '$'.number_format((float) $invoice->total, 2).' vs NTE $'.number_format((float) $pwo->nte, 2).' — a proposal / NTE increase may be required before the portal accepts it.',
                route('portals.index'), ['portal_work_order_id' => $pwo->id]);
        }

        try {
            $ref = PortalManager::for($integration)->pushInvoice($integration, $pwo, $invoice);
            $pwo->forceFill(['status' => 'invoiced', 'invoice_id' => $invoice->id, 'last_synced_at' => now()])->save();

            return PortalSyncEvent::log($integration, 'outbound', 'invoice', $integration->adapter === 'manual' ? 'queued' : 'ok', "{$invoice->number} $".number_format((float) $invoice->total, 2)." → {$ref}", $pwo, ['invoice' => $invoice->number, 'total' => (float) $invoice->total]);
        } catch (Throwable $e) { // includes PortalNotConnected
            $pwo->forceFill(['status' => 'invoiced', 'invoice_id' => $invoice->id])->save();

            return PortalSyncEvent::log($integration, 'outbound', 'invoice', 'failed', $e->getMessage(), $pwo, ['invoice' => $invoice->number]);
        }
    }
}
