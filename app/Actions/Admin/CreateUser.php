<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.13 — user setup by an administrator. Creates an active user in the
 * tenant with a role and a one-time temporary password (shown once, never
 * stored in clear). Seat usage is informational (SOW §3.15 / Exhibit A:
 * 20 included, then blocks of 5) — it never blocks onboarding.
 */
final class CreateUser
{
    use AsAction;

    /** @return array{user: User, temporary_password: string} */
    public function handle(Tenant $tenant, string $name, string $email, Role $role): array
    {
        $temporary = Str::password(16, symbols: false);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => trim($name),
            'email' => Str::lower(trim($email)),
            'password' => $temporary,
            'role' => $role,
            'is_active' => true,
        ]);
        // Admin-provisioned accounts are verified by the admin, not by email round-trip.
        $user->forceFill(['email_verified_at' => now()])->save();

        AuditLog::record('user.created', $user, ['role' => $role->value]);

        return ['user' => $user, 'temporary_password' => $temporary];
    }
}
