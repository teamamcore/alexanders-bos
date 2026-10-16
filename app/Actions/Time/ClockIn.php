<?php

declare(strict_types=1);

namespace App\Actions\Time;

use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.8: clock in against a specific job/work order from the User's device.
 * Category is travel | on_site | shop; shop time is non-billable by default.
 */
final class ClockIn
{
    use AsAction;

    public function handle(
        User $user,
        ?WorkOrder $workOrder = null,
        string $category = 'on_site',
        ?bool $billable = null,
        ?Carbon $at = null,
    ): TimeEntry {
        return TimeEntry::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'work_order_id' => $workOrder?->id,
            'job_id' => $workOrder?->job_id,
            'category' => $category,
            'billable' => $billable ?? ($category !== 'shop'),
            'started_at' => $at ?? now(),
        ]);
    }
}
