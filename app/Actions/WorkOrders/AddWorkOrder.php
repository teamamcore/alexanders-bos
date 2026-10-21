<?php

declare(strict_types=1);

namespace App\Actions\WorkOrders;

use App\Models\Job;
use App\Models\WorkOrder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.2: add a work order under a job. Status defaults to the `new`
 * work-order state (App\States\WorkOrder).
 */
final class AddWorkOrder
{
    use AsAction;

    public function handle(Job $job, ?string $number, ?string $customerNotes, string $scheduleWindow, ?string $workType = null): WorkOrder
    {
        return $job->workOrders()->create([
            'tenant_id' => $job->tenant_id,
            'number' => $number ?: null,
            'customer_notes' => $customerNotes ?: null,
            'schedule_window' => $scheduleWindow,
            'work_type' => $workType ?: null, // SOW §3.11 → §3.3 indicators
        ]);
    }
}
