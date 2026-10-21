<?php

declare(strict_types=1);

namespace App\Actions\Assets;

use App\Models\AssetIssue;
use App\Models\Equipment;
use App\Models\User;
use App\Models\Vehicle;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.7 — issue / damage report from the field against equipment or a
 * vehicle. A "major" issue takes the asset out of service until a repair is logged.
 */
final class ReportAssetIssue
{
    use AsAction;

    public function handle(Equipment|Vehicle $asset, User $reporter, string $description, string $severity = 'minor', ?string $photoPath = null): AssetIssue
    {
        if (! in_array($severity, ['minor', 'major'], true)) {
            throw new DomainException("Unknown severity: {$severity}");
        }

        $issue = $asset->issues()->create([
            'severity' => $severity,
            'description' => trim($description),
            'photo_path' => $photoPath,
            'status' => 'open',
            'reported_by' => $reporter->id,
        ]);

        if ($severity === 'major') {
            $asset->forceFill(['status' => 'down'])->save();
        }

        return $issue;
    }
}
