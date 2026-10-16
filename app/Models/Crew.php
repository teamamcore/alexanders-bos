<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Crew extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
