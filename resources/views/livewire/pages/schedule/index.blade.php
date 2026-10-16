<?php

use App\Actions\WorkOrders\RescheduleWorkOrder;
use App\Actions\WorkOrders\ScheduleWorkOrder;
use App\Models\User;
use App\Models\WorkOrder;
use App\States\WorkOrder\NewWorkOrder;
use App\States\WorkOrder\Scheduled;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $scheduling_id = null;
    public ?int $assignee_id = null;
    public string $date = '';
    public string $window = 'morning';

    public ?int $reschedule_id = null;
    public string $new_date = '';
    public string $reason = '';

    public function with(): array
    {
        return [
            'unscheduled' => WorkOrder::whereState('status', NewWorkOrder::class)
                ->with('job.customer')->latest()->get(),
            'scheduled' => WorkOrder::whereState('status', Scheduled::class)
                ->with('job.customer', 'assignee')->orderBy('scheduled_date')->get(),
            'assignees' => User::where('tenant_id', app('currentTenantId'))->orderBy('name')->get(),
            'windows' => config('batsons.schedule_windows'),
        ];
    }

    public function openSchedule(int $id): void
    {
        $this->reset('assignee_id', 'date');
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

        $this->reset('scheduling_id', 'assignee_id', 'date');
        $this->window = 'morning';
    }

    public function openReschedule(int $id): void
    {
        $this->reset('new_date', 'reason');
        $this->reschedule_id = $id;
    }

    public function reschedule(int $workOrderId): void
    {
        $data = $this->validate([
            'new_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $wo = WorkOrder::findOrFail($workOrderId);
        RescheduleWorkOrder::run($wo, $data['new_date'], $data['reason'] ?: null, auth()->id());

        $this->reset('reschedule_id', 'new_date', 'reason');
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Scheduling & Dispatch') }}</h2>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
        {{-- Unscheduled --}}
        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            <h3 class="font-semibold text-gray-900 px-6 py-4 border-b">Unscheduled work orders</h3>
            <table class="min-w-full divide-y divide-gray-200">
                <tbody class="divide-y divide-gray-200">
                    @forelse ($unscheduled as $wo)
                        <tr class="align-top">
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900">{{ $wo->number ?? 'WO' }}</div>
                                <div class="text-sm text-gray-500">{{ $wo->job?->customer?->name }} — {{ $wo->job?->title }}</div>
                            </td>
                            <td class="px-6 py-4 w-2/3">
                                @if ($scheduling_id === $wo->id)
                                    <form wire:submit="schedule({{ $wo->id }})" class="grid gap-3 sm:grid-cols-4 sm:items-end">
                                        <div>
                                            <x-input-label :value="__('Assignee')" />
                                            <select wire:model="assignee_id" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                                <option value="">—</option>
                                                @foreach ($assignees as $u)
                                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                                @endforeach
                                            </select>
                                            <x-input-error :messages="$errors->get('assignee_id')" class="mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label :value="__('Date')" />
                                            <x-text-input type="date" wire:model="date" class="block mt-1 w-full" />
                                            <x-input-error :messages="$errors->get('date')" class="mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label :value="__('Window')" />
                                            <select wire:model="window" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                                @foreach ($windows as $w)
                                                    <option value="{{ $w }}">{{ str_replace('_', ' ', $w) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="flex gap-2">
                                            <x-primary-button>{{ __('Save') }}</x-primary-button>
                                            <button type="button" wire:click="$set('scheduling_id', null)" class="text-sm text-gray-500">Cancel</button>
                                        </div>
                                    </form>
                                @else
                                    <button wire:click="openSchedule({{ $wo->id }})" class="text-indigo-600 hover:underline text-sm font-medium">Schedule →</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td class="px-6 py-8 text-center text-gray-500">Nothing waiting to be scheduled.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Scheduled --}}
        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            <h3 class="font-semibold text-gray-900 px-6 py-4 border-b">Scheduled</h3>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <tr><th class="px-6 py-3">WO</th><th class="px-6 py-3">Date</th><th class="px-6 py-3">Window</th><th class="px-6 py-3">Assignee</th><th class="px-6 py-3"></th></tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($scheduled as $wo)
                        <tr>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900">{{ $wo->number ?? 'WO' }}</div>
                                <div class="text-sm text-gray-500">{{ $wo->job?->customer?->name }}</div>
                            </td>
                            <td class="px-6 py-4 text-gray-700">{{ $wo->scheduled_date?->toFormattedDateString() }}</td>
                            <td class="px-6 py-4 text-gray-700">{{ str_replace('_', ' ', $wo->schedule_window) }}</td>
                            <td class="px-6 py-4 text-gray-700">{{ $wo->assignee?->name ?? 'Unassigned' }}</td>
                            <td class="px-6 py-4">
                                @if ($reschedule_id === $wo->id)
                                    <form wire:submit="reschedule({{ $wo->id }})" class="flex flex-wrap gap-2 items-end">
                                        <x-text-input type="date" wire:model="new_date" class="w-40" />
                                        <x-text-input wire:model="reason" placeholder="Reason" class="w-48" />
                                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                                        <button type="button" wire:click="$set('reschedule_id', null)" class="text-sm text-gray-500">Cancel</button>
                                        <x-input-error :messages="$errors->get('new_date')" class="w-full mt-1" />
                                    </form>
                                @else
                                    <button wire:click="openReschedule({{ $wo->id }})" class="text-indigo-600 hover:underline text-sm">Reschedule</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">Nothing scheduled yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
