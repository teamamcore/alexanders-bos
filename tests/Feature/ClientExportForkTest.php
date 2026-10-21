<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Gate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\File;

// Platform/Client seam — MSA §2.8, §6.1, §6.2, §11.3. See docs/ARCHITECTURE.md.

afterEach(function () {
    File::deleteDirectory(storage_path('app/exports'));
    File::delete(config_path('clients/forked-test-co.php'));
});

it('exports only the client\'s own data and never another tenant\'s', function () {
    $a = Tenant::create(['name' => 'Export Co', 'settings' => ['client_key' => 'export-co']]);
    $b = Tenant::create(['name' => 'Other Co']);

    app()->instance('currentTenantId', $a->id);
    Customer::create(['name' => 'Acme Exported']);
    app()->instance('currentTenantId', $b->id);
    Customer::create(['name' => 'Globex Hidden']);

    $this->artisan('client:export', ['tenant' => $a->id])->assertSuccessful();

    $zips = File::glob(storage_path('app/exports/export-co-*.zip'));
    expect($zips)->toHaveCount(1);

    $dir = substr($zips[0], 0, -4);
    $customers = File::get($dir.'/data/customers.csv');
    expect($customers)->toContain('Acme Exported')->not->toContain('Globex Hidden');

    $manifest = json_decode(File::get($dir.'/MANIFEST.json'), true);
    expect($manifest['tenant_id'])->toBe($a->id)
        ->and($manifest['data']['customers'])->toBe(1)
        ->and($manifest['excluded'])->not->toBeEmpty();
});

it('redacts credentials from the users export', function () {
    $t = Tenant::create(['name' => 'Redact Co', 'settings' => ['client_key' => 'redact-co']]);
    User::factory()->create(['tenant_id' => $t->id]);

    $this->artisan('client:export', ['tenant' => $t->id])->assertSuccessful();

    $dir = substr(File::glob(storage_path('app/exports/redact-co-*.zip'))[0], 0, -4);
    $header = strtok(File::get($dir.'/data/users.csv'), "\n");
    expect($header)->toContain('email')->not->toContain('password')->not->toContain('remember_token');
});

it('forks a client into a new tenant with structure but no transactional data', function () {
    $src = Tenant::create(['name' => 'Source Co', 'settings' => ['client_key' => 'source-co', 'pay_rules' => ['ot_after' => 40]]]);
    app()->instance('currentTenantId', $src->id);
    Gate::create(['status' => 'scheduled', 'name' => 'Dispatch ready', 'required_actions' => ['confirm']]);
    Equipment::create(['name' => 'Jetter', 'type' => 'jetter', 'billing_rate' => 125, 'billing_unit' => 'hour']);
    Customer::create(['name' => 'Should Not Copy']);

    $this->artisan('client:fork', ['source' => $src->id, 'name' => 'Forked Test Co'])->assertSuccessful();

    $new = Tenant::where('name', 'Forked Test Co')->firstOrFail();
    expect($new->clientKey())->toBe('forked-test-co')
        ->and($new->settings['pay_rules'])->toBe(['ot_after' => 40]);

    app()->instance('currentTenantId', $new->id);
    expect(Gate::count())->toBe(1)
        ->and(Equipment::count())->toBe(1)
        ->and(Equipment::first()->billing_rate)->toEqual(0)
        ->and(Customer::count())->toBe(0);

    expect(File::exists(config_path('clients/forked-test-co.php')))->toBeTrue();
});
