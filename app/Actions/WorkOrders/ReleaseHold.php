<?php

declare(strict_types=1);

namespace App\Actions\WorkOrders;

use App\Models\User;
use App\Models\WorkOrder;
use App\Platform\Notifications\Notifier;
use Lorisleiva\Actions\Concerns\AsAction;

/** Take a work order off hold → back to ready-to-schedule / active; dispatch is told. */
final class ReleaseHold
{
    use AsAction;

    public function handle(WorkOrder $workOrder, ?User $actor = null, ?string $why = null): WorkOrder
    {
        if (! $workOrder->on_hold) {
            return $workOrder;
        }
        $was = $workOrder->holdReasonLabel();
        $workOrder->forceFill(['on_hold' => false, 'hold_reason' => null])->save();
        AddWorkOrderNote::run($workOrder, $actor, 'Hold released'.($why ? " — {$why}" : '')." (was: {$was})", 'system');

        Notifier::send(Notifier::office($workOrder->tenant_id), 'hold_released', "{$workOrder->number} ready: ".($why ?: 'hold released'),
            "Was on hold — {$was}. Schedule it from the dispatch board.", route('schedule.index'), ['work_order_id' => $workOrder->id]);

        return $workOrder;
    }
}
