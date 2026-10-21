<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Depreciation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * SOW §3.7 — billable equipment register: rates + units, usage billed on work
 * orders, maintenance by hours/date with alerts, costs, check-out/return,
 * revenue vs. ownership cost, depreciation.
 *
 * @property int $id
 * @property string $name
 * @property string|null $type
 * @property string $billing_rate
 * @property string $billing_unit
 * @property string $hours_used
 * @property array{every_hours?: int|null, every_days?: int|null}|null $maintenance_schedule
 * @property string|null $purchase_cost
 * @property Carbon|null $purchased_at
 * @property int|null $useful_life_years
 * @property string|null $salvage_value
 * @property string $status
 * @property int|null $checked_out_to
 * @property Carbon|null $checked_out_at
 */
class Equipment extends Model
{
    use BelongsToTenant;

    protected $table = 'equipment';

    protected $guarded = [];

    protected $casts = [
        'maintenance_schedule' => 'array', 'billing_rate' => 'decimal:2', 'hours_used' => 'decimal:2',
        'purchase_cost' => 'decimal:2', 'salvage_value' => 'decimal:2',
        'purchased_at' => 'date', 'checked_out_at' => 'datetime',
    ];

    /** @return HasMany<EquipmentMaintenance, $this> */
    public function maintenance(): HasMany
    {
        return $this->hasMany(EquipmentMaintenance::class)->latest('performed_at');
    }

    /** @return HasMany<EquipmentUsage, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(EquipmentUsage::class)->latest();
    }

    /** @return MorphMany<AssetIssue, $this> */
    public function issues(): MorphMany
    {
        return $this->morphMany(AssetIssue::class, 'issuable')->latest();
    }

    /** @return BelongsTo<User, $this> */
    public function holder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_to');
    }

    public function revenue(): float
    {
        return (float) $this->usages()->sum('amount');
    }

    public function maintenanceCost(): float
    {
        return (float) $this->maintenance()->sum('cost');
    }

    /** @return array{annual: float, to_date: float, book_value: float, pct: float} */
    public function depreciation(): array
    {
        return Depreciation::straightLine(
            $this->purchase_cost !== null ? (float) $this->purchase_cost : null,
            $this->salvage_value !== null ? (float) $this->salvage_value : null,
            $this->useful_life_years,
            $this->purchased_at,
        );
    }

    /** Revenue billed minus (maintenance + depreciation to date) — "does it earn its keep". */
    public function netContribution(): float
    {
        return round($this->revenue() - $this->maintenanceCost() - $this->depreciation()['to_date'], 2);
    }

    /**
     * Maintenance due by hours used, calendar days, or both (§3.7).
     *
     * @return array{due: bool, reasons: list<string>, hours_since: float, days_since: int|null, every_hours: int|null, every_days: int|null}
     */
    public function maintenanceStatus(): array
    {
        $schedule = $this->maintenance_schedule ?? [];
        $everyHours = isset($schedule['every_hours']) ? (int) $schedule['every_hours'] : null;
        $everyDays = isset($schedule['every_days']) ? (int) $schedule['every_days'] : null;

        $last = $this->maintenance()->whereIn('type', ['maintenance', 'repair'])->orderByDesc('performed_at')->first();
        $hoursSince = round((float) $this->hours_used - (float) ($last->hours_at_service ?? 0), 2);
        $since = $last->performed_at ?? $this->purchased_at;
        $daysSince = $since ? (int) $since->diffInDays(now()) : null;

        $reasons = [];
        if ($everyHours && $hoursSince >= $everyHours) {
            $reasons[] = "{$hoursSince}h since service (every {$everyHours}h)";
        }
        if ($everyDays && $daysSince !== null && $daysSince >= $everyDays) {
            $reasons[] = "{$daysSince}d since service (every {$everyDays}d)";
        }

        return [
            'due' => $reasons !== [],
            'reasons' => $reasons,
            'hours_since' => $hoursSince,
            'days_since' => $daysSince,
            'every_hours' => $everyHours,
            'every_days' => $everyDays,
        ];
    }
}
