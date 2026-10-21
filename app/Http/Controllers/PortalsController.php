<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Portals\ImportPortalWorkOrder;
use App\Actions\Portals\PollPortal;
use App\Actions\Portals\RecordPortalPayment;
use App\Actions\Portals\UpdateIntegration;
use App\Models\Customer;
use App\Models\PortalSyncEvent;
use App\Models\PortalWorkOrder;
use App\Models\User;
use App\Models\VendorPortalIntegration;
use App\Platform\Portals\PortalManager;
use App\Platform\Portals\PortalWorkRequest;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Module 3.14 — Vendor Portal Integrations (office + administrators; config is admin-only). */
final class PortalsController extends Controller
{
    public function index(Request $request): View
    {
        $integrations = VendorPortalIntegration::query()->with('defaultCustomer')->withCount(['workOrders as open_count' => fn ($q) => $q->whereIn('status', PortalWorkOrder::OPEN)])->orderBy('id')->get();

        return view('ops.portals.index', [
            'integrations' => $integrations,
            'usage' => PortalManager::includedUsage(),
            'requests' => PortalWorkOrder::query()->with(['integration', 'customer', 'job', 'workOrder', 'invoice'])->latest('imported_at')->take(100)->get(),
            'events' => PortalSyncEvent::query()->with(['integration', 'portalWorkOrder'])->latest()->take(40)->get(),
            'adapters' => config('portals.adapters', []),
            'statuses' => config('batsons.vendor_portals.statuses', []),
            'customers' => Customer::query()->orderBy('name')->get(),
            'isAdmin' => $this->actor($request)->isAdministrator(),
            'canUseSandbox' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'adapter' => ['required', Rule::in(array_keys(config('portals.adapters', [])))],
        ]);
        $key = Str::slug($data['name'], '_');
        $integration = VendorPortalIntegration::create(['key' => $key, 'name' => $data['name'], 'adapter' => $data['adapter'], 'status' => 'planned', 'webhook_token' => Str::random(40)]);

        return back()->with('status', "{$integration->name} added (planned). Configure it below.");
    }

    public function update(Request $request, VendorPortalIntegration $integration): RedirectResponse
    {
        $fields = config("portals.adapters.{$integration->adapter}.connection_fields", []);
        $rules = [
            'name' => ['nullable', 'string', 'max:80'],
            'status' => ['required', Rule::in(config('batsons.vendor_portals.statuses', []))],
            'go_live_at' => ['nullable', 'date'],
            'blocked_reason' => ['nullable', 'string', 'max:250'],
            'default_customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('tenant_id', app('currentTenantId'))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
        foreach ($fields as $f) {
            $rules[$f] = ['nullable', 'string', 'max:500'];
        }
        $data = $request->validate($rules);

        try {
            UpdateIntegration::run($integration, $data, $this->actor($request));
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', "{$integration->fresh()?->name} saved.");
    }

    public function poll(Request $request, VendorPortalIntegration $integration): RedirectResponse
    {
        $r = PollPortal::run($integration);

        return back()->with($r['error'] ? 'error' : 'status', $r['error'] ? "{$integration->name}: {$r['error']}" : "{$integration->name}: {$r['imported']} imported, {$r['paid']} payment update(s).");
    }

    /** Key a request in by hand (manual portals) or inject one into the sandbox. */
    public function import(Request $request, VendorPortalIntegration $integration): RedirectResponse
    {
        $data = $request->validate([
            'external_id' => ['required', 'string', 'max:80'],
            'external_number' => ['nullable', 'string', 'max:40'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'location_name' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:200'],
            'trade' => ['nullable', 'string', 'max:40'],
            'priority' => ['nullable', 'string', 'max:40'],
            'nte' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
            'scheduled_by' => ['nullable', 'date'],
        ]);

        $pwo = ImportPortalWorkOrder::run($integration, PortalWorkRequest::fromArray($data + ['source' => 'manual']));

        return redirect()->route('jobs.show', $pwo->job_id)->with('status', "Imported {$integration->name} request {$pwo->external_id} as {$pwo->workOrder?->number}.");
    }

    public function markPaid(Request $request, PortalWorkOrder $pwo): RedirectResponse
    {
        RecordPortalPayment::run($pwo, today()->toDateString(), 'recorded by '.$this->actor($request)->name);

        return back()->with('status', 'Payment recorded from the portal.');
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
