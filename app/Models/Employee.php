<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * SOW §3.11 — employee profile: qualifications, certifications, milestones,
 * background-check status + date (never documents — MSA Ex. B.2 / B.5(g)).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $title
 * @property string|null $phone
 * @property Carbon|null $hired_at
 * @property string|null $background_check_status
 * @property Carbon|null $background_check_date
 * @property string|null $notes
 */
class Employee extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['background_check_date' => 'date', 'hired_at' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Certification, $this> */
    public function certifications(): HasMany
    {
        return $this->hasMany(Certification::class);
    }

    /** @return HasMany<Qualification, $this> */
    public function qualifications(): HasMany
    {
        return $this->hasMany(Qualification::class);
    }

    /** @return HasMany<EmployeeMilestone, $this> */
    public function milestones(): HasMany
    {
        return $this->hasMany(EmployeeMilestone::class)->orderByDesc('occurred_at');
    }

    /** Qualification level for a work type (0 = none). */
    public function levelFor(string $workType): int
    {
        $qualification = $this->qualifications->firstWhere('work_type', $workType);

        return $qualification instanceof Qualification ? (int) $qualification->value : 0;
    }

    /** Human label for the employee's level on a work type (config('batsons.qualification_levels')). */
    public function levelLabelFor(string $workType): string
    {
        return (string) (config('batsons.qualification_levels')[$this->levelFor($workType)] ?? 'None');
    }

    /** @return array{expired: int, expiring: int, valid: int} */
    public function certificationSummary(): array
    {
        $summary = ['expired' => 0, 'expiring' => 0, 'valid' => 0];
        foreach ($this->certifications as $c) {
            $status = $c->status();
            $summary[$status === 'none' ? 'valid' : $status]++;
        }

        return $summary;
    }

    /** Worst certification state for badges: expired > expiring > ok. */
    public function certificationState(): string
    {
        $s = $this->certificationSummary();

        return $s['expired'] > 0 ? 'expired' : ($s['expiring'] > 0 ? 'expiring' : 'ok');
    }
}
