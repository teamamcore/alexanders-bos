<?php

use App\Actions\Intake\CreateJobFromIntake;
use App\Actions\WorkOrders\AddWorkOrder;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\States\WorkOrder\Scheduled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

it('schedules a work order from the dispatch board', function () {
    $tenant = Tenant::create(['name' => 'Batsons']);
    app()->instance('currentTenantId', $tenant->id);
    $admin = User::create([
        'tenant_id' => $tenant->id, 'name' => 'Admin',
        'email' => 'admin@example.com', 'password' => 'secret', 'role' => Role::Administrator,
    ]);
    $this->actingAs($admin);
    $crew = User::create([
        'tenant_id' => $tenant->id, 'name' => 'Tech',
        'email' => 'tech@example.com', 'password' => 'secret', 'role' => Role::Field,
    ]);
    $job = CreateJobFromIntake::run('Acme', 'Job');
    $wo = AddWorkOrder::run($job, 'WO-1', null, 'morning');

    Volt::test('pages.schedule.index')
        ->call('openSchedule', $wo->id)
        ->set('assignee_id', $crew->id)
        ->set('date', '2026-10-20')
        ->set('window', 'afternoon')
        ->call('schedule', $wo->id)
        ->assertHasNoErrors();

    $wo->refresh();
    expect($wo->status)->toBeInstanceOf(Scheduled::class)
        ->and($wo->assigned_to)->toBe($crew->id);
});
