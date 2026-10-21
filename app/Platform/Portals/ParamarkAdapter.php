<?php

declare(strict_types=1);

namespace App\Platform\Portals;

use App\Models\Invoice;
use App\Models\PortalWorkOrder;
use App\Models\VendorPortalIntegration;

/**
 * Paramark vendor API. Same contract as ServiceChannel; live calls are
 * enabled once Paramark grants vendor API access (api_key + vendor_id).
 */
final class ParamarkAdapter implements PortalAdapter
{
    public function pullWorkRequests(VendorPortalIntegration $integration): array
    {
        $this->guard($integration);

        return [];
    }

    public function pushStatus(VendorPortalIntegration $integration, PortalWorkOrder $pwo, string $portalStatus, ?string $note = null): string
    {
        $this->guard($integration);

        return 'pm-status-pending';
    }

    public function pushInvoice(VendorPortalIntegration $integration, PortalWorkOrder $pwo, Invoice $invoice): string
    {
        $this->guard($integration);

        return 'pm-invoice-pending';
    }

    public function pullPaymentStatus(VendorPortalIntegration $integration, PortalWorkOrder $pwo): ?array
    {
        $this->guard($integration);

        return null;
    }

    private function guard(VendorPortalIntegration $integration): void
    {
        if (! $integration->isConfigured()) {
            throw new PortalNotConnected('Paramark API key / vendor id are not on file yet.');
        }
        throw new PortalNotConnected('Paramark live calls are enabled once the provider grants API access and the integration passes testing.');
    }
}
