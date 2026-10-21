<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.13 — move a user between the three access tiers
 * (field / office / administrator). A tenant keeps at least one administrator.
 */
final class ChangeUserRole
{
    use AsAction;

    public function handle(User $user, Role $role, User $actor): User
    {
        $from = $user->role;

        if ($from === $role) {
            return $user;
        }

        if ($from === Role::Administrator && $user->is_active && DeactivateUser::otherActiveAdministrators($user) === 0) {
            throw new DomainException('At least one active administrator is required.');
        }

        $user->forceFill(['role' => $role])->save();

        AuditLog::record('user.role_changed', $user, ['from' => $from->value, 'to' => $role->value, 'by' => $actor->id]);

        return $user;
    }
}
