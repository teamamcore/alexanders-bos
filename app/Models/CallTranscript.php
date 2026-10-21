<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SOW §3.4 — a phone call transcript (office + limited field). Recording /
 * transcription consent is the Client's responsibility; we record that the
 * notice was given.
 *
 * @property string|null $transcript
 * @property string|null $summary
 * @property string $status
 * @property bool $consent_noted
 * @property int|null $duration_seconds
 * @property Carbon|null $called_at
 */
class CallTranscript extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['consent_noted' => 'boolean', 'called_at' => 'datetime'];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Job, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /** @return BelongsTo<WorkOrder, $this> */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
