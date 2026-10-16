<?php

declare(strict_types=1);

use App\Actions\Intake\CreateJobFromIntake;
use App\Actions\Invoicing\AddProgressPayment;
use App\Actions\Invoicing\ApproveInvoice;
use App\Actions\Invoicing\GenerateInvoiceFromWorkOrder;
use App\Actions\Invoicing\MarkInvoicePaid;
use App\Actions\Invoicing\SyncInvoiceToQuickBooks;
use App\Actions\Invoicing\VoidInvoice;
use App\Actions\WorkOrders\AddWorkOrder;
use App\Models\Tenant;
use App\Models\WorkOrder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function workOrderForInvoice(string $number = 'WO-1001'): WorkOrder
{
    $tenant = Tenant::create(['name' => 'Batsons']);
    app()->instance('currentTenantId', $tenant->id);
    $job = CreateJobFromIntake::run('Acme', 'Backflow repair');

    return AddWorkOrder::run($job, $number, null, 'morning');
}

it('generates an invoice with numbering paired to the work order', function () {
    $wo = workOrderForInvoice('WO-1001');

    $invoice = GenerateInvoiceFromWorkOrder::run($wo, 1250.00);

    expect($invoice->number)->toBe('INV-1001')
        ->and($invoice->status)->toBe('draft')
        ->and($invoice->is_progress_payment)->toBeFalse()
        ->and((float) $invoice->total)->toBe(1250.00)
        ->and($invoice->work_order_id)->toBe($wo->id);
});

it('numbers sequential progress payments on one work order', function () {
    $wo = workOrderForInvoice('WO-1001');

    $p1 = AddProgressPayment::run($wo, 500.00);
    $p2 = AddProgressPayment::run($wo, 750.00);

    expect($p1->number)->toBe('INV-1001-P1')
        ->and($p2->number)->toBe('INV-1001-P2')
        ->and($p1->is_progress_payment)->toBeTrue();
});

it('approves a draft and rejects approving a non-draft', function () {
    $invoice = GenerateInvoiceFromWorkOrder::run(workOrderForInvoice(), 100);

    $approved = ApproveInvoice::run($invoice);
    expect($approved->status)->toBe('approved');

    expect(fn () => ApproveInvoice::run($approved))->toThrow(DomainException::class);
});

it('voids an invoice and refuses to void it twice', function () {
    $invoice = GenerateInvoiceFromWorkOrder::run(workOrderForInvoice(), 100);

    $void = VoidInvoice::run($invoice);
    expect($void->status)->toBe('void')
        ->and($void->voided_at)->not->toBeNull();

    expect(fn () => VoidInvoice::run($void))->toThrow(DomainException::class);
});

it('marks paid but never a voided invoice', function () {
    $invoice = GenerateInvoiceFromWorkOrder::run(workOrderForInvoice(), 100);
    expect(MarkInvoicePaid::run($invoice)->status)->toBe('paid');

    $voided = VoidInvoice::run(GenerateInvoiceFromWorkOrder::run(workOrderForInvoice('WO-1002'), 100));
    expect(fn () => MarkInvoicePaid::run($voided))->toThrow(DomainException::class);
});

it('stamps the QuickBooks sync time', function () {
    $invoice = GenerateInvoiceFromWorkOrder::run(workOrderForInvoice(), 100);
    expect($invoice->qb_synced_at)->toBeNull();

    $synced = SyncInvoiceToQuickBooks::run($invoice);
    expect($synced->qb_synced_at)->not->toBeNull();
});
