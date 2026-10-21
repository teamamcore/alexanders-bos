<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Portals\PollPortal;
use App\Models\Tenant;
use App\Models\VendorPortalIntegration;
use Illuminate\Console\Command;

/** SOW §3.14 — poll every live portal for new work requests and payment updates. */
class PortalsPoll extends Command
{
    protected $signature = 'portals:poll';

    protected $description = 'Pull new work requests and payment status from every live vendor portal.';

    public function handle(): int
    {
        foreach (Tenant::query()->get() as $tenant) {
            app()->instance('currentTenantId', $tenant->id);
            app()->instance('currentTenant', $tenant);
            foreach (VendorPortalIntegration::query()->live()->get() as $integration) {
                $r = PollPortal::run($integration);
                $this->line("{$tenant->name} / {$integration->name}: imported {$r['imported']}, paid {$r['paid']}".($r['error'] ? " — {$r['error']}" : ''));
            }
        }

        return self::SUCCESS;
    }
}
