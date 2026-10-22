<?php

use App\Actions\Communication\NotifyCustomer;
use App\Actions\Communication\SendCustomerMessage;
use App\Actions\WorkOrders\AddWorkOrder;
use App\Models\AuditLog;
use App\Models\CustomerMessage;
use App\Models\EquipmentUsage;
use App\Models\Job;
use App\Models\StockAdjustment;
use App\Models\TimeEntry;
use App\Models\WorkOrder;
use App\Models\WorkOrderNote;
use App\Support\WorkOrderBilling;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * The JOB PARENT (design notes §1, promoted one level): the job owns its work
 * orders, and everything about the engagement lives here — customer, every
 * work order (children), the combined comms streams, activity, and the
 * billables rollup. Positions mirror the work-order parent for continuity:
 * customer top-left, children centre, comms right, billables below.
 */
new #[Layout('layouts.app')] class extends Component
{
    public Job $job;

    // Add work order (kept stable — JobSliceTest pins these)
    public string $wo_number = '';
    public string $customer_notes = '';
    public string $schedule_window = 'morning';
    public string $work_type = '';

    // Job-level customer comms (+ per-WO sendMessage kept for the field flow)
    public string $customer_text = '';
    public string $message_text = '';
    public string $eta = '';
    public ?string $flash = null;
    public bool $showAllComms = false;
    public bool $showAllActivity = false;

    public function mount(Job $job): void
    {
        // Route-model binding is tenant-scoped, so cross-tenant ids 404.
        $this->job = $job;
    }

    public function with(): array
    {
        $job = $this->job->fresh()->load(['customer.contacts', 'customer.corpPoc', 'serviceLocation.contact']);
        $this->job = $job;
        $user = auth()->user();
        $isField = $user->isField();

        $workOrders = $job->workOrders()->with(['assignee', 'crew', 'invoices', 'files', 'tags'])->latest()->get();
        $woIds = $workOrders->pluck('id');
        $woNumbers = $workOrders->mapWithKeys(fn (WorkOrder $w) => [$w->id => $w->number ?? 'WO #'.$w->id]);

        $external = CustomerMessage::query()
            ->where(fn ($q) => $q->where('job_id', $job->id)->orWhereIn('work_order_id', $woIds))
            ->with('sender')->latest()->take($this->showAllComms ? 100 : 5)->get()->reverse()->values();
        $internal = WorkOrderNote::query()->whereIn('work_order_id', $woIds)
            ->whereIn('kind', ['tech_note', 'field_finding', 'scope_update', 'office_note'])
            ->with('author')->latest()->take($this->showAllComms ? 100 : 5)->get()->reverse()->values();

        $activity = collect()
            ->merge(WorkOrderNote::query()->whereIn('work_order_id', $woIds)->where('kind', 'system')->latest()->take(30)->get()
                ->map(fn ($n) => ['at' => $n->created_at, 'text' => ($woNumbers[$n->work_order_id] ?? '').' — '.$n->body]))
            ->merge(AuditLog::query()->where('auditable_type', WorkOrder::class)->whereIn('auditable_id', $woIds)->latest()->take(30)->get()
                ->map(fn ($a) => ['at' => $a->created_at, 'text' => ($woNumbers[$a->auditable_id] ?? '').' — '.($a->action === 'updated' ? 'Updated '.implode(', ', array_keys($a->changes['after'] ?? [])) : ucfirst($a->action))]))
            ->merge(AuditLog::query()->where('auditable_type', Job::class)->where('auditable_id', $job->id)->latest()->take(10)->get()
                ->map(fn ($a) => ['at' => $a->created_at, 'text' => 'Job '.($a->action === 'updated' ? 'updated: '.implode(', ', array_keys($a->changes['after'] ?? [])) : $a->action)]))
            ->sortByDesc('at')->values();

        // Billables rollup: sum the per-work-order tallies (office/admin only).
        $tally = null;
        if (! $isField) {
            $tally = $workOrders->reduce(function (array $sum, WorkOrder $w): array {
                foreach (WorkOrderBilling::tally($w) as $k => $v) {
                    $sum[$k] = round(($sum[$k] ?? 0) + $v, 2);
                }

                return $sum;
            }, []);
        }

        return [
            'workOrders' => $workOrders,
            'woNumbers' => $woNumbers,
            'isField' => $isField,
            'isAdmin' => $user->isAdministrator(),
            'tally' => $tally,
            'external' => $external,
            'internal' => $internal,
            'activity' => $activity,
            'lastEvent' => $activity->first(),
            'timeEntries' => $isField ? collect() : TimeEntry::query()->whereIn('work_order_id', $woIds)->with('user')->latest('started_at')->take(40)->get(),
            'materials' => $isField ? collect() : StockAdjustment::query()->whereIn('work_order_id', $woIds)->where('reason', 'used_on_job')->with('part')->latest()->take(40)->get(),
            'equipment' => $isField ? collect() : EquipmentUsage::query()->whereIn('work_order_id', $woIds)->with('equipment')->latest()->take(40)->get(),
            'invoices' => $workOrders->flatMap->invoices,
            'files' => $workOrders->flatMap->files->sortByDesc('id')->take(12)->values(),
            'recipient' => $job->customer ? SendCustomerMessage::recipient($job->customer) : null,
            'windows' => config('batsons.schedule_windows'),
            'workTypes' => config('batsons.work_types', []),
            'activeCount' => $workOrders->filter(fn (WorkOrder $w) => $w->pillStatus() === 'active' && ! $w->on_hold)->count(),
            'holdCount' => $workOrders->filter(fn (WorkOrder $w) => $w->on_hold)->count(),
            'doneCount' => $workOrders->filter(fn (WorkOrder $w) => in_array($w->pillStatus(), ['billable', 'closed'], true))->count(),
        ];
    }

    private function me(): \App\Models\User
    {
        /** @var \App\Models\User $u */
        $u = auth()->user();

        return $u;
    }

    public function addWorkOrder(): void
    {
        $validated = $this->validate([
            'wo_number' => ['nullable', 'string', 'max:50'],
            'customer_notes' => ['nullable', 'string', 'max:2000'],
            'schedule_window' => ['required', 'in:'.implode(',', config('batsons.schedule_windows'))],
            'work_type' => ['nullable', 'in:'.implode(',', array_keys(config('batsons.work_types', [])))],
        ]);

        AddWorkOrder::run(
            $this->job,
            $validated['wo_number'] ?: null,
            $validated['customer_notes'] ?: null,
            $validated['schedule_window'],
            $validated['work_type'] ?: null,
        );

        $this->flash = 'Work order added to this job.';
        $this->reset('wo_number', 'customer_notes', 'work_type');
        $this->schedule_window = 'morning';
    }

    // ---- Job-level customer comms (threads onto the latest work order) -----
    public function sendCustomer(): void
    {
        $this->validate(['customer_text' => ['required', 'string', 'max:600']]);
        $wo = $this->job->workOrders()->latest()->first();
        $m = SendCustomerMessage::run($this->job->customer, null, $this->customer_text, $this->me(), $wo);
        $this->flash = $m ? ucfirst($m->channel)." {$m->status} to {$m->to}." : 'No phone or email on file for this customer.';
        $this->reset('customer_text');
    }

    public function sendMessage(int $workOrderId): void
    {
        $this->validate(['message_text' => ['required', 'string', 'max:600']]);
        $wo = WorkOrder::findOrFail($workOrderId);
        $m = SendCustomerMessage::run($this->job->customer, null, $this->message_text, $this->me(), $wo);
        $this->flash = $m ? ucfirst($m->channel)." {$m->status} to {$m->to}." : 'No phone or email on file for this customer.';
        $this->reset('message_text');
    }

    public function quickMessage(int $workOrderId, string $key): void
    {
        $wo = WorkOrder::findOrFail($workOrderId);
        $vars = $key === 'on_the_way' && trim($this->eta) !== '' ? ['eta' => 'ETA '.trim($this->eta).'.'] : [];
        $m = NotifyCustomer::run($wo, $key, $this->me(), $vars);
        $this->flash = $m ? ucfirst($m->channel)." {$m->status} to {$m->to}." : 'No phone or email on file for this customer.';
        $this->reset('eta');
    }
}; ?>

<div>
    @php
        $customer = $job->customer;
        $site = $job->serviceLocation;
        $primary = $site?->contact ?? $customer?->corpPoc ?? $customer?->contacts->first();
        $secondary = $customer?->contacts->first(fn ($c) => $primary && $c->id !== $primary->id);
    @endphp

    {{-- ============================ TECH / FIELD VIEW ============================ --}}
    @if ($isField)
        <div class="max-w-xl mx-auto px-3 py-3 space-y-3">
            <div class="flex items-center justify-between rounded-xl bg-brand-950 px-4 py-3 text-white">
                <div class="flex items-center gap-2"><x-application-logo class="h-7 w-7 text-white" /><span class="text-sm font-semibold">{{ now()->format('n/j/y g:ia') }}</span></div>
                <span class="text-sm font-semibold">{{ $job->number ?? 'Job #'.$job->id }}</span>
            </div>
            <div class="card p-4">
                <div class="text-lg font-bold text-slate-900">{{ $job->title ?? 'Job' }}</div>
                <div class="text-slate-800 font-medium">{{ $customer?->name }}</div>
                <div class="text-sm text-slate-600">{{ $site?->label ? $site->label.' · ' : '' }}{{ $site?->address }}</div>
            </div>
            <div class="card divide-y divide-slate-100">
                @forelse ($workOrders as $wo)
                    <a href="{{ route('work-orders.show', $wo) }}" wire:navigate class="flex items-center justify-between px-4 py-3 hover:bg-slate-50">
                        <span class="font-medium text-slate-900">{{ $wo->number ?? 'WO #'.$wo->id }}@if ($wo->on_hold)<span class="pill-hold !ml-1 !py-0.5">hold</span>@endif</span>
                        <span class="text-xs text-slate-500">{{ $wo->scheduled_date?->format('D, M j') ?? str_replace('_', ' ', $wo->schedule_window) }}</span>
                    </a>
                @empty
                    <div class="px-4 py-6 text-center text-sm text-slate-400">No {{ strtolower(term('work_order', true)) }} on this job yet.</div>
                @endforelse
            </div>
        </div>

    {{-- ============================ OFFICE / DESKTOP VIEW ============================ --}}
    @else
        <x-slot name="header">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="page-title">{{ $job->number ?? 'Job #'.$job->id }} <span class="text-slate-400 font-normal">· {{ $job->title ?? 'Untitled job' }}</span></h2>
                <span class="badge-green">{{ $job->status ?? 'open' }}</span>
                <span class="text-sm text-slate-500">{{ $workOrders->count() }} {{ strtolower(term('work_order', true)) }} · {{ $activeCount }} active · {{ $holdCount }} on hold · {{ $doneCount }} billed/closed</span>
                <div class="ml-auto flex items-center gap-2">
                    <a href="{{ route('reports.job', $job->id) }}" class="text-sm font-medium text-brand-700 hover:underline">Cost sheet</a>
                    @if ($isAdmin && $tally)<span class="inline-flex items-center rounded-lg bg-brand-900 px-3 py-1.5 text-sm font-semibold text-white">${{ number_format($tally['total'] ?? 0, 0) }} tally @if (($tally['invoiced'] ?? 0) > 0)<span class="ml-1 text-xs font-normal text-brand-200">· ${{ number_format($tally['invoiced'], 0) }} invoiced</span>@endif</span>@endif
                    <a href="{{ route('jobs.index') }}" wire:navigate class="text-sm font-medium text-brand-700 hover:text-brand-900 hover:underline">&larr; All jobs</a>
                </div>
            </div>
        </x-slot>

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-4 space-y-4">
            @if ($flash)<div class="flash-ok">{{ $flash }}</div>@endif

            {{-- Row 1: customer (left) · work orders, the children (centre) · comms + activity (right) --}}
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
                        <div class="mt-3 text-xs text-slate-500">Reach: {{ $recipient ? $recipient['channel'].' · '.$recipient['to'] : 'nothing on file' }}</div>
                    </div>
                    @if ($files->isNotEmpty())
                        <div class="card p-4">
                            <div class="text-xs uppercase tracking-wide text-slate-400 mb-2">Photos &amp; files <span class="normal-case">· across all {{ strtolower(term('work_order', true)) }}</span></div>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach ($files as $f)
                                    <a href="{{ Storage::disk('public')->url($f->path) }}" target="_blank" title="{{ $f->caption }}"><img src="{{ Storage::disk('public')->url($f->path) }}" class="aspect-square rounded-lg object-cover ring-1 ring-slate-200" alt=""></a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="lg:col-span-5 space-y-4">
                    <div class="card overflow-hidden">
                        <div class="card-head"><span class="card-title">{{ term('work_order', true) }}</span><span class="card-note">the children of this job — click through for the full parent view of each</span></div>
                        <ul class="divide-y divide-slate-100">
                            @forelse ($workOrders as $wo)
                                <li class="px-4 py-3 hover:bg-slate-50">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('work-orders.show', $wo) }}" class="font-semibold text-brand-800 hover:underline">{{ $wo->number ?? 'WO #'.$wo->id }}</a>
                                            @if ($wo->isEmergency())<span class="badge-red">emergency</span>@endif
                                            <span class="badge-blue">{{ $wo->status->label() }}</span>
                                            @if ($wo->on_hold)<span class="pill-hold !py-0.5"><x-icon name="hold" set="standard" class="h-3 w-3" /> {{ $wo->holdReasonLabel() }}</span>@endif
                                        </div>
                                        <div class="flex items-center gap-1">
                                            @if ($wo->status instanceof \App\States\WorkOrder\Scheduled && ! $wo->on_hold)
                                                <button wire:click="quickMessage({{ $wo->id }}, 'on_the_way')" class="rounded bg-brand-50 px-2 py-0.5 text-[11px] font-medium text-brand-800 hover:bg-brand-100">on the way</button>
                                            @endif
                                            <a href="{{ route('schedule.index', ['wo' => $wo->id]) }}" class="rounded bg-accent-50 px-2 py-0.5 text-[11px] font-semibold text-accent-700 hover:bg-accent-100">book</a>
                                        </div>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-3 text-xs text-slate-500">
                                        <span>{{ $wo->scheduled_date?->format('D, M j') ?? 'unscheduled' }} · {{ str_replace('_', ' ', $wo->schedule_window) }}</span>
                                        <span>{{ $wo->crew?->name ?? $wo->assignee?->name ?? 'unassigned' }}</span>
                                        @if ($wo->customer_notes)<span class="text-slate-400 truncate max-w-[18rem]">{{ \Illuminate\Support\Str::limit($wo->customer_notes, 60) }}</span>@endif
                                    </div>
                                </li>
                            @empty
                                <li class="px-4 py-8 text-center text-sm text-slate-400">No {{ strtolower(term('work_order', true)) }} yet — add the first one below.</li>
                            @endforelse
                        </ul>
                        <form wire:submit="addWorkOrder" class="border-t border-slate-100 bg-slate-50/60 p-4 grid gap-3 sm:grid-cols-4 sm:items-end">
                            <div><x-input-label for="wo_number" value="WO number" /><x-text-input wire:model="wo_number" id="wo_number" class="block mt-1 w-full" placeholder="auto" /></div>
                            <div class="sm:col-span-2"><x-input-label for="customer_notes" value="Description of issue" /><x-text-input wire:model="customer_notes" id="customer_notes" class="block mt-1 w-full" placeholder="What the customer reported" /></div>
                            <div><x-input-label for="work_type" value="Work type" />
                                <select wire:model="work_type" id="work_type" class="block mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm"><option value="">—</option>@foreach ($workTypes as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select>
                            </div>
                            <div><x-input-label for="schedule_window" value="Window" />
                                <select wire:model="schedule_window" id="schedule_window" class="block mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm">@foreach ($windows as $w)<option value="{{ $w }}">{{ str_replace('_', ' ', $w) }}</option>@endforeach</select>
                            </div>
                            <div class="sm:col-span-4"><x-primary-button>Add {{ strtolower(term('work_order')) }}</x-primary-button></div>
                        </form>
                    </div>
                    @if ($invoices->isNotEmpty())
                        <div class="card p-4 text-xs text-slate-500">Invoices on this job: @foreach ($invoices as $inv)<span class="badge-gray mr-1">{{ $inv->number }} · {{ $inv->status }}@if ($isAdmin) · ${{ number_format((float) $inv->total, 0) }}@endif</span>@endforeach</div>
                    @endif
                </div>

                <div class="lg:col-span-4 space-y-4">
                    <div class="card p-4">
                        <div class="flex items-center justify-between mb-2"><div class="text-xs uppercase tracking-wide text-slate-400">Comms · whole job</div><button wire:click="$toggle('showAllComms')" class="text-xs text-brand-700">{{ $showAllComms ? 'last 5' : 'expand' }}</button></div>
                        <div class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">External · customer</div>
                        <div class="mt-1 space-y-1.5 max-h-56 overflow-y-auto pr-1">
                            @forelse ($external as $m)
                                <div class="flex {{ $m->direction === 'inbound' ? 'justify-start' : 'justify-end' }}"><div class="max-w-[85%] rounded-2xl px-3 py-1.5 text-sm {{ $m->direction === 'inbound' ? 'bg-slate-100 text-slate-800 rounded-bl-sm' : 'bg-brand-900 text-white rounded-br-sm' }}">{{ $m->body }}<div class="text-[10px] {{ $m->direction === 'inbound' ? 'text-slate-400' : 'text-brand-200' }}">{{ $m->sender?->name ?? ($m->direction === 'inbound' ? 'customer' : 'auto') }}@if ($m->work_order_id) · {{ $woNumbers[$m->work_order_id] ?? '' }}@endif · {{ $m->created_at?->format('M j g:ia') }}</div></div></div>
                            @empty
                                <div class="text-sm text-slate-400">No customer messages on this job yet.</div>
                            @endforelse
                        </div>
                        <form wire:submit="sendCustomer" class="mt-2 flex gap-1"><x-text-input wire:model="customer_text" placeholder="Message the customer…" class="grow text-sm" /><x-primary-button class="!px-2"><x-icon name="send" set="standard" class="h-4 w-4" /></x-primary-button></form>
                        <x-input-error :messages="$errors->get('customer_text')" class="mt-1" />

                        <div class="mt-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">Internal · office ↔ field</div>
                        <ul class="mt-1 space-y-1 text-sm max-h-40 overflow-y-auto">
                            @forelse ($internal as $n)<li class="text-slate-700"><span class="text-xs text-slate-400">{{ $woNumbers[$n->work_order_id] ?? '' }} · {{ $n->author?->name }} · {{ $n->created_at?->format('M j g:ia') }}</span> — {{ $n->body }}@if ($n->kind === 'scope_update')<span class="badge-amber ml-1">scope update</span>@endif</li>@empty<li class="text-slate-400">No internal notes yet.</li>@endforelse
                        </ul>
                    </div>
                    <div class="card p-4">
                        <div class="flex items-center justify-between"><div class="text-xs uppercase tracking-wide text-slate-400">Activity · whole job</div><button wire:click="$toggle('showAllActivity')" class="text-xs text-brand-700">{{ $showAllActivity ? 'collapse' : 'expand' }}</button></div>
                        @if ($lastEvent)<div class="mt-1 text-sm text-slate-800">{{ $lastEvent['text'] }} <span class="text-xs text-slate-400">· {{ $lastEvent['at']?->diffForHumans() }}</span></div>@else<div class="mt-1 text-sm text-slate-400">No activity yet.</div>@endif
                        @if ($showAllActivity)<ul class="mt-2 space-y-1 text-xs text-slate-600 max-h-60 overflow-y-auto">@foreach ($activity as $a)<li>{{ $a['at']?->format('M j g:ia') }} — {{ $a['text'] }}</li>@endforeach</ul>@endif
                    </div>
                </div>
            </div>

            {{-- Row 2: Billables rollup across every work order on the job --}}
            @if ($tally)
                <div class="card p-4">
                    <div class="flex items-center justify-between mb-3"><div class="font-semibold text-slate-900">Billables · whole job</div>@if ($isAdmin)<span class="text-sm font-semibold text-emerald-700">${{ number_format($tally['total'] ?? 0, 2) }} tally · ${{ number_format($tally['uninvoiced'] ?? 0, 2) }} uninvoiced</span>@endif</div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="rounded-lg border border-slate-200 p-3">
                            <div class="text-xs uppercase tracking-wide text-slate-500">Hours</div>
                            <div class="mt-1 text-lg font-bold text-slate-900">{{ $tally['hours'] ?? 0 }} h <span class="text-xs font-normal text-slate-500">· {{ $tally['regular_hours'] ?? 0 }} reg · {{ $tally['ot_hours'] ?? 0 }} OT</span>@if ($isAdmin)<span class="ml-2 text-sm font-semibold text-emerald-700">${{ number_format($tally['labor'] ?? 0, 0) }}</span>@endif</div>
                            <ul class="mt-2 text-xs divide-y divide-slate-100 max-h-36 overflow-y-auto">@foreach ($timeEntries as $e)<li class="py-1 flex justify-between"><span class="text-slate-700">{{ $e->user?->name }} <span class="text-slate-400">{{ $woNumbers[$e->work_order_id] ?? '' }} · {{ $e->started_at->format('D M j') }}</span></span><span class="text-slate-600">{{ $e->ended_at ? number_format((float) $e->started_at->diffInHours($e->ended_at), 2).' h' : 'running' }}</span></li>@endforeach</ul>
                        </div>
                        <div class="rounded-lg border border-slate-200 p-3">
                            <div class="text-xs uppercase tracking-wide text-slate-500">Materials</div>
                            <div class="mt-1 text-lg font-bold text-slate-900">{{ $tally['materials_qty'] ?? 0 }} <span class="text-xs font-normal text-slate-500">items</span>@if ($isAdmin)<span class="ml-2 text-sm font-semibold text-emerald-700">${{ number_format($tally['materials'] ?? 0, 0) }}</span>@endif</div>
                            <ul class="mt-2 text-xs divide-y divide-slate-100 max-h-36 overflow-y-auto">@foreach ($materials as $m)<li class="py-1 flex justify-between"><span class="text-slate-700">{{ $m->part?->name }} <span class="text-slate-400">{{ $woNumbers[$m->work_order_id] ?? '' }}</span></span><span class="text-slate-600">× {{ abs($m->quantity) }}</span></li>@endforeach</ul>
                        </div>
                        <div class="rounded-lg border border-slate-200 p-3">
                            <div class="text-xs uppercase tracking-wide text-slate-500">Equipment</div>
                            <div class="mt-1 text-lg font-bold text-slate-900">{{ $tally['equipment_qty'] ?? 0 }} <span class="text-xs font-normal text-slate-500">uses</span>@if ($isAdmin)<span class="ml-2 text-sm font-semibold text-emerald-700">${{ number_format($tally['equipment'] ?? 0, 0) }}</span>@endif</div>
                            <ul class="mt-2 text-xs divide-y divide-slate-100 max-h-36 overflow-y-auto">@foreach ($equipment as $u)<li class="py-1 flex justify-between"><span class="text-slate-700">{{ $u->equipment?->name }} <span class="text-slate-400">{{ $woNumbers[$u->work_order_id] ?? '' }}</span></span><span class="text-slate-600">{{ $u->quantity }} {{ $u->unit }}</span></li>@endforeach</ul>
                        </div>
                    </div>
                    <div class="mt-2 text-[11px] text-slate-400">Rolled up from every {{ strtolower(term('work_order')) }} on the job. Clock in, add materials, and invoice from the individual {{ strtolower(term('work_order')) }} pages.</div>
                </div>
            @endif
        </div>
    @endif
</div>
