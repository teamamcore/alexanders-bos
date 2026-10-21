<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Assets\ReportAssetIssue;
use App\Actions\Assets\ResolveAssetIssue;
use App\Actions\Fleet\LogVehicleCost;
use App\Actions\Fleet\RecordMileage;
use App\Models\AssetIssue;
use App\Models\Crew;
use App\Models\User;
use App\Models\Vehicle;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Module 3.7 (c) — Fleet. */
final class FleetController extends Controller
{
    public function index(Request $request): View
    {
        $vehicles = Vehicle::query()->with(['driver', 'crew'])->orderBy('name')->get();

        $rows = $vehicles->map(fn (Vehicle $v) => [
            'v' => $v,
            'service_due' => $v->serviceDue(),
            'renewals' => $v->renewalsDue(),
            'stale' => $v->mileageStale(),
            'cost' => $v->costTotal(),
            'tco' => $v->totalCostOfOwnership(),
            'depreciation' => $v->depreciation(),
            'open_issues' => $v->issues()->open()->count(),
        ]);

        return view('ops.fleet.index', [
            'rows' => $rows,
            'users' => User::query()->where('tenant_id', app('currentTenantId'))->where('is_active', true)->orderBy('name')->get(),
            'crews' => Crew::query()->orderBy('name')->get(),
            'serviceItems' => config('batsons.vehicle_service_items', []),
            'canEdit' => ! $this->actor($request)->isField(),
            'stats' => [
                'Vehicles' => $vehicles->count(),
                'Service due' => $rows->filter(fn ($r) => $r['service_due'] !== [])->count(),
                'Renewals due' => $rows->filter(fn ($r) => $r['renewals'] !== [])->count(),
                'Mileage overdue' => $rows->filter(fn ($r) => $r['stale'])->count(),
            ],
        ]);
    }

    public function show(Request $request, Vehicle $vehicle): View
    {
        $vehicle->load(['driver', 'crew', 'maintenance', 'readings', 'issues.reporter']);

        return view('ops.fleet.show', [
            'v' => $vehicle,
            'serviceDue' => $vehicle->serviceDue(),
            'renewals' => $vehicle->renewalsDue(),
            'stale' => $vehicle->mileageStale(),
            'costByType' => $vehicle->costByType(),
            'tco' => $vehicle->totalCostOfOwnership(),
            'depreciation' => $vehicle->depreciation(),
            'costTypes' => config('batsons.vehicle_cost_types', []),
            'serviceItems' => config('batsons.vehicle_service_items', []),
            'users' => User::query()->where('tenant_id', app('currentTenantId'))->where('is_active', true)->orderBy('name')->get(),
            'crews' => Crew::query()->orderBy('name')->get(),
            'canEdit' => ! $this->actor($request)->isField(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $vehicle = Vehicle::create($this->validateVehicle($request));

        return redirect()->route('fleet.show', $vehicle)->with('status', $vehicle->label().' added to the fleet.');
    }

    public function update(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $vehicle->fill($this->validateVehicle($request))->save();

        return back()->with('status', 'Vehicle record updated.');
    }

    public function mileage(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $data = $request->validate(['odometer' => ['required', 'integer', 'min:0'], 'recorded_at' => ['nullable', 'date']]);

        try {
            RecordMileage::run($vehicle, (int) $data['odometer'], $this->actor($request), $data['recorded_at'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['odometer' => $e->getMessage()]);
        }

        return back()->with('status', 'Mileage recorded: '.number_format((int) $data['odometer']).' mi.');
    }

    public function cost(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(config('batsons.vehicle_cost_types', []))],
            'cost' => ['required', 'numeric', 'min:0'],
            'performed_at' => ['nullable', 'date'],
            'odometer' => ['nullable', 'integer', 'min:0'],
            'service_item' => ['nullable', 'string', Rule::in(array_keys(config('batsons.vehicle_service_items', [])))],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            LogVehicleCost::run($vehicle, $data['type'], (float) $data['cost'], $this->actor($request),
                $data['performed_at'] ?? null, isset($data['odometer']) ? (int) $data['odometer'] : null, $data['service_item'] ?? null, $data['notes'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['cost' => $e->getMessage()]);
        }

        return back()->with('status', ucfirst($data['type']).' logged.');
    }

    public function issue(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:500'],
            'severity' => ['required', Rule::in(['minor', 'major'])],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        ReportAssetIssue::run($vehicle, $this->actor($request), $data['description'], $data['severity'],
            $request->hasFile('photo') ? $request->file('photo')->store('issues', 'public') : null);

        return back()->with('status', 'Issue reported.'.($data['severity'] === 'major' ? ' Vehicle marked down until a repair is logged.' : ''));
    }

    public function resolveIssue(Request $request, AssetIssue $issue): RedirectResponse
    {
        ResolveAssetIssue::run($issue, $this->actor($request));

        return back()->with('status', 'Issue resolved.');
    }

    /** @return array<string, mixed> */
    private function validateVehicle(Request $request): array
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:60'],
            'year' => ['nullable', 'integer', 'min:1980', 'max:2100'],
            'make' => ['nullable', 'string', 'max:60'],
            'model' => ['nullable', 'string', 'max:60'],
            'vin' => ['nullable', 'string', 'max:20'],
            'plate' => ['nullable', 'string', 'max:20'],
            'assigned_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', app('currentTenantId'))],
            'assigned_crew_id' => ['nullable', 'integer', Rule::exists('crews', 'id')->where('tenant_id', app('currentTenantId'))],
            'mileage' => ['nullable', 'integer', 'min:0'],
            'registration_due' => ['nullable', 'date'],
            'inspection_due' => ['nullable', 'date'],
            'insurance_due' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'purchased_at' => ['nullable', 'date'],
            'useful_life_years' => ['nullable', 'integer', 'min:1', 'max:50'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'intervals' => ['nullable', 'array'],
            'intervals.*.every_miles' => ['nullable', 'integer', 'min:1'],
            'intervals.*.every_months' => ['nullable', 'integer', 'min:1'],
        ]);

        $intervals = [];
        foreach ($data['intervals'] ?? [] as $name => $cfg) {
            $miles = isset($cfg['every_miles']) && $cfg['every_miles'] !== '' ? (int) $cfg['every_miles'] : null;
            $months = isset($cfg['every_months']) && $cfg['every_months'] !== '' ? (int) $cfg['every_months'] : null;
            if ($miles || $months) {
                $intervals[] = ['name' => (string) $name, 'every_miles' => $miles, 'every_months' => $months];
            }
        }
        unset($data['intervals']);
        if ($request->has('intervals')) {
            $data['service_intervals'] = $intervals ?: null;
        }
        $data['mileage'] ??= 0;

        return $data;
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
