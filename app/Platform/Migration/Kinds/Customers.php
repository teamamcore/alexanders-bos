<?php

declare(strict_types=1);

namespace App\Platform\Migration\Kinds;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\ServiceLocation;
use App\Platform\Migration\Importer;

/** QuickBooks "Customer Contact List" (Online or Desktop) or a customer spreadsheet. */
final class Customers implements Kind
{
    public function aliases(): array
    {
        return [
            'name' => ['Customer', 'Customer full name', 'Customer Name', 'Display Name', 'Company', 'Company Name', 'Name', 'Client'],
            'contact' => ['Full Name', 'Primary Contact', 'Contact', 'Contact Name', 'Attn'],
            'phone' => ['Phone Numbers', 'Phone', 'Main Phone', 'Phone Number', 'Mobile', 'Work Phone'],
            'email' => ['Email', 'Main Email', 'E-mail', 'Email Address'],
            'billing_address' => ['Billing Address', 'Bill to', 'Bill To Address', 'Address', 'Billing Street'],
            'shipping_address' => ['Shipping Address', 'Ship to', 'Ship To Address', 'Service Address', 'Job Address', 'Site Address'],
            'terms' => ['Terms', 'Payment Terms'],
            'notes' => ['Note', 'Notes', 'Customer Notes', 'Memo'],
            'type' => ['Customer Type', 'Type', 'Segment'],
            'balance' => ['Open Balance', 'Balance', 'Balance Total'],
        ];
    }

    public function required(): array
    {
        return ['name'];
    }

    public function dateField(): ?string
    {
        return null;
    }

    public function import(array $row, Importer $ctx): string
    {
        $full = trim($row['name'] ?? '');
        if ($full === '') {
            $ctx->skip('name', 'Blank customer name.');

            return 'skipped';
        }
        $parts = array_map(trim(...), explode(':', $full, 2));
        $name = $parts[0];
        $sub = $parts[1] ?? null;
        $key = Importer::key($name);

        $existing = $ctx->existing('customers', $key);
        if (! $existing instanceof Customer) {
            $existing = Customer::query()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first();
        }
        $terms = Importer::terms($row['terms'] ?? null);
        if (($row['terms'] ?? '') !== '' && $terms === null) {
            $ctx->warn('terms', "Unrecognized terms '{$row['terms']}' — defaulted to Net 30.");
        }
        $attrs = array_filter([
            'phone' => $this->clean($row['phone'] ?? ''),
            'email' => $this->email($row['email'] ?? '', $ctx),
            'payment_terms' => $terms,
            'segment' => $this->segment($row['type'] ?? ''),
            'notes' => $this->clean($row['notes'] ?? ''),
        ], fn ($v) => $v !== null && $v !== '');

        $outcome = 'updated';
        if ($existing instanceof Customer) {
            $customer = $existing;
            $customer->fill($attrs);
            if ($customer->notes === 'Created by QuickBooks import — details pending.' && ! isset($attrs['notes'])) {
                $customer->notes = null;
            }
            $customer->save();
        } else {
            $customer = Customer::create(['name' => $name] + $attrs);
            $outcome = 'imported';
        }
        $ctx->remember('customers', $key, $customer, $row);

        // Contact person
        $contactName = $this->clean($row['contact'] ?? '');
        if ($contactName !== '' && mb_strtolower($contactName) !== mb_strtolower($name)) {
            $contact = Contact::query()->where('customer_id', $customer->id)->whereRaw('lower(name) = ?', [mb_strtolower($contactName)])->first()
                ?? Contact::create(['customer_id' => $customer->id, 'name' => $contactName, 'phone' => $attrs['phone'] ?? null, 'email' => $attrs['email'] ?? null, 'role' => 'Primary contact']);
            if ($customer->corp_poc_contact_id === null) {
                $customer->update(['corp_poc_contact_id' => $contact->id]);
            }
        }

        // Service location(s): QuickBooks sub-customer / job, shipping address, else billing address
        $address = $this->address($row['shipping_address'] ?? '') ?: $this->address($row['billing_address'] ?? '');
        if ($sub !== null && $sub !== '') {
            $this->site($customer, $sub, $address ?: $sub);
        } elseif ($address !== '') {
            $this->site($customer, 'Main', $address);
        } else {
            $ctx->warn('shipping_address', 'No address on the row — add a service location before scheduling.');
        }

        return $outcome;
    }

    private function site(Customer $customer, string $label, string $address): void
    {
        $exists = ServiceLocation::query()->where('customer_id', $customer->id)
            ->where(fn ($q) => $q->whereRaw('lower(address) = ?', [mb_strtolower($address)])->orWhereRaw('lower(label) = ?', [mb_strtolower($label)]))->exists();
        if (! $exists) {
            ServiceLocation::create(['customer_id' => $customer->id, 'label' => $label, 'address' => $address, 'type' => 'job']);
        }
    }

    private function clean(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', $s) ?? $s);
    }

    private function address(string $s): string
    {
        return $this->clean(str_replace(["\r\n", "\n", "\r"], ', ', $s));
    }

    private function email(string $s, Importer $ctx): ?string
    {
        $first = trim(explode(',', str_replace(';', ',', $s))[0]);
        if ($first === '') {
            return null;
        }
        if (! filter_var($first, FILTER_VALIDATE_EMAIL)) {
            $ctx->warn('email', "Email '{$first}' looks invalid — left blank.");

            return null;
        }

        return mb_strtolower($first);
    }

    private function segment(string $s): ?string
    {
        $t = mb_strtolower(trim($s));

        return match (true) {
            $t === '' => null,
            str_contains($t, 'resid') => 'residential',
            str_contains($t, 'indust') => 'industrial',
            str_contains($t, 'comm') => 'commercial',
            str_contains($t, 'prop') || str_contains($t, 'manag') || str_contains($t, 'hoa') => 'property_management',
            default => $t,
        };
    }
}
