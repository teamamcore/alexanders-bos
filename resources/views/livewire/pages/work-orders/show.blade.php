<?php

use App\Actions\Communication\NotifyCustomer;
use App\Actions\Communication\SendCustomerMessage;
use App\Actions\Equipment\LogEquipmentUsage;
use App\Actions\Inventory\AdjustStock;
use App\Actions\Invoicing\GenerateInvoiceFromWorkOrder;
use App\Actions\Time\ClockIn;
use App\Actions\Time\ClockOut;
use App\Actions\WorkOrders\AcknowledgeScopeUpdate;
use App\Actions\WorkOrders\AddWorkOrderNote;
use App\Actions\WorkOrders\PutOnHold;
use App\Actions\WorkOrders\ReleaseHold;
use App\Actions\WorkOrders\StartWorkOrder;
use App\Actions\WorkOrders\UpdateScopeOfWork;
use App\Actions\WorkOrders\UploadWorkOrderFile;
use App\Models\AuditLog;
use App\Models\CustomerMessage;
use App\Models\Equipment;
use App\Models\Part;
use App\Models\TimeEntry;
use App\Models\UserMessage;
use App\Models\WorkOrder;
use App\Models\WorkOrderNote;
use App\Support\WorkOrderBilling;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/**
 * The Work Order PARENT (design notes §1). Everything about the job lives here;
 * office and tech see the same record, laid out for their job: the office gets
 * the full desktop parent; the tech gets the minimal stack with no pricing.
 * Positions never move: customer top-left, description/SOW centre, comms right,
 * billables below, notes at the bottom.
 */
new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public WorkOrder $workOrder;

    public string $note = '';
    public string $scope_add = '';
    public ?int $scope_source = null;
    public string $hold_reason = 'waiting_parts';
    public string $hold_note = '';
    public string $customer_text = '';
    public string $eta = '';
    public ?int $part_id = null;
    public int $part_qty = 1;
    public ?int $equipment_id = null;
    public float $equipment_qty = 1;
    public $photo = null;
    public string $photo_caption = '';
    public string $flash = '';
    public bool $showAllComms = false;
    public bool $showAllActivity = false;

    public function mount(WorkOrder $workOrder): void
    {
        $this->workOrder = $workOrder;
    }

    public function with(): array
    {
        $wo = $this->workOrder->fresh()->load(['job.customer.contacts', 'job.customer.corpPoc', 'job.serviceLocation.contact', 'assignee', 'crew.members', 'invoices', 'files.uploader', 'scopeRevisions.sourceNote', 'scopeRevisions.author']);
        $user = auth()->user();
        $isField = $user->isField();

        $external = CustomerMessage::where('work_order_id', $wo->id)->with('sender')->latest()->take($this->showAllComms ? 100 : 4)->get()->reverse()->values();
        $internal = WorkOrderNote::where('work_order_id', $wo->id)->whereIn('kind', ['tech_note', 'field_finding', 'scope_update', 'office_note'])->with('author')->latest()->take($this->showAllComms ? 100 : 4)->get()->reverse()->values();
        $activity = collect()
            ->merge(WorkOrderNote::where('work_order_id', $wo->id)->where('kind', 'system')->latest()->take(30)->get()->map(fn ($n) => ['at' => $n->created_at, 'text' => $n->body]))
            ->merge(AuditLog::where('auditable_type', WorkOrder::class)->where('auditable_id', $wo->id)->latest()->take(30)->get()->map(fn ($a) => ['at' => $a->created_at, 'text' => $a->action === 'updated' ? 'Updated '.implode(', ', array_keys($a->changes['after'] ?? [])) : ucfirst($a->action)]))
            ->sortByDesc('at')->values();

        $timeEntries = TimeEntry::where('work_order_id', $wo->id)->with('user')->latest('started_at')->get();
        $materials = \App\Models\StockAdjustment::where('work_order_id', $wo->id)->where('reason', 'used_on_job')->with('part')->latest()->get();
        $equipment = \App\Models\EquipmentUsage::where('work_order_id', $wo->id)->with('equipment')->latest()->get();

        return [
            'wo' => $wo,
            'isField' => $isField,
            'isAdmin' => $user->isAdministrator(),
            'tally' => $isField ? null : WorkOrderBilling::tally($wo),
            'external' => $external,
            'internal' => $internal,
            'activity' => $activity,
            'lastEvent' => $activity->first(),
            'timeEntries' => $timeEntries,
            'materials' => $materials,
            'equipment' => $equipment,
            'openEntry' => $timeEntries->first(fn ($e) => $e->user_id === $user->id && $e->ended_at === null),
            'parts' => Part::orderBy('name')->get(),
            'equipmentList' => Equipment::orderBy('name')->get(),
            'holdReasons' => config('batsons.hold_reasons', []),
            'pendingScope' => WorkOrderNote::where('work_order_id', $wo->id)->pendingScopeUpdates()->with('author')->get(),
            'recipient' => $wo->job?->customer ? SendCustomerMessage::recipient($wo->job->customer) : null,
            'pill' => $wo->pillStatus(),
        ];
    }

    private function me(): \App\Models\User
    {
        /** @var \App\Models\User $u */
        $u = auth()->user();

        return $u;
    }

    private function guardOffice(): void
    {
        abort_if($this->me()->isField(), 403, 'Office only.');
    }

    // ---- Notes (tech + office) -------------------------------------------
    public function saveNote(string $kind = 'tech_note'): void
    {
        $this->validate(['note' => ['required', 'string', 'max:2000']]);
        AddWorkOrderNote::run($this->workOrder, $this->me(), $this->note, $this->me()->isField() ? ($kind === 'scope_update' ? 'scope_update' : 'tech_note') : ($kind === 'scope_update' ? 'scope_update' : 'office_note'));
        $this->flash = $kind === 'scope_update' ? 'Scope update sent to the office.' : 'Note saved.';
        $this->reset('note');
    }

    public function ackScope(int $noteId): void
    {
        $this->guardOffice();
        AcknowledgeScopeUpdate::run(WorkOrderNote::findOrFail($noteId), $this->me());
        $this->flash = 'Scope update acknowledged.';
    }

    public function addScope(): void
    {
        $this->guardOffice();
        $this->validate(['scope_add' => ['required', 'string', 'max:4000']]);
        $source = $this->scope_source ? WorkOrderNote::find($this->scope_source) : null;
        UpdateScopeOfWork::run($this->workOrder, $this->me(), $this->scope_add, $source);
        $this->flash = 'Scope of work updated.';
        $this->reset('scope_add', 'scope_source');
    }

    // ---- Status (pill + hold) --------------------------------------------
    public function start(): void
    {
        StartWorkOrder::run($this->workOrder->fresh(), $this->me());
        $this->flash = 'Work started — customer notified.';
    }

    public function hold(): void
    {
        PutOnHold::run($this->workOrder->fresh(), $this->hold_reason, $this->me(), $this->hold_note ?: null);
        $this->flash = 'On hold.';
        $this->reset('hold_note');
    }

    public function release(): void
    {
        ReleaseHold::run($this->workOrder->fresh(), $this->me());
        $this->flash = 'Hold released.';
    }

    public function fieldStatus(string $status): void
    {
        // Tech banner dropdown: Active / On hold / Complete — the only states techs toggle.
        if ($status === 'active') {
            $wo = $this->workOrder->fresh();
            if ($wo->on_hold) {
                ReleaseHold::run($wo, $this->me());
            } elseif ($wo->status instanceof \App\States\WorkOrder\Scheduled) {
                StartWorkOrder::run($wo, $this->me());
            }
            $this->flash = 'Active.';
        } elseif ($status === 'on_hold') {
            PutOnHold::run($this->workOrder->fresh(), 'waiting_parts', $this->me());
            $this->flash = 'On hold — waiting for parts. Change the reason in notes if different.';
        } elseif ($status === 'complete') {
            $this->redirectRoute('work-orders.complete', $this->workOrder, navigate: false);
        }
    }

    // ---- Customer comms (external stream) --------------------------------
    public function quickMessage(string $key): void
    {
        $vars = $key === 'on_the_way' && trim($this->eta) !== '' ? ['eta' => 'ETA '.trim($this->eta).'.'] : [];
        $m = NotifyCustomer::run($this->workOrder->fresh(), $key, $this->me(), $vars);
        $this->flash = $m ? ucfirst($m->channel)." {$m->status} to {$m->to}." : 'No phone or email on file for this customer.';
        $this->reset('eta');
    }

    public function sendCustomer(): void
    {
        $this->validate(['customer_text' => ['required', 'string', 'max:600']]);
        $m = SendCustomerMessage::run($this->workOrder->job->customer, null, $this->customer_text, $this->me(), $this->workOrder);
        $this->flash = $m ? ucfirst($m->channel)." {$m->status} to {$m->to}." : 'No phone or email on file for this customer.';
        $this->reset('customer_text');
    }

    // ---- Billables (children) --------------------------------------------
    public function clockIn(): void
    {
        ClockIn::run($this->me(), $this->workOrder, 'on_site');
        $this->flash = 'Clocked in.';
    }

    public function clockOut(): void
    {
        $open = TimeEntry::where('work_order_id', $this->workOrder->id)->where('user_id', $this->me()->id)->whereNull('ended_at')->first();
        if ($open) {
            ClockOut::run($open);
            $this->flash = 'Clocked out.';
        }
    }

    public function addMaterial(): void
    {
        $this->validate(['part_id' => ['required', 'integer'], 'part_qty' => ['required', 'integer', 'min:1']]);
        AdjustStock::run(Part::findOrFail($this->part_id), -$this->part_qty, 'used_on_job', $this->me(), $this->workOrder);
        $this->flash = 'Material added.';
        $this->reset('part_id', 'part_qty');
        $this->part_qty = 1;
    }

    public function addEquipment(): void
    {
        $this->validate(['equipment_id' => ['required', 'integer'], 'equipment_qty' => ['required', 'numeric', 'gt:0']]);
        LogEquipmentUsage::run(Equipment::findOrFail($this->equipment_id), $this->workOrder, (float) $this->equipment_qty, $this->me());
        $this->flash = 'Equipment logged.';
        $this->reset('equipment_id');
        $this->equipment_qty = 1;
    }

    public function uploadPhoto(): void
    {
        $this->validate(['photo' => ['required', 'image', 'max:8192'], 'photo_caption' => ['nullable', 'string', 'max:160']]);
        UploadWorkOrderFile::run($this->workOrder, $this->me(), $this->photo, 'photo', $this->photo_caption ?: null);
        $this->flash = 'Photo added.';
        $this->reset('photo', 'photo_caption');
    }

    public function invoice(): void
    {
        $this->guardOffice();
        $tally = WorkOrderBilling::tally($this->workOrder);
        $inv = GenerateInvoiceFromWorkOrder::run($this->workOrder->fresh(), $tally['uninvoiced'] > 0 ? $tally['uninvoiced'] : $tally['total']);
        $this->redirectRoute('invoices.index', navigate: false);
        session()->flash('status', "{$inv->number} created from {$this->workOrder->number} — review and approve.");
    }
}; ?>

<div>
    @php
        $customer = $wo->job?->customer;
        $site = $wo->job?->serviceLocation;
        $primary = $site?->contact ?? $customer?->corpPoc ?? $customer?->contacts->first();
        $secondary = $customer?->contacts->first(fn ($c) => $primary && $c->id !== $primary->id);
        $steps = ['inactive' => 'Inactive', 'active' => 'Active', 'billable' => 'Billable', 'closed' => 'Paid / Closed'];
        $order = array_keys($steps); $idx = array_search($pill, $order, true);
    @endphp

    {{-- ============================ TECH / FIELD VIEW ============================ --}}
    @if ($isField)
        <div class="max-w-xl mx-auto px-3 py-3 space-y-3">
            {{-- Banner: logo · date/time · status dropdown --}}
            <div class="flex items-center justify-between rounded-xl bg-brand-950 px-4 py-3 text-white">
                <div class="flex items-center gap-2"><x-application-logo class="h-7 w-7 text-white" /><span class="text-sm font-semibold">{{ now()->format('n/j/y g:ia') }}</span></div>
                <select wire:change="fieldStatus($event.target.value)" class="rounded-lg border-0 bg-white/15 text-sm font-semibold text-white focus:ring-accent-400">
                    <option value="active" @selected(! $wo->on_hold && $pill === 'active')>Active</option>
                    <option value="on_hold" @selected($wo->on_hold)>On hold — parts</option>
                    <option value="complete" @selected($pill !== 'active')>Complete</option>
                </select>
            </div>
            @if ($flash)<div class="flash-ok">{{ $flash }}</div>@endif
            @if ($wo->isEmergency())<div class="flash-err font-semibold">EMERGENCY</div>@endif

            <div class="card p-4">
                <div class="text-lg font-bold text-slate-900">{{ $wo->number }}</div>
                <div class="text-slate-800 font-medium">{{ $customer?->name }}</div>
                <div class="text-sm text-slate-600">{{ $site?->label ? $site->label.' · ' : '' }}{{ $site?->address }}</div>
                @if ($site?->access_notes)<div class="mt-1 text-xs text-amber-800 bg-amber-50 rounded px-2 py-1">{{ $site->access_notes }}</div>@endif
            </div>

            <div class="card p-4 flex items-center justify-between">
                <div><div class="text-xs uppercase tracking-wide text-slate-400">Contact</div><div class="font-medium text-slate-900">{{ $primary?->name ?? $customer?->name }}</div><div class="text-sm text-slate-600">{{ $primary?->phone ?? $customer?->phone }}</div></div>
                <div class="flex gap-2">
                    @if ($primary?->phone ?? $customer?->phone)<a href="tel:{{ $primary?->phone ?? $customer?->phone }}" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-700"><x-icon name="phone" set="standard" /></a>@endif
                    <button wire:click="quickMessage('on_the_way')" class="rounded-full bg-brand-50 px-3 text-xs font-semibold text-brand-800">On the way</button>
                    <button wire:click="quickMessage('arrived')" class="rounded-full bg-brand-50 px-3 text-xs font-semibold text-brand-800">Arrived</button>
                </div>
            </div>

            <div class="card p-4 space-y-2">
                <div class="text-xs uppercase tracking-wide text-slate-400">Description / scope of work</div>
                <div class="text-sm text-slate-800 whitespace-pre-line">{{ $wo->customer_notes ?: $wo->job?->title }}</div>
                @if ($wo->scope_office)<div class="redline text-sm whitespace-pre-line">{{ $wo->scope_office }}</div>@endif
                <div class="text-[11px] text-slate-400">Read-only. Found more? Send a scope update — the office prices it and clears it with the customer first.</div>
            </div>

            <form wire:submit="saveNote" class="card p-4 space-y-2">
                <div class="text-xs uppercase tracking-wide text-slate-400">Notes</div>
                <textarea wire:model="note" rows="3" class="w-full rounded-lg border-slate-300 text-sm" placeholder="What you found, what you did…"></textarea>
                <x-input-error :messages="$errors->get('note')" />
                <div class="flex gap-2">
                    <x-primary-button class="flex-1 justify-center">Save note</x-primary-button>
                    <button type="button" wire:click="saveNote('scope_update')" class="flex-1 rounded-lg bg-accent-500 px-3 py-2 text-sm font-semibold text-white">Send scope update</button>
                    <button type="button" wire:click="$set('note', '')" class="px-3 text-sm text-slate-500">Cancel</button>
                </div>
            </form>

            <div class="card p-4 space-y-2">
                <div class="flex items-center justify-between"><div class="text-xs uppercase tracking-wide text-slate-400">Hours</div>
                    @if ($openEntry)<button wire:click="clockOut" class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-semibold text-white">Clock out</button>@else<button wire:click="clockIn" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white">Clock in</button>@endif
                </div>
                <ul class="text-sm divide-y divide-slate-100">
                    @forelse ($timeEntries->take(5) as $e)<li class="py-1.5 flex justify-between"><span>{{ $e->user?->name }} · {{ $e->started_at->format('D g:ia') }}</span><span class="text-slate-500">{{ $e->ended_at ? number_format((float) $e->started_at->diffInHours($e->ended_at), 2).' h' : 'running…' }}</span></li>@empty<li class="py-1.5 text-slate-400">No time yet.</li>@endforelse
                </ul>
            </div>

            <div class="card p-4 space-y-3">
                <div class="text-xs uppercase tracking-wide text-slate-400">Materials &amp; equipment</div>
                <form wire:submit="addMaterial" class="flex gap-2">
                    <select wire:model="part_id" class="grow rounded-lg border-slate-300 text-sm"><option value="">Part…</option>@foreach ($parts as $p)<option value="{{ $p->id }}">{{ $p->name }}{{ $p->nicknames ? ' ('.implode(', ', $p->nicknames).')' : '' }}</option>@endforeach</select>
                    <x-text-input wire:model="part_qty" type="number" min="1" class="w-16" />
                    <x-secondary-button type="submit">Add</x-secondary-button>
                </form>
                <form wire:submit="addEquipment" class="flex gap-2">
                    <select wire:model="equipment_id" class="grow rounded-lg border-slate-300 text-sm"><option value="">Equipment…</option>@foreach ($equipmentList as $e)<option value="{{ $e->id }}">{{ $e->name }} ({{ $e->billing_unit }}s)</option>@endforeach</select>
                    <x-text-input wire:model="equipment_qty" type="number" step="0.25" min="0.25" class="w-16" />
                    <x-secondary-button type="submit">Add</x-secondary-button>
                </form>
                <ul class="text-sm divide-y divide-slate-100">
                    @foreach ($materials as $m)<li class="py-1 flex justify-between"><span>{{ $m->part?->name }}</span><span class="text-slate-500">× {{ abs($m->quantity) }}</span></li>@endforeach
                    @foreach ($equipment as $u)<li class="py-1 flex justify-between"><span>{{ $u->equipment?->name }}</span><span class="text-slate-500">{{ $u->quantity }} {{ $u->unit }}</span></li>@endforeach
                </ul>
            </div>

            <form wire:submit="uploadPhoto" class="card p-4 flex items-center gap-2">
                <label class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-700 cursor-pointer"><x-icon name="camera" set="standard" /><input type="file" wire:model="photo" accept="image/*" capture="environment" class="hidden"></label>
                <x-text-input wire:model="photo_caption" placeholder="Caption" class="grow text-sm" />
                <x-secondary-button type="submit">Save</x-secondary-button>
                <div wire:loading wire:target="photo" class="text-xs text-slate-400">uploading…</div>
            </form>
            @if ($wo->files->isNotEmpty())
                <div class="grid grid-cols-4 gap-2">@foreach ($wo->files as $f)<a href="{{ Storage::disk('public')->url($f->path) }}" target="_blank"><img src="{{ Storage::disk('public')->url($f->path) }}" class="aspect-square rounded-lg object-cover ring-1 ring-slate-200" alt="{{ $f->caption }}"></a>@endforeach</div>
            @endif

            <a href="{{ route('work-orders.complete', $wo) }}" class="block rounded-xl bg-brand-900 px-4 py-3 text-center text-sm font-semibold text-white">Complete job →</a>
        </div>

    {{-- ============================ OFFICE / DESKTOP VIEW ============================ --}}
    @else
        <x-slot name="header">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="page-title">{{ $wo->number ?? 'WO #'.$wo->id }}</h2>
                @if ($wo->isEmergency())<span class="badge-red">emergency</span>@endif
                {{-- Status pill: Inactive → Active → Billable → Paid/Closed, Hold as sub-state --}}
                <div class="pill-track">
                    @foreach ($steps as $k => $label)
                        @php $i = array_search($k, $order, true); @endphp
                        <span class="pill-step {{ $i < $idx ? 'pill-step-done' : '' }} {{ $i === $idx ? 'pill-step-current' : '' }}">{{ $label }}</span>
                    @endforeach
                </div>
                @if ($wo->on_hold)<span class="pill-hold"><x-icon name="hold" set="standard" class="h-3.5 w-3.5" /> Hold · {{ $wo->holdReasonLabel() }}</span>@endif

                <div class="ml-auto flex items-center gap-2">
                    @if ($pill === 'active' && $wo->status instanceof \App\States\WorkOrder\Scheduled && ! $wo->on_hold)<x-secondary-button wire:click="start">Start work</x-secondary-button>@endif
                    @if ($wo->on_hold)
                        <x-secondary-button wire:click="release">Release hold</x-secondary-button>
                    @elseif ($pill === 'active')
                        <div class="flex items-center gap-1"><select wire:model="hold_reason" class="rounded-lg border-slate-300 text-xs py-1">@foreach ($holdReasons as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select><x-secondary-button wire:click="hold" class="!py-1 !px-2 !text-xs">Hold</x-secondary-button></div>
                    @endif
                    <a href="{{ route('schedule.index', ['wo' => $wo->id]) }}" class="inline-flex items-center rounded-lg bg-accent-500 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-accent-600">Book now</a>
                    <button wire:click="invoice" class="inline-flex items-center gap-2 rounded-lg bg-brand-900 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-800" title="Create the invoice from the running tally">
                        <x-icon name="invoice_dollar" set="standard" class="h-4 w-4" /> Invoice
                        @if ($isAdmin)<span class="rounded-full bg-white/15 px-2 text-xs">${{ number_format($tally['total'], 0) }}@if ($tally['invoiced'] > 0) · ${{ number_format($tally['invoiced'], 0) }} billed @endif</span>@else<span class="rounded-full bg-white/15 px-2 text-xs">{{ $tally['hours'] }} h · {{ $tally['materials_qty'] }} parts</span>@endif
                    </button>
                </div>
            </div>
        </x-slot>

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-4 space-y-4">
            @if ($flash)<div class="flash-ok">{{ $flash }}</div>@endif
            @if ($pendingScope->isNotEmpty())
                <div class="flash-warn">
                    <div class="font-semibold">Scope update needs the office</div>
                    @foreach ($pendingScope as $n)<div class="mt-1 flex items-start justify-between gap-3 text-sm"><span>“{{ $n->body }}” — {{ $n->author?->name }}, {{ $n->created_at?->diffForHumans() }}</span><span class="flex gap-2 shrink-0"><button wire:click="$set('scope_source', {{ $n->id }})" class="text-xs font-semibold text-brand-800">Add to SOW</button><button wire:click="ackScope({{ $n->id }})" class="text-xs text-slate-500">Acknowledge</button></span></div>@endforeach
                </div>
            @endif

            {{-- Row 1: customer (left) · description / SOW (centre) · comms + activity (right) --}}
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                <div class="lg:col-span-3 space-y-4">
                    <div class="card p-4">
                        <div class="text-xs uppercase tracking-wide text-slate-400">Customer</div>
                        <a href="{{ $customer ? route('contacts.show', $customer) : '#' }}" class="block text-base font-bold text-slate-900 hover:text-brand-700">{{ $customer?->name }}</a>
                        <div class="text-sm text-slate-700">{{ $site?->label ? $site->label.' · ' : '' }}{{ $site?->address ?? 'no site on file' }}</div>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                            <div><div class="text-[10px] uppercase tracking-wide text-slate-400">Primary</div><div class="font-medium text-slate-900">{{ $primary?->name ?? '—' }}</div><div class="text-xs text-slate-500">{{ $primary?->role }}</div><div class="text-xs text-slate-600">{{ $primary?->phone }}</div><div class="text-xs text-slate-600 truncate">{{ $primary?->email }}</div></div>
                            <div><div class="text-[10px] uppercase tracking-wide text-accent-600">Secondary</div><div class="font-medium text-slate-900">{{ $secondary?->name ?? '—' }}</div><div class="text-xs text-slate-500">{{ $secondary?->role }}</div><div class="text-xs text-slate-600">{{ $secondary?->phone }}</div></div>
                        </div>
                        <div class="mt-3 flex items-center gap-2 text-xs text-slate-500">Reach: {{ $recipient ? $recipient['channel'].' · '.$recipient['to'] : 'nothing on file' }}</div>
                        <div class="mt-2 text-xs"><a href="{{ route('jobs.show', $wo->job_id) }}" class="text-brand-700 hover:underline">Job {{ $wo->job?->number ?? '#'.$wo->job_id }} →</a> · <a href="{{ route('reports.job', $wo->job_id) }}" class="text-brand-700 hover:underline">cost sheet</a></div>
                    </div>
                    <div class="card p-4 text-sm">
                        <div class="text-xs uppercase tracking-wide text-slate-400 mb-1">Schedule</div>
                        <div class="text-slate-900">{{ $wo->scheduled_date?->format('l, M j') ?? 'Unscheduled' }}@if ($wo->scheduled_date) · {{ config('batsons.lanes')[$wo->lane()] }} · {{ rtrim(rtrim((string) $wo->day_fraction, '0'), '.') }} day(s)@endif</div>
                        <div class="text-slate-600">{{ $wo->crew?->name ?? '' }}{{ $wo->crew && $wo->assignee ? ' · ' : '' }}{{ $wo->assignee?->name ?? '' }}</div>
                    </div>
                </div>

                <div class="lg:col-span-5 space-y-4">
                    <div class="card p-4 space-y-3">
                        <div class="flex items-center justify-between"><div class="text-xs uppercase tracking-wide text-slate-400">Description of issue</div><span class="card-note">customer-stated · office intake</span></div>
                        <div class="text-sm text-slate-800 whitespace-pre-line">{{ $wo->customer_notes ?: ($wo->job?->title ?? '—') }}</div>
                        <div class="border-t border-slate-100 pt-3 flex items-center justify-between"><div class="text-xs uppercase tracking-wide text-slate-400">Field findings</div><span class="card-note">tech discovery notes</span></div>
                        <ul class="space-y-1 text-sm">
                            @forelse ($internal->whereIn('kind', ['tech_note', 'field_finding', 'scope_update']) as $n)<li class="text-slate-700"><span class="text-xs text-slate-400">{{ $n->created_at?->format('M j g:ia') }} · {{ $n->author?->name }}</span> — {{ $n->body }}@if ($n->kind === 'scope_update')<span class="badge-amber ml-1">scope update</span>@endif</li>@empty<li class="text-slate-400">Nothing reported from the field yet.</li>@endforelse
                        </ul>
                        <div class="border-t border-slate-100 pt-3 flex items-center justify-between"><div class="text-xs uppercase tracking-wide text-slate-400">Scope of work</div><span class="card-note">office-maintained · additions redlined</span></div>
                        @if ($wo->scopeRevisions->isEmpty())
                            <div class="text-sm text-slate-400">No scope additions beyond the stated issue.</div>
                        @else
                            <ol class="space-y-2 text-sm">
                                @foreach ($wo->scopeRevisions->reverse() as $r)
                                    <li class="redline py-1"><div class="whitespace-pre-line">{{ $r->added_text }}</div><div class="text-[11px] text-slate-500">{{ $r->author?->name }} · {{ $r->created_at?->format('M j g:ia') }}@if ($r->sourceNote) · authorized by: “{{ \Illuminate\Support\Str::limit($r->sourceNote->body, 60) }}”@endif</div></li>
                                @endforeach
                            </ol>
                        @endif
                        <form wire:submit="addScope" class="flex flex-col gap-2 rounded-lg bg-slate-50 p-3">
                            <textarea wire:model="scope_add" rows="2" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Add to the scope (priced and approved by the customer first)…"></textarea>
                            <div class="flex items-center justify-between gap-2">
                                <select wire:model="scope_source" class="rounded-lg border-slate-300 text-xs py-1"><option value="">Authorized by… (optional)</option>@foreach ($internal as $n)<option value="{{ $n->id }}">{{ \Illuminate\Support\Str::limit($n->body, 50) }}</option>@endforeach</select>
                                <x-secondary-button type="submit" class="!py-1 !px-2 !text-xs">Update SOW</x-secondary-button>
                            </div>
                            <x-input-error :messages="$errors->get('scope_add')" />
                        </form>
                        @if ($wo->work_performed)<div class="border-t border-slate-100 pt-3"><div class="text-xs uppercase tracking-wide text-slate-400">Work performed</div><div class="text-sm text-slate-800 whitespace-pre-line">{{ $wo->work_performed }}</div></div>@endif
                    </div>
                </div>

                <div class="lg:col-span-4 space-y-4">
                    <div class="card p-4">
                        <div class="flex items-center justify-between mb-2"><div class="text-xs uppercase tracking-wide text-slate-400">Comms</div><button wire:click="$toggle('showAllComms')" class="text-xs text-brand-700">{{ $showAllComms ? 'last 4' : 'expand' }}</button></div>
                        <div class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">External · customer</div>
                        <div class="mt-1 space-y-1.5 max-h-56 overflow-y-auto pr-1">
                            @forelse ($external as $m)<div class="flex {{ $m->direction === 'inbound' ? 'justify-start' : 'justify-end' }}"><div class="max-w-[85%] rounded-2xl px-3 py-1.5 text-sm {{ $m->direction === 'inbound' ? 'bg-slate-100 text-slate-800 rounded-bl-sm' : 'bg-brand-900 text-white rounded-br-sm' }}">{{ $m->body }}<div class="text-[10px] {{ $m->direction === 'inbound' ? 'text-slate-400' : 'text-brand-200' }}">{{ $m->sender?->name ?? ($m->direction === 'inbound' ? 'customer' : 'auto') }} · {{ $m->created_at?->format('M j g:ia') }}</div></div></div>@empty<div class="text-sm text-slate-400">No customer messages yet.</div>@endforelse
                        </div>
                        <form wire:submit="sendCustomer" class="mt-2 flex gap-1"><x-text-input wire:model="customer_text" placeholder="Message the customer…" class="grow text-sm" /><x-primary-button class="!px-2"><x-icon name="send" set="standard" class="h-4 w-4" /></x-primary-button></form>
                        <div class="mt-1 flex gap-1 text-[11px]"><button wire:click="quickMessage('on_the_way')" class="rounded bg-brand-50 px-2 py-0.5 text-brand-800">on the way</button><button wire:click="quickMessage('arrived')" class="rounded bg-brand-50 px-2 py-0.5 text-brand-800">arrived</button><x-text-input wire:model="eta" placeholder="ETA" class="w-20 !py-0.5 text-[11px]" /></div>

                        <div class="mt-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">Internal · office ↔ field</div>
                        <ul class="mt-1 space-y-1 text-sm max-h-40 overflow-y-auto">
                            @forelse ($internal as $n)<li class="text-slate-700"><span class="text-xs text-slate-400">{{ $n->author?->name }} · {{ $n->created_at?->format('M j g:ia') }}</span> — {{ $n->body }}</li>@empty<li class="text-slate-400">No internal notes yet.</li>@endforelse
                        </ul>
                    </div>
                    <div class="card p-4">
                        <div class="flex items-center justify-between"><div class="text-xs uppercase tracking-wide text-slate-400">Activity</div><button wire:click="$toggle('showAllActivity')" class="text-xs text-brand-700">{{ $showAllActivity ? 'collapse' : 'expand' }}</button></div>
                        @if ($lastEvent)<div class="mt-1 text-sm text-slate-800">{{ $lastEvent['text'] }} <span class="text-xs text-slate-400">· {{ $lastEvent['at']?->diffForHumans() }}</span></div>@else<div class="mt-1 text-sm text-slate-400">No activity yet.</div>@endif
                        @if ($showAllActivity)<ul class="mt-2 space-y-1 text-xs text-slate-600 max-h-60 overflow-y-auto">@foreach ($activity as $a)<li>{{ $a['at']?->format('M j g:ia') }} — {{ $a['text'] }}</li>@endforeach</ul>@endif
                    </div>
                </div>
            </div>

            {{-- Row 2: Billables — hours · materials · equipment (equipment is its own category) --}}
            <div class="card p-4">
                <div class="flex items-center justify-between mb-3"><div class="font-semibold text-slate-900">Billables</div><button wire:click="invoice" class="text-sm font-semibold text-brand-800 hover:underline">Invoice{{ $isAdmin ? ' · $'.number_format($tally['total'], 2) : '' }} →</button></div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="rounded-lg border border-slate-200 p-3">
                        <div class="flex items-center justify-between"><div class="text-xs uppercase tracking-wide text-slate-500">Hours</div><a href="{{ route('time.index') }}" class="text-[11px] text-brand-700">pop out →</a></div>
                        <div class="mt-1 text-lg font-bold text-slate-900">{{ $tally['hours'] }} h <span class="text-xs font-normal text-slate-500">· {{ $tally['regular_hours'] }} reg · {{ $tally['ot_hours'] }} OT</span>@if ($isAdmin)<span class="ml-2 text-sm font-semibold text-emerald-700">${{ number_format($tally['labor'], 0) }}</span>@endif</div>
                        <ul class="mt-2 text-xs divide-y divide-slate-100 max-h-36 overflow-y-auto">@foreach ($timeEntries as $e)<li class="py-1 flex justify-between"><span class="text-slate-700">{{ $e->user?->name }} <span class="text-slate-400">{{ $e->started_at->format('D M j g:ia') }}</span></span><span class="text-slate-600">{{ $e->ended_at ? number_format((float) $e->started_at->diffInHours($e->ended_at), 2).' h' : 'running' }}</span></li>@endforeach</ul>
                        <div class="mt-2">@if ($openEntry)<button wire:click="clockOut" class="text-xs font-semibold text-red-700">Clock out</button>@else<button wire:click="clockIn" class="text-xs font-semibold text-emerald-700">Clock in on this WO</button>@endif</div>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3">
                        <div class="flex items-center justify-between"><div class="text-xs uppercase tracking-wide text-slate-500">Materials</div><a href="{{ route('parts.index') }}" class="text-[11px] text-brand-700">pop out →</a></div>
                        <div class="mt-1 text-lg font-bold text-slate-900">{{ $tally['materials_qty'] }} <span class="text-xs font-normal text-slate-500">items</span>@if ($isAdmin)<span class="ml-2 text-sm font-semibold text-emerald-700">${{ number_format($tally['materials'], 0) }}</span>@endif</div>
                        <ul class="mt-2 text-xs divide-y divide-slate-100 max-h-36 overflow-y-auto">@foreach ($materials as $m)<li class="py-1 flex justify-between"><span class="text-slate-700">{{ $m->part?->name }}</span><span class="text-slate-600">× {{ abs($m->quantity) }}@if ($isAdmin) · ${{ number_format(abs($m->quantity) * (float) ($m->part?->price ?? 0), 2) }}@endif</span></li>@endforeach</ul>
                        <form wire:submit="addMaterial" class="mt-2 flex gap-1"><select wire:model="part_id" class="grow rounded-lg border-slate-300 text-xs py-1"><option value="">Part…</option>@foreach ($parts as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select><x-text-input wire:model="part_qty" type="number" min="1" class="w-14 !py-1 text-xs" /><x-secondary-button type="submit" class="!py-1 !px-2 !text-xs">Add</x-secondary-button></form>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3">
                        <div class="flex items-center justify-between"><div class="text-xs uppercase tracking-wide text-slate-500">Equipment</div><a href="{{ route('equipment.index') }}" class="text-[11px] text-brand-700">pop out →</a></div>
                        <div class="mt-1 text-lg font-bold text-slate-900">{{ $tally['equipment_qty'] }} <span class="text-xs font-normal text-slate-500">uses</span>@if ($isAdmin)<span class="ml-2 text-sm font-semibold text-emerald-700">${{ number_format($tally['equipment'], 0) }}</span>@endif</div>
                        <ul class="mt-2 text-xs divide-y divide-slate-100 max-h-36 overflow-y-auto">@foreach ($equipment as $u)<li class="py-1 flex justify-between"><span class="text-slate-700">{{ $u->equipment?->name }}</span><span class="text-slate-600">{{ $u->quantity }} {{ $u->unit }}@if ($isAdmin) · ${{ number_format((float) $u->amount, 2) }}@endif</span></li>@endforeach</ul>
                        <form wire:submit="addEquipment" class="mt-2 flex gap-1"><select wire:model="equipment_id" class="grow rounded-lg border-slate-300 text-xs py-1"><option value="">Equipment…</option>@foreach ($equipmentList as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach</select><x-text-input wire:model="equipment_qty" type="number" step="0.25" min="0.25" class="w-14 !py-1 text-xs" /><x-secondary-button type="submit" class="!py-1 !px-2 !text-xs">Add</x-secondary-button></form>
                    </div>
                </div>
                @if ($wo->invoices->isNotEmpty())<div class="mt-3 text-xs text-slate-500">Invoices: @foreach ($wo->invoices as $inv)<span class="badge-gray mr-1">{{ $inv->number }} · {{ $inv->status }}@if ($isAdmin) · ${{ number_format((float) $inv->total, 0) }}@endif</span>@endforeach</div>@endif
            </div>

            {{-- Row 3: Notes + files --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <form wire:submit="saveNote" class="lg:col-span-2 card p-4 space-y-2">
                    <div class="text-xs uppercase tracking-wide text-slate-400">Notes <span class="normal-case text-slate-400">· internal — never on the invoice or to the customer</span></div>
                    <textarea wire:model="note" rows="3" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Office note, or something for the field…"></textarea>
                    <x-input-error :messages="$errors->get('note')" />
                    <div class="flex gap-2">
                        <x-primary-button>Save note</x-primary-button>
                        <button type="button" wire:click="saveNote('scope_update')" class="rounded-lg bg-accent-500 px-3.5 py-2 text-sm font-semibold text-white">Send scope update</button>
                        <button type="button" wire:click="$set('note', '')" class="px-3 text-sm text-slate-500">Cancel</button>
                        <button type="button" wire:click="invoice" class="ml-auto text-sm font-semibold text-brand-800 hover:underline">Invoice →</button>
                    </div>
                </form>
                <div class="card p-4">
                    <div class="flex items-center justify-between"><div class="text-xs uppercase tracking-wide text-slate-400">Photos &amp; files</div></div>
                    <div class="mt-2 grid grid-cols-4 gap-2">@foreach ($wo->files as $f)<a href="{{ Storage::disk('public')->url($f->path) }}" target="_blank" title="{{ $f->caption }}"><img src="{{ Storage::disk('public')->url($f->path) }}" class="aspect-square rounded-lg object-cover ring-1 ring-slate-200" alt=""></a>@endforeach</div>
                    <form wire:submit="uploadPhoto" class="mt-2 flex items-center gap-2"><input type="file" wire:model="photo" accept="image/*" class="text-xs text-slate-500 file:mr-2 file:rounded-md file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs"><x-secondary-button type="submit" class="!py-1 !px-2 !text-xs">Add</x-secondary-button></form>
                    <x-input-error :messages="$errors->get('photo')" class="mt-1" />
                </div>
            </div>
        </div>
    @endif
</div>
