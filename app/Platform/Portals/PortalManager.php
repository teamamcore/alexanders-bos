<?php

declare(strict_types=1);

namespace App\Platform\Portals;

use App\Models\VendorPortalIntegration;
use DomainException;

/** Resolves the adapter for an integration (platform core; adapters are swappable, MSA §2.3). */
final class PortalManager
{
    public static function for(VendorPortalIntegration $integration): PortalAdapter
    {
        return match ($integration->adapter) {
            'sandbox' => new SandboxAdapter,
            'service_channel' => new ServiceChannelAdapter,
            'paramark' => new ParamarkAdapter,
            'manual' => new ManualAdapter,
            default => throw new DomainException("Unknown portal adapter: {$integration->adapter}"),
        };
    }

    /** Portals that count toward the five included (blocked ones don't — SOW §3.14). */
    public static function includedUsage(): array
    {
        $all = VendorPortalIntegration::query()->get();
        $counting = $all->where('counts_toward_included', true)->where('status', '!=', 'blocked')->where('adapter', '!=', 'sandbox');

        return [
            'included' => (int) config('portals.included', 5),
            'used' => $counting->count(),
            'blocked' => $all->where('status', 'blocked')->count(),
            'live' => $all->where('status', 'live')->where('adapter', '!=', 'sandbox')->count(),
        ];
    }
}
