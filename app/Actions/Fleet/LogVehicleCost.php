<?php

declare(strict_types=1);

namespace App\Actions\Fleet;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMaintenance;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.7 — maintenance, repair, and fuel costs logged per vehicle. A
 * service item (oil change, tires, inspection…) resets that interval.
 */
final class LogVehicleCost
{
    use AsAction;

    public function handle(
        Vehicle $vehicle,
        string $type,
        float $cost,
        User $actor,
        ?string $performedAt = null,
        ?int $odometer = null,
        ?string $serviceItem = null,
        ?string $notes = null,
    ): VehicleMaintenance {
        if (! in_array($type, config('batsons.vehicle_cost_types', []), true)) {
            throw new DomainException("Unknown cost type: {$type}");
        }

        $entry = $vehicle->maintenance()->create([
            'type' => $type,
            'service_item' => $serviceItem ?: null,
            'cost' => max(0, $cost),
            'performed_at' => $performedAt ?: today(),
            'odometer' => $odometer ?? $vehicle->mileage,
            'notes' => $notes ?: null,
            'logged_by' => $actor->id,
        ]);

        if ($odometer !== null && $odometer > $vehicle->mileage) {
            RecordMileage::run($vehicle, $odometer, $actor, $performedAt);
        }
        if ($type === 'repair' && $vehicle->status === 'down') {
            $vehicle->forceFill(['status' => 'in_service'])->save();
        }

        return $entry;
    }
}
