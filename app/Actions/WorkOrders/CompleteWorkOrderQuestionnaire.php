<?php

declare(strict_types=1);

namespace App\Actions\WorkOrders;

use App\Actions\Time\ClockOut;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use App\States\WorkOrder\InProgress;
use App\States\WorkOrder\Scheduled;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Completion flow (design notes §1): the questionnaire walks notes →
 * materials → equipment → hours, pre-filled by whatever was logged along the
 * way; this action records the "work performed" answer, closes the tech's open
 * clock on the job, and completes the work order.
 */
final class CompleteWorkOrderQuestionnaire
{
    use AsAction;

    public function handle(WorkOrder $workOrder, User $actor, string $workPerformed, ?string $finalNote = null): WorkOrder
    {
        $workPerformed = trim($workPerformed);
        if ($workPerformed === '') {
            throw new DomainException('Describe the work performed before completing.');
        }

        if ($workOrder->status instanceof Scheduled) {
            StartWorkOrder::run($workOrder, $actor, false);
            $workOrder->refresh();
        }
        if (! $workOrder->status instanceof InProgress) {
            throw new DomainException('This work order is not active.');
        }

        $open = TimeEntry::query()->where('work_order_id', $workOrder->id)->where('user_id', $actor->id)->whereNull('ended_at')->first();
        if ($open instanceof TimeEntry) {
            ClockOut::run($open);
        }

        if ($finalNote && trim($finalNote) !== '') {
            AddWorkOrderNote::run($workOrder, $actor, trim($finalNote), 'field_finding');
        }
        $workOrder->forceFill(['work_performed' => $workPerformed, 'on_hold' => false, 'hold_reason' => null])->save();

        return CompleteWorkOrder::run($workOrder->fresh() ?? $workOrder, $actor);
    }
}
