<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Models\Invoice;
use App\Models\WorkOrder;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.6: an invoice is created with each work order and populated when work
 * is complete. Numbering is paired with the work order (WO-1001 → INV-1001).
 * No card/bank data is ever stored (MSA Exhibit B.2) — amounts and status only.
 */
final class GenerateInvoiceFromWorkOrder
{
    use AsAction;

    public function handle(WorkOrder $workOrder, float $total = 0.0): Invoice
    {
        return Invoice::create([
            'tenant_id' => $workOrder->tenant_id,
            'work_order_id' => $workOrder->id,
            'number' => 'INV-'.$this->suffix($workOrder),
            'status' => 'draft',
            'total' => $total,
            'is_progress_payment' => false,
        ]);
    }

    private function suffix(WorkOrder $workOrder): string
    {
        $suffix = Str::afterLast((string) $workOrder->number, '-');

        return $suffix !== '' ? $suffix : (string) $workOrder->id;
    }
}
