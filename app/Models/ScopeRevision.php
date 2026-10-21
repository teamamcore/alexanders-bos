<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A redline addition to the office scope of work, with the note/conversation
 * that authorized it (design notes §1 — only the office updates the SOW).
 *
 * @property string $added_text
 * @property int|null $source_note_id
 * @property int|null $user_id
 */
class ScopeRevision extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return BelongsTo<WorkOrder, $this> */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /** @return BelongsTo<WorkOrderNote, $this> */
    public function sourceNote(): BelongsTo
    {
        return $this->belongsTo(WorkOrderNote::class, 'source_note_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
