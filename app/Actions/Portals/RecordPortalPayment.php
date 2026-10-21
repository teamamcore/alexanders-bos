<?php

declare(strict_types=1);

namespace App\Actions\Portals;

use App\Actions\Invoicing\MarkInvoicePaid;
use App\Models\PortalSyncEvent;
use App\Models\PortalWorkOrder;
use Lorisleiva\Actions\Concerns\AsAction;

/** SOW §3.14 — "Payment status recorded": the portal says the invoice was paid. */
final class RecordPortalPayment
{
    use AsAction;

    public function handle(PortalWorkOrder $pwo, ?string $paidAt = null, ?string $reference = null): PortalWorkOrder
    {
        $invoice = $pwo->invoice;
        if ($invoice && $invoice->status !== 'paid' && $invoice->status !== 'void') {
            MarkInvoicePaid::run($invoice);
            if ($paidAt) {
                $invoice->forceFill(['paid_at' => $paidAt])->save();
            }
        }

        $pwo->forceFill(['status' => 'paid', 'external_status' => 'PAID', 'last_synced_at' => now()])->save();

        if ($pwo->integration) {
            PortalSyncEvent::log($pwo->integration, 'inbound', 'payment', 'ok', 'Paid'.($reference ? " ({$reference})" : '').($paidAt ? " on {$paidAt}" : ''), $pwo, ['paid_at' => $paidAt, 'reference' => $reference]);
        }

        return $pwo;
    }
}
