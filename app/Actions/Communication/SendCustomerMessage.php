<?php

declare(strict_types=1);

namespace App\Actions\Communication;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\User;
use App\Models\WorkOrder;
use App\Platform\Messaging\Messenger;
use App\Platform\Messaging\TemplateRenderer;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.4 — send a text or email to a customer, either from a template key
 * (scheduled, on_the_way, arrived, …) or as free text from a field user.
 * Returns null when the customer has no usable phone/email (never throws
 * for a missing recipient — automated notices must not block operations).
 *
 * @phpstan-type Recipient array{channel: string, to: string, contact_id: int|null}
 */
final class SendCustomerMessage
{
    use AsAction;

    /** @param  array<string, mixed>  $vars */
    public function handle(
        Customer $customer,
        ?string $templateKey,
        ?string $freeText,
        ?User $sender,
        ?WorkOrder $workOrder = null,
        array $vars = [],
        ?Contact $contact = null,
        ?string $forceChannel = null,
    ): ?CustomerMessage {
        $template = $templateKey ? TemplateRenderer::template($templateKey) : null;
        if ($template && ! $template['is_active']) {
            return null;
        }

        // A template channel is a preference (SMS first, then email); a channel the
        // sender picked explicitly is strict.
        $recipient = self::recipient($customer, $contact, $forceChannel ?? ($template['channel'] ?? null), $forceChannel !== null);
        if ($recipient === null) {
            return null;
        }

        $variables = TemplateRenderer::variables($workOrder, $vars + ['customer' => $customer->name, 'first_name' => explode(' ', trim($customer->name))[0]]);
        $body = $template
            ? TemplateRenderer::render($template['body'], $variables)
            : trim((string) $freeText);

        if ($body === '') {
            throw new DomainException('Message body is empty.');
        }

        $message = CustomerMessage::create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'contact_id' => $recipient['contact_id'],
            'job_id' => $workOrder?->job_id,
            'work_order_id' => $workOrder?->id,
            'direction' => 'outbound',
            'channel' => $recipient['channel'],
            'to' => $recipient['to'],
            'from' => $recipient['channel'] === 'sms' ? config('messaging.sms_from') : config('mail.from.address'),
            'subject' => $template && $recipient['channel'] === 'email'
                ? TemplateRenderer::render($template['subject'] ?? '', $variables) ?: ($variables['company'].' update')
                : null,
            'body' => $body,
            'template_key' => $templateKey,
            'status' => 'queued',
            'sent_by' => $sender?->id,
        ]);

        return app(Messenger::class)->deliver($message);
    }

    /**
     * Resolve where the message goes: explicit contact, else the customer's own
     * phone/email, else the first contact that has one. SMS wins when the
     * customer has a phone and hasn't opted out, unless the template is email-only.
     *
     * @return Recipient|null
     */
    public static function recipient(Customer $customer, ?Contact $contact = null, ?string $preferChannel = null, bool $strict = false): ?array
    {
        $candidates = [];
        if ($contact instanceof Contact) {
            $candidates[] = ['phone' => $contact->phone, 'email' => $contact->email, 'contact_id' => $contact->id];
        }
        $candidates[] = ['phone' => $customer->phone, 'email' => $customer->email, 'contact_id' => null];
        foreach ($customer->contacts as $c) {
            $candidates[] = ['phone' => $c->phone, 'email' => $c->email, 'contact_id' => $c->id];
        }

        $wantEmail = $preferChannel === 'email' || (! $strict && $customer->preferred_channel === 'email');
        $smsAllowed = $preferChannel !== 'email' && (bool) ($customer->sms_opt_in ?? true);
        $emailFallback = ! $strict || $preferChannel !== 'sms';

        foreach ($candidates as $cand) {
            $phone = trim((string) $cand['phone']);
            $email = trim((string) $cand['email']);
            if ($wantEmail && $email !== '') {
                return ['channel' => 'email', 'to' => $email, 'contact_id' => $cand['contact_id']];
            }
            if ($smsAllowed && $phone !== '') {
                return ['channel' => 'sms', 'to' => $phone, 'contact_id' => $cand['contact_id']];
            }
            if ($email !== '' && $emailFallback) {
                return ['channel' => 'email', 'to' => $email, 'contact_id' => $cand['contact_id']];
            }
        }

        return null;
    }
}
