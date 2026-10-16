<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

/**
 * Append-only audit trail (SOW §3.8 time adjustments, §3.2 gate overrides,
 * §3.3 schedule history; MSA Exhibit B.5(d) logging). Records who changed what,
 * the prior values, and when.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::$event(function ($model) use ($event) {
                AuditLog::create([
                    'tenant_id' => $model->tenant_id ?? null,
                    'user_id' => Auth::id(),
                    'auditable_type' => $model::class,
                    'auditable_id' => $model->getKey(),
                    'action' => $event,
                    'changes' => $event === 'updated'
                        ? ['before' => array_intersect_key($model->getOriginal(), $model->getChanges()), 'after' => $model->getChanges()]
                        : $model->getAttributes(),
                ]);
            });
        }
    }
}
