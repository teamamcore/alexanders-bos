<?php

use App\Actions\Equipment\LogEquipmentUsage;
use App\Actions\Inventory\AdjustStock;
use App\Actions\Time\CalculatePay;
use App\Actions\WorkOrders\AddWorkOrderNote;
use App\Actions\WorkOrders\CompleteWorkOrderQuestionnaire;
use App\Models\Equipment;
use App\Models\EquipmentUsage;
use App\Models\Part;
use App\Models\StockAdjustment;
use App\Models\TimeEntry;
use App\Models\WorkOrder;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Completion questionnaire (design notes §1): walks notes → materials →
 * equipment → hours, pre-filled by whatever was logged along the way, asks
 * "anything more?" per category, flags overtime, then completes the WO.
 */
new #[Layout('layouts.app')] class extends Component
{
    public WorkOrder $workOrder;

    public string $extra_note = '';
    public ?int $part_id = null;
    public int $part_qty = 1;
    public ?int $equipment_id = null;
    public float $equipment_qty = 1;
    public string $work_performed = '';
    public array $confirmed = ['notes' => false, 'materials' => false, 'equipment' => false, 'hours' => false];
    public string $flash = '';

    public function mount(WorkOrder $workOrder): void
    {
        $this->workOrder = $workOrder;
        $this->work_performed = (string) ($workOrder->work_performed ?? '');
    }

    public function with(): array
    {
        $wo = $this->workOrder->fresh()->load('job.customer', 'job.serviceLocation');
        $entries = TimeEntry::where('work_order_id', $wo->id)->with('user')->orderBy('started_at')->get();
        $hours = $entries->whereNotNull('ended_at')->sum(fn ($e) => (float) $e->started_at->diffInHours($e->ended_at));
        $ot = $entries->whereNotNull('ended_at')->filter(fn ($e) => CalculatePay::run($e->started_at)['multiplier'] > 1)->sum(fn ($e) => (float) $e->started_at->diffInHours($e->ended_at));

        return [
            'wo' => $wo,
            'notes' => $wo->notes()->whereIn('kind', ['tech_note', 'field_finding', 'scope_update'])->with('author')->oldest()->get(),
            'materials' => StockAdjustment::where('work_order_id', $wo->id)->where('reason', 'used_on_job')->with('part')->get(),
            'equipment' => EquipmentUsage::where('work_order_id', $wo->id)->with('equipment')->get(),
            'entries' => $entries,
            'hours' => round($hours, 2),
            'ot' => round($ot, 2),
            'open' => $entries->whereNull('ended_at')->count(),
            'parts' => Part::orderBy('name')->get(),
            'equipmentList' => Equipment::orderBy('name')->get(),
            'ready' => ! in_array(false, $this->confirmed, true),
        ];
    }

    public function addNote(): void
    {
        $this->validate(['extra_note' => ['required', 'string', 'max:2000']]);
        AddWorkOrderNote::run($this->workOrder, auth()->user(), $this->extra_note, 'field_finding');
        $this->reset('extra_note');
        $this->flash = 'Note added.';
    }

    public function addMaterial(): void
    {
        $this->validate(['part_id' => ['required', 'integer'], 'part_qty' => ['required', 'integer', 'min:1']]);
        AdjustStock::run(Part::findOrFail($this->part_id), -$this->part_qty, 'used_on_job', auth()->user(), $this->workOrder);
        $this->reset('part_id');
        $this->part_qty = 1;
    }

    public function addEquipment(): void
    {
        $this->validate(['equipment_id' => ['required', 'integer'], 'equipment_qty' => ['required', 'numeric', 'gt:0']]);
        LogEquipmentUsage::run(Equipment::findOrFail($this->equipment_id), $this->workOrder, (float) $this->equipment_qty, auth()->user());
        $this->reset('equipment_id');
        $this->equipment_qty = 1;
    }

    public function submit(): void
    {
        $this->validate(['work_performed' => ['required', 'string', 'max:4000']]);
        if (in_array(false, $this->confirmed, true)) {
            $this->addError('confirmed', 'Confirm each section ("nothing more") before completing.');

            return;
        }
        try {
            CompleteWorkOrderQuestionnaire::run($this->workOrder->fresh(), auth()->user(), $this->work_performed);
        } catch (\DomainException $e) {
            $this->addError('work_performed', $e->getMessage());

            return;
        }
        session()->flash('status', "{$this->workOrder->number} complete — the office has it for billing.");
        $this->redirectRoute('work-orders.show', $this->workOrder, navigate: false);
    }
}; ?>

<div class="max-w-2xl mx-auto px-3 sm:px-6 py-4 space-y-4">
    <div>
        <a href="{{ route('work-orders.show', $wo) }}" class="text-xs font-medium text-slate-500 hover:text-slate-700">← {{ $wo->number }}</a>
        <h2 class="page-title">Complete {{ $wo->number }}</h2>
        <div class="text-sm text-slate-500">{{ $wo->job?->customer?->name }} · {{ $wo->job?->serviceLocation?->address }}</div>
    </div>
    @if ($flash)<div class="flash-ok">{{ $flash }}</div>@endif
    @error('confirmed')<div class="flash-err">{{ $message }}</div>@enderror

    {{-- 1. Notes --}}
    <section class="card p-4 space-y-2 {{ $confirmed['notes'] ? 'ring-1 ring-emerald-300' : '' }}">
        <div class="flex items-center justify-between"><div class="font-semibold text-slate-900">1 · Notes <span class="card-note">· {{ $notes->count() }} logged</span></div><label class="inline-flex items-center gap-1.5 text-sm text-slate-700"><input type="checkbox" wire:model.live="confirmed.notes" class="rounded border-slate-300 text-emerald-600"> nothing more</label></div>
        <ul class="text-sm divide-y divide-slate-100">@forelse ($notes as $n)<li class="py-1.5 text-slate-700"><span class="text-xs text-slate-400">{{ $n->created_at?->format('D g:ia') }}</span> — {{ $n->body }}</li>@empty<li class="py-1.5 text-slate-400">No notes yet.</li>@endforelse</ul>
        <form wire:submit="addNote" class="flex gap-2"><x-text-input wire:model="extra_note" placeholder="Anything more to note?" class="grow text-sm" /><x-secondary-button type="submit">Add</x-secondary-button></form>
        <x-input-error :messages="$errors->get('extra_note')" />
    </section>

    {{-- 2. Materials --}}
    <section class="card p-4 space-y-2 {{ $confirmed['materials'] ? 'ring-1 ring-emerald-300' : '' }}">
        <div class="flex items-center justify-between"><div class="font-semibold text-slate-900">2 · Materials <span class="card-note">· {{ $materials->sum(fn ($m) => abs($m->quantity)) }} items</span></div><label class="inline-flex items-center gap-1.5 text-sm text-slate-700"><input type="checkbox" wire:model.live="confirmed.materials" class="rounded border-slate-300 text-emerald-600"> nothing more</label></div>
        <ul class="text-sm divide-y divide-slate-100">@forelse ($materials as $m)<li class="py-1.5 flex justify-between text-slate-700"><span>{{ $m->part?->name }}</span><span class="text-slate-500">× {{ abs($m->quantity) }}</span></li>@empty<li class="py-1.5 text-slate-400">No parts logged.</li>@endforelse</ul>
        <form wire:submit="addMaterial" class="flex gap-2"><select wire:model="part_id" class="grow rounded-lg border-slate-300 text-sm"><option value="">Anything more? Pick a part…</option>@foreach ($parts as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select><x-text-input wire:model="part_qty" type="number" min="1" class="w-16" /><x-secondary-button type="submit">Add</x-secondary-button></form>
    </section>

    {{-- 3. Equipment --}}
    <section class="card p-4 space-y-2 {{ $confirmed['equipment'] ? 'ring-1 ring-emerald-300' : '' }}">
        <div class="flex items-center justify-between"><div class="font-semibold text-slate-900">3 · Equipment <span class="card-note">· {{ $equipment->count() }} uses</span></div><label class="inline-flex items-center gap-1.5 text-sm text-slate-700"><input type="checkbox" wire:model.live="confirmed.equipment" class="rounded border-slate-300 text-emerald-600"> nothing more</label></div>
        <ul class="text-sm divide-y divide-slate-100">@forelse ($equipment as $u)<li class="py-1.5 flex justify-between text-slate-700"><span>{{ $u->equipment?->name }}</span><span class="text-slate-500">{{ $u->quantity }} {{ $u->unit }}</span></li>@empty<li class="py-1.5 text-slate-400">No equipment logged.</li>@endforelse</ul>
        <form wire:submit="addEquipment" class="flex gap-2"><select wire:model="equipment_id" class="grow rounded-lg border-slate-300 text-sm"><option value="">Anything more? Pick equipment…</option>@foreach ($equipmentList as $e)<option value="{{ $e->id }}">{{ $e->name }} ({{ $e->billing_unit }}s)</option>@endforeach</select><x-text-input wire:model="equipment_qty" type="number" step="0.25" min="0.25" class="w-16" /><x-secondary-button type="submit">Add</x-secondary-button></form>
    </section>

    {{-- 4. Hours --}}
    <section class="card p-4 space-y-2 {{ $confirmed['hours'] ? 'ring-1 ring-emerald-300' : '' }}">
        <div class="flex items-center justify-between"><div class="font-semibold text-slate-900">4 · Hours <span class="card-note">· {{ $hours }} h @if ($ot > 0) · <span class="text-amber-700 font-semibold">{{ $ot }} h overtime</span>@endif</span></div><label class="inline-flex items-center gap-1.5 text-sm text-slate-700"><input type="checkbox" wire:model.live="confirmed.hours" class="rounded border-slate-300 text-emerald-600"> looks right</label></div>
        @if ($open)<div class="flash-warn text-xs">You're still clocked in on this job — it will be clocked out when you complete.</div>@endif
        @if ($ot > 0)<div class="flash-warn text-xs">Overtime flagged for the office to review.</div>@endif
        <ul class="text-sm divide-y divide-slate-100">@forelse ($entries as $e)<li class="py-1.5 flex justify-between text-slate-700"><span>{{ $e->user?->name }} · {{ $e->started_at->format('D M j g:ia') }} · {{ $e->category }}</span><span class="text-slate-500">{{ $e->ended_at ? number_format((float) $e->started_at->diffInHours($e->ended_at), 2).' h' : 'running' }}</span></li>@empty<li class="py-1.5 text-slate-400">No time logged — the office will ask.</li>@endforelse</ul>
    </section>

    {{-- 5. Work performed --}}
    <form wire:submit="submit" class="card p-4 space-y-2">
        <div class="font-semibold text-slate-900">5 · Work performed <span class="card-note">· what you did, for the office and the invoice summary</span></div>
        <textarea wire:model="work_performed" rows="4" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Replaced 3/4 ball valve at riser, tested backflow assembly, passed. Left site clean."></textarea>
        <x-input-error :messages="$errors->get('work_performed')" />
        <button class="w-full rounded-xl px-4 py-3 text-sm font-semibold text-white {{ $ready ? 'bg-brand-900 hover:bg-brand-800' : 'bg-slate-300 cursor-not-allowed' }}" @disabled(! $ready)>Submit &amp; complete {{ $wo->number }}</button>
        <div class="text-[11px] text-slate-400 text-center">The customer gets the "work complete" update; pricing is handled by the office.</div>
    </form>
</div>
