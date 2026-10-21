<?php

declare(strict_types=1);

namespace App\Actions\Communication;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Platform\Notifications\Notifier;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.4 — a customer texted back. Matched to a customer (or contact) by
 * phone across tenants; returns null when nobody matches (logged upstream).
 */
final class RecordInboundMessage
{
    use AsAction;

    public function handle(string $from, string $body, ?string $to = null, ?string $providerRef = null): ?CustomerMessage
    {
        $digits = self::digits($from);
        if ($digits === '') {
            return null;
        }

        $customer = Customer::withoutGlobalScopes()->get()->first(fn (Customer $c) => self::digits((string) $c->phone) === $digits);
        $contact = null;
        if (! $customer) {
            $contact = Contact::withoutGlobalScopes()->with('customer')->get()->first(fn (Contact $c) => self::digits((string) $c->phone) === $digits);
            $customer = $contact?->customer;
        }
        if (! $customer) {
            return null;
        }

        $latest = CustomerMessage::withoutGlobalScopes()->where('customer_id', $customer->id)->outbound()->latest()->first();

        $message = CustomerMessage::create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'contact_id' => $contact?->id,
            'job_id' => $latest?->job_id,
            'work_order_id' => $latest?->work_order_id,
            'direction' => 'inbound',
            'channel' => 'sms',
            'to' => $to,
            'from' => $from,
            'body' => trim($body),
            'status' => 'received',
            'provider_ref' => $providerRef,
            'sent_at' => now(),
        ]);

        // SOW §3.12 — office sees customer replies as in-app notifications.
        Notifier::send(Notifier::office($customer->tenant_id), 'customer_reply', "Reply from {$customer->name}", Str::limit(trim($body), 120),
            $latest?->job_id ? route('jobs.show', $latest->job_id) : route('messages.index'), ['customer_message_id' => $message->id]);

        return $message;
    }

    public static function digits(string $phone): string
    {
        $d = preg_replace('/\D+/', '', $phone) ?? '';

        return strlen($d) === 11 && str_starts_with($d, '1') ? substr($d, 1) : $d;
    }
}
