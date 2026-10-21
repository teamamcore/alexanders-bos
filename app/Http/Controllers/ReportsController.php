<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Job;
use App\Models\StockAdjustment;
use App\Models\WorkOrder;
use App\Platform\Reporting\JobCosting;
use App\Platform\Reporting\Reports;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Module 3.9 — Reporting & Job Costing (office + administrators). */
final class ReportsController extends Controller
{
    public function index(Request $request): View
    {
        $months = max(3, min(24, (int) $request->query('months', 12)));
        $trends = Reports::trends($months);
        $ar = Reports::ar();
        $byType = Reports::profitabilityByType();
        $all = JobCosting::total($byType->values()->all());

        $ytdFrom = today()->startOfYear();
        $ytd = JobCosting::total(Reports::profitabilityByType($ytdFrom)->values()->all());

        return view('ops.reports.index', [
            'months' => $months,
            'trends' => $trends,
            'maxMoney' => max(1.0, max(array_merge(array_column($trends, 'invoiced'), array_column($trends, 'cost'), array_column($trends, 'collected')))),
            'ar' => $ar,
            'byType' => $byType,
            'all' => $all,
            'ytd' => $ytd,
            'equipment' => Reports::equipment(),
            'fleet' => Reports::fleet(),
            'kpis' => [
                ['label' => 'Open A/R', 'value' => $ar['total'], 'money' => true, 'url' => route('reports.ar'), 'tone' => $ar['overdue'] > 0 ? 'amber' : null, 'sub' => '$'.number_format($ar['overdue'], 0).' overdue · '.$ar['flagged'].' flagged'],
                ['label' => 'Revenue YTD', 'value' => $ytd['revenue'], 'money' => true, 'url' => route('reports.work-orders', ['from' => $ytdFrom->toDateString()]), 'sub' => number_format($ytd['labor_hours'], 0).' labor hours'],
                ['label' => 'Job cost YTD', 'value' => $ytd['cost'], 'money' => true, 'url' => route('reports.work-orders', ['from' => $ytdFrom->toDateString()]), 'sub' => 'labor $'.number_format($ytd['labor'], 0).' · parts $'.number_format($ytd['materials'], 0).' · equip $'.number_format($ytd['equipment'], 0)],
                ['label' => 'Gross margin YTD', 'value' => $ytd['margin'], 'money' => false, 'url' => route('reports.work-orders', ['from' => $ytdFrom->toDateString()]), 'tone' => ($ytd['margin'] ?? 0) < 30 ? 'amber' : 'green', 'sub' => '$'.number_format($ytd['profit'], 0).' gross profit'],
            ],
        ]);
    }

    public function ar(Request $request): View
    {
        $ar = Reports::ar();
        $bucket = (string) $request->query('bucket', '');
        $terms = (string) $request->query('terms', '');
        $rows = $ar['open']
            ->filter(fn ($inv) => $bucket === '' || Reports::bucketFor(Reports::daysPastDue($inv)) === $bucket)
            ->filter(fn ($inv) => $terms === '' || ($inv->terms ?: 'net_30') === $terms)
            ->map(fn ($inv) => ['invoice' => $inv, 'days' => Reports::daysPastDue($inv), 'flag' => Reports::collectionFlag($inv), 'bucket' => Reports::bucketFor(Reports::daysPastDue($inv))])
            ->sortByDesc('days')->values();

        return view('ops.reports.ar', ['ar' => $ar, 'rows' => $rows, 'bucket' => $bucket, 'terms' => $terms]);
    }

    /** Drill-down: the work orders behind any total. */
    public function workOrders(Request $request): View
    {
        $from = $request->filled('from') ? Carbon::parse((string) $request->query('from')) : null;
        $to = $request->filled('to') ? Carbon::parse((string) $request->query('to'))->endOfDay() : null;
        if ($request->filled('month')) {
            $from = Carbon::createFromFormat('Y-m', (string) $request->query('month'))?->startOfMonth();
            $to = $from?->copy()->endOfMonth();
        }
        $type = (string) $request->query('type', '');
        $customerId = (int) $request->query('customer', 0);

        $wos = Reports::workOrdersInPeriod($from, $to)
            ->filter(fn (WorkOrder $wo) => $type === '' || ($wo->work_type ?: 'untyped') === $type)
            ->filter(fn (WorkOrder $wo) => ! $customerId || $wo->job?->customer_id === $customerId);

        $rows = $wos->map(fn (WorkOrder $wo) => ['wo' => $wo, 'cost' => JobCosting::forWorkOrder($wo)])->sortByDesc(fn ($r) => $r['cost']['revenue'])->values();

        return view('ops.reports.work-orders', [
            'rows' => $rows,
            'total' => JobCosting::total($rows->map(fn ($r) => $r['cost'])),
            'filters' => ['from' => $from?->toDateString(), 'to' => $to?->toDateString(), 'type' => $type, 'customer' => $customerId ?: null],
            'typeLabel' => $type ? (config('batsons.work_types')[$type] ?? ucwords(str_replace('_', ' ', $type))) : null,
            'customer' => $customerId ? Customer::find($customerId) : null,
        ]);
    }

    /** Job cost sheet. */
    public function job(Job $job): View
    {
        $wos = WorkOrder::withoutGlobalScope('assignedToField')->where('job_id', $job->id)->with(['assignee', 'timeEntries.user', 'equipmentUsages.equipment', 'invoices'])->get();

        return view('ops.reports.job', [
            'job' => $job->load('customer', 'serviceLocation'),
            'rows' => $wos->map(fn (WorkOrder $wo) => [
                'wo' => $wo,
                'cost' => JobCosting::forWorkOrder($wo),
                'parts' => StockAdjustment::query()->where('work_order_id', $wo->id)->where('reason', 'used_on_job')->with('part')->get(),
            ]),
            'total' => JobCosting::forJob($job),
            'burden' => (float) config('batsons.labor_burden_pct', 0.25),
        ]);
    }
}
