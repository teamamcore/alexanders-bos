<?php

use App\Actions\Intake\CreateJobFromIntake;
use App\Actions\WorkOrders\AddWorkOrder;
use App\Actions\WorkOrders\RescheduleWorkOrder;
use App\Actions\WorkOrders\ScheduleWorkOrder;
use App\Enums\Role;
use App\Models\ScheduleChange;
use App\Models\Tenant;
use App\Models\User;
use App\States\WorkOrder\Scheduled;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('records schedule history and keeps the work order scheduled', function () {
    $tenant = Tenant::create(['name' => 'Batsons']);
    app()->instance('currentTenantId', $tenant->id);
    $crew = User::create([
        'tenant_id' => $tenant->id, 'name' => 'Tech',
        'email' => 'tech@example.com', 'password' => 'secret', 'role' => Role::Field,
    ]);
    $job = CreateJobFromIntake::run('Acme', 'Job');
    $wo = AddWorkOrder::run($job, 'WO-1', null, 'morning');
    ScheduleWorkOrder::run($wo, $crew, '2026-10-20', 'morning');

    $result = RescheduleWorkOrder::run($wo->refresh(), '2026-10-23', 'Customer not ready', $crew->id);

    expect($result->scheduled_date->toDateString())->toBe('2026-10-23')
        ->and($result->status)->toBeInstanceOf(Scheduled::class);

    $change = ScheduleChange::first();
    expect($change)->not->toBeNull()
        ->and($change->to_date->toDateString())->toBe('2026-10-23')
        ->and($change->from_date->toDateString())->toBe('2026-10-20')
        ->and($change->reason)->toBe('Customer not ready')
        ->and($change->changed_by)->toBe($crew->id);
});
