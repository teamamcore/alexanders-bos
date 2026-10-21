<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Certification;
use App\Models\Equipment;
use App\Models\Part;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Platform\Notifications\Notifier;
use Illuminate\Console\Command;

/**
 * SOW §3.12 — the morning in-app digest that replaces the internal reminder
 * emails: certifications expiring, parts to reorder, equipment service due,
 * fleet service / renewals / mileage. One notification per topic per day.
 */
class NotificationsDigest extends Command
{
    protected $signature = 'notifications:digest';

    protected $description = 'Send the daily in-app digest (certs, reorders, service due, fleet) to office users.';

    public function handle(): int
    {
        $total = 0;

        foreach (Tenant::query()->get() as $tenant) {
            app()->instance('currentTenantId', $tenant->id);
            app()->instance('currentTenant', $tenant);
            $office = Notifier::office($tenant->id);
            if ($office->isEmpty()) {
                continue;
            }

            $expiring = Certification::query()->expiringWithin()->count() + Certification::query()->expired()->count();
            if ($expiring > 0) {
                $total += Notifier::daily($office, 'digest_certifications', "{$expiring} certification(s) expiring or expired", 'Review the team alerts and schedule renewals.', route('team.index'));
            }

            $low = Part::query()->lowStock()->count();
            if ($low > 0) {
                $total += Notifier::daily($office, 'digest_reorder', "{$low} part(s) at or below reorder point", 'Open Parts to receive or reorder.', route('parts.index'));
            }

            $service = Equipment::query()->get()->filter(fn (Equipment $e) => $e->maintenanceStatus()['due'])->count();
            if ($service > 0) {
                $total += Notifier::daily($office, 'digest_equipment', "{$service} equipment item(s) due for service", 'Log maintenance before the next job.', route('equipment.index'));
            }

            $fleet = Vehicle::query()->get();
            $fleetDue = $fleet->filter(fn (Vehicle $v) => $v->serviceDue() !== [] || $v->renewalsDue() !== [])->count();
            $stale = $fleet->filter(fn (Vehicle $v) => $v->mileageStale())->count();
            if ($fleetDue > 0 || $stale > 0) {
                $parts = [];
                if ($fleetDue > 0) {
                    $parts[] = "{$fleetDue} with service or renewals due";
                }
                if ($stale > 0) {
                    $parts[] = "{$stale} need a mileage update";
                }
                $total += Notifier::daily($office, 'digest_fleet', 'Fleet: '.implode(', ', $parts), 'Open Fleet for details.', route('fleet.index'));
            }
        }

        $this->info("Digest sent: {$total} notification(s).");

        return self::SUCCESS;
    }
}
