<?php

declare(strict_types=1);

use App\Actions\Migration\RunImport;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MigrationBatch;
use App\Models\MigrationRecord;
use App\Models\Part;
use App\Models\ServiceLocation;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\BatsonsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// SOW §4 — Data migration: QuickBooks / spreadsheet exports in, report of what could not be imported out.

beforeEach(function () {
    Storage::fake('local');
    $this->seed(BatsonsSeeder::class);
    $this->admin = User::where('email', 'eric@batsonsplumbing.com')->firstOrFail();
    $this->marcus = User::where('email', 'marcus@batsonsplumbing.com')->firstOrFail();
    $this->samples = database_path('migration-samples');
});

it('dry-runs a QuickBooks customer list: full report, nothing written', function () {
    $before = Customer::count();
    $b = RunImport::run('customers', $this->samples.'/customers.csv', true, $this->admin);

    expect($b->mode)->toBe('dry_run')->and($b->status)->toBe('done')
        ->and($b->total)->toBe(6)              // title rows + blank + "Total" footer ignored
        ->and($b->imported)->toBe(3)           // Greenway, Maple, Jordan (Acme already exists → updated ×2 incl. sub-customer row)
        ->and($b->updated)->toBe(2)
        ->and($b->skipped)->toBe(1)            // blank name
        ->and(collect($b->issues)->firstWhere('level', 'skipped')['reason'])->toContain('Blank customer name')
        ->and($b->columns)->toHaveKey('Customer', 'name');
    expect(Customer::count())->toBe($before)->and(MigrationRecord::count())->toBe(0);
});

it('imports customers with sub-customer sites, contacts and terms; re-running updates instead of duplicating', function () {
    $b = RunImport::run('customers', $this->samples.'/customers.csv', false, $this->admin);
    expect($b->imported)->toBe(3);

    $green = Customer::where('name', 'Greenway Apartments')->firstOrFail();
    expect($green->payment_terms)->toBe('net_15')->and($green->segment)->toBe('property_management')->and($green->email)->toBe('manager@greenway-apts.example')
        ->and($green->corpPoc?->name)->toBe('Lee Chen')->and($green->serviceLocations()->count())->toBe(1);
    expect(Customer::where('name', 'Maple Street Bakery')->firstOrFail()->payment_terms)->toBe('due_on_receipt');

    // "Acme Industrial Park:Building C" → a site on the existing Acme, not a new customer
    expect(Customer::where('name', 'like', 'Acme%')->count())->toBe(1)
        ->and(ServiceLocation::where('customer_id', Customer::where('name', 'Acme Industrial Park')->value('id'))->pluck('label'))->toContain('Building C');

    // Jordan Family has only a billing address → it becomes the service location
    expect(Customer::where('name', 'Jordan Family')->firstOrFail()->serviceLocations()->value('address'))->toContain('9 Birch Ln');

    $again = RunImport::run('customers', $this->samples.'/customers.csv', false, $this->admin);
    expect($again->imported)->toBe(0)->and($again->updated)->toBe(5)->and(Customer::where('name', 'Greenway Apartments')->count())->toBe(1);
});

it('imports items into the parts catalog and skips bundles', function () {
    $b = RunImport::run('items', $this->samples.'/items.csv', false, $this->admin);
    expect($b->imported + $b->updated)->toBe(6)->and($b->skipped)->toBe(1)
        ->and(collect($b->issues)->firstWhere('level', 'skipped')['reason'])->toContain('Bundle');

    $valve = Part::where('sku', 'BV-34')->firstOrFail();   // seeded part → updated from QuickBooks, not duplicated
    expect(Part::where('sku', 'BV-34')->count())->toBe(1)->and((float) $valve->price)->toBe(24.5)->and($valve->stock_qty)->toBe(36)->and($valve->category)->toBe('Materials');
    $wh = Part::where('sku', 'WH-50G')->firstOrFail();
    expect((float) $wh->price)->toBe(1480.0)->and($wh->name)->toBe('Water Heater 50 gal gas');
    expect(Part::where('name', 'Backflow Test')->firstOrFail()->unit)->toBe('hour');
});

it('imports invoices as open A/R or paid history, skipping payments, bad amounts and rows outside the window', function () {
    RunImport::run('customers', $this->samples.'/customers.csv', false, $this->admin);
    $b = RunImport::run('receivables', $this->samples.'/receivables.csv', false, $this->admin);

    expect($b->total)->toBe(7)->and($b->imported)->toBe(4)->and($b->skipped)->toBe(3);
    $reasons = collect($b->issues)->where('level', 'skipped')->pluck('reason')->implode(' | ');
    expect($reasons)->toContain('not an invoice')->toContain('import window')->toContain('not a number');

    $open = Invoice::where('number', 'INV-9001')->firstOrFail();
    expect($open->status)->toBe('approved')->and((float) $open->total)->toBe(1240.0)->and($open->paid_at)->toBeNull()->and($open->customer?->name)->toBe('Acme Industrial Park');
    $partial = Invoice::where('number', 'INV-8990')->firstOrFail();
    expect($partial->status)->toBe('approved')->and((float) $partial->total)->toBe(1860.5);
    expect(Invoice::where('number', 'INV-8950')->firstOrFail()->status)->toBe('paid');
    expect(collect($b->issues)->pluck('reason')->implode(' '))->toContain('Partially paid');

    // open A/R shows on the aging report
    $this->actingAs($this->admin)->get('/reports/ar')->assertOk()->assertSee('INV-9001');

    // re-run updates
    $again = RunImport::run('receivables', $this->samples.'/receivables.csv', false, $this->admin);
    expect($again->updated)->toBe(4)->and(Invoice::where('number', 'INV-9001')->count())->toBe(1);
});

it('imports the work order log as closed jobs + completed work orders linked to invoices, with tech attribution', function () {
    RunImport::run('customers', $this->samples.'/customers.csv', false, $this->admin);
    RunImport::run('receivables', $this->samples.'/receivables.csv', false, $this->admin);
    $b = RunImport::run('work_orders', $this->samples.'/work_orders.csv', false, $this->admin);

    expect($b->imported)->toBe(5)->and($b->skipped)->toBe(1); // WO-100 is 9 years old
    $wo = WorkOrder::where('number', 'WO-7781')->firstOrFail();
    expect($wo->status->getValue())->toBe('complete')->and($wo->work_type)->toBe('backflow_testing')->and($wo->assignee?->name)->toBe('Marcus Reyes')
        ->and((float) $wo->labor_hours)->toBe(3.5)->and($wo->job?->customer?->name)->toBe('Acme Industrial Park')->and($wo->job?->status)->toBe('closed')
        ->and($wo->job?->serviceLocation?->address)->toContain('Bldg C');
    expect(Invoice::where('number', 'INV-9001')->firstOrFail()->work_order_id)->toBe($wo->id);

    // unknown tech + missing invoice → warnings, still imported; invoice created as paid history from the amount is NOT done when amount missing
    $w = collect($b->issues)->where('level', 'warning')->pluck('reason')->implode(' | ');
    expect($w)->toContain("Tech 'Chris Nobody'")->toContain('Invoice INV-9009 not found');
    expect(WorkOrder::where('number', 'WO-7790')->firstOrFail()->assigned_to)->toBeNull();

    // the history shows up where the office looks
    $this->actingAs($this->admin)->get(route('work-orders.show', $wo))->assertOk()->assertSee('Annual backflow test on 4 RPZ');
    $this->get('/search?q=WO-7781')->assertOk()->assertSee('WO-7781');
});

it('runs from the admin screen: upload, dry run, commit, issues CSV, samples; field users are kept out', function () {
    $this->actingAs($this->admin)->get('/admin/migration')->assertOk()->assertSee('Data import')->assertSee('Not connected')->assertSee('Import window');

    $file = UploadedFile::fake()->createWithContent('customers.csv', file_get_contents($this->samples.'/customers.csv'));
    $this->post('/admin/migration', ['kind' => 'customers', 'file' => $file])->assertRedirect(route('admin.migration'))->assertSessionHas('status', fn ($s) => str_contains($s, 'Dry run') && str_contains($s, 'Nothing was written'));
    $dry = MigrationBatch::latest('id')->firstOrFail();
    expect($dry->isDryRun())->toBeTrue()->and(Storage::disk('local')->exists($dry->file_path))->toBeTrue()->and(Customer::where('name', 'Greenway Apartments')->exists())->toBeFalse();

    $this->post(route('admin.migration.commit', $dry))->assertRedirect()->assertSessionHas('status', fn ($s) => str_contains($s, 'Imported'));
    expect(Customer::where('name', 'Greenway Apartments')->exists())->toBeTrue();

    $this->get(route('admin.migration.issues', $dry))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->assertSee('Blank customer name');
    $this->get(route('admin.migration.sample', 'work_orders'))->assertOk();
    $this->get('/admin/migration')->assertSee('committed')->assertSee('dry run');

    $this->post('/admin/migration', ['kind' => 'customers', 'file' => UploadedFile::fake()->createWithContent('x.csv', "Foo,Bar\n1,2\n")])->assertRedirect();
    expect(MigrationBatch::latest('id')->firstOrFail()->status)->toBe('failed');

    $this->actingAs($this->marcus)->get('/admin/migration')->assertForbidden();
});

it('bulk-imports a folder from the console in dependency order', function () {
    $this->artisan('import:quickbooks', ['dir' => $this->samples, '--commit' => true])->assertSuccessful()
        ->expectsOutputToContain('Customers: 6 rows')->expectsOutputToContain('Work order records:');
    expect(WorkOrder::where('number', 'WO-7702')->exists())->toBeTrue()->and(Invoice::where('number', 'INV-8990')->firstOrFail()->work_order_id)->not->toBeNull();
    expect(MigrationBatch::where('mode', 'commit')->count())->toBe(4);
});
