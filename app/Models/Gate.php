<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Gate extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['required_actions' => 'array'];
}
