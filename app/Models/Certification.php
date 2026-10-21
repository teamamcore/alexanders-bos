<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SOW §3.11 — a certification or licence held by an employee, with expiry
 * alerts (config('batsons.certification_alert_days')) that also feed the
 * scheduling indicators (§3.3).
 *
 * @property int $id
 * @property int $employee_id
 * @property string $name
 * @property string $type
 * @property string|null $number
 * @property Carbon|null $issued_at
 * @property Carbon|null $expires_at
 */
class Certification extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['expires_at' => 'date', 'issued_at' => 'date'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @param  Builder<Certification>  $q */
    public function scopeExpired(Builder $q): void
    {
        $q->whereNotNull('expires_at')->whereDate('expires_at', '<', today());
    }

    /** @param  Builder<Certification>  $q */
    public function scopeExpiringWithin(Builder $q, ?int $days = null): void
    {
        $days ??= (int) config('batsons.certification_alert_days', 60);
        $q->whereNotNull('expires_at')
            ->whereDate('expires_at', '>=', today())
            ->whereDate('expires_at', '<=', today()->addDays($days));
    }

    /** expired | expiring | valid | none (no expiry). */
    public function status(): string
    {
        if (! $this->expires_at) {
            return 'none';
        }
        if ($this->expires_at->isBefore(today())) {
            return 'expired';
        }
        $days = (int) config('batsons.certification_alert_days', 60);

        return $this->expires_at->lte(today()->addDays($days)) ? 'expiring' : 'valid';
    }

    public function daysUntilExpiry(): ?int
    {
        return $this->expires_at ? (int) today()->diffInDays($this->expires_at, false) : null;
    }
}
