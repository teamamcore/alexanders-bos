<?php

declare(strict_types=1);

namespace App\Platform\Portals;

use App\Models\Invoice;
use App\Models\PortalWorkOrder;
use App\Models\VendorPortalIntegration;

/**
 * "Another supported connection method": portals with no API. Requests are
 * keyed in by the office; pushes are recorded as to-dos the office completes
 * on the portal website, so the audit trail is still complete.
 */
final class ManualAdapter implements PortalAdapter
{
    public function pullWorkRequests(VendorPortalIntegration $integration): array
    {
        return [];
    }

    public function pushStatus(VendorPortalIntegration $integration, PortalWorkOrder $pwo, string $portalStatus, ?string $note = null): string
    {
        return 'manual: enter status on the portal';
    }

    public function pushInvoice(VendorPortalIntegration $integration, PortalWorkOrder $pwo, Invoice $invoice): string
    {
        return 'manual: upload invoice on the portal';
    }

    public function pullPaymentStatus(VendorPortalIntegration $integration, PortalWorkOrder $pwo): ?array
    {
        return null;
    }
}
