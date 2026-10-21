<?php

declare(strict_types=1);

namespace App\Actions\Migration;

use App\Models\AuditLog;
use App\Models\MigrationBatch;
use App\Models\User;
use App\Platform\Migration\Importer;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

/** SOW §4 — import one exported file (dry run by default) and keep the report. */
final class RunImport
{
    use AsAction;

    public function handle(string $kind, string $absolutePath, bool $dryRun, ?User $runner = null, ?string $fileName = null, ?string $storedPath = null): MigrationBatch
    {
        $batch = (new Importer)->run($kind, $absolutePath, $dryRun, $runner, $fileName, $storedPath);

        if (! $dryRun && class_exists(AuditLog::class)) {
            AuditLog::record('migration.import', $batch, [
                'kind' => $kind, 'file' => $batch->file_name, 'imported' => $batch->imported, 'updated' => $batch->updated, 'skipped' => $batch->skipped,
            ]);
        }

        return $batch;
    }

    /** Commit a file that was already uploaded for a dry run. */
    public static function commitBatch(MigrationBatch $dry, ?User $runner = null): MigrationBatch
    {
        if (! $dry->file_path || ! Storage::disk('local')->exists($dry->file_path)) {
            throw new \RuntimeException('The uploaded file is no longer available — upload it again.');
        }

        return self::run($dry->kind, Storage::disk('local')->path($dry->file_path), false, $runner, $dry->file_name, $dry->file_path);
    }
}
