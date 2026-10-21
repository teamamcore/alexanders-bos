<?php

declare(strict_types=1);

namespace App\Actions\Portals;

use App\Actions\WorkOrders\AddWorkOrder;
use App\Models\Customer;
use App\Models\Job;
use App\Models\PortalSyncEvent;
use App\Models\PortalWorkOrder;
use App\Models\ServiceLocation;
use App\Models\VendorPortalIntegration;
use App\Platform\Notifications\Notifier;
use App\Platform\Portals\PortalWorkRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.14 — "Customer issues work in its portal → Imported as a job and work
 * order". Idempotent on (integration, external_id). Customer = the portal's
 * account (or the integration's default customer), site = the location, work
 * type from the portal trade so dispatch indicators and reminders apply.
 */
final class ImportPortalWorkOrder
{
    use AsAction;

    public function handle(VendorPortalIntegration $integration, PortalWorkRequest $req): PortalWorkOrder
    {
        $existing = PortalWorkOrder::query()->where('integration_id', $integration->id)->where('external_id', $req->externalId)->first();
        if ($existing instanceof PortalWorkOrder) {
            return $existing;
        }

        $customer = $integration->defaultCustomer
            ?? ($req->customerName ? Customer::firstOrCreate(['name' => trim($req->customerName)], ['payment_terms' => 'net_30']) : null)
            ?? Customer::firstOrCreate(['name' => $integration->name.' (portal)']);

        $location = null;
        if ($req->address || $req->locationName) {
            $location = ServiceLocation::query()->where('customer_id', $customer->id)
                ->where(fn ($q) => $q->when($req->address, fn ($w) => $w->where('address', $req->address))->when(! $req->address, fn ($w) => $w->where('label', $req->locationName)))
                ->first() ?? ServiceLocation::create([
                    'customer_id' => $customer->id,
                    'label' => $req->locationName,
                    'address' => $req->address ?: ($req->locationName ?? 'Portal location'),
                    'type' => 'job',
                ]);
        }

        $workType = $req->trade ? (config('portals.trade_map')[strtoupper(trim($req->trade))] ?? null) : null;
        $title = trim(($req->locationName ? $req->locationName.' — ' : '').($req->description ? Str::limit($req->description, 60, '…') : ($req->trade ?: 'Portal work request')));

        $job = Job::create([
            'customer_id' => $customer->id,
            'service_location_id' => $location?->id,
            'number' => $req->externalNumber ? $integration->key.'-'.$req->externalNumber : null,
            'title' => $title,
            'status' => 'open',
        ]);

        $notes = trim(($req->description ?? '')."\n".($req->priority ? "Priority: {$req->priority}. " : '').($req->nte !== null ? 'NTE $'.number_format($req->nte, 2).'. ' : '').($req->scheduledBy instanceof Carbon ? 'Scheduled by '.$req->scheduledBy->format('M j').'.' : ''));
        $wo = AddWorkOrder::run($job, $req->externalNumber ? 'WO-'.$req->externalNumber : null, $notes ?: null, 'morning', $workType);

        $pwo = PortalWorkOrder::create([
            'tenant_id' => $integration->tenant_id,
            'integration_id' => $integration->id,
            'external_id' => $req->externalId,
            'external_number' => $req->externalNumber,
            'external_status' => 'OPEN',
            'customer_id' => $customer->id,
            'service_location_id' => $location?->id,
            'job_id' => $job->id,
            'work_order_id' => $wo->id,
            'customer_name' => $req->customerName,
            'location_name' => $req->locationName,
            'address' => $req->address,
            'trade' => $req->trade,
            'priority' => $req->priority,
            'nte' => $req->nte,
            'description' => $req->description,
            'scheduled_by' => $req->scheduledBy,
            'status' => 'imported',
            'raw' => $req->raw,
            'imported_at' => now(),
            'last_synced_at' => now(),
        ]);

        PortalSyncEvent::log($integration, 'inbound', 'import', 'ok', "Imported {$req->externalId} as {$wo->number}", $pwo, $req->raw);

        Notifier::send(Notifier::office($integration->tenant_id), 'portal_request',
            "{$integration->name}: new work request ".($req->externalNumber ? "#{$req->externalNumber}" : $req->externalId),
            trim(($req->locationName ? $req->locationName.' · ' : '').($req->priority ?? '').($req->scheduledBy instanceof Carbon ? ' · by '.$req->scheduledBy->format('M j') : '')),
            route('jobs.show', $job), ['portal_work_order_id' => $pwo->id]);

        return $pwo;
    }
}
