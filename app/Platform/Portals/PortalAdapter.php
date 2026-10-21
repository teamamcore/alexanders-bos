<?php

declare(strict_types=1);

namespace App\Platform\Portals;

use App\Models\Invoice;
use App\Models\PortalWorkOrder;
use App\Models\VendorPortalIntegration;

/**
 * SOW §3.14 — the one shape every portal connection implements, so the
 * import → job → status → invoice → payment flow is portal-agnostic.
 */
interface PortalAdapter
{
    /** @return list<PortalWorkRequest> new / open work requests the customer has issued */
    public function pullWorkRequests(VendorPortalIntegration $integration): array;

    /** @return string portal acknowledgement / reference */
    public function pushStatus(VendorPortalIntegration $integration, PortalWorkOrder $pwo, string $portalStatus, ?string $note = null): string;

    /** @return string portal invoice reference */
    public function pushInvoice(VendorPortalIntegration $integration, PortalWorkOrder $pwo, Invoice $invoice): string;

    /** @return array{paid: bool, paid_at: string|null, reference: string|null}|null null when the portal has nothing new */
    public function pullPaymentStatus(VendorPortalIntegration $integration, PortalWorkOrder $pwo): ?array;
}
