<?php

declare(strict_types=1);

namespace App\Actions\WorkOrders;

use App\Models\User;
use App\Models\WorkOrderNote;
use Lorisleiva\Actions\Concerns\AsAction;

/** Office has seen the tech's scope-update ping (clears it from "actions needed"). */
final class AcknowledgeScopeUpdate
{
    use AsAction;

    public function handle(WorkOrderNote $note, User $actor): WorkOrderNote
    {
        if ($note->kind === 'scope_update' && ! $note->acknowledged_at) {
            $note->forceFill(['acknowledged_at' => now(), 'acknowledged_by' => $actor->id])->save();
        }

        return $note;
    }
}
