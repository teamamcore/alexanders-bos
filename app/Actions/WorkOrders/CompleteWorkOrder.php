<?php

declare(strict_types=1);

namespace App\Actions\WorkOrders;

use App\Actions\Communication\NotifyCustomer;
use App\Models\User;
use App\Models\WorkOrder;
use App\States\WorkOrder\Completed;
use App\States\WorkOrder\InProgress;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/** SOW §3.2/§3.4 — field user ends work: in progress → completed, customer told the work is done. */
final class CompleteWorkOrder
{
    use AsAction;

    public function handle(WorkOrder $workOrder, User $actor, bool $notify = true): WorkOrder
    {
        if (! $workOrder->status instanceof InProgress) {
            throw new DomainException('Only a work order in progress can be completed.');
        }

        $workOrder->status->transitionTo(Completed::class);
        $workOrder->refresh();

        if ($notify) {
            NotifyCustomer::run($workOrder, 'work_complete', $actor, [], true);
        }

        return $workOrder;
    }
}
