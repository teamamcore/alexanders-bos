<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SOW §3.16 / Exhibit A — one metered AI call.
 *
 * @property string $feature
 * @property string $provider
 * @property string|null $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $cache_read_tokens
 * @property string $cost_usd
 * @property int $duration_ms
 * @property bool $ok
 */
class AiUsage extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['ok' => 'boolean', 'cost_usd' => 'decimal:6'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function cost(string $model, int $in, int $out, int $cacheRead = 0): float
    {
        $p = config("ai.pricing.{$model}");
        if (! $p) {
            return 0.0;
        }

        return round(($in / 1_000_000) * $p['input'] + ($out / 1_000_000) * $p['output'] + ($cacheRead / 1_000_000) * ($p['cache_read'] ?? 0), 6);
    }
}
