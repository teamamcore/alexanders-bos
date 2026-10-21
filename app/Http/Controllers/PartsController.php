<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Inventory\AdjustStock;
use App\Actions\Inventory\CheckDelivery;
use App\Actions\Inventory\ImportSupplierPriceList;
use App\Models\Job;
use App\Models\Part;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WorkOrder;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Module 3.7 (a) — Parts & Inventory. */
final class PartsController extends Controller
{
    public function index(Request $request): View
    {
        $q = (string) $request->query('q', '');
        $parts = Part::query()->with('supplier')->search($q)->orderBy('name')->get();

        $glossary = Part::query()->whereNotNull('nicknames')->orderBy('name')->get()
            ->flatMap(fn (Part $p) => collect($p->nicknames ?? [])->map(fn ($n) => ['nickname' => $n, 'part' => $p]))
            ->sortBy('nickname')->values();

        return view('ops.parts.index', [
            'q' => $q,
            'parts' => $parts,
            'lowStock' => Part::query()->lowStock()->orderBy('name')->get(),
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'glossary' => $glossary,
            'jobs' => Job::query()->latest()->take(50)->get(),
            'canEdit' => ! $this->actor($request)->isField(),
            'stats' => [
                'Catalog items' => Part::query()->count(),
                'Low stock' => Part::query()->lowStock()->count(),
                'One-off items' => Part::query()->where('is_one_off', true)->count(),
                'Suppliers' => Supplier::query()->count(),
            ],
        ]);
    }

    public function show(Request $request, Part $part): View
    {
        $part->load(['supplier', 'job', 'stockAdjustments', 'deliveryChecks.workOrder', 'history']);

        return view('ops.parts.show', [
            'part' => $part,
            'reasons' => config('batsons.stock_adjustment_reasons', []),
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'workOrders' => WorkOrder::query()->with('job')->latest()->take(50)->get(),
            'canEdit' => ! $this->actor($request)->isField(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'sku' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:80'],
            'nicknames' => ['nullable', 'string', 'max:300'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('tenant_id', app('currentTenantId'))],
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:20'],
            'stock_qty' => ['nullable', 'integer', 'min:0'],
            'reorder_threshold' => ['nullable', 'integer', 'min:0'],
            'is_one_off' => ['nullable', 'boolean'],
            'job_id' => ['nullable', 'integer', Rule::exists('jobs_records', 'id')->where('tenant_id', app('currentTenantId'))],
            'notes' => ['nullable', 'string', 'max:500'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $photoPath = $request->hasFile('photo') ? $request->file('photo')->store('parts', 'public') : null;

        $part = Part::create([
            'name' => $data['name'],
            'sku' => $data['sku'] ?? null,
            'category' => $data['category'] ?? null,
            'nicknames' => collect(explode(',', (string) ($data['nicknames'] ?? '')))->map(fn ($n) => trim($n))->filter()->values()->all() ?: null,
            'supplier_id' => $data['supplier_id'] ?? null,
            'price' => $data['price'],
            'cost' => $data['cost'] ?? null,
            'unit' => ($data['unit'] ?? null) ?: 'each',
            'stock_qty' => (int) ($data['stock_qty'] ?? 0),
            'reorder_threshold' => $data['reorder_threshold'] ?? null,
            'is_one_off' => (bool) ($data['is_one_off'] ?? false),
            'job_id' => $data['job_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'photo_path' => $photoPath,
        ]);

        return redirect()->route('parts.show', $part)->with('status', ($part->is_one_off ? 'One-off item' : 'Part')." {$part->name} added.");
    }

    public function updatePrice(Request $request, Part $part): RedirectResponse
    {
        $data = $request->validate(['price' => ['required', 'numeric', 'min:0']]);
        $part->price = $data['price'];
        $part->save(); // Auditable → item history

        return back()->with('status', "Price for {$part->name} is now $".number_format((float) $data['price'], 2).'.');
    }

    public function adjust(Request $request, Part $part): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', Rule::in(array_keys(config('batsons.stock_adjustment_reasons', [])))],
            'work_order_id' => ['nullable', 'integer', Rule::exists('work_orders', 'id')->where('tenant_id', app('currentTenantId'))],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);
        $wo = ! empty($data['work_order_id']) ? WorkOrder::withoutGlobalScope('assignedToField')->find((int) $data['work_order_id']) : null;

        try {
            AdjustStock::run($part, (int) $data['quantity'], $data['reason'], $this->actor($request), $wo, $data['notes'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()]);
        }

        return back()->with('status', 'Stock adjusted. On hand: '.$part->fresh()?->stock_qty.'.');
    }

    public function delivery(Request $request, Part $part): RedirectResponse
    {
        $data = $request->validate([
            'qty_ordered' => ['required', 'integer', 'min:0'],
            'qty_received' => ['required', 'integer', 'min:0'],
            'qty_damaged' => ['nullable', 'integer', 'min:0'],
            'reference' => ['nullable', 'string', 'max:80'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('tenant_id', app('currentTenantId'))],
            'work_order_id' => ['nullable', 'integer', Rule::exists('work_orders', 'id')->where('tenant_id', app('currentTenantId'))],
            'notes' => ['nullable', 'string', 'max:500'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        try {
            $check = CheckDelivery::run(
                $part,
                (int) $data['qty_ordered'],
                (int) $data['qty_received'],
                (int) ($data['qty_damaged'] ?? 0),
                $this->actor($request),
                ! empty($data['work_order_id']) ? WorkOrder::withoutGlobalScope('assignedToField')->find((int) $data['work_order_id']) : null,
                ! empty($data['supplier_id']) ? Supplier::find((int) $data['supplier_id']) : null,
                $data['reference'] ?? null,
                $data['notes'] ?? null,
                $request->hasFile('photo') ? $request->file('photo')->store('deliveries', 'public') : null,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['qty_received' => $e->getMessage()]);
        }

        $msg = 'Delivery checked.';
        if ($check->shortage()) {
            $msg .= " Short {$check->shortage()}.";
        }
        if ($check->qty_damaged) {
            $msg .= " Damaged {$check->qty_damaged}.";
        }

        return back()->with('status', $msg);
    }

    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('tenant_id', app('currentTenantId'))],
            'supplier_name' => ['nullable', 'string', 'max:120', 'required_without:supplier_id'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $supplier = ! empty($data['supplier_id'])
            ? Supplier::findOrFail((int) $data['supplier_id'])
            : Supplier::firstOrCreate(['name' => trim((string) $data['supplier_name'])]);

        try {
            $r = ImportSupplierPriceList::run($supplier, (string) file_get_contents($request->file('file')->getRealPath()));
        } catch (DomainException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return back()->with('status', "Imported {$supplier->name} price list: {$r['created']} created, {$r['updated']} updated, {$r['unchanged']} unchanged, {$r['skipped']} skipped.");
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
