<?php

declare(strict_types=1);

namespace App\Actions\Time;

use App\Models\TimeEntry;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.8: time entries are adjustable by authorized administrators. Every
 * adjustment records who changed it, the prior value, and when.
 */
final class AdjustTimeEntry
{
    use AsAction;

    private const array ADJUSTABLE = ['started_at', 'ended_at', 'category', 'billable'];

    /** @param  array<string, mixed>  $changes */
    public function handle(TimeEntry $entry, array $changes, int $adjustedBy): TimeEntry
    {
        $changes = array_intersect_key($changes, array_flip(self::ADJUSTABLE));

        $prior = [];
        foreach (array_keys($changes) as $field) {
            $value = $entry->getAttribute($field);
            $prior[$field] = $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d H:i:s')
                : $value;
        }

        $entry->update(array_merge($changes, [
            'adjusted_by' => $adjustedBy,
            'prior_value' => $prior,
            'adjusted_at' => now(),
        ]));

        return $entry->refresh();
    }
}
