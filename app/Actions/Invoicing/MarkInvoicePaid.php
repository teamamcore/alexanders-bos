<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Models\Invoice;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/** SOW §3.6: record payment status (never card/bank data — MSA Exhibit B.2). */
final class MarkInvoicePaid
{
    use AsAction;

    public function handle(Invoice $invoice): Invoice
    {
        if ($invoice->status === 'void') {
            throw new DomainException("A voided invoice ({$invoice->number}) cannot be marked paid.");
        }

        $invoice->update(['status' => 'paid']);

        return $invoice->refresh();
    }
}
