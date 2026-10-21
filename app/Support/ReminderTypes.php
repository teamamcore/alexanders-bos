<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant;

/**
 * SOW §3.10 — configurable reminder types. Platform defaults live in
 * config('batsons.reminder_types'); a tenant overrides any field in
 * tenants.settings.reminder_types (edited under Admin → Reminders).
 *
 * @phpstan-type ReminderType array{label: string, interval_months: int, lead_days_min: int, lead_days_max: int, auto_message: bool, work_type: string|null, customer_label: string}
 */
final class ReminderTypes
{
    /** @return array<string, ReminderType> */
    public static function all(): array
    {
        $defaults = config('batsons.reminder_types', []);
        $overrides = Tenant::current()?->settings['reminder_types'] ?? [];
        $out = [];
        foreach ($defaults as $key => $def) {
            $out[$key] = self::normalize(array_merge($def, $overrides[$key] ?? []), (string) $key);
        }
        // Tenant-added types (not in platform defaults).
        foreach ($overrides as $key => $def) {
            if (! isset($out[$key]) && is_array($def) && ! empty($def['label'])) {
                $out[$key] = self::normalize($def, (string) $key);
            }
        }

        return $out;
    }

    /** @return ReminderType|null */
    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function label(string $key): string
    {
        return self::get($key)['label'] ?? ucwords(str_replace('_', ' ', $key));
    }

    /** Reminder type triggered when a work order of this work type is completed. */
    public static function forWorkType(?string $workType): ?string
    {
        if (! $workType) {
            return null;
        }
        foreach (self::all() as $key => $t) {
            if ($t['work_type'] === $workType) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $def
     * @return ReminderType
     */
    private static function normalize(array $def, string $key): array
    {
        $label = (string) ($def['label'] ?? ucwords(str_replace('_', ' ', $key)));

        return [
            'label' => $label,
            'interval_months' => max(1, (int) ($def['interval_months'] ?? 12)),
            'lead_days_min' => max(0, (int) ($def['lead_days_min'] ?? 0)),
            'lead_days_max' => max((int) ($def['lead_days_min'] ?? 0), (int) ($def['lead_days_max'] ?? 30)),
            'auto_message' => (bool) ($def['auto_message'] ?? false),
            'work_type' => isset($def['work_type']) && $def['work_type'] !== '' ? (string) $def['work_type'] : null,
            'customer_label' => (string) ($def['customer_label'] ?? strtolower($label)),
        ];
    }
}
