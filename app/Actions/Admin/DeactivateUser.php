<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.13 / MSA §5.1 — deactivate a departed user promptly. The account is
 * kept (history, audit trail, time entries) but can no longer sign in; any
 * live sessions are dropped. Guards: you can't deactivate yourself, and a
 * tenant always keeps at least one active administrator.
 */
final class DeactivateUser
{
    use AsAction;

    public function handle(User $user, User $actor): User
    {
        if ($user->is($actor)) {
            throw new DomainException('You cannot deactivate your own account.');
        }

        if ($user->role === Role::Administrator && self::otherActiveAdministrators($user) === 0) {
            throw new DomainException('At least one active administrator is required.');
        }

        $user->forceFill(['is_active' => false, 'remember_token' => null])->save();

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        AuditLog::record('user.deactivated', $user, ['by' => $actor->id]);

        return $user;
    }

    public static function otherActiveAdministrators(User $user): int
    {
        return User::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('role', Role::Administrator)
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->count();
    }
}
