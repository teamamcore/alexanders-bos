<?php

declare(strict_types=1);

namespace App\Actions\Reminders;

use App\Models\Reminder;
use App\Models\WorkOrder;
use App\Support\ReminderTypes;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.10 — the hook on work-order completion:
 *  • if the work order was created from a reminder → mark it done and roll the schedule;
 *  • else if its work type maps to a reminder type → put the customer on that schedule.
 */
final class OnWorkOrderCompleted
{
    use AsAction;

    public function handle(WorkOrder $workOrder): void
    {
        $workOrder->loadMissing('job.customer', 'job.serviceLocation');
        $job = $workOrder->job;
        if (! $job || ! $job->customer) {
            return;
        }

        $reminder = Reminder::query()->open()->where('job_id', $job->id)->first();
        if ($reminder instanceof Reminder) {
            $reminder->forceFill(['status' => 'done', 'completed_at' => now()])->save();
            if ($reminder->recurringService) {
                $reminder->recurringService->rollForward(today());
                OpenReminderForCycle::run($reminder->recurringService->fresh() ?? $reminder->recurringService);
            }

            return;
        }

        $type = ReminderTypes::forWorkType($workOrder->work_type);
        if ($type !== null) {
            ScheduleRecurringService::run($job->customer, $type, today(), null, $job->serviceLocation, null, null, $workOrder);
        }
    }
}
