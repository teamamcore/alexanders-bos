<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Qualification;
use App\Models\User;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.11 — administrators assign each employee a qualification value per
 * work type (levels + work types come from discovery → config/batsons.php).
 * Values feed the scheduling indicators (§3.3). Changes are audited.
 */
final class SetQualification
{
    use AsAction;

    public function handle(Employee $employee, string $workType, int $level, User $actor): Qualification
    {
        if (! array_key_exists($workType, config('batsons.work_types', []))) {
            throw new DomainException("Unknown work type: {$workType}");
        }
        if (! array_key_exists($level, config('batsons.qualification_levels', []))) {
            throw new DomainException("Unknown qualification level: {$level}");
        }

        /** @var Qualification $qualification */
        $qualification = $employee->qualifications()->firstOrNew(['work_type' => $workType]);
        $from = (int) ($qualification->exists ? $qualification->value : 0);

        if ($qualification->exists && $from === $level) {
            return $qualification;
        }

        $qualification->value = $level;
        $qualification->save();

        AuditLog::record('qualification.set', $employee, [
            'work_type' => $workType, 'from' => $from, 'to' => $level, 'by' => $actor->id,
        ]);

        return $qualification;
    }
}
