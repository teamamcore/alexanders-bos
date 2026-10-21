<?php

declare(strict_types=1);

namespace App\Actions\Equipment;

use App\Models\AuditLog;
use App\Models\Equipment;
use App\Models\User;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/** SOW §3.7 — check-out by crew; return via ReturnEquipment (with optional issue report). */
final class CheckOutEquipment
{
    use AsAction;

    public function handle(Equipment $equipment, User $to, User $actor): Equipment
    {
        if ($equipment->status === 'checked_out') {
            throw new DomainException("{$equipment->name} is already checked out.");
        }
        if ($equipment->status === 'down') {
            throw new DomainException("{$equipment->name} is down for repair.");
        }

        $equipment->forceFill(['status' => 'checked_out', 'checked_out_to' => $to->id, 'checked_out_at' => now()])->save();
        AuditLog::record('equipment.checked_out', $equipment, ['to' => $to->id, 'by' => $actor->id]);

        return $equipment;
    }
}
