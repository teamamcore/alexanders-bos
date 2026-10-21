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

    /**
     * The tenant bound for this request/test (SetCurrentTenant binds the id).
     * Cached on the container; re-resolved if the bound id changes.
     */
    public static function current(): ?self
    {
        if (! app()->bound('currentTenantId')) {
            return null;
        }
        $id = (int) app('currentTenantId');

        if (app()->bound('currentTenant') && app('currentTenant') instanceof self && app('currentTenant')->id === $id) {
            return app('currentTenant');
        }

        $tenant = self::find($id);
        if ($tenant instanceof self) {
            app()->instance('currentTenant', $tenant);
        }

        return $tenant;
    }

    /** SOW §3.13 — tenant terminology override, else platform default. */
    public function term(string $key): string
    {
        return $this->settings['terminology'][$key]
            ?? config("batsons.terminology.{$key}", Str::headline($key));
    }

    /** SOW §3.13 — tenant abbreviation override, else platform default. */
    public function abbr(string $key): string
    {
        return $this->settings['abbreviations'][$key]
            ?? config("batsons.abbreviations.{$key}", Str::upper(Str::substr($key, 0, 2)));
    }

    /**
     * SOW §3.15 / Exhibit A — included seats, then blocks of five.
     *
     * @return array{included: int, active: int, available: int, additional: int, blocks: int, block_size: int}
     */
    public function seatUsage(): array
    {
        $included = (int) ($this->settings['included_users'] ?? config('batsons.included_users', 20));
        $blockSize = max(1, (int) config('batsons.additional_user_block', 5));
        $active = $this->users()->where('is_active', true)->count();
        $additional = max(0, $active - $included);

        return [
            'included' => $included,
            'active' => $active,
            'available' => max(0, $included - $active),
            'additional' => $additional,
            'blocks' => (int) ceil($additional / $blockSize),
            'block_size' => $blockSize,
        ];
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
