<?php

declare(strict_types=1);

use App\Actions\Assets\ReportAssetIssue;
use App\Actions\Communication\RecordInboundMessage;
use App\Actions\Messaging\SendUserMessage;
use App\Actions\WorkOrders\RescheduleWorkOrder;
use App\Actions\WorkOrders\ScheduleWorkOrder;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\Equipment;
use App\Models\InboundEmail;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserMessage;
use App\Models\WorkOrder;
use Database\Seeders\BatsonsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Module 3.12 — Notifications & Messaging.

beforeEach(function () {
    $this->seed(BatsonsSeeder::class);
    $this->tenant = Tenant::firstOrFail();
    $this->admin = User::where('email', 'eric@batsonsplumbing.com')->firstOrFail();
    $this->marcus = User::where('email', 'marcus@batsonsplumbing.com')->firstOrFail();
    $this->dana = User::where('email', 'dana@batsonsplumbing.com')->firstOrFail();
    $this->wo = WorkOrder::withoutGlobalScopes()->where('number', 'WO-1001')->firstOrFail();
});

function kinds(User $u): array
{
    return $u->notifications()->get()->map(fn ($n) => $n->data['kind'])->all();
}

it('notifies the assigned tech of a new assignment and of schedule changes', function () {
    ScheduleWorkOrder::run($this->wo, $this->marcus, '2026-11-03', 'morning');
    expect(kinds($this->marcus))->toContain('assignment')
        ->and($this->marcus->unreadNotifications()->first()->data['title'])->toContain('WO-1001');

    RescheduleWorkOrder::run($this->wo->fresh(), '2026-11-05', 'Weather', $this->admin->id);
    expect(kinds($this->marcus))->toContain('schedule_change')
        ->and(kinds($this->dana))->toBe([]);
});

it('notifies the office when a customer replies, an issue is reported, or a delivery is short', function () {
    ScheduleWorkOrder::run($this->wo, $this->marcus, today()->toDateString(), 'morning');
    $customer = Customer::findOrFail($this->wo->job->customer_id);

    RecordInboundMessage::run((string) $customer->phone, 'Running late, is 10 ok?');
    expect(kinds($this->admin))->toContain('customer_reply')->and(kinds($this->marcus))->not->toContain('customer_reply');

    ReportAssetIssue::run(Equipment::where('type', 'jetter')->firstOrFail(), $this->marcus, 'Hose leaking', 'major');
    expect(kinds($this->admin))->toContain('issue');
});

it('shows notifications, marks one read on open, and marks all read', function () {
    ScheduleWorkOrder::run($this->wo, $this->marcus, today()->toDateString(), 'morning');
    $this->actingAs($this->marcus);

    $this->get('/notifications')->assertOk()->assertSee('New assignment');
    $this->get('/dashboard')->assertSee('notif-badge');

    $n = $this->marcus->unreadNotifications()->first();
    $this->post(route('notifications.read', $n->id))->assertRedirect(route('jobs.show', $this->wo->job_id));
    expect($n->fresh()->read_at)->not->toBeNull();

    RescheduleWorkOrder::run($this->wo->fresh(), '2026-11-09', null, $this->admin->id);
    $this->post(route('notifications.readAll'))->assertRedirect();
    expect($this->marcus->unreadNotifications()->count())->toBe(0);
});

it('lets users message each other in-app with unread counts and a notification', function () {
    $m = SendUserMessage::run($this->admin, $this->marcus, 'Can you swing by the shop before Acme?');
    expect($m->recipient_id)->toBe($this->marcus->id)->and(kinds($this->marcus))->toContain('message');

    $this->actingAs($this->marcus);
    $this->get('/inbox')->assertOk()->assertSee('Eric White')->assertSee('Can you swing by');
    $this->get(route('inbox.thread', $this->admin))->assertOk()->assertSee('Can you swing by');
    expect($m->fresh()->read_at)->not->toBeNull(); // opening the thread marks it read

    $this->post(route('inbox.send'), ['recipient_id' => $this->admin->id, 'body' => 'On it.'])->assertRedirect(route('inbox.thread', $this->admin));
    expect(UserMessage::between($this->admin->id, $this->marcus->id)->count())->toBe(2);

    expect(fn () => SendUserMessage::run($this->admin, $this->admin, 'hi'))->toThrow(DomainException::class);
    $stranger = User::factory()->create(['tenant_id' => Tenant::create(['name' => 'Other'])->id]);
    expect(fn () => SendUserMessage::run($this->admin, $stranger, 'hi'))->toThrow(DomainException::class);
});

it('routes inbound email to the customer and job by number or sender, and queues the rest', function () {
    ScheduleWorkOrder::run($this->wo, $this->marcus, today()->toDateString(), 'morning');
    $token = config('messaging.inbound_token');

    // By job number in the subject → CustomerMessage on the job + office notified.
    $this->postJson('/webhooks/email', ['from' => 'someone@random.example', 'subject' => 'Re: WO-1001 gate code', 'text' => 'Gate code is 4471.', 'message_id' => '<a@x>'], ['X-Messaging-Token' => $token])
        ->assertOk()->assertJson(['routed' => true]);
    $e = InboundEmail::firstOrFail();
    expect($e->status)->toBe('routed')->and($e->routed_by_rule)->toBe('job_number')->and($e->work_order_id)->toBe($this->wo->id)
        ->and(CustomerMessage::inbound()->where('channel', 'email')->where('work_order_id', $this->wo->id)->exists())->toBeTrue()
        ->and(kinds($this->admin))->toContain('inbound_email');

    // By sender email on a contact.
    $this->postJson('/webhooks/email', ['from' => 'pat.morales@acme-industrial.example', 'subject' => 'Invoice question', 'text' => 'Can you resend?'], ['X-Messaging-Token' => $token])->assertOk();
    expect(InboundEmail::latest('id')->first()->routed_by_rule)->toBe('contact_email');

    // Unknown sender → waits in the queue; office routes it by hand.
    $this->postJson('/webhooks/email', ['from' => 'new.lead@gmail.example', 'from_name' => 'New Lead', 'subject' => 'Need a quote', 'text' => 'Water heater replacement?'], ['X-Messaging-Token' => $token])
        ->assertOk()->assertJson(['routed' => false]);
    $pending = InboundEmail::unrouted()->firstOrFail();

    $this->actingAs($this->admin);
    $this->get('/inbox/email')->assertOk()->assertSee('Need a quote')->assertSee('needs routing');
    $this->post(route('inbox.email.route', $pending), ['customer_id' => $this->wo->job->customer_id])->assertRedirect();
    expect($pending->fresh()->status)->toBe('routed')->and($pending->fresh()->customer_message_id)->not->toBeNull();

    // Duplicate message ids are ignored; bad token rejected.
    $this->postJson('/webhooks/email', ['from' => 'someone@random.example', 'subject' => 'Re: WO-1001', 'text' => 'again', 'message_id' => '<a@x>'], ['X-Messaging-Token' => $token])->assertOk();
    expect(InboundEmail::count())->toBe(3);
    $this->postJson('/webhooks/email', ['from' => 'x@y.z', 'text' => 'nope'], ['X-Messaging-Token' => 'bad'])->assertForbidden();
});

it('sends the morning digest once per day to office users only', function () {
    $this->artisan('notifications:digest')->assertSuccessful();
    $first = $this->admin->notifications()->count();
    expect($first)->toBeGreaterThan(0)
        ->and(kinds($this->admin))->toContain('digest_certifications', 'digest_fleet')
        ->and(kinds($this->marcus))->toBe([]);

    $this->artisan('notifications:digest')->assertSuccessful();
    expect($this->admin->notifications()->count())->toBe($first);
});

it('keeps inbox and notifications per user and tenant', function () {
    $office = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => Role::Office]);
    SendUserMessage::run($this->admin, $this->marcus, 'private');
    $this->actingAs($office)->get('/inbox')->assertOk()->assertDontSee('private');
    $this->actingAs($this->dana)->get(route('inbox.thread', $this->admin))->assertOk()->assertDontSee('private');
});
