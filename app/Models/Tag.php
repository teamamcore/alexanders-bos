<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * SOW §3.16 — a tag (AI-suggested or user-applied) on work orders, jobs,
 * notes, calls… Search filters by it.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $slug
 * @property string $source
 */
class Tag extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public static function normalize(string $name): string
    {
        return Str::slug(Str::lower(trim($name)));
    }

    public static function findOrCreateByName(string $name, string $source = 'ai'): self
    {
        $slug = self::normalize($name);

        return self::query()->firstOrCreate(['slug' => $slug], ['name' => Str::lower(trim($name)), 'source' => $source]);
    }
}
