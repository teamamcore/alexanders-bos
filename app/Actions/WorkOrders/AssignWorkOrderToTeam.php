<?php

declare(strict_types=1);

namespace App\Actions\WorkOrders;

use App\Models\Crew;
use App\Models\User;
use App\Models\WorkOrder;
use App\Platform\Notifications\Notifier;
use App\States\WorkOrder\Completed;
use App\States\WorkOrder\NewWorkOrder;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Drag-and-drop from the W.O.S panel onto a team lane (design notes §2):
 * sets team, day, lane (All Day / AM / PM → schedule window) and the team lead
 * as assignee. An emergency dropped on a team bumps that team's other work for
 * the day to On Hold ("emergency_bump") and tells them.
 */
final class AssignWorkOrderToTeam
{
    use AsAction;

    public function handle(WorkOrder $workOrder, Crew $crew, string $date, string $lane, User $actor): WorkOrder
    {
        $lead = $crew->lead();
        if (! $lead instanceof User) {
            throw new DomainException("{$crew->name} has no members yet — add techs to the roster first.");
        }
        $window = WorkOrder::windowForLane($lane, $workOrder->isEmergency());

        if ($workOrder->on_hold) {
            ReleaseHold::run($workOrder, $actor, 'scheduled from the board');
        }

        if ($workOrder->status instanceof NewWorkOrder) {
            ScheduleWorkOrder::run($workOrder, $lead, $date, $window);
        } elseif ($workOrder->scheduled_date?->toDateString() !== $date) {
            RescheduleWorkOrder::run($workOrder, $date, 'Moved on the dispatch board', $actor->id);
        }
        $workOrder->forceFill(['crew_id' => $crew->id, 'assigned_to' => $lead->id, 'schedule_window' => $window])->save();
        $workOrder->refresh();

        if ($workOrder->isEmergency()) {
            $bumped = WorkOrder::withoutGlobalScope('assignedToField')
                ->where('crew_id', $crew->id)->whereDate('scheduled_date', $date)->whereKeyNot($workOrder->id)
                ->where('on_hold', false)->whereNotState('status', Completed::class)->get();
            foreach ($bumped as $other) {
                PutOnHold::run($other, 'emergency_bump', $actor, "Bumped by {$workOrder->number}");
            }
            if ($bumped->isNotEmpty()) {
                Notifier::send($crew->members, 'emergency', "Emergency assigned: {$workOrder->number}",
                    $bumped->count().' job(s) moved to on hold for today.', route('work-orders.show', $workOrder), ['work_order_id' => $workOrder->id]);
            }
        }

        return $workOrder;
    }
}
