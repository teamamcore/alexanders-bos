<?php

declare(strict_types=1);

use App\Actions\AI\AskHelp;
use App\Actions\AI\SearchEverything;
use App\Actions\AI\SuggestTagsFor;
use App\Actions\Communication\RecordCallTranscript;
use App\Actions\WorkOrders\AddWorkOrderNote;
use App\Actions\WorkOrders\CompleteWorkOrderQuestionnaire;
use App\Actions\WorkOrders\ScheduleWorkOrder;
use App\Models\AiUsage;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Platform\AI\AI;
use App\Platform\AI\ClaudeAssistant;
use App\Platform\AI\OfflineAssistant;
use Database\Seeders\BatsonsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Module 3.16 — AI-assisted search, tagging, help, call summaries (offline assistant under test).

beforeEach(function () {
    config(['ai.driver' => 'offline', 'ai.api_key' => null]);
    $this->seed(BatsonsSeeder::class);
    $this->tenant = Tenant::firstOrFail();
    $this->admin = User::where('email', 'eric@batsonsplumbing.com')->firstOrFail();
    $this->marcus = User::where('email', 'marcus@batsonsplumbing.com')->firstOrFail();
    $this->wo = WorkOrder::withoutGlobalScopes()->where('number', 'WO-1001')->firstOrFail();
});

it('resolves the offline assistant without a key and Claude with one, metering every call', function () {
    expect(AI::assistant())->toBeInstanceOf(OfflineAssistant::class)->and(AI::enabled())->toBeFalse();

    config(['ai.driver' => 'claude', 'ai.api_key' => 'sk-ant-test']);
    expect(AI::assistant())->toBeInstanceOf(ClaudeAssistant::class)->and(AI::enabled())->toBeTrue();
    config(['ai.driver' => 'offline', 'ai.api_key' => null]);

    $this->actingAs($this->admin);
    SearchEverything::run('WO-1001');
    expect(AiUsage::where('feature', 'search')->count())->toBeGreaterThanOrEqual(2)
        ->and(AiUsage::first()->provider)->toBe('offline')->and((float) AiUsage::first()->cost_usd)->toBe(0.0);
    expect(AiUsage::cost('claude-opus-5-5', 1_000_000, 100_000))->toBe(6.0);
});

it('searches everything from one box: numbers, customers, notes, nicknames, tags, messages', function () {
    $this->actingAs($this->admin);
    AddWorkOrderNote::run($this->wo, $this->marcus, 'Found a cracked flange behind the toilet — will need a new wax ring.', 'field_finding');
    $this->wo->applyTags(['leak'], 'user');

    $r = SearchEverything::run('WO-1001');
    expect($r['plan']['numbers'])->toBe(['WO-1001'])->and(collect($r['hits'])->pluck('title')->first(fn ($t) => str_contains($t, 'WO-1001')))->not->toBeNull();

    $r = SearchEverything::run('cracked flange');
    expect(collect($r['hits'])->where('type', 'work_order')->first()['snippet'])->toContain('Note: Found a cracked flange');

    $r = SearchEverything::run('the big wrench');
    expect(collect($r['hits'])->where('type', 'part')->pluck('title'))->toContain('24" Pipe Wrench');

    $r = SearchEverything::run('#leak');
    expect(collect($r['hits'])->where('type', 'work_order')->isNotEmpty())->toBeTrue();

    $r = SearchEverything::run('Riverside Dental customer');
    expect(collect($r['hits'])->where('type', 'customer')->pluck('title'))->toContain('Riverside Dental')->and($r['answer'])->toContain('result');

    $this->get('/search?q=Acme')->assertOk()->assertSee('Acme Industrial Park')->assertSee('Looked in');

    // field users: no customers/invoices/messages in results
    $r = SearchEverything::run('Acme', true);
    expect(collect($r['hits'])->pluck('type')->unique()->values()->all())->not->toContain('customer', 'invoice', 'message');
});

it('suggests and applies tags on work orders and calls, and auto-tags on completion', function () {
    $this->actingAs($this->admin);
    AddWorkOrderNote::run($this->wo, $this->marcus, 'Annual backflow test; RPZ leaking at the relief valve, parts needed — special order.', 'field_finding');
    $tags = SuggestTagsFor::run($this->wo, true);
    expect($tags)->toContain('backflow', 'leak', 'parts-needed')->and($this->wo->fresh()->tags->pluck('slug'))->toContain('backflow');

    $this->post(route('work-orders.tags.add', $this->wo), ['tag' => 'VIP Customer'])->assertRedirect();
    expect($this->wo->fresh()->tags->pluck('slug'))->toContain('vip-customer');
    $this->delete(route('work-orders.tags.remove', [$this->wo, 'vip-customer']))->assertRedirect();
    expect($this->wo->fresh()->tags->pluck('slug'))->not->toContain('vip-customer');
    $this->get(route('work-orders.show', $this->wo))->assertSee('#backflow');

    // completion auto-tags from everything logged
    $wo2 = WorkOrder::withoutGlobalScopes()->where('number', 'WO-2002')->firstOrFail(); // sewer main line stoppage (seeded history)
    $wo2->forceFill(['status' => 'new'])->save();
    ScheduleWorkOrder::run($wo2->fresh(), $this->marcus, today()->toDateString(), 'morning');
    CompleteWorkOrderQuestionnaire::run($wo2->fresh(), $this->marcus, 'Jetted the main line 60 ft, cleared roots, camera verified clean.');
    expect($wo2->fresh()->tags->pluck('slug'))->toContain('sewer');

    $call = RecordCallTranscript::run($this->admin, Customer::first(), 'Hi, this is Pat at Acme. The water heater in the break room is leaking again and we need someone out today. Can you also send a quote for a replacement? Thanks, appreciate it.', true);
    expect($call->fresh()->summary)->toContain('Pat at Acme')->and($call->fresh()->sentiment)->toBe('positive')
        ->and($call->fresh()->action_items)->not->toBeEmpty()->and($call->fresh()->tags->pluck('slug'))->toContain('water-heater', 'leak', 'quote-requested');
    $this->get('/messages/calls')->assertOk()->assertSee('Action items');
});

it('answers in-app help from the built-in guide, aware of the current page', function () {
    $this->actingAs($this->marcus);
    $r = AskHelp::run('what data must never be stored', 'work-orders.show');
    expect($r['answer'])->toContain('Prohibited')->and($r['sources'])->not->toBeEmpty();

    $this->get('/help?q=how+do+I+put+a+work+order+on+hold&from=work-orders.show')->assertOk()->assertSee('From the guide')->assertSee('hold');
    $this->get('/help')->assertOk()->assertSee('Try asking');
    $this->actingAs($this->admin)->get('/dashboard')->assertSee(route('help'))->assertSee(route('search'));
});

it('shows administrators the metered AI usage', function () {
    $this->actingAs($this->admin);
    SearchEverything::run('backflow');
    $this->get('/admin/ai-usage')->assertOk()->assertSee('AI calls')->assertSee('search')->assertSee('Offline');
    $this->actingAs($this->marcus)->get('/admin/ai-usage')->assertForbidden();
});
