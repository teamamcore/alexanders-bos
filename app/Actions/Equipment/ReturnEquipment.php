<?php

declare(strict_types=1);

namespace App\Actions\Equipment;

use App\Actions\Assets\ReportAssetIssue;
use App\Models\AuditLog;
use App\Models\Equipment;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/** SOW §3.7 — return from the field, optionally with a damage / issue report. */
final class ReturnEquipment
{
    use AsAction;

    public function handle(Equipment $equipment, User $actor, ?string $issue = null, string $severity = 'minor', ?string $photoPath = null): Equipment
    {
        $from = $equipment->checked_out_to;
        $equipment->forceFill(['status' => 'available', 'checked_out_to' => null, 'checked_out_at' => null])->save();
        AuditLog::record('equipment.returned', $equipment, ['from' => $from, 'by' => $actor->id]);

        if ($issue !== null && trim($issue) !== '') {
            ReportAssetIssue::run($equipment, $actor, $issue, $severity, $photoPath);
        }

        return $equipment->fresh() ?? $equipment;
    }
}
