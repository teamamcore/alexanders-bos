<?php

use App\Actions\WorkOrders\AssignWorkOrderToTeam;
use App\Actions\WorkOrders\CreateEmergencyWorkOrder;
use App\Actions\WorkOrders\RescheduleWorkOrder;
use App\Actions\WorkOrders\ScheduleWorkOrder;
use App\Models\Crew;
use App\Models\User;
use App\Models\WorkOrder;
use App\States\WorkOrder\Completed;
use App\States\WorkOrder\NewWorkOrder;
use App\States\WorkOrder\Scheduled;
use App\Support\SchedulingIndicators;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Dispatch board (design notes §2, scheduling.jpeg): Day / Week / Month;
 * day view = Roster row + All Day / AM / PM lanes, one column per team;
 * right panel W.O.S (Unscheduled / On Hold / Scheduled — identical to the Work
 * Orders module) with +WO quick-create and drag-and-drop onto the lanes;
 * Emergency control at the top (red frame, All-Day default, bumps the team's
 * current work to On Hold).
 */
new #[Layout('layouts.app')] class extends Component
{
    public string $view = 'day';
    public string $date = '';
    public ?int $focus_wo = null;      // "Book now" from a work order
    public ?int $preview_id = null;    // W.O.S card summary popover

    // quick-create (+WO)
    public bool $creating = false;
    public string $customer_name = '';
    public string $title = '';

    // emergency
    public bool $emergency_open = false;
    public string $em_customer = '';
    public string $em_address = '';
    public string $em_description = '';

    // roster editing
    public bool $roster_open = false;
    public string $team_name = '';
    public array $roster = []; // crew_id => [user ids]

    // legacy inline forms (kept — the W.O.S "Schedule" form + tests use them)
    public ?int $scheduling_id = null;
    public ?int $assignee_id = null;
    public string $window = 'morning';
    public ?int $reschedule_id = null;
    public string $new_date = '';
    public string $reason = '';

    public function mount(): void
    {
        $this->date = (string) request()->query('date', today()->toDateString());
        $this->view = in_array(request()->query('view'), ['day', 'week', 'month'], true) ? (string) request()->query('view') : 'day';
        $this->focus_wo = request()->filled('wo') ? (int) request()->query('wo') : null;
        if ($this->focus_wo) {
            $this->preview_id = $this->focus_wo;
        }
    }

    public function with(): array
    {
        $day = Carbon::parse($this->date);
        $teams = Crew::query()->with('members')->orderBy('id')->get();
        foreach ($teams as $t) {
            $this->roster[$t->id] ??= $t->members->pluck('id')->all();
        }

        $all = WorkOrder::query()->with(['job.customer', 'job.serviceLocation', 'assignee', 'crew'])->whereNotState('status', Completed::class)->get();
        $byBoard = $all->groupBy(fn (WorkOrder $wo) => $wo->board());

        // Day grid: team column × lane
        $dayWos = $all->filter(fn (WorkOrder $wo) => $wo->scheduled_date?->isSameDay($day) || ($wo->scheduled_date && (float) $wo->day_fraction > 1 && $day->between($wo->scheduled_date, $wo->scheduled_date->copy()->addDays((int) ceil((float) $wo->day_fraction) - 1))));
        $grid = [];
        foreach ($teams as $t) {
            foreach (array_keys(config('batsons.lanes')) as $lane) {
                $grid[$t->id][$lane] = $dayWos->filter(fn (WorkOrder $wo) => $wo->crew_id === $t->id && $wo->lane() === $lane)->sortByDesc(fn ($wo) => $wo->isEmergency())->values();
            }
        }
        $unassigned = $dayWos->filter(fn (WorkOrder $wo) => ! $wo->crew_id)->values();

        // Week: 7 day columns (no crew lanes — left to our discretion)
        $weekStart = $day->copy()->startOfWeek(Carbon::MONDAY);
        $week = collect(range(0, 6))->map(fn ($i) => ['date' => $weekStart->copy()->addDays($i), 'wos' => $all->filter(fn ($wo) => $wo->scheduled_date?->isSameDay($weekStart->copy()->addDays($i)))->values()]);

        // Month grid
        $monthStart = $day->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $month = collect(range(0, 41))->map(function ($i) use ($monthStart, $all, $day) {
            $d = $monthStart->copy()->addDays($i);

            return ['date' => $d, 'in' => $d->month === $day->month, 'count' => $all->filter(fn ($wo) => $wo->scheduled_date?->isSameDay($d))->count(), 'emergency' => $all->contains(fn ($wo) => $wo->isEmergency() && $wo->scheduled_date?->isSameDay($d))];
        });

        $workType = $this->scheduling_id ? $all->firstWhere('id', $this->scheduling_id)?->work_type : null;
        $assignees = User::where('tenant_id', app('currentTenantId'))->where('is_active', true)->orderBy('name')->get();

        return [
            'day' => $day,
            'teams' => $teams,
            'grid' => $grid,
            'unassigned' => $unassigned,
            'lanes' => config('batsons.lanes'),
            'unscheduled' => $byBoard->get('unscheduled', collect())->sortByDesc(fn ($wo) => $wo->isEmergency())->values(),
            'onHold' => $byBoard->get('on_hold', collect())->values(),
            'scheduled' => $byBoard->get('scheduled', collect())->sortBy('scheduled_date')->values(),
            'preview' => $this->preview_id ? $all->firstWhere('id', $this->preview_id) : null,
            'week' => $week,
            'month' => $month,
            'assignees' => $assignees,
            'indicators' => $this->scheduling_id ? SchedulingIndicators::forUsers($assignees, $workType) : [],
            'workType' => $workType,
            'windows' => config('batsons.schedule_windows'),
            'members' => $assignees,
            'canEdit' => ! auth()->user()->isField(),
        ];
    }

    private function me(): User
    {
        /** @var User $u */
        $u = auth()->user();

        return $u;
    }

    // ---- navigation ---------------------------------------------------------
    public function setView(string $view): void
    {
        $this->view = in_array($view, ['day', 'week', 'month'], true) ? $view : 'day';
    }

    public function shift(int $dir): void
    {
        $d = Carbon::parse($this->date);
        $this->date = (match ($this->view) { 'week' => $d->addWeeks($dir), 'month' => $d->addMonths($dir), default => $d->addDays($dir) })->toDateString();
    }

    public function goto(string $date): void
    {
        $this->date = Carbon::parse($date)->toDateString();
        $this->view = 'day';
    }

    public function today(): void
    {
        $this->date = today()->toDateString();
    }

    // ---- drag & drop ----------------------------------------------------------
    public function dropOn(int $workOrderId, int $crewId, string $lane): void
    {
        abort_if($this->me()->isField(), 403);
        $wo = WorkOrder::findOrFail($workOrderId);
        try {
            AssignWorkOrderToTeam::run($wo, Crew::findOrFail($crewId), $this->date, $lane, $this->me());
        } catch (\DomainException $e) {
            $this->addError('board', $e->getMessage());
        }
        $this->preview_id = null;
    }

    public function unassign(int $workOrderId): void
    {
        abort_if($this->me()->isField(), 403);
        $wo = WorkOrder::findOrFail($workOrderId);
        if ($wo->status instanceof Scheduled) {
            $wo->status->transitionTo(NewWorkOrder::class);
        }
        $wo->forceFill(['crew_id' => null, 'assigned_to' => null, 'scheduled_date' => null])->save();
    }

    public function setDays(int $workOrderId, float $days): void
    {
        $wo = WorkOrder::findOrFail($workOrderId);
        $wo->forceFill(['day_fraction' => max(0.5, min(10, $days))])->save();
    }

    // ---- quick create / emergency ---------------------------------------------
    public function quickCreate(): void
    {
        $this->validate(['customer_name' => ['required', 'string', 'max:160'], 'title' => ['required', 'string', 'max:200']]);
        $job = \App\Actions\Intake\CreateJobFromIntake::run($this->customer_name, $this->title);
        \App\Actions\WorkOrders\AddWorkOrder::run($job, null, $this->title, 'morning');
        $this->reset('creating', 'customer_name', 'title');
    }

    public function createEmergency(): void
    {
        $this->validate(['em_customer' => ['required', 'string', 'max:160'], 'em_description' => ['required', 'string', 'max:2000'], 'em_address' => ['nullable', 'string', 'max:200']]);
        $wo = CreateEmergencyWorkOrder::run($this->em_customer, $this->em_description, $this->me(), $this->em_address ?: null);
        $this->reset('emergency_open', 'em_customer', 'em_address', 'em_description');
        $this->preview_id = $wo->id;
    }

    // ---- roster ---------------------------------------------------------------
    public function addTeam(): void
    {
        $this->validate(['team_name' => ['required', 'string', 'max:40']]);
        Crew::create(['name' => $this->team_name]);
        $this->reset('team_name');
    }

    public function saveRoster(): void
    {
        foreach ($this->roster as $crewId => $userIds) {
            $crew = Crew::find((int) $crewId);
            $crew?->members()->sync(array_map('intval', (array) $userIds));
        }
        $this->roster_open = false;
    }

    // ---- legacy inline schedule / reschedule (W.O.S "Schedule" form) ----------
    public function openSchedule(int $id): void
    {
        $this->reset('assignee_id');
        $this->window = 'morning';
        $this->scheduling_id = $id;
    }

    public function schedule(int $workOrderId): void
    {
        $data = $this->validate([
            'assignee_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'window' => ['required', 'in:'.implode(',', config('batsons.schedule_windows'))],
        ]);
        $wo = WorkOrder::findOrFail($workOrderId);
        $user = User::where('tenant_id', app('currentTenantId'))->findOrFail($data['assignee_id']);
        ScheduleWorkOrder::run($wo, $user, $data['date'], $data['window']);
        $crew = Crew::whereHas('members', fn ($q) => $q->whereKey($user->id))->first();
        if ($crew) {
            $wo->forceFill(['crew_id' => $crew->id])->save();
        }
        $this->reset('scheduling_id', 'assignee_id');
        $this->window = 'morning';
    }

    public function openReschedule(int $id): void
    {
        $this->reset('new_date', 'reason');
        $this->reschedule_id = $id;
    }

    public function reschedule(int $workOrderId): void
    {
        $data = $this->validate(['new_date' => ['required', 'date'], 'reason' => ['nullable', 'string', 'max:500']]);
        RescheduleWorkOrder::run(WorkOrder::findOrFail($workOrderId), $data['new_date'], $data['reason'] ?: null, auth()->id());
        $this->reset('reschedule_id', 'new_date', 'reason');
    }
}; ?>

<div x-data="{ drag: null }">
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="page-title">Hello, {{ explode(' ', auth()->user()->name)[0] }}</h2>
            <div class="flex items-center gap-1 text-sm">
                <button wire:click="shift(-1)" class="rounded-md px-2 py-1 text-slate-500 hover:bg-slate-100">‹</button>
                <button wire:click="today" class="rounded-md px-2 py-1 font-semibold text-slate-800 hover:bg-slate-100">{{ $view === 'month' ? $day->format('F Y') : ($view === 'week' ? $day->copy()->startOfWeek(Carbon::MONDAY)->format('M j').' – '.$day->copy()->endOfWeek(Carbon::SUNDAY)->format('M j') : $day->format('l, M j')) }}</button>
                <button wire:click="shift(1)" class="rounded-md px-2 py-1 text-slate-500 hover:bg-slate-100">›</button>
                <input type="date" wire:model.live="date" class="ml-1 rounded-lg border-slate-300 text-xs py-1">
            </div>
            <div class="pill-track">
                @foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $v => $l)<button wire:click="setView('{{ $v }}')" class="pill-step {{ $view === $v ? 'pill-step-current' : 'pill-step-done' }}">{{ $l }}</button>@endforeach
            </div>
            <div class="ml-auto flex items-center gap-2">
                @if ($canEdit)
                    <button wire:click="$toggle('roster_open')" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Roster</button>
                    <button wire:click="$toggle('emergency_open')" class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700"><x-icon name="emergency" set="standard" class="h-4 w-4" /> Emergency</button>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="px-3 sm:px-4 lg:px-6 py-3 space-y-3">
        @error('board')<div class="flash-err">{{ $message }}</div>@enderror
        @if (session('status'))<div class="flash-ok">{{ session('status') }}</div>@endif

        @if ($emergency_open)
            <form wire:submit="createEmergency" class="card border-2 border-red-400 p-4 grid gap-3 sm:grid-cols-4 sm:items-end">
                <div class="sm:col-span-4 text-sm font-semibold text-red-700">Emergency dispatch — defaults to All Day; dropping it on a team puts their current job on hold.</div>
                <div><x-input-label value="Customer" /><x-text-input wire:model="em_customer" class="mt-1 w-full" /><x-input-error :messages="$errors->get('em_customer')" /></div>
                <div><x-input-label value="Address" /><x-text-input wire:model="em_address" class="mt-1 w-full" /></div>
                <div><x-input-label value="What's happening" /><x-text-input wire:model="em_description" class="mt-1 w-full" placeholder="Main break flooding basement" /><x-input-error :messages="$errors->get('em_description')" /></div>
                <div class="flex gap-2"><button class="rounded-lg bg-red-600 px-3.5 py-2 text-sm font-semibold text-white">Create emergency</button><button type="button" wire:click="$set('emergency_open', false)" class="text-sm text-slate-500">Cancel</button></div>
            </form>
        @endif

        @if ($roster_open)
            <div class="card p-4 space-y-3">
                <div class="font-semibold text-slate-900">Roster <span class="card-note">· pick members → that's a team (5 now, 7–8 max)</span></div>
                <div class="grid gap-3 md:grid-cols-3">
                    @foreach ($teams as $t)
                        <div class="rounded-lg border border-slate-200 p-3">
                            <div class="font-semibold text-slate-800 mb-1">{{ $t->name }}</div>
                            <select wire:model="roster.{{ $t->id }}" multiple size="4" class="w-full rounded-lg border-slate-300 text-sm">@foreach ($members as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select>
                        </div>
                    @endforeach
                    <form wire:submit="addTeam" class="rounded-lg border border-dashed border-slate-300 p-3 flex flex-col gap-2"><x-input-label value="New team" /><x-text-input wire:model="team_name" placeholder="Team 3" /><x-secondary-button type="submit">Add team</x-secondary-button></form>
                </div>
                <div class="flex gap-2"><x-primary-button wire:click="saveRoster">Save roster</x-primary-button><button wire:click="$set('roster_open', false)" class="text-sm text-slate-500">Close</button></div>
            </div>
        @endif

        <div class="grid grid-cols-1 xl:grid-cols-4 gap-3">
            {{-- ================= BOARD ================= --}}
            <div class="xl:col-span-3 min-w-0">
                @if ($view === 'day')
                    <div class="card overflow-x-auto">
                        <table class="w-full table-fixed text-xs">
                            <thead>
                                <tr class="bg-slate-50/80">
                                    <th class="w-16 px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Roster</th>
                                    @foreach ($teams as $t)
                                        <th class="px-2 py-2 text-left align-top">
                                            <div class="font-semibold text-slate-900">{{ $t->name }}</div>
                                            <div class="flex flex-wrap gap-1 mt-1">@forelse ($t->members as $m)<span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-700">{{ $m->name }}</span>@empty<span class="text-[10px] text-amber-600">no members</span>@endforelse</div>
                                        </th>
                                    @endforeach
                                    @if ($teams->isEmpty())<th class="px-2 py-4 text-left text-slate-400 font-normal">No teams yet — open Roster and build one.</th>@endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lanes as $laneKey => $laneLabel)
                                    <tr class="border-t border-slate-100 {{ $laneKey === 'all_day' ? '' : 'h-40' }} align-top">
                                        <td class="px-2 py-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400">{{ $laneLabel }}</td>
                                        @foreach ($teams as $t)
                                            <td class="p-1.5">
                                                <div class="lane {{ $laneKey === 'all_day' ? '!min-h-[2.5rem]' : '!min-h-[9rem]' }}"
                                                     @if ($canEdit)
                                                     @dragover.prevent="$el.classList.add('lane-over')" @dragleave="$el.classList.remove('lane-over')"
                                                     @drop.prevent="$el.classList.remove('lane-over'); $wire.dropOn(parseInt($event.dataTransfer.getData('text/plain')), {{ $t->id }}, '{{ $laneKey }}')"
                                                     @endif>
                                                    @foreach ($grid[$t->id][$laneKey] as $wo)
                                                        <div class="wo-card {{ $wo->isEmergency() ? 'wo-card-emergency' : '' }} {{ $wo->on_hold ? 'wo-card-hold' : '' }}"
                                                             @if ($canEdit) draggable="true" @dragstart="$event.dataTransfer.setData('text/plain', '{{ $wo->id }}')" @endif
                                                             wire:click="$set('preview_id', {{ $wo->id }})">
                                                            <div class="flex items-center justify-between"><span class="font-semibold text-slate-900">{{ $wo->number }}</span><span class="rounded bg-slate-100 px-1 text-[10px] text-slate-600" title="days">{{ rtrim(rtrim((string) $wo->day_fraction, '0'), '.') }}d</span></div>
                                                            <div class="text-slate-800 truncate">{{ $wo->job?->customer?->name }}</div>
                                                            <div class="text-slate-500 truncate">{{ $wo->job?->serviceLocation?->label ?? $wo->job?->serviceLocation?->address ?? '' }}</div>
                                                            @if ($wo->on_hold)<div class="text-[10px] text-amber-700">on hold · {{ $wo->holdReasonLabel() }}</div>@endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                                @if ($unassigned->isNotEmpty())
                                    <tr class="border-t border-slate-100"><td class="px-2 py-2 text-[10px] font-semibold uppercase tracking-wider text-amber-600">No team</td><td colspan="{{ max(1, $teams->count()) }}" class="p-1.5"><div class="flex flex-wrap gap-1.5">@foreach ($unassigned as $wo)<div class="wo-card w-44 {{ $wo->isEmergency() ? 'wo-card-emergency' : '' }}" @if ($canEdit) draggable="true" @dragstart="$event.dataTransfer.setData('text/plain', '{{ $wo->id }}')" @endif wire:click="$set('preview_id', {{ $wo->id }})"><div class="font-semibold">{{ $wo->number }}</div><div class="truncate">{{ $wo->job?->customer?->name }} · {{ $wo->assignee?->name }}</div></div>@endforeach</div></td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                @elseif ($view === 'week')
                    <div class="card overflow-x-auto"><div class="grid grid-cols-7 divide-x divide-slate-100 min-w-[56rem]">
                        @foreach ($week as $col)
                            <div class="min-h-[18rem]">
                                <button wire:click="goto('{{ $col['date']->toDateString() }}')" class="w-full px-2 py-2 text-left text-xs {{ $col['date']->isToday() ? 'bg-brand-50 text-brand-900' : 'bg-slate-50/80 text-slate-600' }}"><div class="font-semibold">{{ $col['date']->format('D') }}</div><div>{{ $col['date']->format('M j') }}</div></button>
                                <div class="p-1.5 space-y-1.5">@foreach ($col['wos'] as $wo)<div class="wo-card {{ $wo->isEmergency() ? 'wo-card-emergency' : '' }} {{ $wo->on_hold ? 'wo-card-hold' : '' }}" wire:click="$set('preview_id', {{ $wo->id }})"><div class="font-semibold">{{ $wo->number }}</div><div class="truncate">{{ $wo->job?->customer?->name }}</div><div class="text-[10px] text-slate-500">{{ $wo->crew?->name ?? $wo->assignee?->name }} · {{ $lanes[$wo->lane()] }}</div></div>@endforeach</div>
                            </div>
                        @endforeach
                    </div></div>
                @else
                    <div class="card p-2"><div class="grid grid-cols-7 gap-1 text-xs">
                        @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d)<div class="px-2 py-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">{{ $d }}</div>@endforeach
                        @foreach ($month as $cell)
                            <button wire:click="goto('{{ $cell['date']->toDateString() }}')" class="h-20 rounded-lg border p-1.5 text-left {{ $cell['in'] ? 'bg-white border-slate-200' : 'bg-slate-50 border-slate-100 text-slate-400' }} {{ $cell['date']->isToday() ? 'ring-2 ring-brand-400' : '' }} {{ $cell['emergency'] ? '!border-red-400' : '' }}">
                                <div class="font-semibold">{{ $cell['date']->day }}</div>
                                @if ($cell['count'])<div class="mt-1 inline-block rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-semibold text-brand-800">{{ $cell['count'] }} WO</div>@endif
                            </button>
                        @endforeach
                    </div></div>
                @endif
            </div>

            {{-- ================= W.O.S PANEL ================= --}}
            <aside class="space-y-3">
                @if ($preview)
                    <div class="card p-3 text-sm {{ $preview->isEmergency() ? 'border-2 border-red-400' : '' }}">
                        <div class="flex items-start justify-between"><div><div class="font-bold text-slate-900">{{ $preview->number }}</div><div class="text-slate-800">{{ $preview->job?->customer?->name }}</div><div class="text-xs text-slate-500">{{ $preview->job?->serviceLocation?->address }}</div></div><button wire:click="$set('preview_id', null)" class="text-slate-400">✕</button></div>
                        <div class="mt-2 text-xs text-slate-700 line-clamp-4">{{ $preview->customer_notes }}</div>
                        <div class="mt-2 flex flex-wrap gap-1 text-[11px]">
                            <span class="badge-gray">{{ ucfirst($preview->pillStatus()) }}</span>
                            @if ($preview->on_hold)<span class="badge-amber">hold · {{ $preview->holdReasonLabel() }}</span>@endif
                            @if ($preview->scheduled_date)<span class="badge-blue">{{ $preview->scheduled_date->format('D M j') }} · {{ $lanes[$preview->lane()] }}</span>@endif
                            @if ($preview->isEmergency())<span class="badge-red">emergency</span>@endif
                        </div>
                        <div class="mt-2 flex items-center gap-2 text-xs">
                            <span class="text-slate-500">days</span>@foreach ([0.5, 1, 2, 3] as $d)<button wire:click="setDays({{ $preview->id }}, {{ $d }})" class="rounded px-1.5 py-0.5 {{ (float) $preview->day_fraction === (float) $d ? 'bg-brand-900 text-white' : 'bg-slate-100 text-slate-700' }}">{{ $d }}</button>@endforeach
                        </div>
                        <div class="mt-3 flex gap-2">
                            <a href="{{ route('work-orders.show', $preview) }}" class="rounded-lg bg-brand-900 px-3 py-1.5 text-xs font-semibold text-white">Open {{ abbr('work_order') }}</a>
                            @if ($canEdit && $preview->crew_id)<button wire:click="unassign({{ $preview->id }})" class="text-xs text-slate-500">unassign</button>@endif
                        </div>
                        <div class="mt-2 text-[11px] text-slate-400">Drag a card from the lists below onto a team lane to schedule it for {{ $day->format('M j') }}.</div>
                    </div>
                @endif

                <div class="card overflow-hidden">
                    <div class="card-head !py-2"><span class="card-title">W.O.S</span>@if ($canEdit)<button wire:click="$toggle('creating')" class="text-xs font-semibold text-brand-800">+ {{ abbr('work_order') }}</button>@endif</div>
                    @if ($creating)
                        <form wire:submit="quickCreate" class="p-3 space-y-2 border-b border-slate-100 bg-slate-50/60"><x-text-input wire:model="customer_name" placeholder="Customer" class="w-full text-sm" /><x-text-input wire:model="title" placeholder="Problem / title" class="w-full text-sm" /><div class="flex gap-2"><x-primary-button class="!py-1 !px-2 !text-xs">Create</x-primary-button><button type="button" wire:click="$set('creating', false)" class="text-xs text-slate-500">Cancel</button></div><x-input-error :messages="$errors->get('customer_name')" /><x-input-error :messages="$errors->get('title')" /></form>
                    @endif

                    @foreach ([['Unscheduled', $unscheduled, 'unscheduled'], ['On hold', $onHold, 'on_hold'], ['Scheduled', $scheduled, 'scheduled']] as [$label, $list, $key])
                        <div class="px-3 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400 flex justify-between"><span>{{ $label }}</span><span>{{ $list->count() }}</span></div>
                        <div class="px-2 pb-2 space-y-1.5 max-h-72 overflow-y-auto">
                            @forelse ($list as $wo)
                                <div class="wo-card {{ $wo->isEmergency() ? 'wo-card-emergency' : '' }} {{ $wo->on_hold ? 'wo-card-hold' : '' }} {{ $preview_id === $wo->id ? 'ring-2 ring-brand-400' : '' }}"
                                     @if ($canEdit) draggable="true" @dragstart="$event.dataTransfer.setData('text/plain', '{{ $wo->id }}')" @endif
                                     wire:click="$set('preview_id', {{ $wo->id }})">
                                    <div class="flex items-center justify-between"><span class="font-semibold text-slate-900">{{ $wo->number }}</span>@if ($wo->scheduled_date)<span class="text-[10px] text-slate-500">{{ $wo->scheduled_date->format('D M j') }}</span>@endif</div>
                                    <div class="truncate text-slate-800">{{ $wo->job?->customer?->name }}</div>
                                    <div class="truncate text-slate-500">{{ $wo->job?->serviceLocation?->label ?? $wo->job?->serviceLocation?->address ?? '' }}</div>
                                    @if ($key === 'unscheduled' && $canEdit)
                                        @if ($scheduling_id === $wo->id)
                                            <form wire:submit="schedule({{ $wo->id }})" class="mt-2 space-y-1" @click.stop>
                                                <select wire:model="assignee_id" class="w-full rounded border-slate-300 text-[11px] py-1"><option value="">Tech…</option>@foreach ($assignees as $u)@php $tag = isset($indicators[$u->id]) ? SchedulingIndicators::label($indicators[$u->id], $workType) : ''; @endphp<option value="{{ $u->id }}">{{ $u->name }}{{ $tag ? ' — '.$tag : '' }}</option>@endforeach</select>
                                                <div class="flex gap-1"><select wire:model="window" class="rounded border-slate-300 text-[11px] py-1">@foreach ($windows as $w)<option value="{{ $w }}">{{ str_replace('_', ' ', $w) }}</option>@endforeach</select><x-primary-button class="!py-1 !px-2 !text-[11px]">Save</x-primary-button><button type="button" wire:click="$set('scheduling_id', null)" class="text-[11px] text-slate-500">✕</button></div>
                                                <x-input-error :messages="$errors->get('assignee_id')" />
                                            </form>
                                        @else
                                            <button wire:click.stop="openSchedule({{ $wo->id }})" class="mt-1 text-[11px] font-medium text-brand-700">Schedule for {{ $day->format('M j') }} →</button>
                                        @endif
                                    @endif
                                    @if ($key === 'scheduled' && $canEdit)
                                        @if ($reschedule_id === $wo->id)
                                            <form wire:submit="reschedule({{ $wo->id }})" class="mt-2 flex flex-wrap gap-1" @click.stop><x-text-input type="date" wire:model="new_date" class="!py-1 text-[11px]" /><x-text-input wire:model="reason" placeholder="Reason" class="!py-1 text-[11px] w-24" /><x-primary-button class="!py-1 !px-2 !text-[11px]">Save</x-primary-button><button type="button" wire:click="$set('reschedule_id', null)" class="text-[11px] text-slate-500">✕</button></form>
                                        @else
                                            <button wire:click.stop="openReschedule({{ $wo->id }})" class="mt-1 text-[11px] text-slate-500">reschedule</button>
                                        @endif
                                    @endif
                                </div>
                            @empty
                                <div class="px-2 py-2 text-[11px] text-slate-400">None.</div>
                            @endforelse
                        </div>
                    @endforeach
                </div>
            </aside>
        </div>
    </div>
</div>
