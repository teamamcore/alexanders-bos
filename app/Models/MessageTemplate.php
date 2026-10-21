<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * SOW §3.4 — tenant override of a platform message template.
 *
 * @property string $key
 * @property string $name
 * @property string $channel
 * @property string|null $subject
 * @property string $body
 * @property bool $is_active
 */
class MessageTemplate extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];
}
