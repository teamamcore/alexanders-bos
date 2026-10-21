<?php

declare(strict_types=1);

namespace App\Actions\Reminders;

use App\Actions\WorkOrders\AddWorkOrder;
use App\Models\Job;
use App\Models\Reminder;
use App\Models\User;
use App\Support\ReminderTypes;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.10 → §3.2/§3.3 — turn a reminder into work: a job under the customer
 * with one work order (typed so the dispatch board shows qualified techs).
 * Completing that work order closes the reminder and rolls the schedule.
 */
final class ScheduleReminderJob
{
    use AsAction;

    public function handle(Reminder $reminder, User $actor, ?string $window = 'morning', ?string $notes = null): Job
    {
        if ($reminder->job_id) {
            throw new DomainException('A job has already been created for this reminder.');
        }
        if (! $reminder->customer) {
            throw new DomainException('This reminder has no customer.');
        }

        $def = ReminderTypes::get($reminder->type);
        $title = $reminder->typeLabel().($reminder->serviceLocation ? ' — '.($reminder->serviceLocation->label ?: $reminder->serviceLocation->address) : '');

        $job = Job::create([
            'customer_id' => $reminder->customer_id,
            'service_location_id' => $reminder->service_location_id,
            'title' => $title,
            'status' => 'open',
        ]);

        $wo = AddWorkOrder::run(
            $job,
            null,
            trim(($notes ? $notes.' ' : '').$reminder->typeLabel().' due '.$reminder->due_at->format('M j, Y').'.'),
            $window ?: 'morning',
            $def['work_type'] ?? null,
        );

        $reminder->forceFill(['status' => 'scheduled', 'job_id' => $job->id, 'work_order_id' => $wo->id])->save();

        return $job;
    }
}
