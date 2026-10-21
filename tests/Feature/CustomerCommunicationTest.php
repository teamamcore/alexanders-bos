<?php

declare(strict_types=1);

use App\Actions\Communication\RecordCallTranscript;
use App\Actions\Communication\SendCustomerMessage;
use App\Actions\Communication\UpdateMessageTemplate;
use App\Actions\WorkOrders\CompleteWorkOrder;
use App\Actions\WorkOrders\RescheduleWorkOrder;
use App\Actions\WorkOrders\ScheduleWorkOrder;
use App\Actions\WorkOrders\StartWorkOrder;
use App\Enums\Role;
use App\Mail\CustomerMessageMail;
use App\Models\CallTranscript;
use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Platform\Messaging\TemplateRenderer;
use Database\Seeders\BatsonsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

// Module 3.4 — Customer Communication.

beforeEach(function () {
    Storage::fake('public');
    $this->seed(BatsonsSeeder::class);
    $this->tenant = Tenant::firstOrFail();
    $this->admin = User::where('email', 'eric@batsonsplumbing.com')->firstOrFail();
    $this->marcus = User::where('email', 'marcus@batsonsplumbing.com')->firstOrFail();
    $this->wo = WorkOrder::withoutGlobalScopes()->where('number', 'WO-1001')->firstOrFail();
    $this->customer = Customer::findOrFail($this->wo->job->customer_id);
});

it('texts the customer the scheduled day when a work order is scheduled, and again when rescheduled', function () {
    ScheduleWorkOrder::run($this->wo, $this->marcus, '2026-11-03', 'morning');

    $m = CustomerMessage::where('work_order_id', $this->wo->id)->where('template_key', 'scheduled')->firstOrFail();
    expect($m->channel)->toBe('sms')->and($m->status)->toBe('sent')->and($m->to)->toBe($this->customer->phone)
        ->and($m->body)->toContain('Tuesday, Nov 3')->toContain('morning')->toContain('Batsons Plumbing');

    RescheduleWorkOrder::run($this->wo->fresh(), '2026-11-05', 'Crew out sick', $this->admin->id);
    expect(CustomerMessage::where('template_key', 'rescheduled')->where('work_order_id', $this->wo->id)->value('body'))->toContain('Thursday, Nov 5');
});

it('never blocks scheduling when the customer has no phone or email', function () {
    $this->customer->update(['phone' => null, 'email' => null]);
    $this->customer->contacts()->delete();

    ScheduleWorkOrder::run($this->wo, $this->marcus, '2026-11-03', 'morning');
    expect(CustomerMessage::count())->toBe(0)->and($this->wo->fresh()->scheduled_date->toDateString())->toBe('2026-11-03');
});

it('respects the tenant auto-notify switch and inactive templates', function () {
    $this->tenant->update(['settings' => array_merge($this->tenant->settings, ['auto_notify' => false])]);
    app()->instance('currentTenant', $this->tenant->fresh());
    ScheduleWorkOrder::run($this->wo, $this->marcus, '2026-11-03', 'morning');
    expect(CustomerMessage::count())->toBe(0);

    $this->tenant->update(['settings' => array_merge($this->tenant->settings, ['auto_notify' => true])]);
    app()->instance('currentTenant', $this->tenant->fresh());
    UpdateMessageTemplate::run($this->tenant, 'on_the_way', 'x', 'sms', null, false, $this->admin);
    expect(SendCustomerMessage::run($this->customer, 'on_the_way', null, $this->marcus, $this->wo))->toBeNull();
});

it('falls back to email when the customer only has an email address', function () {
    Mail::fake();
    $this->customer->update(['phone' => null, 'email' => 'ops@acme.example']);
    $this->customer->contacts()->delete();

    $m = SendCustomerMessage::run($this->customer->fresh(), 'scheduled', null, null, $this->wo);
    expect($m->channel)->toBe('email')->and($m->to)->toBe('ops@acme.example')->and($m->status)->toBe('sent')->and($m->subject)->not->toBeEmpty();
    Mail::assertSent(CustomerMessageMail::class, fn ($mail) => $mail->hasTo('ops@acme.example'));
});

it('lets a field user send on-the-way, arrival and free-text messages from the job, monitored by the office', function () {
    ScheduleWorkOrder::run($this->wo, $this->marcus, today()->toDateString(), 'morning');
    CustomerMessage::query()->delete();

    $this->actingAs($this->marcus);
    Volt::test('pages.jobs.show', ['job' => $this->wo->job])
        ->call('quickMessage', $this->wo->id, 'on_the_way')
        ->call('quickMessage', $this->wo->id, 'arrived')
        ->set('message_text', 'Please move the car out of the driveway.')
        ->call('sendMessage', $this->wo->id)
        ->assertHasNoErrors();

    $bodies = CustomerMessage::where('work_order_id', $this->wo->id)->pluck('body');
    expect($bodies)->toHaveCount(3)
        ->and($bodies->first(fn ($b) => str_contains($b, 'on the way')))->toContain('Marcus Reyes')
        ->and($bodies->first(fn ($b) => str_contains($b, 'arrived')))->toContain('1200 Industrial Blvd')
        ->and($bodies)->toContain('Please move the car out of the driveway.');

    // Office monitors everything; field users don't get the monitor page.
    $this->actingAs($this->admin)->get('/messages')->assertOk()->assertSee('Please move the car out of the driveway.')->assertSee('Marcus Reyes');
    $this->actingAs($this->marcus)->get('/messages')->assertForbidden();
});

it('moves a work order through start and complete with customer status updates', function () {
    ScheduleWorkOrder::run($this->wo, $this->marcus, today()->toDateString(), 'morning');

    StartWorkOrder::run($this->wo->fresh(), $this->marcus);
    expect($this->wo->fresh()->status->label())->toBe('In progress')
        ->and(CustomerMessage::where('template_key', 'work_started')->exists())->toBeTrue();

    CompleteWorkOrder::run($this->wo->fresh(), $this->marcus);
    expect($this->wo->fresh()->status->label())->toBe('Complete')
        ->and(CustomerMessage::where('template_key', 'work_complete')->value('body'))->toContain('complete');

    expect(fn () => StartWorkOrder::run($this->wo->fresh(), $this->marcus))->toThrow(DomainException::class);
});

it('renders configurable templates with placeholders and lets office users customize them', function () {
    expect(TemplateRenderer::render('Hi {first_name}, {tech} ETA {eta}. {missing}', ['first_name' => 'Pat', 'tech' => 'Dana', 'eta' => '15 min']))
        ->toBe('Hi Pat, Dana ETA 15 min.');

    $this->actingAs($this->admin);
    $this->get('/messages/templates')->assertOk()->assertSee('Placeholders')->assertSee('on_the_way');
    $this->post(route('messages.templates.update', 'on_the_way'), ['body' => 'Heads up {first_name} — {tech} is rolling your way!', 'channel' => 'sms', 'is_active' => 1])->assertRedirect();

    expect(TemplateRenderer::template('on_the_way')['overridden'])->toBeTrue();
    ScheduleWorkOrder::run($this->wo, $this->marcus, today()->toDateString(), 'morning');
    $m = SendCustomerMessage::run($this->customer->fresh(), 'on_the_way', null, $this->marcus, $this->wo->fresh());
    expect($m->body)->toStartWith('Heads up Acme — Marcus Reyes is rolling your way!');

    $this->actingAs($this->marcus)->post(route('messages.templates.update', 'on_the_way'), ['body' => 'x', 'channel' => 'sms'])->assertForbidden();
});

it('records inbound replies from the texting platform webhook and links them to the customer', function () {
    ScheduleWorkOrder::run($this->wo, $this->marcus, today()->toDateString(), 'morning');

    $this->postJson('/webhooks/sms', ['from' => $this->customer->phone, 'body' => 'Great, see you then'], ['X-Messaging-Token' => 'wrong'])->assertForbidden();

    $this->postJson('/webhooks/sms', ['from' => '+1 ('.substr(preg_replace('/\D/', '', $this->customer->phone), 0, 3).') '.substr(preg_replace('/\D/', '', $this->customer->phone), 3, 3).'-'.substr(preg_replace('/\D/', '', $this->customer->phone), 6), 'body' => 'Great, see you then'], ['X-Messaging-Token' => config('messaging.inbound_token')])
        ->assertOk()->assertJson(['matched' => true]);

    $reply = CustomerMessage::inbound()->firstOrFail();
    expect($reply->customer_id)->toBe($this->customer->id)->and($reply->work_order_id)->toBe($this->wo->id)->and($reply->status)->toBe('received');

    $this->postJson('/webhooks/sms', ['from' => '+15550000000', 'body' => 'who dis'], ['X-Messaging-Token' => config('messaging.inbound_token')])->assertStatus(202)->assertJson(['matched' => false]);
});

it('stores call transcripts with the consent notice and shows them to the office', function () {
    expect(fn () => RecordCallTranscript::run($this->admin, $this->customer, 'Hello, calling about the backflow test.', false))->toThrow(DomainException::class);

    $this->actingAs($this->admin)->post(route('messages.calls.store'), [
        'customer_id' => $this->customer->id, 'job_id' => $this->wo->job_id, 'direction' => 'inbound', 'phone' => $this->customer->phone,
        'duration_minutes' => 4.5, 'transcript' => "Customer asked to move the test to next week. Agreed on Thursday morning.\nAlso wants a quote on a water heater.", 'consent_noted' => 1,
    ])->assertRedirect();

    $call = CallTranscript::firstOrFail();
    expect($call->summary)->toBe('Customer asked to move the test to next week.')->and($call->duration_seconds)->toBe(270)->and($call->status)->toBe('transcribed')->and($call->job_id)->toBe($this->wo->job_id);

    $this->get('/messages/calls')->assertOk()->assertSee('Customer asked to move the test');
    $this->actingAs($this->marcus)->get('/messages/calls')->assertOk(); // limited field usage
});

it('captures phone and email at intake so updates can go out', function () {
    $this->actingAs($this->admin);
    Volt::test('pages.jobs.index')
        ->set('customer_name', 'Harbor Point Marina')
        ->set('customer_phone', '555-201-3344')
        ->set('customer_email', 'dock@harborpoint.example')
        ->set('job_title', 'Clogged sterilizer drain')
        ->call('createJob')->assertHasNoErrors();

    $c = Customer::where('name', 'Harbor Point Marina')->firstOrFail();
    expect($c->phone)->toBe('555-201-3344')->and($c->email)->toBe('dock@harborpoint.example');
    expect(SendCustomerMessage::recipient($c)['channel'])->toBe('sms');
    $c->update(['sms_opt_in' => false]);
    expect(SendCustomerMessage::recipient($c->fresh())['channel'])->toBe('email');
});

it('keeps messages tenant-scoped', function () {
    ScheduleWorkOrder::run($this->wo, $this->marcus, today()->toDateString(), 'morning');
    $other = Tenant::create(['name' => 'Other Co']);
    $stranger = User::factory()->create(['tenant_id' => $other->id, 'role' => Role::Administrator]);
    $this->actingAs($stranger)->get('/messages')->assertOk()->assertDontSee('Acme Industrial Park');
});
