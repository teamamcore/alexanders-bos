<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/** SOW §3.13 — restore a deactivated user (e.g. seasonal rehire). */
final class ReactivateUser
{
    use AsAction;

    public function handle(User $user, User $actor): User
    {
        $user->forceFill(['is_active' => true])->save();

        AuditLog::record('user.reactivated', $user, ['by' => $actor->id]);

        return $user;
    }
}
