<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Models\Invoice;
use App\Models\WorkOrder;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.6: progress payments and invoices can be associated with one work
 * order. Each progress invoice is numbered INV-<wo>-P<n>.
 */
final class AddProgressPayment
{
    use AsAction;

    public function handle(WorkOrder $workOrder, float $amount): Invoice
    {
        $suffix = Str::afterLast((string) $workOrder->number, '-') ?: (string) $workOrder->id;
        $seq = Invoice::where('work_order_id', $workOrder->id)
            ->where('is_progress_payment', true)
            ->count() + 1;

        return Invoice::create([
            'tenant_id' => $workOrder->tenant_id,
            'work_order_id' => $workOrder->id,
            'number' => "INV-{$suffix}-P{$seq}",
            'status' => 'draft',
            'customer_id' => $workOrder->job?->customer_id,
            'terms' => $workOrder->job->customer->payment_terms ?? 'net_30',
            'total' => $amount,
            'is_progress_payment' => true,
        ]);
    }
}
