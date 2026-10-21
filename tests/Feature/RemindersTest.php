<?php

declare(strict_types=1);

use App\Actions\Reminders\ContactReminder;
use App\Actions\Reminders\RunReminders;
use App\Actions\Reminders\ScheduleRecurringService;
use App\Actions\Reminders\ScheduleReminderJob;
use App\Actions\Reminders\UpdateReminderTypes;
use App\Actions\WorkOrders\CompleteWorkOrder;
use App\Actions\WorkOrders\ScheduleWorkOrder;
use App\Actions\WorkOrders\StartWorkOrder;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\RecurringService;
use App\Models\Reminder;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\ReminderTypes;
use Database\Seeders\BatsonsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

// Module 3.10 — Recurring Service & Reminders.

beforeEach(function () {
    $this->seed(BatsonsSeeder::class);
    $this->tenant = Tenant::firstOrFail();
    $this->admin = User::where('email', 'eric@batsonsplumbing.com')->firstOrFail();
    $this->marcus = User::where('email', 'marcus@batsonsplumbing.com')->firstOrFail();
    $this->wo = WorkOrder::withoutGlobalScopes()->where('number', 'WO-1001')->firstOrFail(); // backflow_testing
    $this->customer = Customer::findOrFail($this->wo->job->customer_id);
});

function finish(WorkOrder $wo, User $tech): void
{
    ScheduleWorkOrder::run($wo, $tech, today()->toDateString(), 'morning');
    StartWorkOrder::run($wo->fresh(), $tech, false);
    CompleteWorkOrder::run($wo->fresh(), $tech, false);
}

it('puts the customer on an annual backflow schedule when a backflow test completes, with a 45–60 day contact window', function () {
    RecurringService::query()->delete();
    Reminder::query()->delete();

    finish($this->wo, $this->marcus);

    $service = RecurringService::where('customer_id', $this->customer->id)->where('type', 'backflow_test')->firstOrFail();
    expect($service->last_serviced_at->toDateString())->toBe(today()->toDateString())
        ->and($service->next_due_at->toDateString())->toBe(today()->addMonths(12)->toDateString())
        ->and($service->service_location_id)->toBe($this->wo->job->service_location_id)
        ->and($service->source_work_order_id)->toBe($this->wo->id);

    $reminder = $service->currentReminder();
    expect($reminder->status)->toBe('pending')
        ->and($reminder->remind_at->toDateString())->toBe(today()->addMonths(12)->subDays(60)->toDateString())
        ->and($reminder->contact_by->toDateString())->toBe(today()->addMonths(12)->subDays(45)->toDateString());
});

it('alerts the office when the window opens and sends the automatic customer message when enabled', function () {
    RecurringService::query()->delete();
    Reminder::query()->delete();
    $service = ScheduleRecurringService::run($this->customer, 'backflow_test', null, today()->addDays(50), null, true);
    $reminder = $service->currentReminder();
    expect($reminder->remind_at->isPast() || $reminder->remind_at->isToday())->toBeTrue();

    $r = RunReminders::run($this->tenant->id);
    expect($r['notified'])->toBe(1)->and($r['messaged'])->toBe(1);

    $reminder->refresh();
    expect($reminder->status)->toBe('contacted')->and($reminder->contact_method)->toBe('sms');
    $msg = CustomerMessage::where('template_key', 'service_reminder')->firstOrFail();
    expect($msg->body)->toContain('backflow')->toContain(today()->addDays(50)->format('M j'));
    expect($this->admin->notifications()->get()->pluck('data.kind'))->toContain('reminders');

    // Second run is idempotent.
    $again = RunReminders::run($this->tenant->id);
    expect($again['notified'])->toBe(0);
});

it('leaves the customer alone when auto-message is off and lets the office text, call, or email', function () {
    RecurringService::query()->delete();
    Reminder::query()->delete();
    $service = ScheduleRecurringService::run($this->customer, 'water_heater_service', null, today()->addDays(10), null, false);
    RunReminders::run($this->tenant->id);
    $reminder = $service->currentReminder();
    expect($reminder->status)->toBe('notified')->and(CustomerMessage::where('template_key', 'service_reminder')->count())->toBe(0);

    $this->actingAs($this->admin);
    $this->get('/reminders')->assertOk()->assertSee('Ready to work')->assertSee($this->customer->name);

    $this->post(route('reminders.contact', $reminder), ['method' => 'call', 'note' => 'Left voicemail'])->assertRedirect();
    expect($reminder->fresh()->status)->toBe('contacted')->and($reminder->fresh()->notes)->toContain('Left voicemail');

    ContactReminder::run($reminder->fresh(), $this->admin, 'sms');
    expect(CustomerMessage::where('template_key', 'service_reminder')->count())->toBe(1);
});

it('turns a reminder into a typed job, closes it when the work completes, and rolls the schedule a year', function () {
    RecurringService::query()->delete();
    Reminder::query()->delete();
    $service = ScheduleRecurringService::run($this->customer, 'backflow_test', null, today()->addDays(30), $this->wo->job->serviceLocation);
    $reminder = $service->currentReminder();

    $this->actingAs($this->admin)->post(route('reminders.schedule', $reminder), ['window' => 'afternoon'])->assertRedirect();
    $reminder->refresh();
    expect($reminder->status)->toBe('scheduled')->and($reminder->job_id)->not->toBeNull();
    $wo = WorkOrder::withoutGlobalScopes()->findOrFail($reminder->work_order_id);
    expect($wo->work_type)->toBe('backflow_testing')->and($wo->schedule_window)->toBe('afternoon')->and($wo->customer_notes)->toContain('backflow test');

    expect(fn () => ScheduleReminderJob::run($reminder->fresh(), $this->admin))->toThrow(DomainException::class);

    finish($wo, $this->marcus);
    expect($reminder->fresh()->status)->toBe('done')
        ->and($service->fresh()->last_serviced_at->toDateString())->toBe(today()->toDateString())
        ->and($service->fresh()->next_due_at->toDateString())->toBe(today()->addMonths(12)->toDateString())
        ->and($service->fresh()->currentReminder()->status)->toBe('pending')
        ->and(RecurringService::where('customer_id', $this->customer->id)->where('type', 'backflow_test')->count())->toBe(1);
});

it('lets administrators configure reminder types and uses them for new schedules', function () {
    $this->actingAs($this->admin);
    $this->get('/admin/reminder-types')->assertOk()->assertSee('Recurring reminder types');

    UpdateReminderTypes::run($this->tenant, [
        'backflow_test' => ['label' => 'Backflow test', 'interval_months' => 12, 'lead_days_max' => 60, 'lead_days_min' => 45, 'work_type' => 'backflow_testing'],
        'water_heater_service' => ['label' => 'Water heater service', 'interval_months' => 12, 'lead_days_max' => 30, 'lead_days_min' => 7, 'auto_message' => 1, 'work_type' => 'water_heater'],
        'new' => ['key' => 'grease_trap', 'label' => 'Grease trap pump-out', 'customer_label' => 'grease trap service', 'interval_months' => 3, 'lead_days_max' => 14, 'lead_days_min' => 3],
    ], $this->admin);

    $types = ReminderTypes::all();
    expect($types)->toHaveKey('grease_trap')
        ->and($types['grease_trap']['interval_months'])->toBe(3)
        ->and($types['water_heater_service']['auto_message'])->toBeTrue()
        ->and(ReminderTypes::forWorkType('water_heater'))->toBe('water_heater_service');

    $s = ScheduleRecurringService::run($this->customer, 'grease_trap', Carbon::parse('2026-10-01'));
    expect($s->next_due_at->toDateString())->toBe('2027-01-01')->and($s->lead_days_max)->toBe(14);

    expect(fn () => UpdateReminderTypes::run($this->tenant, ['x' => ['key' => 'x', 'label' => 'Bad', 'lead_days_max' => 5, 'lead_days_min' => 10]], $this->admin))->toThrow(DomainException::class);
    $this->actingAs($this->marcus)->get('/admin/reminder-types')->assertForbidden();
});

it('adds a recurring service by hand, pauses it, and skips a cycle without losing the schedule', function () {
    $this->actingAs($this->admin);
    $this->post(route('reminders.services.store'), ['customer_id' => $this->customer->id, 'type' => 'water_heater_service', 'last_serviced_at' => '2026-01-15', 'label' => '50-gal Rheem'])->assertRedirect();
    $s = RecurringService::where('label', '50-gal Rheem')->firstOrFail();
    expect($s->next_due_at->toDateString())->toBe('2027-01-15')->and($s->currentReminder())->not->toBeNull();

    $this->post(route('reminders.skip', $s->currentReminder()), ['note' => 'Customer replaced unit'])->assertRedirect();
    $s->refresh();
    expect($s->reminders()->where('status', 'skipped')->count())->toBe(1)
        ->and($s->next_due_at->toDateString())->toBe('2028-01-15')
        ->and($s->currentReminder()->status)->toBe('pending');

    $this->post(route('reminders.services.toggle', $s))->assertRedirect();
    expect($s->fresh()->is_active)->toBeFalse()->and($s->fresh()->currentReminder())->toBeNull();
});

it('keeps reminders office-only and tenant-scoped', function () {
    $this->actingAs($this->marcus)->get('/reminders')->assertForbidden();
    $other = Tenant::create(['name' => 'Other']);
    $stranger = User::factory()->create(['tenant_id' => $other->id, 'role' => Role::Office]);
    $this->actingAs($stranger)->get('/reminders')->assertOk()->assertDontSee('Acme Industrial Park');
});
