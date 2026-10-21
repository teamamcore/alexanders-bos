<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\InboundEmail;
use App\Models\Job;
use App\Models\Tenant;
use App\Models\WorkOrder;
use App\Platform\Notifications\Notifier;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.12 — one Client inbound email address routes mail into the platform.
 * Routing rules, in order: a job / work-order number in the subject, the
 * sender's email on a customer, the sender's email on a contact. Matched mail
 * becomes an inbound CustomerMessage on the job; everything lands in
 * inbound_emails for the office to see and (if needed) route by hand.
 */
final class IngestInboundEmail
{
    use AsAction;

    public function handle(string $fromEmail, ?string $fromName, ?string $toEmail, ?string $subject, ?string $body, ?string $messageId = null): InboundEmail
    {
        $fromEmail = Str::lower(trim($fromEmail));

        if ($messageId && ($dupe = InboundEmail::withoutGlobalScopes()->where('message_id', $messageId)->first())) {
            return $dupe;
        }

        $email = new InboundEmail([
            'message_id' => $messageId ?: null,
            'from_email' => $fromEmail,
            'from_name' => $fromName ?: null,
            'to_email' => $toEmail ? Str::lower(trim($toEmail)) : null,
            'subject' => $subject ? Str::limit(trim($subject), 250, '') : null,
            'body' => $body,
            'status' => 'new',
            'received_at' => now(),
        ]);

        // Rule 1 — a job / WO number in the subject (e.g. "Re: WO-1001" or "J-1001").
        if ($subject && preg_match('/\b(WO|J|INV)-\d+(?:-\d+)?\b/i', $subject, $m)) {
            $number = strtoupper($m[0]);
            if ($wo = WorkOrder::withoutGlobalScopes()->where('number', $number)->first()) {
                $email->fill(['tenant_id' => $wo->tenant_id, 'work_order_id' => $wo->id, 'job_id' => $wo->job_id, 'customer_id' => $wo->job?->customer_id, 'routed_by_rule' => 'job_number']);
            } elseif ($job = Job::withoutGlobalScopes()->where('number', $number)->first()) {
                $email->fill(['tenant_id' => $job->tenant_id, 'job_id' => $job->id, 'customer_id' => $job->customer_id, 'routed_by_rule' => 'job_number']);
            }
        }

        // Rule 2 / 3 — sender email on a customer or a contact.
        if (! $email->customer_id) {
            if ($customer = Customer::withoutGlobalScopes()->whereRaw('lower(email) = ?', [$fromEmail])->first()) {
                $email->fill(['tenant_id' => $customer->tenant_id, 'customer_id' => $customer->id, 'routed_by_rule' => 'customer_email']);
            } elseif ($contact = Contact::withoutGlobalScopes()->whereRaw('lower(email) = ?', [$fromEmail])->first()) {
                $email->fill(['tenant_id' => $contact->tenant_id, 'customer_id' => $contact->customer_id, 'routed_by_rule' => 'contact_email']);
            }
        }

        // Fall back to the only tenant (single-client deployment) so the office still sees it.
        if (! $email->tenant_id) {
            $tenants = Tenant::query()->get();
            $email->tenant_id = $tenants->count() === 1 ? $tenants->first()?->id : null;
        }

        if ($email->customer_id) {
            $email->status = 'routed';
        }
        $email->save();

        if ($email->customer_id && $email->tenant_id) {
            $message = CustomerMessage::create([
                'tenant_id' => $email->tenant_id,
                'customer_id' => $email->customer_id,
                'job_id' => $email->job_id,
                'work_order_id' => $email->work_order_id,
                'direction' => 'inbound',
                'channel' => 'email',
                'to' => $email->to_email,
                'from' => $fromEmail,
                'subject' => $email->subject,
                'body' => Str::limit((string) $body, 5000, '…'),
                'status' => 'received',
                'provider_ref' => $messageId,
                'sent_at' => now(),
            ]);
            $email->forceFill(['customer_message_id' => $message->id])->save();
        }

        if ($email->tenant_id) {
            $who = $email->customer ? $email->customer->name : ($fromName ?: $fromEmail);
            Notifier::send(
                Notifier::office($email->tenant_id),
                'inbound_email',
                ($email->status === 'routed' ? 'Email from ' : 'Unrouted email from ').$who,
                $email->subject ?: Str::limit((string) $body, 100),
                $email->status === 'routed' && $email->job_id ? route('jobs.show', $email->job_id) : route('inbox.email'),
                ['inbound_email_id' => $email->id],
            );
        }

        return $email;
    }
}
