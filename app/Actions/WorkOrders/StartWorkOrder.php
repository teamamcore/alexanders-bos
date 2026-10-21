<?php

declare(strict_types=1);

namespace App\Actions\WorkOrders;

use App\Actions\Communication\NotifyCustomer;
use App\Actions\Portals\PushPortalStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\States\WorkOrder\InProgress;
use App\States\WorkOrder\Scheduled;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/** SOW §3.2/§3.4 — field user begins work: scheduled → in progress, customer told work has started. */
final class StartWorkOrder
{
    use AsAction;

    public function handle(WorkOrder $workOrder, User $actor, bool $notify = true): WorkOrder
    {
        if (! $workOrder->status instanceof Scheduled) {
            throw new DomainException('Only a scheduled work order can be started.');
        }

        $workOrder->status->transitionTo(InProgress::class);
        $workOrder->refresh();

        if ($notify) {
            NotifyCustomer::run($workOrder, 'work_started', $actor, [], true);
        }

        PushPortalStatus::run($workOrder, 'in_progress', "Work started — {$actor->name}"); // SOW §3.14

        return $workOrder;
    }
}
