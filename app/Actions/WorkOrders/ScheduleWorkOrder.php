<?php

declare(strict_types=1);

namespace App\Actions\WorkOrders;

use App\Actions\Communication\NotifyCustomer;
use App\Models\User;
use App\Models\WorkOrder;
use App\Platform\Notifications\Notifier;
use App\States\WorkOrder\NewWorkOrder;
use App\States\WorkOrder\Scheduled;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.3: assign a work order to a User, set its day and window, and move it
 * new → scheduled. Qualification/skill checks (§3.11) hook in here later.
 */
final class ScheduleWorkOrder
{
    use AsAction;

    public function handle(WorkOrder $workOrder, User $assignee, string $date, string $window): WorkOrder
    {
        $workOrder->update([
            'assigned_to' => $assignee->id,
            'scheduled_date' => $date,
            'schedule_window' => $window,
        ]);

        if ($workOrder->status instanceof NewWorkOrder) {
            $workOrder->status->transitionTo(Scheduled::class);
        }

        $workOrder->refresh();

        // SOW §3.4 — customer receives the scheduled day (automatic, tenant switchable).
        NotifyCustomer::run($workOrder, 'scheduled', null, [], true);

        // SOW §3.3/§3.12 — the tech is told about the new assignment.
        Notifier::send($assignee, 'assignment', "New assignment: {$workOrder->number}",
            trim(($workOrder->job->customer->name ?? '')." · {$date} ".str_replace('_', ' ', $window)),
            route('jobs.show', $workOrder->job_id), ['work_order_id' => $workOrder->id]);

        return $workOrder;
    }
}
