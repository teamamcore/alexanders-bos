<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Models\Invoice;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.6: synchronize the invoice with Client's QuickBooks. This records the
 * sync timestamp; the actual QuickBooks API call (Desktop or Online) is a
 * subprocessor integration wired in separately. QuickBooks stays the system of
 * record for accounting (MSA Exhibit B.10d).
 */
final class SyncInvoiceToQuickBooks
{
    use AsAction;

    public function handle(Invoice $invoice): Invoice
    {
        // Integration point: push to QuickBooks here, then stamp the sync time.
        $invoice->update(['qb_synced_at' => now()]);

        return $invoice->refresh();
    }
}
