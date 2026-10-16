<?php

declare(strict_types=1);

namespace App\Actions\Time;

use App\Models\TimeEntry;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/** SOW §3.8: close an open time entry. */
final class ClockOut
{
    use AsAction;

    public function handle(TimeEntry $entry, ?Carbon $at = null): TimeEntry
    {
        $entry->update(['ended_at' => $at ?? now()]);

        return $entry->refresh();
    }
}
