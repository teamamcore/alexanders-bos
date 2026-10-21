<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Assets\ReportAssetIssue;
use App\Actions\Assets\ResolveAssetIssue;
use App\Actions\Equipment\CheckOutEquipment;
use App\Actions\Equipment\LogEquipmentMaintenance;
use App\Actions\Equipment\LogEquipmentUsage;
use App\Actions\Equipment\ReturnEquipment;
use App\Models\AssetIssue;
use App\Models\Equipment;
use App\Models\User;
use App\Models\WorkOrder;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Module 3.7 (b) — Billable Equipment. */
final class EquipmentController extends Controller
{
    public function index(Request $request): View
    {
        $items = Equipment::query()->with('holder')->orderBy('name')->get();

        $rows = $items->map(fn (Equipment $e) => [
            'item' => $e,
            'maintenance' => $e->maintenanceStatus(),
            'revenue' => $e->revenue(),
            'cost' => $e->maintenanceCost(),
            'depreciation' => $e->depreciation(),
            'net' => $e->netContribution(),
            'open_issues' => $e->issues()->open()->count(),
        ]);

        return view('ops.equipment.index', [
            'rows' => $rows,
            'types' => config('batsons.equipment_types', []),
            'units' => config('batsons.equipment_billing_units', []),
            'canEdit' => ! $this->actor($request)->isField(),
            'stats' => [
                'Items' => $items->count(),
                'Checked out' => $items->where('status', 'checked_out')->count(),
                'Service due' => $rows->filter(fn ($r) => $r['maintenance']['due'])->count(),
                'Down' => $items->where('status', 'down')->count(),
            ],
        ]);
    }

    public function show(Request $request, Equipment $equipment): View
    {
        $equipment->load(['holder', 'usages.workOrder', 'maintenance', 'issues.reporter']);

        return view('ops.equipment.show', [
            'e' => $equipment,
            'maintenance' => $equipment->maintenanceStatus(),
            'revenue' => $equipment->revenue(),
            'cost' => $equipment->maintenanceCost(),
            'depreciation' => $equipment->depreciation(),
            'net' => $equipment->netContribution(),
            'types' => config('batsons.equipment_types', []),
            'units' => config('batsons.equipment_billing_units', []),
            'costTypes' => config('batsons.equipment_cost_types', []),
            'workOrders' => WorkOrder::query()->with('job')->latest()->take(50)->get(),
            'users' => User::query()->where('tenant_id', app('currentTenantId'))->where('is_active', true)->orderBy('name')->get(),
            'canEdit' => ! $this->actor($request)->isField(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateEquipment($request);
        $equipment = Equipment::create($data);

        return redirect()->route('equipment.show', $equipment)->with('status', "{$equipment->name} added to the register.");
    }

    public function update(Request $request, Equipment $equipment): RedirectResponse
    {
        $equipment->fill($this->validateEquipment($request))->save();

        return back()->with('status', 'Equipment record updated.');
    }

    public function usage(Request $request, Equipment $equipment): RedirectResponse
    {
        $data = $request->validate([
            'work_order_id' => ['required', 'integer', Rule::exists('work_orders', 'id')->where('tenant_id', app('currentTenantId'))],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'used_on' => ['nullable', 'date'],
        ]);
        $wo = WorkOrder::withoutGlobalScope('assignedToField')->findOrFail((int) $data['work_order_id']);

        try {
            $usage = LogEquipmentUsage::run($equipment, $wo, (float) $data['quantity'], $this->actor($request), $data['used_on'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()]);
        }

        return back()->with('status', "Billed {$usage->quantity} {$usage->unit}(s) on {$wo->number}: $".number_format((float) $usage->amount, 2).'.');
    }

    public function maintenance(Request $request, Equipment $equipment): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(config('batsons.equipment_cost_types', []))],
            'cost' => ['required', 'numeric', 'min:0'],
            'performed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            LogEquipmentMaintenance::run($equipment, $data['type'], (float) $data['cost'], $this->actor($request), $data['performed_at'] ?? null, $data['notes'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['type' => $e->getMessage()]);
        }

        return back()->with('status', ucfirst($data['type']).' logged.');
    }

    public function checkout(Request $request, Equipment $equipment): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('tenant_id', app('currentTenantId'))]]);

        try {
            CheckOutEquipment::run($equipment, User::findOrFail((int) $data['user_id']), $this->actor($request));
        } catch (DomainException $e) {
            return back()->withErrors(['user_id' => $e->getMessage()]);
        }

        return back()->with('status', "{$equipment->name} checked out.");
    }

    public function return(Request $request, Equipment $equipment): RedirectResponse
    {
        $data = $request->validate([
            'issue' => ['nullable', 'string', 'max:500'],
            'severity' => ['nullable', Rule::in(['minor', 'major'])],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        ReturnEquipment::run(
            $equipment,
            $this->actor($request),
            $data['issue'] ?? null,
            $data['severity'] ?? 'minor',
            $request->hasFile('photo') ? $request->file('photo')->store('issues', 'public') : null,
        );

        return back()->with('status', "{$equipment->name} returned.".(! empty($data['issue']) ? ' Issue reported.' : ''));
    }

    public function issue(Request $request, Equipment $equipment): RedirectResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:500'],
            'severity' => ['required', Rule::in(['minor', 'major'])],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        ReportAssetIssue::run($equipment, $this->actor($request), $data['description'], $data['severity'],
            $request->hasFile('photo') ? $request->file('photo')->store('issues', 'public') : null);

        return back()->with('status', 'Issue reported.'.($data['severity'] === 'major' ? ' Item marked down until a repair is logged.' : ''));
    }

    public function resolveIssue(Request $request, AssetIssue $issue): RedirectResponse
    {
        ResolveAssetIssue::run($issue, $this->actor($request));

        return back()->with('status', 'Issue resolved.');
    }

    /** @return array<string, mixed> */
    private function validateEquipment(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['nullable', 'string', Rule::in(config('batsons.equipment_types', []))],
            'billing_rate' => ['required', 'numeric', 'min:0'],
            'billing_unit' => ['required', Rule::in(array_keys(config('batsons.equipment_billing_units', [])))],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'purchased_at' => ['nullable', 'date'],
            'useful_life_years' => ['nullable', 'integer', 'min:1', 'max:50'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'every_hours' => ['nullable', 'integer', 'min:1'],
            'every_days' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $data['maintenance_schedule'] = array_filter([
            'every_hours' => isset($data['every_hours']) ? (int) $data['every_hours'] : null,
            'every_days' => isset($data['every_days']) ? (int) $data['every_days'] : null,
        ]) ?: null;
        unset($data['every_hours'], $data['every_days']);

        return $data;
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
