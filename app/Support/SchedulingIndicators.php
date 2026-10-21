<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * SOW §3.11 → §3.3: for a set of assignable users, the qualification level on
 * the work order's work type plus certification health, so the dispatch board
 * can show who is qualified and who has a lapsed licence.
 *
 * @phpstan-type Indicator array{level: int, level_label: string, expired: int, expiring: int, employee_id: int|null}
 */
final class SchedulingIndicators
{
    /**
     * @param  Collection<int, User>  $users
     * @return array<int, Indicator> keyed by user id
     */
    public static function forUsers(Collection $users, ?string $workType): array
    {
        $employees = Employee::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->with(['qualifications', 'certifications'])
            ->get()
            ->keyBy('user_id');

        $levels = config('batsons.qualification_levels', []);
        $out = [];

        foreach ($users as $user) {
            /** @var Employee|null $employee */
            $employee = $employees->get($user->id);
            $level = ($employee && $workType) ? $employee->levelFor($workType) : 0;
            $certs = $employee?->certificationSummary() ?? ['expired' => 0, 'expiring' => 0, 'valid' => 0];

            $out[$user->id] = [
                'level' => $level,
                'level_label' => (string) ($levels[$level] ?? 'None'),
                'expired' => $certs['expired'],
                'expiring' => $certs['expiring'],
                'employee_id' => $employee?->id,
            ];
        }

        return $out;
    }

    /** One-line label for a select option: "Backflow: Lead · ⚠ 1 expired". */
    public static function label(array $indicator, ?string $workType): string
    {
        $parts = [];
        if ($workType) {
            $parts[] = self::workTypeLabel($workType).': '.$indicator['level_label'];
        }
        if ($indicator['expired'] > 0) {
            $parts[] = '⚠ '.$indicator['expired'].' expired';
        } elseif ($indicator['expiring'] > 0) {
            $parts[] = '⏳ '.$indicator['expiring'].' expiring';
        }

        return implode(' · ', $parts);
    }

    public static function workTypeLabel(string $workType): string
    {
        return (string) (config('batsons.work_types')[$workType] ?? ucwords(str_replace('_', ' ', $workType)));
    }
}
