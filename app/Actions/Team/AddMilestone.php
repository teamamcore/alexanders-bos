<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\Employee;
use App\Models\EmployeeMilestone;
use Lorisleiva\Actions\Concerns\AsAction;

/** SOW §3.11 — add a dated milestone to an employee profile. */
final class AddMilestone
{
    use AsAction;

    public function handle(Employee $employee, string $title, string $occurredAt, ?string $notes = null): EmployeeMilestone
    {
        return $employee->milestones()->create([
            'title' => trim($title),
            'occurred_at' => $occurredAt,
            'notes' => $notes ?: null,
        ]);
    }
}
