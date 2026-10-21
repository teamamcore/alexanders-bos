<?php

declare(strict_types=1);

use App\Actions\Equipment\CheckOutEquipment;
use App\Actions\Equipment\LogEquipmentMaintenance;
use App\Actions\Equipment\LogEquipmentUsage;
use App\Actions\Equipment\ReturnEquipment;
use App\Actions\Fleet\LogVehicleCost;
use App\Actions\Fleet\RecordMileage;
use App\Actions\Inventory\AdjustStock;
use App\Actions\Inventory\ImportSupplierPriceList;
use App\Enums\Role;
use App\Models\Equipment;
use App\Models\Part;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Support\Depreciation;
use Database\Seeders\BatsonsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// Module 3.7 — Parts & Inventory, Billable Equipment, Fleet.

beforeEach(function () {
    Storage::fake('public');
    $this->seed(BatsonsSeeder::class);
    $this->tenant = Tenant::firstOrFail();
    $this->admin = User::where('email', 'eric@batsonsplumbing.com')->firstOrFail();
    $this->field = User::where('email', 'marcus@batsonsplumbing.com')->firstOrFail();
    $this->wo = WorkOrder::withoutGlobalScopes()->where('number', 'WO-1001')->firstOrFail();
});

// ---- Parts & inventory ------------------------------------------------------

it('renders the three asset pages', function () {
    $this->actingAs($this->admin);
    $this->get('/parts')->assertOk()->assertSee('Parts &amp; Inventory', false)->assertSee('Reorder alerts');
    $this->get('/equipment')->assertOk()->assertSee('Billable Equipment')->assertSee('Jetter');
    $this->get('/fleet')->assertOk()->assertSee('Fleet')->assertSee('Truck 3');
});

it('finds parts by crew nickname, SKU, or name', function () {
    $this->actingAs($this->admin);
    $this->get('/parts?q=BV34')->assertOk()->assertSee('3/4&quot; Brass Ball Valve', false);
    $this->get('/parts?q=the big wrench')->assertOk()->assertSee('24&quot; Pipe Wrench', false);
    $this->get('/parts?q=no-such-thing')->assertOk()->assertSee('Nothing matches');
});

it('lets office users change a price manually and records it in item history', function () {
    $part = Part::where('sku', 'BV-34')->firstOrFail();
    $this->actingAs($this->admin)->post(route('parts.price', $part), ['price' => 19.95])->assertRedirect();
    expect((float) $part->fresh()->price)->toBe(19.95)
        ->and($part->history()->where('action', 'updated')->exists())->toBeTrue();

    $this->actingAs($this->field)->post(route('parts.price', $part), ['price' => 1])->assertForbidden();
});

it('adjusts stock with a reason, links it to a work order, and raises low-stock alerts', function () {
    $part = Part::where('sku', 'BV-34')->firstOrFail();
    $before = $part->stock_qty;

    AdjustStock::run($part, -($before - 1), 'used_on_job', $this->field, $this->wo, 'Backflow repair');
    expect($part->fresh()->stock_qty)->toBe(1)
        ->and($part->fresh()->isLowStock())->toBeTrue()
        ->and($part->stockAdjustments()->first()->work_order_id)->toBe($this->wo->id);

    expect(fn () => AdjustStock::run($part, -1, 'felt_like_it', $this->admin))->toThrow(DomainException::class);
    $this->actingAs($this->admin)->get('/parts')->assertSee('Reorder alerts')->assertSee($part->name);
});

it('checks a delivery against the order, records shortage and damage with a photo, and receives usable stock', function () {
    $part = Part::where('sku', 'BV-34')->firstOrFail();
    $start = $part->stock_qty;

    $this->actingAs($this->field)->post(route('parts.delivery', $part), [
        'reference' => 'PO-77', 'qty_ordered' => 10, 'qty_received' => 8, 'qty_damaged' => 2,
        'work_order_id' => $this->wo->id, 'photo' => UploadedFile::fake()->image('box.jpg'),
    ])->assertRedirect()->assertSessionHas('status', fn ($s) => str_contains($s, 'Short 2') && str_contains($s, 'Damaged 2'));

    $check = $part->deliveryChecks()->first();
    expect($check->shortage())->toBe(2)->and($check->usable())->toBe(6)
        ->and($part->fresh()->stock_qty)->toBe($start + 6)
        ->and($check->work_order_id)->toBe($this->wo->id);
    Storage::disk('public')->assertExists($check->photo_path);
});

it('imports a supplier price list, updating known SKUs and creating new ones', function () {
    $supplier = Supplier::firstOrFail();
    $csv = "sku,name,price,category\nBV-34,3/4 Brass Ball Valve,21.50,Valves\nNEW-1,Widget,4.25,Misc\n,bad row,1\n";

    $r = ImportSupplierPriceList::run($supplier, $csv);
    expect($r)->toMatchArray(['updated' => 1, 'created' => 1, 'skipped' => 1])
        ->and((float) Part::where('sku', 'BV-34')->value('price'))->toBe(21.5)
        ->and(Part::where('sku', 'NEW-1')->value('category'))->toBe('Misc');

    $this->actingAs($this->admin)->post(route('parts.import'), [
        'supplier_name' => 'TECH Supply', 'file' => UploadedFile::fake()->createWithContent('prices.csv', "sku,price\nNEW-2,9.99\n"),
    ])->assertRedirect()->assertSessionHas('status', fn ($s) => str_contains($s, '1 created'));
    expect(Supplier::where('name', 'TECH Supply')->exists())->toBeTrue();

    $this->actingAs($this->field)->post(route('parts.import'), ['supplier_name' => 'x', 'file' => UploadedFile::fake()->createWithContent('p.csv', 'sku,price')])->assertForbidden();
});

it('creates a one-off item from a receipt tied to a job', function () {
    $this->actingAs($this->admin)->post(route('parts.store'), [
        'name' => 'Odd flange from Home Depot', 'price' => 12, 'cost' => 9.5, 'is_one_off' => 1,
        'job_id' => $this->wo->job_id, 'stock_qty' => 1, 'nicknames' => 'flange thing, HD flange',
        'photo' => UploadedFile::fake()->image('receipt.jpg'),
    ])->assertRedirect();

    $p = Part::where('name', 'Odd flange from Home Depot')->firstOrFail();
    expect($p->is_one_off)->toBeTrue()->and($p->job_id)->toBe($this->wo->job_id)->and($p->nicknames)->toBe(['flange thing', 'HD flange']);
    Storage::disk('public')->assertExists($p->photo_path);
});

// ---- Billable equipment -----------------------------------------------------

it('bills equipment on a work order, accrues hours, and flags maintenance due by hours', function () {
    $jetter = Equipment::where('type', 'jetter')->firstOrFail();
    $jetter->usages()->delete();
    $jetter->maintenance()->delete();
    $jetter->forceFill(['billing_rate' => 150, 'billing_unit' => 'hour', 'hours_used' => 0, 'maintenance_schedule' => ['every_hours' => 10]])->save();

    LogEquipmentUsage::run($jetter, $this->wo, 4, $this->field);
    expect((float) $jetter->fresh()->hours_used)->toBe(4.0)
        ->and($jetter->fresh()->revenue())->toBe(600.0)
        ->and($jetter->fresh()->maintenanceStatus()['due'])->toBeFalse();

    LogEquipmentUsage::run($jetter, $this->wo, 6.5, $this->field);
    $status = $jetter->fresh()->maintenanceStatus();
    expect($status['due'])->toBeTrue()->and($status['hours_since'])->toBe(10.5);

    LogEquipmentMaintenance::run($jetter->fresh(), 'maintenance', 180, $this->admin, today()->toDateString(), 'Pump service');
    expect($jetter->fresh()->maintenanceStatus()['due'])->toBeFalse()
        ->and($jetter->fresh()->maintenanceCost())->toBe(180.0)
        ->and($jetter->fresh()->netContribution())->toBe(round(1575.0 - 180.0 - $jetter->fresh()->depreciation()['to_date'], 2));

    $this->actingAs($this->admin)->get(route('equipment.show', $jetter))->assertOk()->assertSee('WO-1001')->assertSee('Pump service');
});

it('tracks check-out and return with a damage report that takes a unit out of service until repaired', function () {
    $saw = Equipment::where('type', 'concrete_saw')->firstOrFail();

    CheckOutEquipment::run($saw, $this->field, $this->field);
    expect($saw->fresh()->status)->toBe('checked_out')->and($saw->fresh()->checked_out_to)->toBe($this->field->id);
    expect(fn () => CheckOutEquipment::run($saw->fresh(), $this->admin, $this->admin))->toThrow(DomainException::class);

    ReturnEquipment::run($saw->fresh(), $this->field, 'Blade guard cracked', 'major');
    expect($saw->fresh()->status)->toBe('down')
        ->and($saw->fresh()->issues()->open()->where('severity', 'major')->count())->toBe(1);

    LogEquipmentMaintenance::run($saw->fresh(), 'repair', 95, $this->admin);
    expect($saw->fresh()->status)->toBe('available');

    // Field users can report issues through the UI.
    $this->actingAs($this->field)->post(route('equipment.issue', $saw), ['description' => 'Pull cord frayed', 'severity' => 'minor'])->assertRedirect();
    expect($saw->fresh()->issues()->open()->count())->toBe(2);
});

it('computes straight-line depreciation', function () {
    $d = Depreciation::straightLine(12000, 2000, 5, Carbon::parse('2024-01-01'), Carbon::parse('2026-01-01'));
    expect($d['annual'])->toBe(2000.0)->and($d['to_date'])->toBe(4000.0)->and($d['book_value'])->toBe(8000.0);

    $none = Depreciation::straightLine(null, null, null, null);
    expect($none['to_date'])->toBe(0.0);
});

// ---- Fleet ----------------------------------------------------------------

it('records mileage, flags service by miles and renewals, and clears the stale-mileage reminder', function () {
    $truck = Vehicle::where('name', 'Truck 3')->firstOrFail();
    expect($truck->mileageStale())->toBeTrue()
        ->and(collect($truck->renewalsDue())->pluck('name'))->toContain('Inspection');

    RecordMileage::run($truck, $truck->mileage + 6000, $this->field);
    expect($truck->fresh()->mileageStale())->toBeFalse()
        ->and(collect($truck->fresh()->serviceDue())->pluck('name'))->toContain('oil_change');

    expect(fn () => RecordMileage::run($truck->fresh(), 10, $this->field))->toThrow(DomainException::class);

    LogVehicleCost::run($truck->fresh(), 'maintenance', 89.5, $this->admin, today()->toDateString(), $truck->fresh()->mileage, 'oil_change');
    expect(collect($truck->fresh()->serviceDue())->pluck('name'))->not->toContain('oil_change')
        ->and($truck->fresh()->costTotal())->toBeGreaterThanOrEqual(89.5);

    $this->actingAs($this->field)->post(route('fleet.mileage', $truck), ['odometer' => $truck->fresh()->mileage + 10])->assertRedirect();
    $this->actingAs($this->admin)->get(route('fleet.show', $truck))->assertOk()->assertSee('Cost of ownership')->assertSee('Inspection');
});

it('reports total cost of ownership and depreciation per vehicle', function () {
    $truck = Vehicle::where('name', 'Truck 3')->firstOrFail();
    LogVehicleCost::run($truck, 'fuel', 120, $this->field);
    $tco = $truck->fresh()->totalCostOfOwnership();
    expect($tco)->toBe(round((float) $truck->purchase_cost + $truck->fresh()->costTotal(), 2))
        ->and($truck->fresh()->depreciation()['to_date'])->toBeGreaterThan(0)
        ->and($truck->fresh()->costByType())->toHaveKey('fuel');
});

it('keeps register edits to office and administrators', function () {
    $this->actingAs($this->field);
    $this->post(route('equipment.store'), ['name' => 'X', 'billing_rate' => 1, 'billing_unit' => 'hour'])->assertForbidden();
    $this->post(route('fleet.store'), ['name' => 'X'])->assertForbidden();
    $this->post(route('parts.store'), ['name' => 'X', 'price' => 1])->assertForbidden();

    $office = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => Role::Office]);
    $this->actingAs($office)->post(route('fleet.store'), [
        'name' => 'Van 9', 'year' => 2023, 'make' => 'Ford', 'model' => 'Transit', 'mileage' => 1200,
        'intervals' => ['oil_change' => ['every_miles' => 5000, 'every_months' => '']],
    ])->assertRedirect();
    $van = Vehicle::where('name', 'Van 9')->firstOrFail();
    expect($van->service_intervals)->toBe([['name' => 'oil_change', 'every_miles' => 5000, 'every_months' => null]]);
});
