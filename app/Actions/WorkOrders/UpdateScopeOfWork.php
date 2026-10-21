<?php

declare(strict_types=1);

namespace App\Actions\WorkOrders;

use App\Models\ScopeRevision;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderNote;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Only the office updates the scope of work. Each addition is kept as a
 * redline revision linked to the note / conversation that authorized it, and
 * appended to the office SOW text (design notes §1).
 */
final class UpdateScopeOfWork
{
    use AsAction;

    public function handle(WorkOrder $workOrder, User $actor, string $addedText, ?WorkOrderNote $sourceNote = null): ScopeRevision
    {
        if ($actor->isField()) {
            throw new DomainException('Only the office updates the scope of work.');
        }
        $addedText = trim($addedText);
        if ($addedText === '') {
            throw new DomainException('Nothing to add to the scope.');
        }

        $revision = $workOrder->scopeRevisions()->create([
            'tenant_id' => $workOrder->tenant_id,
            'user_id' => $actor->id,
            'added_text' => $addedText,
            'source_note_id' => $sourceNote?->id,
        ]);

        $workOrder->forceFill(['scope_office' => trim(($workOrder->scope_office ? $workOrder->scope_office."\n\n" : '').$addedText)])->save();

        if ($sourceNote instanceof WorkOrderNote) {
            AcknowledgeScopeUpdate::run($sourceNote, $actor);
        }
        AddWorkOrderNote::run($workOrder, $actor, 'Scope of work updated'.($sourceNote instanceof WorkOrderNote ? " (authorized by note #{$sourceNote->id})" : ''), 'system');

        return $revision;
    }
}
