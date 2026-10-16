<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Models\Invoice;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/** SOW §3.6: invoices associated with work orders may be voided when necessary. */
final class VoidInvoice
{
    use AsAction;

    public function handle(Invoice $invoice): Invoice
    {
        if ($invoice->status === 'void') {
            throw new DomainException("Invoice {$invoice->number} is already void.");
        }

        $invoice->update(['status' => 'void', 'voided_at' => now()]);

        return $invoice->refresh();
    }
}
