<?php

declare(strict_types=1);

namespace App\Actions\Fleet;

use App\Models\MileageReading;
use App\Models\User;
use App\Models\Vehicle;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/** SOW §3.7 — mileage reported from the field (monthly reminder keys off the last reading). */
final class RecordMileage
{
    use AsAction;

    public function handle(Vehicle $vehicle, int $odometer, User $actor, ?string $recordedAt = null): MileageReading
    {
        if ($odometer < $vehicle->mileage) {
            throw new DomainException("Odometer ({$odometer}) is lower than the last recorded mileage ({$vehicle->mileage}).");
        }

        $vehicle->forceFill(['mileage' => $odometer])->save();

        return $vehicle->readings()->create([
            'odometer' => $odometer,
            'recorded_at' => $recordedAt ?: today(),
            'recorded_by' => $actor->id,
        ]);
    }
}
