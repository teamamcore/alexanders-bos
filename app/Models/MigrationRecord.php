<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * SOW §4 — maps a source record (QuickBooks id, invoice number, customer
 * name…) to the row it became, so re-imports update instead of duplicate.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $batch_id
 * @property string $kind
 * @property string $source_key
 * @property string $model_type
 * @property int $model_id
 * @property array<string, mixed>|null $raw
 */
class MigrationRecord extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['raw' => 'array'];

    /** @return MorphTo<Model, $this> */
    public function model(): MorphTo
    {
        return $this->morphTo('model');
    }
}
