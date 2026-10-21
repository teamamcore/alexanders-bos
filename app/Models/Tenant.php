<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tenant extends Model
{
    protected $guarded = [];

    protected $casts = ['settings' => 'array'];

    /**
     * Stable identifier for this client's layer (config/clients/<key>.php,
     * app/Client/<Studly>, export folder). Falls back to a slug of the name.
     */
    public function clientKey(): string
    {
        return $this->settings['client_key'] ?? Str::slug($this->name ?: 'tenant-'.$this->id);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
