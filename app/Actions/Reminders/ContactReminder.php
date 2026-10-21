<?php

declare(strict_types=1);

namespace App\Actions\Reminders;

use App\Actions\Communication\SendCustomerMessage;
use App\Models\Reminder;
use App\Models\User;
use App\Support\ReminderTypes;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.10 — "Follow-up call or message". Either sends the reminder template
 * to the customer (method sms/email) or records that the office called.
 */
final class ContactReminder
{
    use AsAction;

    public function handle(Reminder $reminder, User $actor, string $method = 'sms', ?string $note = null): Reminder
    {
        if (! in_array($method, ['sms', 'email', 'call', 'in_person'], true)) {
            throw new DomainException("Unknown contact method: {$method}");
        }

        if (in_array($method, ['sms', 'email'], true)) {
            $customer = $reminder->customer;
            if (! $customer) {
                throw new DomainException('This reminder has no customer.');
            }
            $message = SendCustomerMessage::run($customer->load('contacts'), 'service_reminder', null, $actor, null, [
                'service' => ReminderTypes::get($reminder->type)['customer_label'] ?? $reminder->typeLabel(),
                'due_date' => $reminder->due_at->format('M j'),
                'address' => $reminder->serviceLocation->address ?? '',
                'note' => (string) $note,
            ], null, $method);
            if ($message === null) {
                throw new DomainException("{$customer->name} has no ".($method === 'sms' ? 'mobile number' : 'email').' on file.');
            }
            $method = $message->channel;
        }

        $reminder->forceFill([
            'status' => in_array($reminder->status, ['scheduled', 'done'], true) ? $reminder->status : 'contacted',
            'contacted_at' => now(),
            'contact_method' => $method,
            'notes' => trim(($reminder->notes ? $reminder->notes."\n" : '').now()->format('M j').' — '.$actor->name.' ('.$method.')'.($note ? ": {$note}" : '')) ?: null,
        ])->save();

        return $reminder;
    }
}
