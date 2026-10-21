<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\InboundEmail;
use App\Models\Job;
use App\Models\User;
use DomainException;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/** SOW §3.12 — office user routes an unmatched inbound email to a customer / job by hand. */
final class RouteInboundEmail
{
    use AsAction;

    public function handle(InboundEmail $email, User $actor, ?Customer $customer, ?Job $job = null, bool $archive = false): InboundEmail
    {
        if ($archive) {
            $email->forceFill(['status' => 'archived', 'assigned_to' => $actor->id])->save();

            return $email;
        }

        $customer ??= $job?->customer;
        if (! $customer) {
            throw new DomainException('Pick a customer or a job to route this email to.');
        }

        $email->forceFill([
            'customer_id' => $customer->id,
            'job_id' => $job?->id,
            'status' => 'routed',
            'routed_by_rule' => 'manual',
            'assigned_to' => $actor->id,
        ])->save();

        if (! $email->customer_message_id) {
            $message = CustomerMessage::create([
                'tenant_id' => $email->tenant_id,
                'customer_id' => $customer->id,
                'job_id' => $job?->id,
                'direction' => 'inbound',
                'channel' => 'email',
                'to' => $email->to_email,
                'from' => $email->from_email,
                'subject' => $email->subject,
                'body' => Str::limit((string) $email->body, 5000, '…'),
                'status' => 'received',
                'provider_ref' => $email->message_id,
                'sent_at' => $email->received_at ?? now(),
            ]);
            $email->forceFill(['customer_message_id' => $message->id])->save();
        }

        return $email;
    }
}
