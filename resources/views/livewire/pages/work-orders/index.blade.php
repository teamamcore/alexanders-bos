<?php

use App\Actions\Intake\CreateJobFromIntake;
use App\Actions\WorkOrders\AddWorkOrder;
use App\Actions\WorkOrders\CreateEmergencyWorkOrder;
use App\Models\WorkOrder;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Work Orders module — the categories here (Unscheduled / On Hold / Scheduled /
 * Billable / Closed) are mirrored exactly by the W.O.S panel on the dispatch
 * board (design notes §2: continuity).
 */
new #[Layout('layouts.app')] class extends Component
{
    public string $tab = 'unscheduled';
    public string $q = '';

    // quick create (+WO)
    public bool $creating = false;
    public string $customer_name = '';
    public string $description = '';
    public string $work_type = '';
    public bool $emergency = false;

    public function mount(): void
    {
        $this->tab = (string) request()->query('tab', 'unscheduled');
    }

    public function with(): array
    {
        $all = WorkOrder::query()->with(['job.customer', 'job.serviceLocation', 'assignee', 'crew'])
            ->when($this->q !== '', fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$this->q}%")
                ->orWhereHas('job', fn ($j) => $j->where('title', 'like', "%{$this->q}%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$this->q}%")))))
            ->latest()->get();

        $groups = $all->groupBy(fn (WorkOrder $wo) => $wo->board());

        return [
            'groups' => $groups,
            'counts' => collect(['unscheduled', 'on_hold', 'scheduled', 'billable', 'closed'])->mapWithKeys(fn ($k) => [$k => $groups->get($k, collect())->count()]),
            'rows' => $groups->get($this->tab, collect())->sortByDesc(fn (WorkOrder $wo) => [$wo->isEmergency(), $wo->scheduled_date?->timestamp ?? 0])->values(),
            'workTypes' => config('batsons.work_types', []),
            'canCreate' => ! auth()->user()->isField(),
        ];
    }

    public function create(): void
    {
        $data = $this->validate([
            'customer_name' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:2000'],
            'work_type' => ['nullable', 'in:'.implode(',', array_keys(config('batsons.work_types', [])))],
        ]);

        if ($this->emergency) {
            $wo = CreateEmergencyWorkOrder::run($data['customer_name'], $data['description'], auth()->user(), null, $data['work_type'] ?: null);
        } else {
            $job = CreateJobFromIntake::run($data['customer_name'], \Illuminate\Support\Str::limit($data['description'], 60, '…'));
            $wo = AddWorkOrder::run($job, null, $data['description'], 'morning', $data['work_type'] ?: null);
        }

        $this->reset('creating', 'customer_name', 'description', 'work_type', 'emergency');
        $this->redirectRoute('work-orders.show', $wo, navigate: false);
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="page-title">{{ term('work_order', true) }}</h2>
            <div class="flex items-center gap-2">
                <x-text-input wire:model.live.debounce.300ms="q" placeholder="Search WO#, customer, title…" class="w-64 text-sm" />
                @if ($canCreate)
                    <x-primary-button type="button" wire:click="$set('creating', true)">+ {{ abbr('work_order') }}</x-primary-button>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-4 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if ($creating)
            <form wire:submit="create" class="card p-5 grid gap-3 sm:grid-cols-6 sm:items-end {{ $emergency ? 'ring-2 ring-red-400' : '' }}">
                <div class="sm:col-span-2"><x-input-label value="Customer" /><x-text-input wire:model="customer_name" class="mt-1 w-full" placeholder="Publix @ 123 McBee" /><x-input-error :messages="$errors->get('customer_name')" class="mt-1" /></div>
                <div class="sm:col-span-2"><x-input-label value="Problem as the customer stated it" /><x-text-input wire:model="description" class="mt-1 w-full" placeholder="Water heater leaking in the break room" /><x-input-error :messages="$errors->get('description')" class="mt-1" /></div>
                <div><x-input-label value="Work type" /><select wire:model="work_type" class="mt-1 w-full rounded-lg border-slate-300 text-sm"><option value="">—</option>@foreach ($workTypes as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                <div class="flex items-center gap-2">
                    <label class="inline-flex items-center gap-1.5 text-sm text-red-700"><input type="checkbox" wire:model.live="emergency" class="rounded border-red-300 text-red-600 focus:ring-red-500"> Emergency</label>
                    <x-primary-button>Create</x-primary-button>
                    <button type="button" wire:click="$set('creating', false)" class="text-sm text-slate-500">Cancel</button>
                </div>
            </form>
        @endif

        {{-- Category tabs — identical to the W.O.S panel --}}
        <div class="flex flex-wrap gap-1 text-sm">
            @foreach (['unscheduled' => 'Unscheduled', 'on_hold' => 'On hold', 'scheduled' => 'Scheduled', 'billable' => 'Billable', 'closed' => 'Paid / closed'] as $k => $label)
                <button wire:click="$set('tab', '{{ $k }}')" class="px-3 py-1.5 rounded-md {{ $tab === $k ? 'bg-brand-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }} <span class="ml-1 text-xs {{ $tab === $k ? 'text-brand-200' : 'text-slate-400' }}">{{ $counts[$k] }}</span></button>
            @endforeach
        </div>

        <div class="card overflow-hidden">
            <table class="table">
                <thead><tr><th>{{ abbr('work_order') }}</th><th>Customer · location</th><th>Problem</th><th>Type</th><th>Team / tech</th><th>Day</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($rows as $wo)
                        <tr class="{{ $wo->isEmergency() ? 'bg-red-50/50' : '' }}">
                            <td><a href="{{ route('work-orders.show', $wo) }}" class="font-semibold text-brand-800 hover:underline">{{ $wo->number ?? 'WO #'.$wo->id }}</a>@if ($wo->isEmergency())<span class="badge-red ml-1">emergency</span>@endif</td>
                            <td><div class="text-slate-900">{{ $wo->job?->customer?->name }}</div><div class="text-xs text-slate-500">{{ $wo->job?->serviceLocation?->label ?? $wo->job?->serviceLocation?->address ?? '—' }}</div></td>
                            <td class="text-slate-600 max-w-xs truncate">{{ \Illuminate\Support\Str::limit($wo->customer_notes ?? $wo->job?->title, 70) }}</td>
                            <td class="text-xs text-slate-600">{{ $wo->work_type ? ($workTypes[$wo->work_type] ?? $wo->work_type) : '—' }}</td>
                            <td class="text-slate-700">{{ $wo->crew?->name ?? $wo->assignee?->name ?? '—' }}</td>
                            <td class="text-slate-700">{{ $wo->scheduled_date?->format('D M j') ?? '—' }}@if ($wo->scheduled_date) <span class="text-xs text-slate-400">{{ config('batsons.lanes')[$wo->lane()] }} · {{ rtrim(rtrim((string) $wo->day_fraction, '0'), '.') }}d</span>@endif</td>
                            <td>
                                <span class="{{ ['inactive' => 'badge-gray', 'active' => 'badge-blue', 'billable' => 'badge-amber', 'closed' => 'badge-green'][$wo->pillStatus()] }}">{{ ucfirst($wo->pillStatus()) }}</span>
                                @if ($wo->on_hold)<span class="pill-hold !ml-1 !py-0.5">hold · {{ $wo->holdReasonLabel() }}</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-10 text-center text-slate-500">Nothing in this category.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
