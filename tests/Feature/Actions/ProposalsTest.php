<?php

declare(strict_types=1);

use App\Actions\Proposals\ConvertProposalToJob;
use App\Actions\Proposals\CreateProposal;
use App\Actions\Proposals\MoveProposalStage;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Tenant;
use App\Models\WorkOrder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function proposalCustomer(): Customer
{
    $tenant = Tenant::create(['name' => 'Batsons']);
    app()->instance('currentTenantId', $tenant->id);

    return Customer::create(['name' => 'Acme Industrial']);
}

it('creates a proposal and totals its line items', function () {
    $customer = proposalCustomer();

    $proposal = CreateProposal::run($customer, 'Backflow retrofit', [
        ['description' => 'Labor', 'amount' => 800],
        ['description' => 'Parts', 'amount' => 450.50],
    ]);

    expect($proposal->stage)->toBe('draft')
        ->and((float) $proposal->total)->toBe(1250.50)
        ->and($proposal->title)->toBe('Backflow retrofit');
});

it('enforces the pipeline transitions', function () {
    $proposal = CreateProposal::run(proposalCustomer(), 'Job');

    expect(MoveProposalStage::run($proposal, 'sent')->stage)->toBe('sent');
    expect(MoveProposalStage::run($proposal->refresh(), 'follow_up')->stage)->toBe('follow_up');

    // can't skip straight from draft to won
    $draft = CreateProposal::run(proposalCustomer(), 'Other');
    expect(fn () => MoveProposalStage::run($draft, 'won'))->toThrow(DomainException::class);
});

it('converts a proposal into a linked job and work order', function () {
    $customer = proposalCustomer();
    $proposal = CreateProposal::run($customer, 'Sewer line repair', [
        ['description' => 'Excavation', 'amount' => 2000],
    ]);

    $job = ConvertProposalToJob::run($proposal);

    expect($job)->toBeInstanceOf(Job::class)
        ->and($job->customer_id)->toBe($customer->id)
        ->and($job->title)->toBe('Sewer line repair')
        ->and($proposal->refresh()->stage)->toBe('won')
        ->and($proposal->converted_job_id)->toBe($job->id);

    $wo = WorkOrder::withoutGlobalScopes()->where('job_id', $job->id)->first();
    expect($wo)->not->toBeNull()
        ->and($wo->customer_notes)->toContain('Excavation');

    // converting twice is blocked
    expect(fn () => ConvertProposalToJob::run($proposal->refresh()))->toThrow(DomainException::class);
});
