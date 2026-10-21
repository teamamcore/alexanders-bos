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
 * SOW §3.7 — fleet register: year/make/model/VIN/plate, assigned crew or
 * driver, mileage with service intervals, renewal reminders, costs, issues,
 * total cost of ownership and depreciation.
 *
 * @property int $id
 * @property string|null $name
 * @property int|null $year
 * @property string|null $make
 * @property string|null $model
 * @property string|null $vin
 * @property string|null $plate
 * @property int|null $assigned_crew_id
 * @property int|null $assigned_user_id
 * @property int $mileage
 * @property list<array{name: string, every_miles?: int|null, every_months?: int|null}>|null $service_intervals
 * @property Carbon|null $registration_due
 * @property Carbon|null $inspection_due
 * @property Carbon|null $insurance_due
 * @property string|null $purchase_cost
 * @property Carbon|null $purchased_at
 * @property int|null $useful_life_years
 * @property string|null $salvage_value
 * @property string $status
 */
class Vehicle extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'service_intervals' => 'array', 'registration_due' => 'date', 'inspection_due' => 'date', 'insurance_due' => 'date',
        'purchase_cost' => 'decimal:2', 'salvage_value' => 'decimal:2', 'purchased_at' => 'date',
    ];

    /** @return HasMany<VehicleMaintenance, $this> */
    public function maintenance(): HasMany
    {
        return $this->hasMany(VehicleMaintenance::class)->latest('performed_at');
    }

    /** @return HasMany<MileageReading, $this> */
    public function readings(): HasMany
    {
        return $this->hasMany(MileageReading::class)->latest('recorded_at');
    }

    /** @return MorphMany<AssetIssue, $this> */
    public function issues(): MorphMany
    {
        return $this->morphMany(AssetIssue::class, 'issuable')->latest();
    }

    /** @return BelongsTo<User, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** @return BelongsTo<Crew, $this> */
    public function crew(): BelongsTo
    {
        return $this->belongsTo(Crew::class, 'assigned_crew_id');
    }

    public function label(): string
    {
        return $this->name ?: trim("{$this->year} {$this->make} {$this->model}");
    }

    /**
     * Service items due by miles or months since last done (§3.7).
     *
     * @return list<array{name: string, reason: string}>
     */
    public function serviceDue(): array
    {
        $due = [];
        foreach ($this->service_intervals ?? [] as $interval) {
            $name = (string) $interval['name'];
            if ($name === '') {
                continue;
            }
            $last = $this->maintenance()->where('service_item', $name)->orderByDesc('performed_at')->first();
            $milesSince = $this->mileage - (int) ($last->odometer ?? 0);
            $monthsSince = $last?->performed_at ? (int) $last->performed_at->diffInMonths(now()) : null;

            $everyMiles = isset($interval['every_miles']) ? (int) $interval['every_miles'] : null;
            $everyMonths = isset($interval['every_months']) ? (int) $interval['every_months'] : null;

            if ($everyMiles && $milesSince >= $everyMiles) {
                $due[] = ['name' => $name, 'reason' => number_format($milesSince).' mi since last (every '.number_format($everyMiles).')'];
            } elseif ($everyMonths && ($monthsSince === null ? $this->purchased_at && $this->purchased_at->diffInMonths(now()) >= $everyMonths : $monthsSince >= $everyMonths)) {
                $due[] = ['name' => $name, 'reason' => ($monthsSince ?? '—')." mo since last (every {$everyMonths})"];
            }
        }

        return $due;
    }

    /**
     * Registration / inspection / insurance renewals due within the alert window or overdue.
     *
     * @return list<array{name: string, due: Carbon, overdue: bool}>
     */
    public function renewalsDue(): array
    {
        $window = (int) config('batsons.renewal_alert_days', 30);
        $out = [];
        foreach (['Registration' => $this->registration_due, 'Inspection' => $this->inspection_due, 'Insurance' => $this->insurance_due] as $name => $date) {
            if ($date && $date->lte(today()->addDays($window))) {
                $out[] = ['name' => $name, 'due' => $date, 'overdue' => $date->isBefore(today())];
            }
        }

        return $out;
    }

    /** Monthly mileage input reminder: true when no reading in the stale window. */
    public function mileageStale(): bool
    {
        $last = $this->readings()->first();
        $days = (int) config('batsons.fleet_mileage_stale_days', 30);

        return ! $last || $last->recorded_at->lt(today()->subDays($days));
    }

    public function costTotal(): float
    {
        return (float) $this->maintenance()->sum('cost');
    }

    /** @return array<string, float> cost by type (maintenance|repair|fuel) */
    public function costByType(): array
    {
        return $this->maintenance()->selectRaw('type, sum(cost) as total')->groupBy('type')->pluck('total', 'type')
            ->map(fn ($v) => (float) $v)->all();
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

    /** Total cost of ownership to date: purchase + all logged costs. */
    public function totalCostOfOwnership(): float
    {
        return round((float) $this->purchase_cost + $this->costTotal(), 2);
    }
}
