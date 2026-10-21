<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\BatsonsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

// In-app build checklist mirroring the Notion Build Timeline (config/build.php).

beforeEach(function () {
    $this->seed(BatsonsSeeder::class);
    $this->admin = User::where('email', 'eric@batsonsplumbing.com')->firstOrFail();
});

it('shows every timeline item with its status and links done modules to their screens', function () {
    $this->actingAs($this->admin);
    $r = $this->get('/build')->assertOk()->assertSee('Build checklist');

    foreach (config('build.items') as $item) {
        $r->assertSee(e($item['name']), false);
    }
    $r->assertSee('Open Team')->assertSee('Open Dispatch board')->assertSee('Open Admin');
});

it('hides admin-only links from office users but still shows the checklist', function () {
    $office = User::factory()->create(['tenant_id' => Tenant::first()->id, 'role' => Role::Office]);
    $this->actingAs($office)->get('/build')->assertOk()->assertSee('Open Team')->assertDontSee('Open Admin');
});

it('every linked route in the checklist exists', function () {
    foreach (config('build.items') as $item) {
        if (! empty($item['link'])) {
            expect(Route::has($item['link']['route']))->toBeTrue($item['link']['route']);
        }
    }
});
