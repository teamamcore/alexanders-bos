<?php

declare(strict_types=1);

namespace App\Actions\Reminders;

use App\Models\Customer;
use App\Models\RecurringService;
use App\Models\Reminder;
use App\Models\ServiceLocation;
use App\Models\WorkOrder;
use App\Support\ReminderTypes;
use DomainException;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.10 — "Service completed → Reminder scheduled". Creates (or refreshes)
 * the recurring service for a customer/site and opens the reminder for the
 * next cycle. Called automatically when a matching work order completes, or
 * by the office for devices with a known due date.
 */
final class ScheduleRecurringService
{
    use AsAction;

    public function handle(
        Customer $customer,
        string $type,
        ?Carbon $lastServicedAt = null,
        ?Carbon $nextDueAt = null,
        ?ServiceLocation $location = null,
        ?bool $autoMessage = null,
        ?string $label = null,
        ?WorkOrder $sourceWorkOrder = null,
        ?string $notes = null,
    ): RecurringService {
        $def = ReminderTypes::get($type);
        if ($def === null) {
            throw new DomainException("Unknown reminder type: {$type}");
        }
        if (! $lastServicedAt && ! $nextDueAt) {
            throw new DomainException('Provide the last service date or the next due date.');
        }
        $nextDueAt ??= $lastServicedAt->copy()->addMonths($def['interval_months']);

        $service = RecurringService::query()
            ->where('customer_id', $customer->id)
            ->where('type', $type)
            ->where('service_location_id', $location?->id)
            ->first();

        $attrs = [
            'customer_id' => $customer->id,
            'service_location_id' => $location?->id,
            'type' => $type,
            'label' => $label ?: ($service->label ?? null),
            'interval_months' => $def['interval_months'],
            'lead_days_min' => $def['lead_days_min'],
            'lead_days_max' => $def['lead_days_max'],
            'auto_message' => $autoMessage ?? ($service->auto_message ?? $def['auto_message']),
            'is_active' => true,
            'last_serviced_at' => $lastServicedAt ?? $service?->last_serviced_at,
            'next_due_at' => $nextDueAt,
            'source_work_order_id' => $sourceWorkOrder->id ?? $service?->source_work_order_id,
            'notes' => $notes ?: ($service->notes ?? null),
        ];

        $service = $service ? tap($service)->update($attrs) : RecurringService::create($attrs);

        OpenReminderForCycle::run($service->fresh() ?? $service);

        return $service->fresh() ?? $service;
    }
}
