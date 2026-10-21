<?php

declare(strict_types=1);

use App\Actions\Admin\ChangeUserRole;
use App\Actions\Admin\CreateUser;
use App\Actions\Admin\DeactivateUser;
use App\Actions\Admin\UpdateTerminology;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\BatsonsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

// Module 3.13 — Administration & Security.

beforeEach(function () {
    $this->seed(BatsonsSeeder::class);
    $this->tenant = Tenant::firstOrFail();
    $this->admin = User::where('email', 'eric@batsonsplumbing.com')->firstOrFail();
    $this->admin->forceFill(['email_verified_at' => now()])->save();
});

function officeUser(Tenant $tenant): User
{
    return User::factory()->create(['tenant_id' => $tenant->id, 'role' => Role::Office]);
}

it('renders the admin pages for administrators', function () {
    $this->actingAs($this->admin);
    $this->get('/admin/users')->assertOk()->assertSee('Add a user')->assertSee('included seats');
    $this->get('/admin/terminology')->assertOk()->assertSee('Your term');
    $this->get('/admin/security')->assertOk()->assertSee('Security Overview')->assertSee('Passkeys store only a public key');
});

it('forbids office and field users from administration', function () {
    $this->actingAs(officeUser($this->tenant));
    $this->get('/admin/users')->assertForbidden();
    $this->post('/admin/terminology', ['terminology' => ['job' => 'X']])->assertForbidden();
});

it('creates a user with a role and a one-time temporary password', function () {
    $this->actingAs($this->admin);

    $this->post(route('admin.users.store'), ['name' => 'Pat Field', 'email' => 'pat@batsonsplumbing.com', 'role' => 'field'])
        ->assertRedirect()
        ->assertSessionHas('temporary_password')
        ->assertSessionHas('temporary_for', 'pat@batsonsplumbing.com');

    $pat = User::where('email', 'pat@batsonsplumbing.com')->firstOrFail();
    expect($pat->tenant_id)->toBe($this->tenant->id)
        ->and($pat->role)->toBe(Role::Field)
        ->and($pat->is_active)->toBeTrue()
        ->and($pat->email_verified_at)->not->toBeNull();

    expect(AuditLog::where('action', 'user.created')->where('auditable_id', $pat->id)->exists())->toBeTrue();
    $this->get('/admin/users')->assertSee('Pat Field');
});

it('reports seat usage against the included seats and blocks of five', function () {
    $this->tenant->update(['settings' => array_merge($this->tenant->settings, ['included_users' => 2])]);
    foreach (range(1, 3) as $i) {
        CreateUser::run($this->tenant, "User $i", "u$i@example.com", Role::Field);
    }
    // seeder users + 3 new = active count; included 2 → the rest is additional
    $seats = $this->tenant->fresh()->seatUsage();
    expect($seats['included'])->toBe(2)
        ->and($seats['additional'])->toBe($seats['active'] - 2)
        ->and($seats['blocks'])->toBe((int) ceil(($seats['active'] - 2) / 5))
        ->and($seats['block_size'])->toBe(5);
});

it('blocks sign-in for deactivated users and ends their live session', function () {
    $user = officeUser($this->tenant);
    DeactivateUser::run($user, $this->admin);

    // Password sign-in is refused with a clear message.
    Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password')
        ->call('login')
        ->assertHasErrors(['form.email']);
    $this->assertGuest();

    // An already-signed-in session is dropped on the next request.
    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('lets a reactivated user sign in again', function () {
    $user = officeUser($this->tenant);
    DeactivateUser::run($user, $this->admin);

    $this->actingAs($this->admin)->post(route('admin.users.reactivate', $user))->assertRedirect();

    Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password')
        ->call('login')
        ->assertHasNoErrors();
    $this->assertAuthenticatedAs($user->fresh());
});

it('refuses to deactivate yourself or the last active administrator', function () {
    expect(fn () => DeactivateUser::run($this->admin, $this->admin))->toThrow(DomainException::class);

    $second = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => Role::Administrator]);
    expect(fn () => DeactivateUser::run($this->admin, $second))->not->toThrow(DomainException::class);
    // now $second is the last admin
    expect(fn () => ChangeUserRole::run($second, Role::Office, $second))->toThrow(DomainException::class);

    // Through the UI the error surfaces as a flash, not a 500.
    $this->actingAs($second)->post(route('admin.users.deactivate', $second))
        ->assertRedirect()->assertSessionHasErrors('user');
});

it('scopes user administration to the admin\'s own tenant', function () {
    $other = Tenant::create(['name' => 'Other Co']);
    $stranger = User::factory()->create(['tenant_id' => $other->id, 'role' => Role::Field]);

    $this->actingAs($this->admin)
        ->post(route('admin.users.deactivate', $stranger))->assertNotFound();
    expect($stranger->fresh()->is_active)->toBeTrue();
});

it('applies configurable terminology across the UI', function () {
    $this->actingAs($this->admin);

    $this->post(route('admin.terminology.update'), [
        'terminology' => ['job' => 'Project', 'work_order' => 'Ticket', 'proposal' => '   '],
        'abbreviations' => ['work_order' => 'TKT'],
    ])->assertRedirect();

    $this->get('/dashboard')->assertSee('Projects')->assertDontSee('>Jobs<', false);
    $this->get('/admin/terminology')->assertSee('TKT-1001-1');

    expect(term('work_order'))->toBe('Ticket')
        ->and(abbr('work_order'))->toBe('TKT')
        ->and(term('proposal'))->toBe('Proposal') // blank → default
        ->and(abbr('job'))->toBe('J');
});

it('falls back to platform defaults when no tenant is bound', function () {
    expect(term('work_order', true))->toBe('Work Orders')
        ->and(abbr('invoice'))->toBe('INV');
});

it('records terminology changes in the audit trail', function () {
    UpdateTerminology::run($this->tenant, ['customer' => 'Client'], []);
    $log = AuditLog::where('action', 'terminology.updated')->latest()->firstOrFail();
    expect($log->changes['after']['terminology'])->toBe(['customer' => 'Client']);
});

it('offers passkey sign-in and passkey management', function () {
    $this->get('/login')->assertOk()->assertSee('passkey');
    $this->get(route('passkeys.authentication_options'))->assertOk()->assertJsonStructure(['challenge', 'rpId']);

    $this->actingAs($this->admin)->get('/profile')->assertOk()->assertSee('Passkeys');
    expect($this->admin->passkeys()->count())->toBe(0);
});
