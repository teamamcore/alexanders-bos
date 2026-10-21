<?php

declare(strict_types=1);

use App\Actions\Team\AddCertification;
use App\Actions\Team\CreateEmployee;
use App\Actions\Team\SetQualification;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\SchedulingIndicators;
use Database\Seeders\BatsonsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

// Module 3.11 — Team Records & Qualifications.

beforeEach(function () {
    $this->seed(BatsonsSeeder::class);
    $this->tenant = Tenant::firstOrFail();
    $this->admin = User::where('email', 'eric@batsonsplumbing.com')->firstOrFail();
    $this->office = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => Role::Office]);
});

it('renders the team roster and a profile', function () {
    $this->actingAs($this->admin);
    $this->get('/team')->assertOk()->assertSee('Roster')->assertSee('Marcus Reyes');

    $marcus = Employee::where('name', 'Marcus Reyes')->firstOrFail();
    $this->get(route('team.show', $marcus))->assertOk()
        ->assertSee('Certifications &amp; licences', false)
        ->assertSee('Qualification values')
        ->assertSee('Background check');
});

it('creates an employee profile linked to a user and records the hire milestone', function () {
    $this->actingAs($this->office);
    $u = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->post(route('team.store'), ['name' => 'New Hire', 'title' => 'Apprentice', 'user_id' => $u->id, 'hired_at' => '2026-10-01'])
        ->assertRedirect();

    $e = Employee::where('name', 'New Hire')->firstOrFail();
    expect($e->user_id)->toBe($u->id)
        ->and($e->milestones()->count())->toBe(1)
        ->and($e->milestones()->first()->title)->toBe('Hired');
});

it('raises expiration alerts for certifications and licences', function () {
    $this->actingAs($this->admin);
    $e = CreateEmployee::run('Alert Person', 'Tech');
    AddCertification::run($e, 'Med Gas Cert', 'certification', null, null, today()->addDays(20)->toDateString());
    AddCertification::run($e, 'Old Licence', 'license', 'L-1', null, today()->subDay()->toDateString());
    AddCertification::run($e, 'Forever', 'certification');

    expect($e->fresh()->load('certifications')->certificationSummary())->toBe(['expired' => 1, 'expiring' => 1, 'valid' => 1])
        ->and($e->fresh()->load('certifications')->certificationState())->toBe('expired');

    $this->get('/team')->assertOk()
        ->assertSee('Certification &amp; licence alerts', false)
        ->assertSee('Med Gas Cert')->assertSee('Old Licence');
});

it('adds a certification through the profile page', function () {
    $this->actingAs($this->office);
    $e = Employee::where('name', 'Marcus Reyes')->firstOrFail();

    $this->post(route('team.certifications.store', $e), ['name' => 'OSHA 30', 'type' => 'certification', 'expires_at' => today()->addYear()->toDateString()])
        ->assertRedirect();

    expect($e->certifications()->where('name', 'OSHA 30')->exists())->toBeTrue();
});

it('restricts background-check updates to administrators and audits them', function () {
    $e = Employee::where('name', 'Marcus Reyes')->firstOrFail();

    $this->actingAs($this->office)->post(route('team.background', $e), ['status' => 'cleared', 'date' => '2026-09-01'])->assertForbidden();

    $this->actingAs($this->admin)->post(route('team.background', $e), ['status' => 'cleared', 'date' => '2026-09-01'])->assertRedirect();
    expect($e->fresh()->background_check_status)->toBe('cleared')
        ->and($e->fresh()->background_check_date->toDateString())->toBe('2026-09-01')
        ->and(AuditLog::where('action', 'background_check.updated')->where('auditable_id', $e->id)->exists())->toBeTrue();

    // No document columns exist anywhere on the employee record (MSA Ex. B.2).
    expect(array_keys($e->fresh()->getAttributes()))->not->toContain('document', 'document_path', 'file');
});

it('lets administrators assign qualification values in the matrix', function () {
    $this->actingAs($this->office)->get('/admin/qualifications')->assertForbidden();

    $this->actingAs($this->admin);
    $this->get('/admin/qualifications')->assertOk()->assertSee('Qualification values by work type');

    $dana = Employee::where('name', 'Dana Whitfield')->firstOrFail();
    $this->post(route('admin.qualifications.update'), ['q' => [$dana->id => ['sewer' => 3, 'backflow_testing' => 0]]])->assertRedirect();

    expect($dana->fresh()->load('qualifications')->levelFor('sewer'))->toBe(3)
        ->and($dana->fresh()->load('qualifications')->levelFor('backflow_testing'))->toBe(0)
        ->and(AuditLog::where('action', 'qualification.set')->where('auditable_id', $dana->id)->exists())->toBeTrue();

    expect(fn () => SetQualification::run($dana, 'juggling', 1, $this->admin))->toThrow(DomainException::class);
});

it('feeds qualification and certification indicators into the dispatch board', function () {
    $this->actingAs($this->admin);
    $users = User::where('tenant_id', $this->tenant->id)->get();
    $marcus = $users->firstWhere('email', 'marcus@batsonsplumbing.com');
    $dana = $users->firstWhere('email', 'dana@batsonsplumbing.com');

    $ind = SchedulingIndicators::forUsers($users, 'backflow_testing');
    expect($ind[$marcus->id]['level_label'])->toBe('Lead')
        ->and($ind[$marcus->id]['expiring'])->toBe(1)
        ->and($ind[$dana->id]['expired'])->toBe(1)
        ->and(SchedulingIndicators::label($ind[$marcus->id], 'backflow_testing'))->toContain('Backflow testing: Lead', 'expiring')
        ->and(SchedulingIndicators::label($ind[$dana->id], 'backflow_testing'))->toContain('expired');

    $wo = WorkOrder::withoutGlobalScopes()->where('number', 'WO-1001')->firstOrFail();
    Volt::test('pages.schedule.index')
        ->call('openSchedule', $wo->id)
        ->assertSee('Backflow testing: Lead')
        ->assertSee('expired');
});

it('limits field users to their own profile', function () {
    $marcus = User::where('email', 'marcus@batsonsplumbing.com')->firstOrFail();
    $own = Employee::where('user_id', $marcus->id)->firstOrFail();
    $other = Employee::where('name', 'Dana Whitfield')->firstOrFail();

    $this->actingAs($marcus);
    $this->get('/team')->assertRedirect(route('team.show', $own));
    $this->get(route('team.show', $own))->assertOk()->assertDontSee('Assign →');
    $this->get(route('team.show', $other))->assertForbidden();
    $this->post(route('team.store'), ['name' => 'Nope'])->assertForbidden();
});

it('keeps team records tenant-scoped', function () {
    $other = Tenant::create(['name' => 'Other Co']);
    app()->instance('currentTenantId', $other->id);
    $stranger = Employee::create(['name' => 'Stranger']);

    $this->actingAs($this->admin)->get(route('team.show', $stranger))->assertNotFound();
});
