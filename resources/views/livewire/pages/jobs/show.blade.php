<?php

use App\Actions\WorkOrders\AddWorkOrder;
use App\Models\Job;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Job $job;
    public string $wo_number = '';
    public string $customer_notes = '';
    public string $schedule_window = 'morning';

    public function mount(Job $job): void
    {
        // Route-model binding is tenant-scoped, so cross-tenant ids 404.
        $this->job = $job->load('customer', 'serviceLocation');
    }

    public function with(): array
    {
        return [
            'workOrders' => $this->job->workOrders()->with('assignee')->latest()->get(),
            'windows' => config('batsons.schedule_windows'),
        ];
    }

    public function addWorkOrder(): void
    {
        $validated = $this->validate([
            'wo_number' => ['nullable', 'string', 'max:50'],
            'customer_notes' => ['nullable', 'string', 'max:2000'],
            'schedule_window' => ['required', 'in:'.implode(',', config('batsons.schedule_windows'))],
        ]);

        AddWorkOrder::run(
            $this->job,
            $validated['wo_number'] ?: null,
            $validated['customer_notes'] ?: null,
            $validated['schedule_window'],
        );

        $this->reset('wo_number', 'customer_notes');
        $this->schedule_window = 'morning';
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $job->title ?? 'Job' }} <span class="text-gray-400">· {{ $job->customer?->name }}</span>
            </h2>
            <a href="{{ route('jobs.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">&larr; All jobs</a>
        </div>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        {{-- Add work order --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="font-semibold text-gray-900 mb-4">Add work order</h3>
            <form wire:submit="addWorkOrder" class="grid gap-4 sm:grid-cols-4 sm:items-end">
                <div>
                    <x-input-label for="wo_number" :value="__('WO number')" />
                    <x-text-input wire:model="wo_number" id="wo_number" class="block mt-1 w-full" placeholder="WO-1002" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="customer_notes" :value="__('Customer notes')" />
                    <x-text-input wire:model="customer_notes" id="customer_notes" class="block mt-1 w-full" placeholder="What the customer reported" />
                </div>
                <div>
                    <x-input-label for="schedule_window" :value="__('Window')" />
                    <select wire:model="schedule_window" id="schedule_window" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                        @foreach ($windows as $w)
                            <option value="{{ $w }}">{{ str_replace('_', ' ', $w) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-4">
                    <x-primary-button>{{ __('Add work order') }}</x-primary-button>
                </div>
            </form>
        </div>

        {{-- Work orders --}}
        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <th class="px-6 py-3">WO #</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Window</th>
                        <th class="px-6 py-3">Assignee</th>
                        <th class="px-6 py-3">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($workOrders as $wo)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-medium text-gray-900">{{ $wo->number ?? '—' }}</td>
                            <td class="px-6 py-4"><span class="inline-flex px-2 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">{{ $wo->status->label() }}</span></td>
                            <td class="px-6 py-4 text-gray-700">{{ str_replace('_', ' ', $wo->schedule_window) }}</td>
                            <td class="px-6 py-4 text-gray-700">{{ $wo->assignee?->name ?? 'Unassigned' }}</td>
                            <td class="px-6 py-4 text-gray-500">{{ \Illuminate\Support\Str::limit($wo->customer_notes, 50) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No work orders yet — add one above.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
