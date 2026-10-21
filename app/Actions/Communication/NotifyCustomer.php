<?php

declare(strict_types=1);

namespace App\Actions\Communication;

use App\Models\CustomerMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.4 — event-based customer update for a work order: scheduled day,
 * rescheduled, on the way, arrived, work started / complete, status note.
 * Honors the tenant's auto-notify switch and the template's active flag.
 *
 * @param  array<string, mixed>  $vars
 */
final class NotifyCustomer
{
    use AsAction;

    /** @param  array<string, mixed>  $vars */
    public function handle(WorkOrder $workOrder, string $templateKey, ?User $sender = null, array $vars = [], bool $automated = false): ?CustomerMessage
    {
        if ($automated && ! (Tenant::current()?->settings['auto_notify'] ?? true)) {
            return null;
        }

        $customer = $workOrder->job?->customer;
        if (! $customer) {
            return null;
        }

        $workOrder->loadMissing('job.customer.contacts', 'job.serviceLocation', 'assignee');

        return SendCustomerMessage::run($customer, $templateKey, null, $sender, $workOrder, $vars);
    }
}
