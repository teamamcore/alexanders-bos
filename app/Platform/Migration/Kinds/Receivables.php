<?php

declare(strict_types=1);

namespace App\Platform\Migration\Kinds;

use App\Models\Invoice;
use App\Platform\Migration\Importer;

/**
 * QuickBooks invoice list / A/R aging detail / "Transaction List by Customer"
 * → Invoices. Open balance > 0 lands as open A/R (approved); otherwise paid
 * history. Totals come from QuickBooks as-is; QuickBooks stays the ledger.
 */
final class Receivables implements Kind
{
    public function aliases(): array
    {
        return [
            'date' => ['Date', 'Transaction Date', 'Txn Date', 'Invoice Date', 'Created'],
            'number' => ['Num', 'No.', 'Number', 'Invoice #', 'Invoice Number', 'Doc Number', 'Ref No.'],
            'customer' => ['Customer', 'Name', 'Customer full name', 'Customer Name', 'Client'],
            'type' => ['Transaction Type', 'Type'],
            'due_date' => ['Due Date', 'Due'],
            'amount' => ['Amount', 'Total', 'Invoice Total', 'Total Amount'],
            'open_balance' => ['Open Balance', 'Balance', 'Amount Due', 'Balance Due'],
            'status' => ['Status', 'A/R Paid', 'Paid', 'Payment Status'],
            'paid_date' => ['Paid Date', 'Last Payment Date', 'Payment Date', 'Last Paid'],
            'terms' => ['Terms'],
            'memo' => ['Memo/Description', 'Memo', 'Description'],
        ];
    }

    public function required(): array
    {
        return ['customer', 'amount'];
    }

    public function dateField(): string
    {
        return 'date';
    }

    public function import(array $row, Importer $ctx): string
    {
        $type = mb_strtolower(trim($row['type'] ?? ''));
        if ($type !== '' && ! str_contains($type, 'invoice')) {
            $ctx->skip('type', "Transaction type '{$row['type']}' is not an invoice (payments, credits and estimates stay in QuickBooks).");

            return 'skipped';
        }
        $amount = Importer::money($row['amount'] ?? null);
        if ($amount === null) {
            $ctx->skip('amount', "Amount '{$row['amount']}' is not a number.");

            return 'skipped';
        }
        [$customer] = $ctx->customerFor($row['customer']);

        $number = trim($row['number'] ?? '');
        $date = Importer::date($row['date'] ?? null);
        $key = $number !== '' ? 'num:'.Importer::key($number) : 'row:'.Importer::key(($date?->toDateString() ?? '').'|'.$row['customer'].'|'.$amount);
        if ($number === '') {
            $ctx->warn('number', 'No invoice number — matched on date + customer + amount for re-imports.');
        }

        $open = Importer::money($row['open_balance'] ?? null);
        $statusText = mb_strtolower(trim($row['status'] ?? ''));
        $isPaid = $open !== null ? $open <= 0 : (in_array($statusText, ['paid', 'closed', 'yes'], true));
        if ($open === null && $statusText === '') {
            $ctx->warn('open_balance', 'No open balance or status column — assumed open.');
        }
        $terms = Importer::terms($row['terms'] ?? null) ?? $customer->payment_terms ?? 'net_30';
        $due = Importer::date($row['due_date'] ?? null) ?? $date?->copy()->addDays((int) (config('batsons.payment_terms')[$terms] ?? 30));
        $paidAt = $isPaid ? (Importer::date($row['paid_date'] ?? null) ?? $due ?? $date) : null;

        $attrs = [
            'customer_id' => $customer->id,
            'number' => $number !== '' ? $number : null,
            'status' => $isPaid ? 'paid' : 'approved',
            'total' => $isPaid ? $amount : ($open ?? $amount),
            'terms' => $terms,
            'is_progress_payment' => false,
            'issued_at' => $date,
            'due_at' => $due,
            'paid_at' => $paidAt,
            'qb_synced_at' => now(), // it came from QuickBooks
        ];
        if (! $isPaid && $open !== null && abs($open - $amount) > 0.009) {
            $ctx->warn('open_balance', 'Partially paid in QuickBooks: $'.number_format($open, 2).' of $'.number_format($amount, 2).' still open — stored the open amount.');
        }

        $existing = $ctx->existing('receivables', $key);
        if (! $existing instanceof Invoice && $number !== '') {
            $existing = Invoice::query()->where('number', $number)->first();
        }
        if ($existing instanceof Invoice) {
            $existing->forceFill($attrs)->save();
            $ctx->remember('receivables', $key, $existing, $row);

            return 'updated';
        }
        $invoice = Invoice::create($attrs);
        $ctx->remember('receivables', $key, $invoice, $row);

        return 'imported';
    }
}
