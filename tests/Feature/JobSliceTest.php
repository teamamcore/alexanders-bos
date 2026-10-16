<?php

use App\Enums\Role;
use App\Models\Job;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

function actingAdmin(): array
{
    $tenant = Tenant::create(['name' => 'Batsons']);
    app()->instance('currentTenantId', $tenant->id);
    $admin = User::create([
        'tenant_id' => $tenant->id, 'name' => 'Admin',
        'email' => 'admin@example.com', 'password' => 'secret', 'role' => Role::Administrator,
    ]);
    app()->instance('currentUser', $admin);
    test()->actingAs($admin);

    return [$tenant, $admin];
}

it('renders the jobs page for an authenticated user', function () {
    actingAdmin();
    $this->get(route('jobs.index'))->assertOk()->assertSee('New job');
});

it('creates a customer and job from intake', function () {
    [$tenant] = actingAdmin();

    Volt::test('pages.jobs.index')
        ->set('customer_name', 'Acme Industrial')
        ->set('job_title', 'Backflow test')
        ->call('createJob')
        ->assertHasNoErrors();

    $job = Job::where('title', 'Backflow test')->first();
    expect($job)->not->toBeNull();
    expect($job->tenant_id)->toBe($tenant->id);
    expect($job->customer->name)->toBe('Acme Industrial');
});

it('adds a work order to a job', function () {
    [$tenant] = actingAdmin();
    $customer = \App\Models\Customer::create(['name' => 'Acme']);
    $job = Job::create(['customer_id' => $customer->id, 'title' => 'Job', 'status' => 'open']);

    Volt::test('pages.jobs.show', ['job' => $job])
        ->set('wo_number', 'WO-1002')
        ->set('customer_notes', 'Leak reported')
        ->set('schedule_window', 'afternoon')
        ->call('addWorkOrder')
        ->assertHasNoErrors();

    $wo = WorkOrder::where('number', 'WO-1002')->first();
    expect($wo)->not->toBeNull();
    expect($wo->job_id)->toBe($job->id);
    expect($wo->schedule_window)->toBe('afternoon');
});
