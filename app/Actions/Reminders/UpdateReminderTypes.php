<?php

declare(strict_types=1);

namespace App\Actions\Reminders;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use DomainException;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.10 — configurable reminder types: interval, contact window, default
 * auto-message, and the work type that triggers them. Stored as tenant
 * overrides over the platform defaults.
 *
 * @param  array<string, array<string, mixed>>  $types  key => fields
 */
final class UpdateReminderTypes
{
    use AsAction;

    /** @param  array<string, array<string, mixed>>  $types */
    public function handle(Tenant $tenant, array $types, User $actor): Tenant
    {
        $clean = [];
        foreach ($types as $key => $def) {
            $key = Str::slug((string) ($def['key'] ?? $key), '_');
            if ($key === '' || empty($def['label'])) {
                continue;
            }
            $min = max(0, (int) ($def['lead_days_min'] ?? 0));
            $max = max(0, (int) ($def['lead_days_max'] ?? 0));
            if ($max < $min) {
                throw new DomainException("“{$def['label']}”: the window start must be at least as many days out as the contact-by date.");
            }
            $clean[$key] = [
                'label' => trim((string) $def['label']),
                'customer_label' => trim((string) ($def['customer_label'] ?? '')) ?: strtolower(trim((string) $def['label'])),
                'interval_months' => max(1, (int) ($def['interval_months'] ?? 12)),
                'lead_days_min' => $min,
                'lead_days_max' => $max,
                'auto_message' => (bool) ($def['auto_message'] ?? false),
                'work_type' => isset($def['work_type']) && $def['work_type'] !== '' ? (string) $def['work_type'] : null,
            ];
        }

        $settings = $tenant->settings ?? [];
        $before = $settings['reminder_types'] ?? [];
        $settings['reminder_types'] = $clean;
        $tenant->settings = $settings;
        $tenant->save();
        app()->instance('currentTenant', $tenant);

        AuditLog::record('reminder_types.updated', $tenant, ['before' => $before, 'after' => $clean, 'by' => $actor->id]);

        return $tenant;
    }
}
