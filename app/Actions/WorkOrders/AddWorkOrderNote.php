<?php

declare(strict_types=1);

namespace App\Actions\WorkOrders;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderNote;
use App\Platform\Notifications\Notifier;
use DomainException;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Internal note stream on the work order. A `scope_update` is the tech's
 * "Send scope update" ping: findings that need office pricing / customer
 * approval before extra work starts (company policy — techs never price).
 */
final class AddWorkOrderNote
{
    use AsAction;

    public function handle(WorkOrder $workOrder, ?User $author, string $body, string $kind = 'tech_note'): WorkOrderNote
    {
        if (! array_key_exists($kind, WorkOrderNote::KINDS)) {
            throw new DomainException("Unknown note kind: {$kind}");
        }
        $body = trim($body);
        if ($body === '') {
            throw new DomainException('Note is empty.');
        }

        $note = $workOrder->notes()->create([
            'tenant_id' => $workOrder->tenant_id,
            'user_id' => $author?->id,
            'kind' => $kind,
            'body' => $body,
        ]);

        if ($kind === 'scope_update') {
            Notifier::send(Notifier::office($workOrder->tenant_id, $author?->id), 'scope_update',
                "Scope update on {$workOrder->number}".($workOrder->job?->customer ? " — {$workOrder->job->customer->name}" : ''),
                Str::limit($body, 140).($author instanceof User ? " — {$author->name}" : ''),
                route('work-orders.show', $workOrder), ['work_order_id' => $workOrder->id, 'note_id' => $note->id]);
        }

        return $note;
    }
}
