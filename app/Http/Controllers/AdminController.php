<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Admin\ChangeUserRole;
use App\Actions\Admin\CreateUser;
use App\Actions\Admin\DeactivateUser;
use App\Actions\Admin\ReactivateUser;
use App\Actions\Admin\UpdateTerminology;
use App\Actions\Reminders\UpdateReminderTypes;
use App\Actions\Team\SetQualification;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ReminderTypes;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\LaravelPasskeys\Models\Passkey;

/** Module 3.13 — Administration & Security. Routes are guarded by role:administrator. */
final class AdminController extends Controller
{
    // ---- Users ------------------------------------------------------------

    public function users(): View
    {
        $tenant = $this->tenant();

        return view('ops.admin.users', [
            'tenant' => $tenant,
            'users' => User::query()
                ->where('tenant_id', $tenant->id)
                ->withCount('passkeys')
                ->orderByDesc('is_active')->orderBy('name')
                ->get(),
            'seats' => $tenant->seatUsage(),
            'roles' => Role::cases(),
            'recent' => AuditLog::query()
                ->where('tenant_id', $tenant->id)
                ->where('auditable_type', User::class)
                ->latest()->take(10)->get(),
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        $result = CreateUser::run($this->tenant(), $data['name'], $data['email'], Role::from($data['role']));

        return back()
            ->with('status', "Created {$result['user']->name} as {$result['user']->role->label()}.")
            ->with('temporary_password', $result['temporary_password'])
            ->with('temporary_for', $result['user']->email);
    }

    public function deactivateUser(Request $request, User $user): RedirectResponse
    {
        $this->assertSameTenant($user);

        try {
            DeactivateUser::run($user, $this->actor($request));
        } catch (DomainException $e) {
            return back()->withErrors(['user' => $e->getMessage()]);
        }

        return back()->with('status', "{$user->name} deactivated. Their sessions were ended.");
    }

    public function reactivateUser(Request $request, User $user): RedirectResponse
    {
        $this->assertSameTenant($user);
        ReactivateUser::run($user, $this->actor($request));

        return back()->with('status', "{$user->name} reactivated.");
    }

    public function changeRole(Request $request, User $user): RedirectResponse
    {
        $this->assertSameTenant($user);
        $data = $request->validate(['role' => ['required', Rule::enum(Role::class)]]);

        try {
            ChangeUserRole::run($user, Role::from($data['role']), $this->actor($request));
        } catch (DomainException $e) {
            return back()->withErrors(['user' => $e->getMessage()]);
        }

        return back()->with('status', "{$user->name} is now {$user->fresh()?->role->label()}.");
    }

    // ---- Terminology ------------------------------------------------------

    public function terminology(): View
    {
        $tenant = $this->tenant();

        return view('ops.admin.terminology', [
            'tenant' => $tenant,
            'defaults' => config('batsons.terminology', []),
            'defaultAbbr' => config('batsons.abbreviations', []),
            'terminology' => $tenant->settings['terminology'] ?? [],
            'abbreviations' => $tenant->settings['abbreviations'] ?? [],
        ]);
    }

    public function updateTerminology(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'terminology' => ['array'],
            'terminology.*' => ['nullable', 'string', 'max:40'],
            'abbreviations' => ['array'],
            'abbreviations.*' => ['nullable', 'string', 'max:10'],
        ]);

        UpdateTerminology::run($this->tenant(), $data['terminology'] ?? [], $data['abbreviations'] ?? []);

        return back()->with('status', 'Terminology saved. Labels update across the app immediately.');
    }

    // ---- Qualification matrix (SOW §3.11) ---------------------------------

    public function qualifications(): View
    {
        return view('ops.admin.qualifications', [
            'employees' => Employee::query()->with('qualifications')->orderBy('name')->get(),
            'workTypes' => config('batsons.work_types', []),
            'levels' => config('batsons.qualification_levels', []),
        ]);
    }

    public function updateQualifications(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'q' => ['required', 'array'],
            'q.*' => ['array'],
            'q.*.*' => ['integer', Rule::in(array_keys(config('batsons.qualification_levels', [])))],
        ]);

        $actor = $this->actor($request);
        $employees = Employee::query()->whereIn('id', array_keys($data['q']))->with('qualifications')->get()->keyBy('id');
        $changed = 0;

        foreach ($data['q'] as $employeeId => $levels) {
            $employee = $employees->get((int) $employeeId);
            if (! $employee instanceof Employee) {
                continue;
            }
            foreach ($levels as $workType => $level) {
                try {
                    $before = $employee->levelFor((string) $workType);
                    SetQualification::run($employee, (string) $workType, (int) $level, $actor);
                    $changed += $before === (int) $level ? 0 : 1;
                } catch (DomainException) {
                    // unknown work type — only configured keys are rendered, so skip
                }
            }
        }

        return back()->with('status', $changed ? "Saved {$changed} qualification change(s)." : 'No changes.');
    }

    // ---- Appearance: icon set (design notes §4) ----------------------------

    public function updateAppearance(Request $request): RedirectResponse
    {
        $data = $request->validate(['icon_set' => ['required', Rule::in(array_keys(config('batsons.icon_sets', [])))]]);
        $tenant = $this->tenant();
        $settings = $tenant->settings ?? [];
        $settings['icon_set'] = $data['icon_set'];
        $tenant->settings = $settings;
        $tenant->save();
        app()->instance('currentTenant', $tenant);
        AuditLog::record('appearance.updated', $tenant, ['icon_set' => $data['icon_set'], 'by' => $this->actor($request)->id]);

        return back()->with('status', 'Icon set: '.config('batsons.icon_sets')[$data['icon_set']].'.');
    }

    // ---- Reminder types (SOW §3.10) ---------------------------------------

    public function reminderTypes(): View
    {
        return view('ops.admin.reminder-types', [
            'types' => ReminderTypes::all(),
            'workTypes' => config('batsons.work_types', []),
        ]);
    }

    public function updateReminderTypes(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'types' => ['required', 'array'],
            'types.*.key' => ['nullable', 'string', 'max:40'],
            'types.*.label' => ['nullable', 'string', 'max:80'],
            'types.*.customer_label' => ['nullable', 'string', 'max:80'],
            'types.*.interval_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'types.*.lead_days_min' => ['nullable', 'integer', 'min:0', 'max:365'],
            'types.*.lead_days_max' => ['nullable', 'integer', 'min:0', 'max:365'],
            'types.*.auto_message' => ['nullable', 'boolean'],
            'types.*.work_type' => ['nullable', 'string', Rule::in(array_keys(config('batsons.work_types', [])))],
        ]);

        try {
            UpdateReminderTypes::run($this->tenant(), $data['types'], $this->actor($request));
        } catch (DomainException $e) {
            return back()->withErrors(['types' => $e->getMessage()]);
        }

        return back()->with('status', 'Reminder types saved.');
    }

    // ---- Security overview ------------------------------------------------

    public function security(): View
    {
        $tenant = $this->tenant();
        $users = User::query()->where('tenant_id', $tenant->id)->where('is_active', true);

        return view('ops.admin.security', [
            'tenant' => $tenant,
            'overview' => Str::markdown(File::get(base_path('docs/SECURITY.md'))),
            'targets' => config('batsons.service_targets', []),
            'posture' => [
                'Active users' => (clone $users)->count(),
                'Administrators' => (clone $users)->where('role', Role::Administrator)->count(),
                'Users with a passkey' => Passkey::query()
                    ->whereIn('authenticatable_id', (clone $users)->select('id'))
                    ->distinct('authenticatable_id')->count('authenticatable_id'),
                'Audit events (30 days)' => AuditLog::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('created_at', '>=', now()->subDays(30))->count(),
            ],
        ]);
    }

    // ---- helpers ----------------------------------------------------------

    private function tenant(): Tenant
    {
        $tenant = Tenant::current();
        abort_if(! $tenant instanceof Tenant, 403, 'No tenant bound for this session.');

        return $tenant;
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function assertSameTenant(User $user): void
    {
        abort_unless($user->tenant_id === $this->tenant()->id, 404);
    }
}
