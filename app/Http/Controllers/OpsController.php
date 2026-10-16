<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Invoicing\AddProgressPayment;
use App\Actions\Invoicing\ApproveInvoice;
use App\Actions\Invoicing\GenerateInvoiceFromWorkOrder;
use App\Actions\Invoicing\MarkInvoicePaid;
use App\Actions\Invoicing\SyncInvoiceToQuickBooks;
use App\Actions\Invoicing\VoidInvoice;
use App\Actions\Time\ClockIn;
use App\Actions\Time\ClockOut;
use App\Actions\Time\PayForTimeEntry;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\TimeEntry;
use App\Models\WorkOrder;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Prototype UI wiring for modules 3.6 Invoicing and 3.8 Time & Pay. */
class OpsController extends Controller
{
    public function dashboard(): View
    {
        return view('ops.dashboard', [
            'counts' => [
                'Customers' => Customer::count(),
                'Jobs' => Job::count(),
                'Work Orders' => WorkOrder::count(),
                'Invoices' => Invoice::count(),
            ],
            'jobs' => Job::with(['customer', 'workOrders'])->latest()->take(6)->get(),
            'invoices' => Invoice::with('workOrder')->latest()->take(6)->get(),
        ]);
    }

    public function invoices(): View
    {
        return view('ops.invoices', [
            'workOrders' => WorkOrder::with('job.customer')->latest()->get(),
            'invoices' => Invoice::with('workOrder')->latest()->get(),
        ]);
    }

    public function time(): View
    {
        $entries = TimeEntry::with(['user', 'workOrder'])->latest()->get();

        return view('ops.time', [
            'entries' => $entries->map(fn (TimeEntry $e) => [
                'entry' => $e,
                'pay' => $e->ended_at ? PayForTimeEntry::run($e) : null,
            ]),
            'openEntry' => $entries->firstWhere('ended_at', null),
            'workOrders' => WorkOrder::with('job')->latest()->get(),
        ]);
    }

    // ---- Invoice actions -------------------------------------------------

    public function generateInvoice(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $total = (float) $request->input('total', 0);
        $invoice = GenerateInvoiceFromWorkOrder::run($workOrder, $total);

        return back()->with('status', "Invoice {$invoice->number} generated.");
    }

    public function progressPayment(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $invoice = AddProgressPayment::run($workOrder, (float) $request->input('amount', 0));

        return back()->with('status', "Progress payment {$invoice->number} added.");
    }

    public function invoiceAction(string $action, Invoice $invoice): RedirectResponse
    {
        try {
            match ($action) {
                'approve' => ApproveInvoice::run($invoice),
                'void' => VoidInvoice::run($invoice),
                'paid' => MarkInvoicePaid::run($invoice),
                'sync' => SyncInvoiceToQuickBooks::run($invoice),
                default => null,
            };

            return back()->with('status', "Invoice {$invoice->number}: {$action} done.");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ---- Time actions ----------------------------------------------------

    public function clockIn(Request $request): RedirectResponse
    {
        $wo = $request->filled('work_order_id')
            ? WorkOrder::find((int) $request->input('work_order_id'))
            : null;

        ClockIn::run($request->user(), $wo, $request->input('category', 'on_site'));

        return back()->with('status', 'Clocked in.');
    }

    public function clockOut(TimeEntry $timeEntry): RedirectResponse
    {
        ClockOut::run($timeEntry);

        return back()->with('status', 'Clocked out.');
    }
}
