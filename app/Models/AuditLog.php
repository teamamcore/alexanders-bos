<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    protected $guarded = [];

    protected $casts = ['changes' => 'array'];

    /**
     * Explicit audit entry for administrative actions on models that are not
     * Auditable themselves (users, tenant settings) — MSA Ex. B.5(d).
     *
     * @param  array<string, mixed>  $changes
     */
    public static function record(string $action, Model $subject, array $changes = []): self
    {
        $tenantId = $subject->getAttribute('tenant_id')
            ?? ($subject instanceof Tenant ? $subject->id : null)
            ?? (app()->bound('currentTenantId') ? (int) app('currentTenantId') : null);

        return self::create([
            'tenant_id' => $tenantId,
            'user_id' => Auth::id(),
            'auditable_type' => $subject::class,
            'auditable_id' => $subject->getKey(),
            'action' => $action,
            'changes' => $changes,
        ]);
    }
}
