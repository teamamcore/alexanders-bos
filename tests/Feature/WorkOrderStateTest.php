<?php

use App\Actions\Intake\CreateJobFromIntake;
use App\Actions\WorkOrders\AddWorkOrder;
use App\Models\Tenant;
use App\States\WorkOrder\Completed;
use App\States\WorkOrder\InProgress;
use App\States\WorkOrder\Scheduled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

uses(RefreshDatabase::class);

function freshWorkOrder()
{
    $tenant = Tenant::create(['name' => 'Batsons']);
    app()->instance('currentTenantId', $tenant->id);
    $job = CreateJobFromIntake::run('Acme', 'Job');

    return AddWorkOrder::run($job, 'WO-1', null, 'morning');
}

it('advances new -> scheduled -> in_progress -> complete', function () {
    $wo = freshWorkOrder();

    $wo->status->transitionTo(Scheduled::class);
    $wo->status->transitionTo(InProgress::class);
    $wo->status->transitionTo(Completed::class);

    expect($wo->fresh()->status)->toBeInstanceOf(Completed::class);
});

it('forbids skipping straight from new to complete', function () {
    freshWorkOrder()->status->transitionTo(Completed::class);
})->throws(CouldNotPerformTransition::class);
