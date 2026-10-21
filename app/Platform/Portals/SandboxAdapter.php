<?php

declare(strict_types=1);

namespace App\Platform\Portals;

use App\Models\Invoice;
use App\Models\PortalWorkOrder;
use App\Models\VendorPortalIntegration;
use Illuminate\Support\Str;

/**
 * Built-in demo portal: "issues" the fixture requests in config/portals.php
 * (plus anything injected from the UI) and acknowledges every push.
 */
final class SandboxAdapter implements PortalAdapter
{
    public function pullWorkRequests(VendorPortalIntegration $integration): array
    {
        $queued = collect($integration->credentials['queued_requests'] ?? []);
        $fixtures = collect(config('portals.sandbox_requests', []));

        return $fixtures->merge($queued)->map(fn (array $d) => PortalWorkRequest::fromArray($d))->values()->all();
    }

    public function pushStatus(VendorPortalIntegration $integration, PortalWorkOrder $pwo, string $portalStatus, ?string $note = null): string
    {
        return 'sbx-status-'.Str::lower(Str::random(8));
    }

    public function pushInvoice(VendorPortalIntegration $integration, PortalWorkOrder $pwo, Invoice $invoice): string
    {
        return 'sbx-inv-'.Str::lower(Str::random(8));
    }

    public function pullPaymentStatus(VendorPortalIntegration $integration, PortalWorkOrder $pwo): ?array
    {
        // The sandbox "pays" an invoice 1 day after it was pushed.
        if ($pwo->status === 'invoiced' && $pwo->last_synced_at && $pwo->last_synced_at->lt(now()->subDay())) {
            return ['paid' => true, 'paid_at' => now()->toDateString(), 'reference' => 'sbx-pay-'.Str::lower(Str::random(6))];
        }

        return null;
    }
}
