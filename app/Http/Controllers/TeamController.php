<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Team\AddCertification;
use App\Actions\Team\AddMilestone;
use App\Actions\Team\CreateEmployee;
use App\Actions\Team\UpdateBackgroundCheck;
use App\Models\Certification;
use App\Models\Employee;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Module 3.11 — Team Records & Qualifications.
 * Office + administrators manage profiles; field users see only their own.
 * Background check edits and the qualification matrix are administrator-only.
 */
final class TeamController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $this->actor($request);

        if ($user->isField()) {
            $own = Employee::where('user_id', $user->id)->first();
            abort_unless($own instanceof Employee, 403, 'No team profile is linked to your account yet.');

            return redirect()->route('team.show', $own);
        }

        $employees = Employee::query()
            ->with(['user', 'certifications', 'qualifications'])
            ->orderBy('name')->get();

        $alertDays = (int) config('batsons.certification_alert_days', 60);

        return view('ops.team.index', [
            'employees' => $employees,
            'expiring' => Certification::query()->with('employee')->expiringWithin($alertDays)->orderBy('expires_at')->get(),
            'expired' => Certification::query()->with('employee')->expired()->orderByDesc('expires_at')->get(),
            'alertDays' => $alertDays,
            'workTypes' => config('batsons.work_types', []),
            'unlinkedUsers' => User::query()
                ->where('tenant_id', app('currentTenantId'))
                ->where('is_active', true)
                ->whereNotIn('id', $employees->pluck('user_id')->filter())
                ->orderBy('name')->get(),
            'stats' => [
                'Team members' => $employees->count(),
                'Expiring in '.$alertDays.'d' => Certification::query()->expiringWithin($alertDays)->count(),
                'Expired' => Certification::query()->expired()->count(),
                'Background checks open' => $employees->filter(fn (Employee $e) => $e->background_check_status !== 'cleared')->count(),
            ],
            'canEdit' => true, // field users were redirected above
        ]);
    }

    public function show(Request $request, Employee $employee): View
    {
        $user = $this->actor($request);
        abort_if($user->isField() && $employee->user_id !== $user->id, 403);

        $employee->load(['user', 'certifications' => fn ($q) => $q->orderBy('expires_at'), 'qualifications', 'milestones']);

        return view('ops.team.show', [
            'employee' => $employee,
            'workTypes' => config('batsons.work_types', []),
            'levels' => config('batsons.qualification_levels', []),
            'bgStatuses' => config('batsons.background_check_statuses', []),
            'canEdit' => ! $user->isField(),
            'isAdmin' => $user->isAdministrator(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:80'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', app('currentTenantId'))],
            'hired_at' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $linked = ! empty($data['user_id']) ? User::find((int) $data['user_id']) : null;
        $employee = CreateEmployee::run($data['name'], $data['title'] ?? null, $linked, $data['hired_at'] ?? null, $data['phone'] ?? null);

        return redirect()->route('team.show', $employee)->with('status', "Profile created for {$employee->name}.");
    }

    public function storeCertification(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['certification', 'license'])],
            'number' => ['nullable', 'string', 'max:80'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
        ]);

        AddCertification::run($employee, $data['name'], $data['type'], $data['number'] ?? null, $data['issued_at'] ?? null, $data['expires_at'] ?? null);

        return back()->with('status', "Added {$data['name']}.");
    }

    public function storeMilestone(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'occurred_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        AddMilestone::run($employee, $data['title'], $data['occurred_at'], $data['notes'] ?? null);

        return back()->with('status', 'Milestone added.');
    }

    public function updateBackgroundCheck(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(config('batsons.background_check_statuses', []))],
            'date' => ['nullable', 'date'],
        ]);

        try {
            UpdateBackgroundCheck::run($employee, $data['status'], $data['date'] ?? null, $this->actor($request));
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', 'Background check updated (status and date only).');
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
