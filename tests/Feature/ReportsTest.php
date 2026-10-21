<?php

declare(strict_types=1);

use App\Actions\Equipment\LogEquipmentUsage;
use App\Actions\Inventory\AdjustStock;
use App\Actions\Invoicing\ApproveInvoice;
use App\Actions\Invoicing\GenerateInvoiceFromWorkOrder;
use App\Actions\Invoicing\MarkInvoicePaid;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\Tenant;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Platform\Reporting\JobCosting;
use App\Platform\Reporting\Reports;
use Database\Seeders\BatsonsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

// Module 3.9 — Reporting & Job Costing.

beforeEach(function () {
    $this->seed(BatsonsSeeder::class);
    $this->tenant = Tenant::firstOrFail();
    $this->admin = User::where('email', 'eric@batsonsplumbing.com')->firstOrFail();
    $this->marcus = User::where('email', 'marcus@batsonsplumbing.com')->firstOrFail();
    $this->wo = WorkOrder::withoutGlobalScopes()->where('number', 'WO-1001')->firstOrFail();
});

it('costs a work order by labor with burden and pay rules, materials at cost, and equipment usage', function () {
    config(['batsons.labor_burden_pct' => 0.25]);
    Employee::where('user_id', $this->marcus->id)->update(['hourly_rate' => 40]);

    // 4 regular hours on a Tuesday at 9am → 4 × 1.0 × 40 × 1.25 = 200
    TimeEntry::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->marcus->id, 'work_order_id' => $this->wo->id, 'job_id' => $this->wo->job_id,
        'category' => 'on_site', 'billable' => true, 'started_at' => Carbon::parse('2026-10-20 09:00'), 'ended_at' => Carbon::parse('2026-10-20 13:00')]);
    // 2 after-hours hours (Tue 7pm) → 2 × 1.5 × 40 × 1.25 = 150
    TimeEntry::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->marcus->id, 'work_order_id' => $this->wo->id, 'job_id' => $this->wo->job_id,
        'category' => 'on_site', 'billable' => true, 'started_at' => Carbon::parse('2026-10-20 19:00'), 'ended_at' => Carbon::parse('2026-10-20 21:00')]);

    $valve = Part::where('sku', 'BV-34')->firstOrFail(); // cost 11.20
    AdjustStock::run($valve, -2, 'used_on_job', $this->marcus, $this->wo);

    $jetter = Equipment::where('type', 'jetter')->firstOrFail(); // 150/h
    LogEquipmentUsage::run($jetter, $this->wo, 2, $this->marcus);

    $inv = GenerateInvoiceFromWorkOrder::run($this->wo, 1200);
    ApproveInvoice::run($inv);

    $c = JobCosting::forWorkOrder($this->wo);
    expect($c['labor_hours'])->toBe(6.0)
        ->and($c['labor'])->toBe(350.0)
        ->and($c['materials'])->toBe(22.4)
        ->and($c['equipment'])->toBe(300.0)
        ->and($c['cost'])->toBe(672.4)
        ->and($c['revenue'])->toBe(1200.0)
        ->and($c['profit'])->toBe(527.6)
        ->and($c['margin'])->toBe(44.0);

    expect(JobCosting::forJob($this->wo->job)['cost'])->toBe(672.4);
});

it('ages receivables by bucket and terms, with collection flags, from the invoice terms', function () {
    Invoice::query()->delete();
    $customer = Customer::findOrFail($this->wo->job->customer_id);
    $customer->update(['payment_terms' => 'net_15']);

    $inv = GenerateInvoiceFromWorkOrder::run($this->wo->fresh(), 500);
    expect($inv->customer_id)->toBe($customer->id)->and($inv->terms)->toBe('net_15');

    ApproveInvoice::run($inv);
    expect($inv->fresh()->issued_at->toDateString())->toBe(today()->toDateString())
        ->and($inv->fresh()->due_at->toDateString())->toBe(today()->addDays(15)->toDateString());

    // Backdate: 50 days past due → 31–60 bucket, collection flag (>30).
    $inv->forceFill(['issued_at' => today()->subDays(65), 'due_at' => today()->subDays(50)])->save();
    $ar = Reports::ar();
    expect($ar['total'])->toBe(500.0)->and($ar['overdue'])->toBe(500.0)->and($ar['flagged'])->toBe(1)
        ->and($ar['buckets']['31_60']['count'])->toBe(1)
        ->and($ar['by_terms']['net_15']['overdue'])->toBe(500.0)
        ->and(Reports::collectionFlag($inv->fresh()))->toBe('collection');

    MarkInvoicePaid::run($inv->fresh());
    expect($inv->fresh()->paid_at->toDateString())->toBe(today()->toDateString())
        ->and(Reports::ar()['total'])->toBe(0.0);
});

it('reports profitability by job type and a monthly trend series', function () {
    $byType = Reports::profitabilityByType();
    expect($byType)->not->toBeEmpty()->and($byType->first())->toHaveKeys(['label', 'revenue', 'cost', 'profit', 'margin']);

    $trends = Reports::trends(6);
    expect($trends)->toHaveCount(6)->and($trends[5]['month'])->toBe(today()->format('Y-m'))
        ->and(array_sum(array_column($trends, 'invoiced')))->toBeGreaterThan(0);
});

it('renders the dashboard, A/R, drill-down, and job cost sheet for office users and blocks field users', function () {
    $this->actingAs($this->admin);
    $this->get('/reports')->assertOk()->assertSee('Reports &amp; Job Costing', false)->assertSee('Profitability by job type')->assertSee('Trends');
    $this->get('/reports/ar')->assertOk()->assertSee('Open invoices')->assertSee('collections');
    $this->get('/reports/work-orders?type=backflow_testing')->assertOk()->assertSee('Backflow testing');
    $this->get(route('reports.job', $this->wo->job_id))->assertOk()->assertSee('Labor incl.');
    $this->get('/reports/work-orders?month='.today()->format('Y-m'))->assertOk();

    $this->actingAs($this->marcus)->get('/reports')->assertForbidden();
});

it('seeds enough history that the reports have something to say', function () {
    $ar = Reports::ar();
    expect($ar['open']->count())->toBeGreaterThan(0)->and($ar['flagged'])->toBeGreaterThan(0);
    expect(Reports::profitabilityByType()->count())->toBeGreaterThan(1);
});
