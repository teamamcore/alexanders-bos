<?php

use App\Actions\Intake\CreateJobFromIntake;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a customer and job from intake, trimming the name', function () {
    $tenant = Tenant::create(['name' => 'Batsons']);
    app()->instance('currentTenantId', $tenant->id);

    $job = CreateJobFromIntake::run('  Acme Industrial ', 'Backflow test');

    expect($job->exists)->toBeTrue()
        ->and($job->tenant_id)->toBe($tenant->id)
        ->and($job->customer->name)->toBe('Acme Industrial')
        ->and($job->title)->toBe('Backflow test');
});

it('reuses an existing customer by name', function () {
    $tenant = Tenant::create(['name' => 'Batsons']);
    app()->instance('currentTenantId', $tenant->id);

    CreateJobFromIntake::run('Acme', 'Job A');
    CreateJobFromIntake::run('Acme', 'Job B');

    expect(Customer::count())->toBe(1)
        ->and(Job::count())->toBe(2);
});
