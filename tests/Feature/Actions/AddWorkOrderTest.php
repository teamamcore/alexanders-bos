<?php

use App\Actions\Intake\CreateJobFromIntake;
use App\Actions\WorkOrders\AddWorkOrder;
use App\Models\Tenant;
use App\States\WorkOrder\NewWorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('adds a work order that starts in the new state', function () {
    $tenant = Tenant::create(['name' => 'Batsons']);
    app()->instance('currentTenantId', $tenant->id);

    $job = CreateJobFromIntake::run('Acme', 'Job');
    $wo = AddWorkOrder::run($job, 'WO-1', 'Leak reported', 'morning');

    expect($wo->job_id)->toBe($job->id)
        ->and($wo->schedule_window)->toBe('morning')
        ->and($wo->customer_notes)->toBe('Leak reported')
        ->and($wo->status)->toBeInstanceOf(NewWorkOrder::class);
});
