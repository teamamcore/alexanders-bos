<?php

declare(strict_types=1);

namespace App\Actions\WorkOrders;

use App\Models\Customer;
use App\Models\Job;
use App\Models\ServiceLocation;
use App\Models\User;
use App\Models\WorkOrder;
use App\Platform\Notifications\Notifier;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Emergency flow (design notes §2): an emergency work order defaults to the
 * All-Day lane with a red frame; when dropped on a team, that team's current
 * work moves to On Hold (see AssignWorkOrderToTeam). Opens in the W.O.S panel.
 */
final class CreateEmergencyWorkOrder
{
    use AsAction;

    public function handle(string $customerName, string $description, User $actor, ?string $address = null, ?string $workType = null): WorkOrder
    {
        $customer = Customer::firstOrCreate(['name' => trim($customerName)]);
        $location = null;
        if ($address) {
            $location = ServiceLocation::query()->where('customer_id', $customer->id)->where('address', trim($address))->first()
                ?? ServiceLocation::create(['customer_id' => $customer->id, 'address' => trim($address), 'type' => 'job']);
        }

        $job = Job::create([
            'customer_id' => $customer->id,
            'service_location_id' => $location?->id,
            'title' => 'EMERGENCY — '.Str::limit(trim($description), 60, '…'),
            'status' => 'open',
        ]);

        $wo = AddWorkOrder::run($job, null, trim($description), 'emergency', $workType);
        $wo->forceFill(['priority' => 'emergency', 'number' => $wo->number ?: 'WO-E'.$wo->id])->save();
        AddWorkOrderNote::run($wo, $actor, 'Emergency work order created', 'system');

        Notifier::send(Notifier::office($wo->tenant_id, $actor->id), 'emergency', "EMERGENCY: {$customer->name}",
            Str::limit(trim($description), 120).' — dispatch from the schedule board.',
            route('schedule.index'), ['work_order_id' => $wo->id]);

        return $wo->fresh() ?? $wo;
    }
}
