<?php

declare(strict_types=1);

namespace App\Actions\Equipment;

use App\Models\Equipment;
use App\Models\EquipmentMaintenance;
use App\Models\User;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/** SOW §3.7 — maintenance, repair, parts, and rental costs logged per item; resets the service clock. */
final class LogEquipmentMaintenance
{
    use AsAction;

    public function handle(Equipment $equipment, string $type, float $cost, User $actor, ?string $performedAt = null, ?string $notes = null, ?float $hoursAtService = null): EquipmentMaintenance
    {
        if (! in_array($type, config('batsons.equipment_cost_types', []), true)) {
            throw new DomainException("Unknown cost type: {$type}");
        }

        $entry = $equipment->maintenance()->create([
            'type' => $type,
            'cost' => max(0, $cost),
            'performed_at' => $performedAt ?: today(),
            'hours_at_service' => $hoursAtService ?? (float) $equipment->hours_used,
            'notes' => $notes ?: null,
            'logged_by' => $actor->id,
        ]);

        if ($type === 'repair' && $equipment->status === 'down') {
            $equipment->forceFill(['status' => 'available'])->save();
        }

        return $entry;
    }
}
