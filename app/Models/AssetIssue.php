<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * SOW §3.7 — issue / damage report from the field against a piece of
 * equipment or a vehicle.
 *
 * @property int $id
 * @property string $severity
 * @property string $description
 * @property string $status
 * @property string|null $photo_path
 * @property int|null $reported_by
 * @property Carbon|null $resolved_at
 */
class AssetIssue extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['resolved_at' => 'datetime'];

    /** @return MorphTo<Model, $this> */
    public function issuable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /** @param  Builder<AssetIssue>  $q */
    public function scopeOpen(Builder $q): void
    {
        $q->where('status', 'open');
    }
}
