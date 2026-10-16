<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Models\Invoice;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/** SOW §3.6: review and approval screen — draft → approved. */
final class ApproveInvoice
{
    use AsAction;

    public function handle(Invoice $invoice): Invoice
    {
        if ($invoice->status !== 'draft') {
            throw new DomainException("Only a draft invoice can be approved; {$invoice->number} is {$invoice->status}.");
        }

        $invoice->update(['status' => 'approved']);

        return $invoice->refresh();
    }
}
