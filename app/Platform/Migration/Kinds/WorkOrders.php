<?php

declare(strict_types=1);

namespace App\Platform\Migration\Kinds;

use App\Models\Invoice;
use App\Models\Job;
use App\Models\ServiceLocation;
use App\Models\User;
use App\Models\WorkOrder;
use App\Platform\Migration\Importer;
use Illuminate\Support\Carbon;

/**
 * Historical work order records — Batsons' spreadsheet / work-order log
 * (QuickBooks has no work orders). Each row becomes customer → site → job →
 * completed work order, linked to its invoice when the number matches.
 */
final class WorkOrders implements Kind
{
    public function aliases(): array
    {
        return [
            'date' => ['Date', 'Service Date', 'Completed', 'Date Completed', 'Job Date', 'Work Date'],
            'number' => ['WO', 'WO #', 'Work Order', 'Work Order #', 'Ticket', 'Ticket #', 'Job #', 'Job Number'],
            'customer' => ['Customer', 'Name', 'Customer Name', 'Client', 'Account'],
            'site' => ['Site', 'Service Address', 'Address', 'Location', 'Job Address', 'Job Site', 'Property'],
            'description' => ['Description', 'Work Performed', 'Job', 'Scope', 'Work Description', 'Memo', 'Notes'],
            'work_type' => ['Work Type', 'Type', 'Category', 'Service', 'Service Type'],
            'tech' => ['Tech', 'Technician', 'Employee', 'Assigned To', 'Plumber', 'Crew'],
            'hours' => ['Hours', 'Labor Hours', 'Labor Hrs', 'Hrs', 'Time'],
            'invoice' => ['Invoice', 'Invoice #', 'Invoice Number', 'Inv #', 'Num'],
            'amount' => ['Amount', 'Total', 'Billed', 'Invoice Total'],
            'status' => ['Status'],
        ];
    }

    public function required(): array
    {
        return ['date', 'customer'];
    }

    public function dateField(): string
    {
        return 'date';
    }

    public function import(array $row, Importer $ctx): string
    {
        $date = Importer::date($row['date']);
        if (! $date instanceof Carbon) {
            $ctx->skip('date', "Unreadable date '{$row['date']}'.");

            return 'skipped';
        }
        [$customer, $sub] = $ctx->customerFor($row['customer']);

        $siteText = trim($row['site'] ?? '') ?: $sub;
        if ($siteText) {
            $site = ServiceLocation::query()->where('customer_id', $customer->id)
                ->where(fn ($q) => $q->whereRaw('lower(address) = ?', [mb_strtolower($siteText)])->orWhereRaw('lower(label) = ?', [mb_strtolower($siteText)]))->first()
                ?? ServiceLocation::create(['customer_id' => $customer->id, 'label' => $sub ?: 'Site', 'address' => $siteText, 'type' => 'job']);
        } else {
            $site = ServiceLocation::query()->where('customer_id', $customer->id)->first();
            if ($site === null) {
                $ctx->warn('site', 'No service address on the row or on file — work order saved without a site.');
            }
        }

        $description = trim(preg_replace('/\s+/', ' ', $row['description'] ?? '') ?? '');
        $number = trim($row['number'] ?? '');
        $key = $number !== '' ? 'num:'.Importer::key($number) : 'row:'.Importer::key($date->toDateString().'|'.$row['customer'].'|'.$description);

        $workType = $this->workType($row['work_type'] ?? '', $description);
        if (($row['work_type'] ?? '') !== '' && $workType === null) {
            $ctx->warn('work_type', "Work type '{$row['work_type']}' is not one of the configured types — left blank.");
        }
        $tech = null;
        if (($t = trim($row['tech'] ?? '')) !== '') {
            $tech = User::query()->whereRaw('lower(name) = ?', [mb_strtolower($t)])->first()
                ?? User::query()->whereRaw('lower(name) like ?', [mb_strtolower(explode(' ', $t)[0]).'%'])->first();
            if ($tech === null) {
                $ctx->warn('tech', "Tech '{$t}' is not a user — left unassigned (add them under Team to attribute history).");
            }
        }
        $hours = Importer::money($row['hours'] ?? null);

        $existing = $ctx->existing('work_orders', $key);
        if ($existing instanceof WorkOrder) {
            $wo = $existing;
            $wo->forceFill(['customer_notes' => $description ?: $wo->customer_notes, 'work_performed' => $description ?: $wo->work_performed, 'work_type' => $workType ?? $wo->work_type,
                'assigned_to' => $tech->id ?? $wo->assigned_to, 'labor_hours' => $hours ?? $wo->labor_hours, 'scheduled_date' => $date])->save();
            $wo->job?->update(['service_location_id' => $site->id ?? $wo->job->service_location_id, 'title' => $description ?: $wo->job->title]);
            $outcome = 'updated';
        } else {
            $job = Job::create(['customer_id' => $customer->id, 'service_location_id' => $site?->id, 'number' => $number !== '' ? 'J-'.preg_replace('/^(wo|j)-?/i', '', $number) : null,
                'title' => $description !== '' ? mb_substr($description, 0, 120) : 'Imported work order', 'status' => 'closed', 'notes' => 'Imported from historical records.']);
            $wo = WorkOrder::create(['job_id' => $job->id, 'number' => $number !== '' ? $number : null, 'status' => 'complete', 'customer_notes' => $description ?: null, 'work_performed' => $description ?: null,
                'work_type' => $workType, 'assigned_to' => $tech?->id, 'labor_hours' => $hours, 'scheduled_date' => $date, 'schedule_window' => 'all_day', 'priority' => 'normal']);
            WorkOrder::withoutGlobalScopes()->whereKey($wo->id)->update(['created_at' => $date, 'updated_at' => $date]);
            $outcome = 'imported';
        }
        $ctx->remember('work_orders', $key, $wo, $row);

        // Link the invoice if it is already in (import receivables first), else create a paid history record from the amount.
        $invNo = trim($row['invoice'] ?? '');
        $amount = Importer::money($row['amount'] ?? null);
        if ($invNo !== '') {
            $inv = Invoice::query()->where('number', $invNo)->first();
            if ($inv instanceof Invoice) {
                $inv->forceFill(['work_order_id' => $wo->id, 'customer_id' => $inv->customer_id ?? $customer->id])->save();
            } elseif ($amount !== null) {
                Invoice::create(['work_order_id' => $wo->id, 'customer_id' => $customer->id, 'number' => $invNo, 'status' => 'paid', 'total' => $amount, 'terms' => $customer->payment_terms ?? 'net_30',
                    'is_progress_payment' => false, 'issued_at' => $date, 'due_at' => $date, 'paid_at' => $date, 'qb_synced_at' => now()]);
                $ctx->warn('invoice', "Invoice {$invNo} was not in the system — recorded as paid history from the row amount; import the invoice list to get exact dates.");
            } else {
                $ctx->warn('invoice', "Invoice {$invNo} not found and no amount on the row — work order saved unbilled.");
            }
        }

        return $outcome;
    }

    private function workType(string $given, string $description): ?string
    {
        $types = config('batsons.work_types', []);
        $g = mb_strtolower(trim($given));
        foreach ($types as $key => $label) {
            if ($g !== '' && ($g === $key || $g === mb_strtolower($label) || str_contains($g, explode(' ', mb_strtolower($label))[0]))) {
                return $key;
            }
        }
        if ($g !== '') {
            return null;
        }
        $d = mb_strtolower($description);
        foreach ($types as $key => $label) {
            if (str_contains($d, explode(' ', mb_strtolower($label))[0]) || str_contains($d, str_replace('_', ' ', $key))) {
                return $key;
            }
        }

        return null;
    }
}
