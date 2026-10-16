<?php

declare(strict_types=1);

namespace App\Actions\WorkOrders;

use App\Models\ScheduleChange;
use App\Models\WorkOrder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.3: reschedule a work order. Records the reason and keeps schedule
 * history; the crew notification (and optional customer notice) hook in here.
 */
final class RescheduleWorkOrder
{
    use AsAction;

    public function handle(WorkOrder $workOrder, string $newDate, ?string $reason = null, ?int $changedBy = null): WorkOrder
    {
        ScheduleChange::create([
            'tenant_id' => $workOrder->tenant_id,
            'work_order_id' => $workOrder->id,
            'from_date' => $workOrder->scheduled_date,
            'to_date' => $newDate,
            'reason' => $reason,
            'changed_by' => $changedBy,
        ]);

        $workOrder->update(['scheduled_date' => $newDate]);

        return $workOrder->refresh();
    }
}
