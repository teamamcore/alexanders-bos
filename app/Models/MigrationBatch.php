<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Platform\Migration\Importer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * SOW §4 — one import run (dry run or commit) of one kind of record.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $run_by
 * @property string $kind
 * @property string $source
 * @property string|null $file_name
 * @property string|null $file_path
 * @property string $mode
 * @property string $status
 * @property int $total
 * @property int $imported
 * @property int $updated
 * @property int $skipped
 * @property int $warnings
 * @property list<array{row: int, level: string, field: string|null, reason: string, record: string}>|null $issues
 * @property array<string, string>|null $columns
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 */
class MigrationBatch extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['issues' => 'array', 'columns' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];

    /** @return BelongsTo<User, $this> */
    public function runner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'run_by');
    }

    /** @return HasMany<MigrationRecord, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(MigrationRecord::class, 'batch_id');
    }

    public function isDryRun(): bool
    {
        return $this->mode === 'dry_run';
    }

    public function kindLabel(): string
    {
        return Importer::KINDS[$this->kind] ?? $this->kind;
    }
}
