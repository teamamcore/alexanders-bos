<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SOW §3.7 — parts catalog item. Searchable by the names crews actually use
 * (nicknames / abbreviations / SKU). Price is manually editable by authorized
 * users; every change lands in the audit trail (= item history).
 *
 * @property int $id
 * @property string $name
 * @property string|null $sku
 * @property string|null $category
 * @property array<int, string>|null $nicknames
 * @property string|null $photo_path
 * @property string $price
 * @property string $unit
 * @property string|null $cost
 * @property int $stock_qty
 * @property int|null $reorder_threshold
 * @property bool $is_one_off
 * @property int|null $supplier_id
 */
class Part extends Model
{
    use Auditable, BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['nicknames' => 'array', 'price' => 'decimal:2', 'cost' => 'decimal:2', 'is_one_off' => 'boolean'];

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Job, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /** @return HasMany<StockAdjustment, $this> */
    public function stockAdjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class)->latest();
    }

    /** @return HasMany<DeliveryCheck, $this> */
    public function deliveryChecks(): HasMany
    {
        return $this->hasMany(DeliveryCheck::class)->latest();
    }

    /** @return HasMany<AuditLog, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'auditable_id')->where('auditable_type', self::class)->latest();
    }

    /**
     * Search by name, SKU, category, or any crew nickname/abbreviation (§3.7).
     *
     * @param  Builder<Part>  $q
     */
    public function scopeSearch(Builder $q, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $q->where(fn (Builder $w) => $w
            ->where('name', 'like', $like)
            ->orWhere('sku', 'like', $like)
            ->orWhere('category', 'like', $like)
            ->orWhere('nicknames', 'like', $like));
    }

    /** @param  Builder<Part>  $q */
    public function scopeLowStock(Builder $q): void
    {
        $q->whereNotNull('reorder_threshold')->whereColumn('stock_qty', '<=', 'reorder_threshold');
    }

    public function isLowStock(): bool
    {
        return $this->reorder_threshold !== null && $this->stock_qty <= $this->reorder_threshold;
    }
}
