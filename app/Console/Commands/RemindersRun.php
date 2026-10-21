<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Reminders\RunReminders;
use App\Models\Tenant;
use Illuminate\Console\Command;

/** SOW §3.10 — daily: open cycle reminders, alert the office, send auto messages. */
class RemindersRun extends Command
{
    protected $signature = 'reminders:run';

    protected $description = 'Open due service reminders, alert the office when a contact window opens, send automatic customer messages.';

    public function handle(): int
    {
        foreach (Tenant::query()->get() as $tenant) {
            app()->instance('currentTenantId', $tenant->id);
            app()->instance('currentTenant', $tenant);
            $r = RunReminders::run($tenant->id);
            $this->info("{$tenant->name}: opened {$r['opened']}, notified {$r['notified']}, messaged {$r['messaged']}.");
        }

        return self::SUCCESS;
    }
}
