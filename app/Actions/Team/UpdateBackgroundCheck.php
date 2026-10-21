<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.11 / MSA Ex. B.2, B.5(g) — background check is STATUS + DATE only.
 * Supporting documents are never stored in the platform and this action is
 * administrator-only (enforced at the route). Audited.
 */
final class UpdateBackgroundCheck
{
    use AsAction;

    public function handle(Employee $employee, string $status, ?string $date, User $actor): Employee
    {
        if (! in_array($status, config('batsons.background_check_statuses', []), true)) {
            throw new DomainException("Unknown background check status: {$status}");
        }

        $before = ['status' => $employee->background_check_status, 'date' => $employee->background_check_date?->toDateString()];

        $employee->forceFill([
            'background_check_status' => $status,
            'background_check_date' => $date ?: null,
        ])->save();

        AuditLog::record('background_check.updated', $employee, [
            'before' => $before,
            'after' => ['status' => $status, 'date' => $date ?: null],
            'by' => $actor->id,
        ]);

        return $employee;
    }
}
