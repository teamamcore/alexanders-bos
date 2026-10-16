<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\BatsonsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BatsonsSeeder::class);
    $this->admin = User::where('email', 'eric@batsonsplumbing.com')->firstOrFail();
    $this->admin->forceFill(['email_verified_at' => now()])->save();
    $this->actingAs($this->admin);
});

it('renders the ops pages', function () {
    $this->get('/dashboard')->assertOk()->assertSee('Operations Dashboard');
    $this->get('/invoices')->assertOk()->assertSee('Invoicing');
    $this->get('/time')->assertOk()->assertSee('Time');
});

it('generates an invoice from a work order through the UI', function () {
    $wo = WorkOrder::withoutGlobalScopes()->firstOrFail();

    $this->post(route('invoices.generate', $wo), ['total' => 999.50])
        ->assertRedirect();

    $inv = Invoice::withoutGlobalScopes()->firstOrFail();
    expect($inv->number)->toStartWith('INV-')
        ->and((float) $inv->total)->toBe(999.50);

    $this->get('/invoices')->assertSee($inv->number);
});

it('clocks in and out through the UI', function () {
    $this->post(route('time.clockin'), ['category' => 'on_site'])->assertRedirect();
    $open = TimeEntry::withoutGlobalScopes()->whereNull('ended_at')->firstOrFail();
    $this->post(route('time.clockout', $open))->assertRedirect();
    expect($open->fresh()->ended_at)->not->toBeNull();
});
