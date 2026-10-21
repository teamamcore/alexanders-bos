<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\AuditLog;
use App\Models\Tenant;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.13 — configurable terminology and abbreviations. Overrides live in
 * tenants.settings (Client layer); platform defaults in config/batsons.php.
 * Blank values fall back to the default. Read through term() / abbr().
 */
final class UpdateTerminology
{
    use AsAction;

    /**
     * @param  array<string, string|null>  $terminology
     * @param  array<string, string|null>  $abbreviations
     */
    public function handle(Tenant $tenant, array $terminology, array $abbreviations = []): Tenant
    {
        $settings = $tenant->settings ?? [];
        $before = [
            'terminology' => $settings['terminology'] ?? [],
            'abbreviations' => $settings['abbreviations'] ?? [],
        ];

        $settings['terminology'] = $this->clean($terminology, array_keys(config('batsons.terminology', [])));
        $settings['abbreviations'] = $this->clean($abbreviations, array_keys(config('batsons.terminology', [])));

        $tenant->settings = $settings;
        $tenant->save();

        // Refresh the per-request tenant so term() reflects the change immediately.
        app()->instance('currentTenant', $tenant);

        AuditLog::record('terminology.updated', $tenant, [
            'before' => $before,
            'after' => ['terminology' => $settings['terminology'], 'abbreviations' => $settings['abbreviations']],
        ]);

        return $tenant;
    }

    /**
     * @param  array<string, string|null>  $values
     * @param  list<string>  $allowedKeys
     * @return array<string, string>
     */
    private function clean(array $values, array $allowedKeys): array
    {
        $out = [];
        foreach ($allowedKeys as $key) {
            $value = trim((string) ($values[$key] ?? ''));
            if ($value !== '') {
                $out[$key] = mb_substr($value, 0, 40);
            }
        }

        return $out;
    }
}
