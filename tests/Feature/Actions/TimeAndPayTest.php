<?php

declare(strict_types=1);

use App\Actions\Time\AdjustTimeEntry;
use App\Actions\Time\CalculatePay;
use App\Actions\Time\ClockIn;
use App\Actions\Time\ClockOut;
use App\Actions\Time\PayForTimeEntry;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function timeTech(): User
{
    $tenant = Tenant::create(['name' => 'Batsons']);
    app()->instance('currentTenantId', $tenant->id);

    return User::create([
        'tenant_id' => $tenant->id, 'name' => 'Tech',
        'email' => 'tech@example.com', 'password' => 'secret', 'role' => Role::Field,
    ]);
}

it('clocks in and out and records duration', function () {
    $tech = timeTech();

    $entry = ClockIn::run($tech, null, 'on_site', null, Carbon::parse('2026-10-19 08:00'));
    expect($entry->started_at)->not->toBeNull()
        ->and($entry->ended_at)->toBeNull()
        ->and($entry->billable)->toBeTrue();

    $entry = ClockOut::run($entry, Carbon::parse('2026-10-19 12:30'));
    expect($entry->ended_at->toDateTimeString())->toBe('2026-10-19 12:30:00');
});

it('treats shop time as non-billable by default', function () {
    $tech = timeTech();
    $entry = ClockIn::run($tech, null, 'shop');
    expect($entry->billable)->toBeFalse();
});

it('records who/prior-value/when on an adjustment', function () {
    $tech = timeTech();
    $entry = ClockIn::run($tech, null, 'on_site', null, Carbon::parse('2026-10-19 08:00'));
    ClockOut::run($entry, Carbon::parse('2026-10-19 12:00'));

    $adjusted = AdjustTimeEntry::run($entry->refresh(), [
        'ended_at' => Carbon::parse('2026-10-19 13:00'),
    ], adjustedBy: $tech->id);

    expect($adjusted->ended_at->toDateTimeString())->toBe('2026-10-19 13:00:00')
        ->and($adjusted->adjusted_by)->toBe($tech->id)
        ->and($adjusted->adjusted_at)->not->toBeNull()
        ->and($adjusted->prior_value)->toMatchArray(['ended_at' => '2026-10-19 12:00:00']);
});

it('applies the after-hours and weekend pay multipliers', function () {
    // Mon–Thu regular hours = 1.0
    expect(CalculatePay::run(Carbon::parse('2026-10-19 09:00')))  // Monday 9am
        ->toMatchArray(['multiplier' => 1.0, 'classification' => 'regular']);

    // Mon–Thu after hours = 1.5
    expect(CalculatePay::run(Carbon::parse('2026-10-19 18:30')))  // Monday 6:30pm
        ->toMatchArray(['multiplier' => 1.5, 'classification' => 'after_hours']);
    expect(CalculatePay::run(Carbon::parse('2026-10-22 05:00')))  // Thursday 5am
        ->toMatchArray(['multiplier' => 1.5, 'classification' => 'after_hours']);

    // Friday before noon = normal weekday
    expect(CalculatePay::run(Carbon::parse('2026-10-23 10:00')))  // Friday 10am
        ->toMatchArray(['multiplier' => 1.0, 'classification' => 'regular']);

    // Friday noon onward = 2.0
    expect(CalculatePay::run(Carbon::parse('2026-10-23 12:00')))  // Friday noon
        ->toMatchArray(['multiplier' => 2.0, 'classification' => 'weekend']);

    // Weekend = 2.0
    expect(CalculatePay::run(Carbon::parse('2026-10-24 09:00')))  // Saturday
        ->toMatchArray(['multiplier' => 2.0, 'classification' => 'weekend']);
});

it('computes payable hours for a closed entry', function () {
    $tech = timeTech();
    // Saturday 4 hours at 2x = 8 pay-hours
    $entry = ClockIn::run($tech, null, 'on_site', null, Carbon::parse('2026-10-24 08:00'));
    ClockOut::run($entry, Carbon::parse('2026-10-24 12:00'));

    $pay = PayForTimeEntry::run($entry->refresh());
    expect($pay['hours'])->toBe(4.0)
        ->and($pay['multiplier'])->toBe(2.0)
        ->and($pay['pay_hours'])->toBe(8.0);
});
