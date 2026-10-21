<?php

declare(strict_types=1);

namespace App\Platform\Portals;

use App\Models\Invoice;
use App\Models\PortalWorkOrder;
use App\Models\VendorPortalIntegration;

/**
 * ServiceChannel (developer.servicechannel.com). Wire-up points are laid out
 * against the published API; every call requires provider-granted credentials
 * (OAuth2 client credentials + subscriber). Until those exist the adapter
 * reports "not connected" and the poll records it without failing the run.
 *
 * Endpoints (v3): GET /workorders?status=OPEN → pullWorkRequests;
 * POST /workorders/{id}/notes + PUT /workorders/{id}/status → pushStatus;
 * POST /invoices (work order id, lines, total) → pushInvoice;
 * GET /invoices/{id} → pullPaymentStatus.
 */
final class ServiceChannelAdapter implements PortalAdapter
{
    public function pullWorkRequests(VendorPortalIntegration $integration): array
    {
        $this->guard($integration);

        // TODO(live): OAuth2 token → GET {base_url}/v3/workorders?status=OPEN&subscriber={subscriber_id}
        return [];
    }

    public function pushStatus(VendorPortalIntegration $integration, PortalWorkOrder $pwo, string $portalStatus, ?string $note = null): string
    {
        $this->guard($integration);

        // TODO(live): PUT {base_url}/v3/workorders/{external_id}/status {status: ..., note: ...}
        return 'sc-status-pending';
    }

    public function pushInvoice(VendorPortalIntegration $integration, PortalWorkOrder $pwo, Invoice $invoice): string
    {
        $this->guard($integration);

        // TODO(live): POST {base_url}/v3/invoices {workOrderId, invoiceNumber, total, lines}
        return 'sc-invoice-pending';
    }

    public function pullPaymentStatus(VendorPortalIntegration $integration, PortalWorkOrder $pwo): ?array
    {
        $this->guard($integration);

        return null;
    }

    private function guard(VendorPortalIntegration $integration): void
    {
        if (! $integration->isConfigured()) {
            throw new PortalNotConnected('ServiceChannel credentials are not on file yet (provider approval pending).');
        }
        throw new PortalNotConnected('ServiceChannel live calls are enabled once the provider grants API access and the integration passes testing.');
    }
}
