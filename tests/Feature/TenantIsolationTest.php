<?php

use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('scopes records to the current tenant', function () {
    $a = Tenant::create(['name' => 'Tenant A']);
    $b = Tenant::create(['name' => 'Tenant B']);

    app()->instance('currentTenantId', $a->id);
    Customer::create(['name' => 'Acme']);

    app()->instance('currentTenantId', $b->id);
    Customer::create(['name' => 'Globex']);

    // As tenant A, only A's customer is visible.
    app()->instance('currentTenantId', $a->id);
    expect(Customer::count())->toBe(1);
    expect(Customer::first()->name)->toBe('Acme');

    // The global scope is what hides the other; both rows exist underneath.
    expect(Customer::withoutGlobalScopes()->count())->toBe(2);
});

it('auto-assigns tenant_id on create from the bound tenant', function () {
    $t = Tenant::create(['name' => 'Tenant T']);
    app()->instance('currentTenantId', $t->id);

    $c = Customer::create(['name' => 'Initech']);
    expect($c->tenant_id)->toBe($t->id);
});
