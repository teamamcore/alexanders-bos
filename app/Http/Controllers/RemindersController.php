<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Reminders\ContactReminder;
use App\Actions\Reminders\OpenReminderForCycle;
use App\Actions\Reminders\ScheduleRecurringService;
use App\Actions\Reminders\ScheduleReminderJob;
use App\Models\Customer;
use App\Models\RecurringService;
use App\Models\Reminder;
use App\Models\ServiceLocation;
use App\Models\User;
use App\Support\ReminderTypes;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Module 3.10 — Recurring Service & Reminders (office + administrators). */
final class RemindersController extends Controller
{
    public function index(Request $request): View
    {
        $open = Reminder::query()->open()->with(['customer', 'serviceLocation', 'job', 'recurringService'])->orderBy('due_at')->get();

        return view('ops.reminders.index', [
            'toWork' => $open->filter(fn (Reminder $r) => in_array($r->status, ['notified', 'contacted'], true))->values(),
            'scheduled' => $open->where('status', 'scheduled')->values(),
            'upcoming' => $open->where('status', 'pending')->values(),
            'services' => RecurringService::query()->with(['customer', 'serviceLocation'])->orderBy('next_due_at')->get(),
            'types' => ReminderTypes::all(),
            'customers' => Customer::query()->with('serviceLocations')->orderBy('name')->get(),
            'windows' => config('batsons.schedule_windows', []),
            'stats' => [
                'Ready to work' => $open->whereIn('status', ['notified', 'contacted'])->count(),
                'Late (past contact-by)' => $open->filter(fn (Reminder $r) => $r->isLate())->count(),
                'Scheduled' => $open->where('status', 'scheduled')->count(),
                'Next 90 days' => $open->filter(fn (Reminder $r) => $r->due_at->lte(today()->addDays(90)))->count(),
            ],
        ]);
    }

    public function storeService(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('tenant_id', app('currentTenantId'))],
            'service_location_id' => ['nullable', 'integer', Rule::exists('service_locations', 'id')->where('tenant_id', app('currentTenantId'))],
            'type' => ['required', 'string', Rule::in(array_keys(ReminderTypes::all()))],
            'last_serviced_at' => ['nullable', 'date'],
            'next_due_at' => ['nullable', 'date', 'required_without:last_serviced_at'],
            'label' => ['nullable', 'string', 'max:120'],
            'auto_message' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service = ScheduleRecurringService::run(
                Customer::findOrFail((int) $data['customer_id']),
                $data['type'],
                ! empty($data['last_serviced_at']) ? Carbon::parse($data['last_serviced_at']) : null,
                ! empty($data['next_due_at']) ? Carbon::parse($data['next_due_at']) : null,
                ! empty($data['service_location_id']) ? ServiceLocation::find((int) $data['service_location_id']) : null,
                (bool) ($data['auto_message'] ?? false),
                $data['label'] ?? null,
                null,
                $data['notes'] ?? null,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['type' => $e->getMessage()]);
        }

        return back()->with('status', $service->displayLabel().' is on the schedule — next due '.$service->next_due_at->toFormattedDateString().'.');
    }

    public function toggleService(Request $request, RecurringService $service): RedirectResponse
    {
        $service->forceFill(['is_active' => ! $service->is_active])->save();
        if (! $service->is_active) {
            $service->reminders()->whereIn('status', ['pending', 'notified', 'contacted'])->update(['status' => 'skipped']);
        }

        return back()->with('status', $service->displayLabel().($service->is_active ? ' resumed.' : ' paused.'));
    }

    public function contact(Request $request, Reminder $reminder): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(['sms', 'email', 'call', 'in_person'])],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        try {
            ContactReminder::run($reminder, $this->actor($request), $data['method'], $data['note'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['method' => $e->getMessage()]);
        }

        return back()->with('status', in_array($data['method'], ['sms', 'email'], true) ? 'Reminder sent to the customer.' : 'Contact logged.');
    }

    public function schedule(Request $request, Reminder $reminder): RedirectResponse
    {
        $data = $request->validate([
            'window' => ['nullable', Rule::in(config('batsons.schedule_windows', []))],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);

        try {
            $job = ScheduleReminderJob::run($reminder, $this->actor($request), $data['window'] ?? 'morning', $data['notes'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['window' => $e->getMessage()]);
        }

        return redirect()->route('jobs.show', $job)->with('status', 'Job created from the reminder — schedule the work order from the dispatch board.');
    }

    public function skip(Request $request, Reminder $reminder): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:300']]);
        $reminder->forceFill(['status' => 'skipped', 'notes' => trim(($reminder->notes ? $reminder->notes."\n" : '').'Skipped by '.$this->actor($request)->name.($data['note'] ? ": {$data['note']}" : ''))])->save();

        // Keep the schedule alive: roll to the next cycle so it comes back around.
        if ($reminder->recurringService?->is_active) {
            $reminder->recurringService->rollForward($reminder->due_at);
            OpenReminderForCycle::run($reminder->recurringService->fresh() ?? $reminder->recurringService);
        }

        return back()->with('status', 'Reminder skipped for this cycle.');
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
