<?php

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('limits field users to their own assigned work orders', function () {
    $t = Tenant::create(['name' => 'Tenant']);
    app()->instance('currentTenantId', $t->id);

    $field = User::create(['tenant_id' => $t->id, 'name' => 'Field One',
        'email' => 'field1@example.com', 'password' => 'secret', 'role' => Role::Field]);
    $other = User::create(['tenant_id' => $t->id, 'name' => 'Field Two',
        'email' => 'field2@example.com', 'password' => 'secret', 'role' => Role::Field]);
    $office = User::create(['tenant_id' => $t->id, 'name' => 'Office',
        'email' => 'office@example.com', 'password' => 'secret', 'role' => Role::Office]);

    $cust = Customer::create(['name' => 'Customer']);
    $job = Job::create(['customer_id' => $cust->id, 'title' => 'Job']);
    WorkOrder::create(['job_id' => $job->id, 'assigned_to' => $field->id]);
    WorkOrder::create(['job_id' => $job->id, 'assigned_to' => $other->id]);

    // Field user sees only their own.
    app()->instance('currentUser', $field);
    expect(WorkOrder::count())->toBe(1);
    expect(WorkOrder::first()->assigned_to)->toBe($field->id);

    // Office user sees all work orders in the tenant.
    app()->instance('currentUser', $office);
    expect(WorkOrder::count())->toBe(2);
});
