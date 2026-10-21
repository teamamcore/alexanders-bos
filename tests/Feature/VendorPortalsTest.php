<?php

declare(strict_types=1);

use App\Actions\Invoicing\ApproveInvoice;
use App\Actions\Invoicing\GenerateInvoiceFromWorkOrder;
use App\Actions\Portals\PollPortal;
use App\Actions\Portals\UpdateIntegration;
use App\Actions\WorkOrders\CompleteWorkOrder;
use App\Actions\WorkOrders\ScheduleWorkOrder;
use App\Actions\WorkOrders\StartWorkOrder;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\PortalSyncEvent;
use App\Models\PortalWorkOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VendorPortalIntegration;
use App\Platform\Portals\PortalManager;
use Database\Seeders\BatsonsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// Module 3.14 — Vendor Portal Integrations.

beforeEach(function () {
    $this->seed(BatsonsSeeder::class);
    $this->tenant = Tenant::firstOrFail();
    $this->admin = User::where('email', 'eric@batsonsplumbing.com')->firstOrFail();
    $this->marcus = User::where('email', 'marcus@batsonsplumbing.com')->firstOrFail();
    $this->sandbox = VendorPortalIntegration::where('adapter', 'sandbox')->firstOrFail();
    $this->sc = VendorPortalIntegration::where('adapter', 'service_channel')->firstOrFail();
});

it('imports portal work requests as a customer, site, job and typed work order — once', function () {
    $r = PollPortal::run($this->sandbox);
    expect($r['imported'])->toBe(2)->and($r['error'])->toBeNull();

    $pwo = PortalWorkOrder::where('external_id', 'SC-88213')->firstOrFail();
    expect($pwo->status)->toBe('imported')
        ->and($pwo->customer->name)->toBe('National Retail Co.')
        ->and($pwo->job->serviceLocation->address)->toBe('2100 Commerce Pkwy')
        ->and($pwo->workOrder->work_type)->toBe('sewer')
        ->and($pwo->workOrder->number)->toBe('WO-88213')
        ->and((float) $pwo->nte)->toBe(750.0)
        ->and($pwo->workOrder->customer_notes)->toContain('NTE $750.00');
    expect(PortalWorkOrder::where('external_id', 'SC-88240')->value('status'))->toBe('imported');
    expect($this->admin->notifications()->get()->pluck('data.kind'))->toContain('portal_request');

    // Polling again does not duplicate.
    expect(PollPortal::run($this->sandbox)['imported'])->toBe(0)
        ->and(PortalWorkOrder::count())->toBe(2)
        ->and(Customer::where('name', 'National Retail Co.')->count())->toBe(1);
});

it('sends status and invoice back to the portal as the work moves, flags NTE, and records payment', function () {
    PollPortal::run($this->sandbox);
    $pwo = PortalWorkOrder::where('external_id', 'SC-88213')->firstOrFail();
    $wo = $pwo->workOrder;

    ScheduleWorkOrder::run($wo, $this->marcus, today()->toDateString(), 'morning');
    expect($pwo->fresh()->status)->toBe('scheduled')->and($pwo->fresh()->external_status)->toBe('SCHEDULED');
    StartWorkOrder::run($wo->fresh(), $this->marcus, false);
    expect($pwo->fresh()->status)->toBe('in_progress');
    CompleteWorkOrder::run($wo->fresh(), $this->marcus, false);
    expect($pwo->fresh()->status)->toBe('completed')
        ->and(PortalSyncEvent::where('portal_work_order_id', $pwo->id)->where('type', 'status')->where('status', 'ok')->count())->toBe(3);

    $inv = GenerateInvoiceFromWorkOrder::run($wo->fresh(), 925.00); // over the $750 NTE
    ApproveInvoice::run($inv);
    $pwo->refresh();
    expect($pwo->status)->toBe('invoiced')->and($pwo->invoice_id)->toBe($inv->id)->and($pwo->isPastNte())->toBeTrue()
        ->and(PortalSyncEvent::where('portal_work_order_id', $pwo->id)->where('type', 'invoice')->exists())->toBeTrue()
        ->and($this->admin->notifications()->get()->pluck('data.kind'))->toContain('portal_nte');

    // Portal reports payment → invoice paid, request closed.
    $this->actingAs($this->admin)->post(route('portals.paid', $pwo))->assertRedirect();
    expect($pwo->fresh()->status)->toBe('paid')->and($inv->fresh()->status)->toBe('paid')->and($inv->fresh()->paid_at)->not->toBeNull();
});

it('accepts work requests and payment notices on the portal webhook with the integration token', function () {
    $token = $this->sandbox->webhook_token;
    $this->postJson("/webhooks/portals/{$this->sandbox->id}", ['type' => 'work_request', 'external_id' => 'SC-99001', 'external_number' => '99001', 'customer_name' => 'National Retail Co.', 'location_name' => 'Store #0500', 'address' => '1 Mall Dr', 'trade' => 'WATER HEATER', 'nte' => 1200, 'description' => 'No hot water in break room'], ['X-Portal-Token' => 'nope'])->assertForbidden();

    $this->postJson("/webhooks/portals/{$this->sandbox->id}", ['type' => 'work_request', 'external_id' => 'SC-99001', 'external_number' => '99001', 'customer_name' => 'National Retail Co.', 'location_name' => 'Store #0500', 'address' => '1 Mall Dr', 'trade' => 'WATER HEATER', 'nte' => 1200, 'description' => 'No hot water in break room'], ['X-Portal-Token' => $token])
        ->assertOk()->assertJson(['ok' => true]);
    $pwo = PortalWorkOrder::where('external_id', 'SC-99001')->firstOrFail();
    expect($pwo->workOrder->work_type)->toBe('water_heater');

    $inv = GenerateInvoiceFromWorkOrder::run($pwo->workOrder, 400);
    ApproveInvoice::run($inv);
    $this->postJson("/webhooks/portals/{$this->sandbox->id}", ['type' => 'payment', 'external_id' => 'SC-99001', 'paid_at' => today()->toDateString(), 'reference' => 'ACH-1'], ['X-Portal-Token' => $token])->assertOk();
    expect($pwo->fresh()->status)->toBe('paid')->and($inv->fresh()->status)->toBe('paid');
});

it('records a portal that is not connected yet without failing the poll, and counts only eligible portals toward the five', function () {
    $r = PollPortal::run($this->sc);
    expect($r['error'])->toContain('not on file')
        ->and($this->sc->fresh()->last_error)->not->toBeNull()
        ->and(PortalSyncEvent::where('integration_id', $this->sc->id)->where('status', 'failed')->exists())->toBeTrue();

    $usage = PortalManager::includedUsage();
    expect($usage['included'])->toBe(5)->and($usage['used'])->toBe(2); // ServiceChannel + Paramark; sandbox excluded

    UpdateIntegration::run($this->sc, ['status' => 'blocked', 'blocked_reason' => 'Provider refuses vendor API access'], $this->admin);
    expect($this->sc->fresh()->counts_toward_included)->toBeFalse()
        ->and(PortalManager::includedUsage()['used'])->toBe(1)
        ->and(PortalManager::includedUsage()['blocked'])->toBe(1);

    expect(fn () => UpdateIntegration::run($this->sc->fresh(), ['status' => 'blocked', 'blocked_reason' => ''], $this->admin))->toThrow(DomainException::class);
});

it('stores credentials encrypted and keeps them when the form is saved blank', function () {
    UpdateIntegration::run($this->sc, ['status' => 'building', 'client_id' => 'abc', 'client_secret' => 's3cret', 'username' => 'u', 'password' => 'p', 'subscriber_id' => '42', 'base_url' => 'https://api.servicechannel.com'], $this->admin);
    $raw = DB::table('vendor_portal_integrations')->where('id', $this->sc->id)->value('credentials');
    expect($raw)->not->toContain('s3cret')->and($this->sc->fresh()->isConfigured())->toBeTrue();

    $this->actingAs($this->admin)->post(route('portals.update', $this->sc), ['status' => 'building', 'client_secret' => ''])->assertRedirect();
    expect($this->sc->fresh()->credentials['client_secret'])->toBe('s3cret');
});

it('renders the portals page, lets office users key in a manual request, and keeps config admin-only', function () {
    $this->actingAs($this->admin);
    $this->get('/portals')->assertOk()->assertSee('Vendor Portals')->assertSee('ServiceChannel')->assertSee('Paramark')->assertSee('Sandbox');

    $manual = VendorPortalIntegration::create(['key' => 'city_facilities', 'name' => 'City Facilities (email)', 'adapter' => 'manual', 'status' => 'live']);
    $this->post(route('portals.import', $manual), ['external_id' => 'CF-7', 'external_number' => '7', 'customer_name' => 'City of Springfield', 'trade' => 'BACKFLOW', 'description' => 'Annual test — Fire Station 3'])->assertRedirect();
    expect(PortalWorkOrder::where('external_id', 'CF-7')->firstOrFail()->workOrder->work_type)->toBe('backflow_testing');

    $office = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => Role::Office]);
    $this->actingAs($office)->get('/portals')->assertOk()->assertDontSee('Add a portal');
    $this->actingAs($office)->post(route('portals.update', $this->sc), ['status' => 'live'])->assertForbidden();
    $this->actingAs($this->marcus)->get('/portals')->assertForbidden();
});
