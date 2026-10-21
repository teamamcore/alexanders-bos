<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\Employee;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/** SOW §3.11 — create an employee profile, optionally linked to a sign-in user. */
final class CreateEmployee
{
    use AsAction;

    public function handle(string $name, ?string $title = null, ?User $user = null, ?string $hiredAt = null, ?string $phone = null): Employee
    {
        $employee = Employee::create([
            'name' => trim($name),
            'title' => $title ?: null,
            'user_id' => $user?->id,
            'hired_at' => $hiredAt ?: null,
            'phone' => $phone ?: null,
        ]);

        if ($hiredAt) {
            $employee->milestones()->create(['title' => 'Hired', 'occurred_at' => $hiredAt]);
        }

        return $employee;
    }
}
