<?php

declare(strict_types=1);

use App\Actions\Inventory\CheckDelivery;
use App\Actions\WorkOrders\AssignWorkOrderToTeam;
use App\Actions\WorkOrders\CreateEmergencyWorkOrder;
use App\Actions\WorkOrders\PutOnHold;
use App\Actions\WorkOrders\ScheduleWorkOrder;
use App\Actions\WorkOrders\UpdateScopeOfWork;
use App\Models\Crew;
use App\Models\Customer;
use App\Models\Part;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderNote;
use Database\Seeders\BatsonsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

// Design pass — docs/design-notes-2026-10.md + whiteboards in docs/design/.

beforeEach(function () {
    Storage::fake('public');
    $this->seed(BatsonsSeeder::class);
    $this->tenant = Tenant::firstOrFail();
    $this->admin = User::where('email', 'eric@batsonsplumbing.com')->firstOrFail();
    $this->marcus = User::where('email', 'marcus@batsonsplumbing.com')->firstOrFail();
    $this->dana = User::where('email', 'dana@batsonsplumbing.com')->firstOrFail();
    $this->wo = WorkOrder::withoutGlobalScopes()->where('number', 'WO-1001')->firstOrFail();
    $this->team1 = Crew::where('name', 'Team 1')->firstOrFail();
});

it('renders the new chrome: top bar with page name and back, collapsible sidebar, client-selectable icon set', function () {
    $this->actingAs($this->admin);
    $r = $this->get('/dashboard')->assertOk()
        ->assertSee('Toggle sidebar')->assertSee('title="Back"', false)->assertSee('Collapse')
        ->assertSee('Work Orders')->assertSee('Contacts')->assertSee('Administration');

    // plumbing set is default: the restroom-symbol contacts glyph has a centre divider line
    $r->assertSee('<path d="M12 3.5v17"/>', false);

    $this->tenant->update(['settings' => array_merge($this->tenant->settings, ['icon_set' => 'standard'])]);
    app()->instance('currentTenant', $this->tenant->fresh());
    $this->get('/dashboard')->assertOk()->assertDontSee('<path d="M12 3.5v17"/>', false);

    // field users: no Contacts / Reports / Admin in the sidebar, bottom tab bar present
    $this->actingAs($this->marcus)->get('/dashboard')->assertOk()->assertDontSee('Administration')->assertSee('Clock');
});

it('mirrors the Work Orders categories (Unscheduled / On hold / Scheduled / Billable / Closed) and quick-creates', function () {
    $this->actingAs($this->admin);
    $this->get('/work-orders')->assertOk()->assertSee('Unscheduled')->assertSee('On hold')->assertSee('Paid / closed')->assertSee('WO-1001');

    Volt::test('pages.work-orders.index')
        ->set('creating', true)->set('customer_name', 'Publix @ 123 McBee')->set('description', 'Leaking toilet in men\'s room')->set('work_type', 'sewer')
        ->call('create')->assertHasNoErrors()->assertRedirect();
    $wo = WorkOrder::withoutGlobalScopes()->latest('id')->first();
    expect($wo->board())->toBe('unscheduled')->and($wo->job->customer->name)->toBe('Publix @ 123 McBee');
});

it('shows the office the full parent (pill, tally, SOW, comms, billables) and the tech the minimal stack without pricing', function () {
    ScheduleWorkOrder::run($this->wo, $this->marcus, today()->toDateString(), 'morning');

    $this->actingAs($this->admin)->get(route('work-orders.show', $this->wo))->assertOk()
        ->assertSee('Inactive')->assertSee('Billable')->assertSee('Paid / Closed')->assertSee('Book now')->assertSee('Invoice')
        ->assertSee('Description of issue')->assertSee('Scope of work')->assertSee('Billables')->assertSee('External · customer')->assertSee('Secondary')
        ->assertSee('$'); // running tally for the admin

    $tech = $this->actingAs($this->marcus)->get(route('work-orders.show', $this->wo))->assertOk()
        ->assertSee('Send scope update')->assertSee('Clock in')->assertSee('Complete job')->assertSee('On hold — parts')
        ->assertDontSee('Billables')->assertDontSee('Book now')->assertDontSee('Invoice');
    expect(preg_match('/\$\d/', $tech->getContent()))->toBe(0);
});

it('routes a scope-update ping to the office, surfaces it in actions needed, and lets the office redline the SOW from it', function () {
    ScheduleWorkOrder::run($this->wo, $this->marcus, today()->toDateString(), 'morning');

    $this->actingAs($this->marcus);
    Volt::test('pages.work-orders.show', ['workOrder' => $this->wo])
        ->set('note', 'Riser corroded behind the wall — needs 4 ft of copper and a new ball valve.')
        ->call('saveNote', 'scope_update')->assertHasNoErrors();

    $ping = WorkOrderNote::pendingScopeUpdates()->firstOrFail();
    expect($this->admin->notifications()->get()->pluck('data.kind'))->toContain('scope_update');

    $this->actingAs($this->admin);
    $this->get('/dashboard')->assertSee('Actions needed')->assertSee('Riser corroded');

    $rev = UpdateScopeOfWork::run($this->wo, $this->admin, 'Replace 4 ft of 3/4" copper riser and 3/4" ball valve (approved by Pat, $340).', $ping);
    expect($rev->source_note_id)->toBe($ping->id)->and($ping->fresh()->acknowledged_at)->not->toBeNull()
        ->and($this->wo->fresh()->scope_office)->toContain('ball valve');
    $this->get(route('work-orders.show', $this->wo))->assertSee('authorized by')->assertSee('Replace 4 ft');

    expect(fn () => UpdateScopeOfWork::run($this->wo, $this->marcus, 'nope'))->toThrow(DomainException::class);
});

it('treats Hold as a sub-state with a reasons taxonomy and releases it when the parts arrive', function () {
    ScheduleWorkOrder::run($this->wo, $this->marcus, today()->toDateString(), 'morning');
    PutOnHold::run($this->wo->fresh(), 'waiting_parts', $this->marcus, 'Need a 009 kit');
    $wo = $this->wo->fresh();
    expect($wo->on_hold)->toBeTrue()->and($wo->pillStatus())->toBe('active')->and($wo->board())->toBe('on_hold')->and($wo->holdReasonLabel())->toBe('Waiting for parts')
        ->and($this->admin->notifications()->get()->pluck('data.kind'))->toContain('hold');

    expect(fn () => PutOnHold::run($wo, 'bored', $this->marcus))->toThrow(DomainException::class);

    // part-arrival trigger (ties into Inventory)
    CheckDelivery::run(Part::where('sku', 'WT-009-RK34')->firstOrFail(), 1, 1, 0, $this->admin, $wo, null, 'PO-9');
    expect($this->wo->fresh()->on_hold)->toBeFalse()
        ->and($this->admin->notifications()->get()->pluck('data.kind'))->toContain('hold_released');
});

it('completes through the pre-filled questionnaire and records work performed', function () {
    ScheduleWorkOrder::run($this->wo, $this->marcus, today()->toDateString(), 'morning');
    $this->actingAs($this->marcus);
    $this->get(route('work-orders.complete', $this->wo))->assertOk()->assertSee('1 · Notes')->assertSee('4 · Hours')->assertSee('Work performed');

    Volt::test('pages.work-orders.complete', ['workOrder' => $this->wo])
        ->set('work_performed', 'Tested RPZ, replaced check #2, passed.')
        ->call('submit')->assertHasErrors(['confirmed']);

    Volt::test('pages.work-orders.complete', ['workOrder' => $this->wo])
        ->set('confirmed', ['notes' => true, 'materials' => true, 'equipment' => true, 'hours' => true])
        ->set('work_performed', 'Tested RPZ, replaced check #2, passed.')
        ->call('submit')->assertHasNoErrors()->assertRedirect(route('work-orders.show', $this->wo));

    $wo = $this->wo->fresh();
    expect($wo->status->label())->toBe('Complete')->and($wo->work_performed)->toContain('replaced check #2')->and($wo->pillStatus())->toBe('billable');
});

it('drags a work order onto a team lane: team, lead, day, AM/PM window', function () {
    $this->actingAs($this->admin);
    $this->get('/schedule')->assertOk()->assertSee('Roster')->assertSee('All day')->assertSee('W.O.S')->assertSee('Team 1')->assertSee('Emergency');

    Volt::test('pages.schedule.index')->set('date', '2026-11-03')->call('dropOn', $this->wo->id, $this->team1->id, 'pm')->assertHasNoErrors();
    $wo = $this->wo->fresh();
    expect($wo->crew_id)->toBe($this->team1->id)->and($wo->assigned_to)->toBe($this->team1->lead()->id)
        ->and($wo->scheduled_date->toDateString())->toBe('2026-11-03')->and($wo->schedule_window)->toBe('afternoon')->and($wo->lane())->toBe('pm');

    // move it: same day, new lane; new day → reschedule history
    Volt::test('pages.schedule.index')->set('date', '2026-11-04')->call('dropOn', $wo->id, $this->team1->id, 'am');
    expect($this->wo->fresh()->scheduled_date->toDateString())->toBe('2026-11-04')->and($this->wo->fresh()->lane())->toBe('am')
        ->and($this->wo->fresh()->scheduleChanges()->count())->toBe(1);

    $this->actingAs($this->marcus);
    Volt::test('pages.schedule.index')->call('dropOn', $wo->id, $this->team1->id, 'pm')->assertForbidden();
});

it('runs the emergency flow: all-day red card, bumps the team\'s current job to on hold', function () {
    $this->actingAs($this->admin);
    AssignWorkOrderToTeam::run($this->wo, $this->team1, today()->toDateString(), 'am', $this->admin);

    $em = CreateEmergencyWorkOrder::run('Riverside Dental', 'Main break flooding the lab', $this->admin, '88 River Rd');
    expect($em->isEmergency())->toBeTrue()->and($em->lane())->toBe('all_day')->and($em->board())->toBe('unscheduled');
    $this->get('/schedule')->assertSee('wo-card-emergency', false);

    AssignWorkOrderToTeam::run($em, $this->team1, today()->toDateString(), 'all_day', $this->admin);
    expect($this->wo->fresh()->on_hold)->toBeTrue()->and($this->wo->fresh()->hold_reason)->toBe('emergency_bump')
        ->and($em->fresh()->schedule_window)->toBe('emergency')
        ->and($this->marcus->notifications()->get()->pluck('data.kind'))->toContain('emergency');
});

it('renders week and month views and the Book-now deep link', function () {
    $this->actingAs($this->admin);
    ScheduleWorkOrder::run($this->wo, $this->marcus, today()->toDateString(), 'morning');
    $this->get('/schedule?view=week')->assertOk()->assertSee(today()->format('M j'));
    $this->get('/schedule?view=month')->assertOk()->assertSee(today()->format('F Y'));
    $this->get('/schedule?wo='.$this->wo->id)->assertOk()->assertSee('Open '.abbr('work_order'));
});

it('shows the contacts module: business header, locations with local contacts, filtered comms, AR chart', function () {
    $this->actingAs($this->admin);
    $acme = Customer::where('name', 'Acme Industrial Park')->firstOrFail();
    $this->get('/contacts')->assertOk()->assertSee('Acme Industrial Park')->assertSee('Paid YTD');
    $this->get(route('contacts.show', $acme))->assertOk()->assertSee('Corp. POC')->assertSee('Pat Morales')->assertSee('Locations')->assertSee('1200 Industrial Blvd')->assertSee('AR / Report')->assertSee('Paid · YTD');

    $this->post(route('contacts.update', $acme), ['segment' => 'industrial', 'owner_user_id' => $this->admin->id])->assertRedirect();
    expect($acme->fresh()->segment)->toBe('industrial')->and($acme->fresh()->owner->name)->toBe('Eric White');

    $this->post(route('contacts.locations.store', $acme), ['address' => '456 Woodruff Rd', 'label' => 'Building D'])->assertRedirect();
    expect($acme->serviceLocations()->count())->toBe(2);
    $this->actingAs($this->marcus)->get('/contacts')->assertForbidden();
});
