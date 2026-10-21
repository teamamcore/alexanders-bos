<?php

declare(strict_types=1);

namespace App\Actions\Reminders;

use App\Models\RecurringService;
use App\Models\Reminder;
use Lorisleiva\Actions\Concerns\AsAction;

/** SOW §3.10 — make sure the current cycle of a recurring service has exactly one open reminder. */
final class OpenReminderForCycle
{
    use AsAction;

    public function handle(RecurringService $service): Reminder
    {
        $open = $service->currentReminder();

        $attrs = [
            'customer_id' => $service->customer_id,
            'service_location_id' => $service->service_location_id,
            'type' => $service->type,
            'due_at' => $service->next_due_at,
            'remind_at' => $service->remindAt(),
            'contact_by' => $service->contactBy(),
            'auto_message' => $service->auto_message,
        ];

        if ($open instanceof Reminder) {
            // Due date moved (schedule refreshed) → keep the reminder but re-time it.
            if (! $open->due_at->equalTo($service->next_due_at)) {
                $open->fill($attrs + ['status' => 'pending', 'notified_at' => null])->save();
            }

            return $open;
        }

        return $service->reminders()->create($attrs + ['status' => 'pending']);
    }
}
