<?php

use App\Actions\Intake\CreateJobFromIntake;
use App\Actions\WorkOrders\AddWorkOrder;
use App\Actions\WorkOrders\ScheduleWorkOrder;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\States\WorkOrder\Scheduled;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function scheduledSetup(): array
{
    $tenant = Tenant::create(['name' => 'Batsons']);
    app()->instance('currentTenantId', $tenant->id);
    $crew = User::create([
        'tenant_id' => $tenant->id, 'name' => 'Field Tech',
        'email' => 'tech@example.com', 'password' => 'secret', 'role' => Role::Field,
    ]);
    $job = CreateJobFromIntake::run('Acme', 'Job');
    $wo = AddWorkOrder::run($job, 'WO-1', null, 'morning');

    return [$wo, $crew];
}

it('assigns, dates, and moves a work order to scheduled', function () {
    [$wo, $crew] = scheduledSetup();

    $result = ScheduleWorkOrder::run($wo, $crew, '2026-10-20', 'afternoon');

    expect($result->assigned_to)->toBe($crew->id)
        ->and($result->scheduled_date->toDateString())->toBe('2026-10-20')
        ->and($result->schedule_window)->toBe('afternoon')
        ->and($result->status)->toBeInstanceOf(Scheduled::class);
});
