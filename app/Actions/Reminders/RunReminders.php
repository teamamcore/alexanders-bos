<?php

declare(strict_types=1);

namespace App\Actions\Reminders;

use App\Actions\Communication\SendCustomerMessage;
use App\Models\RecurringService;
use App\Models\Reminder;
use App\Platform\Notifications\Notifier;
use App\Support\ReminderTypes;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.10 — the daily pass for one tenant: open any missing cycle
 * reminders, alert the office when a contact window opens, and send the
 * optional automatic customer message.
 *
 * @return array{opened: int, notified: int, messaged: int}
 */
final class RunReminders
{
    use AsAction;

    /** @return array{opened: int, notified: int, messaged: int} */
    public function handle(int $tenantId): array
    {
        $result = ['opened' => 0, 'notified' => 0, 'messaged' => 0];

        // 1) Every active schedule has an open reminder for its current cycle.
        foreach (RecurringService::query()->active()->get() as $service) {
            if (! $service->currentReminder()) {
                OpenReminderForCycle::run($service);
                $result['opened']++;
            }
        }

        // 2) Windows that opened → office alert (+ auto message).
        $due = Reminder::query()->dueToNotify()->with(['customer.contacts', 'recurringService', 'serviceLocation'])->get();
        foreach ($due as $reminder) {
            $reminder->forceFill(['status' => 'notified', 'notified_at' => now()])->save();
            $result['notified']++;

            if ($reminder->auto_message && $reminder->customer) {
                $message = SendCustomerMessage::run($reminder->customer, 'service_reminder', null, null, null, [
                    'service' => ReminderTypes::get($reminder->type)['customer_label'] ?? $reminder->typeLabel(),
                    'due_date' => $reminder->due_at->format('M j'),
                    'address' => $reminder->serviceLocation->address ?? '',
                ]);
                if ($message) {
                    $reminder->forceFill(['status' => 'contacted', 'contacted_at' => now(), 'contact_method' => $message->channel])->save();
                    $result['messaged']++;
                }
            }
        }

        if ($result['notified'] > 0) {
            $count = Reminder::query()->whereIn('status', ['notified', 'contacted'])->count();
            Notifier::daily(Notifier::office($tenantId), 'reminders',
                "{$result['notified']} service reminder(s) ready to work",
                "{$count} open in the window · ".($result['messaged'] ? "{$result['messaged']} customer(s) messaged automatically" : 'call or message the customer to schedule'),
                route('reminders.index'));
        }

        return $result;
    }
}
