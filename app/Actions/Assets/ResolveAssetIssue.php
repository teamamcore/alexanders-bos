<?php

declare(strict_types=1);

namespace App\Actions\Assets;

use App\Models\AssetIssue;
use App\Models\Equipment;
use App\Models\User;
use App\Models\Vehicle;
use Lorisleiva\Actions\Concerns\AsAction;

/** SOW §3.7 — close an issue; brings a "down" asset back if nothing major remains open. */
final class ResolveAssetIssue
{
    use AsAction;

    public function handle(AssetIssue $issue, User $actor): AssetIssue
    {
        $issue->forceFill(['status' => 'resolved', 'resolved_by' => $actor->id, 'resolved_at' => now()])->save();

        $asset = $issue->issuable;
        if (($asset instanceof Equipment || $asset instanceof Vehicle)
            && $asset->status === 'down'
            && ! $asset->issues()->open()->where('severity', 'major')->exists()) {
            $asset->forceFill(['status' => $asset instanceof Vehicle ? 'in_service' : 'available'])->save();
        }

        return $issue;
    }
}
