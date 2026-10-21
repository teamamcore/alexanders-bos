<?php

declare(strict_types=1);

namespace App\Actions\WorkOrders;

use App\Models\User;
use App\Models\WorkOrder;
use App\Platform\Notifications\Notifier;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Hold is a sub-state of Active (design notes §1): waiting for parts, customer
 * approval, discovery follow-up… The schedule is kept; the card shows "On hold".
 */
final class PutOnHold
{
    use AsAction;

    public function handle(WorkOrder $workOrder, string $reason, User $actor, ?string $note = null): WorkOrder
    {
        $reasons = config('batsons.hold_reasons', []);
        if (! array_key_exists($reason, $reasons)) {
            throw new DomainException("Unknown hold reason: {$reason}");
        }

        $workOrder->forceFill(['on_hold' => true, 'hold_reason' => $reason])->save();
        AddWorkOrderNote::run($workOrder, $actor, 'On hold — '.$reasons[$reason].($note ? ": {$note}" : ''), 'system');

        if ($actor->isField()) {
            Notifier::send(Notifier::office($workOrder->tenant_id), 'hold', "{$workOrder->number} on hold: {$reasons[$reason]}",
                trim(($note ?? '').' — '.$actor->name, ' —'), route('work-orders.show', $workOrder), ['work_order_id' => $workOrder->id]);
        }

        return $workOrder;
    }
}
