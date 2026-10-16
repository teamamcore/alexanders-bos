<?php

use App\Actions\Intake\CreateJobFromIntake;
use App\Models\Job;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $customer_name = '';
    public string $job_title = '';

    public function with(): array
    {
        // Tenant-scoped automatically via BelongsToTenant global scope.
        return [
            'jobs' => Job::with('customer')->withCount('workOrders')->latest()->get(),
        ];
    }

    public function createJob(): void
    {
        $validated = $this->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'job_title' => ['required', 'string', 'max:255'],
        ]);

        // Intake (SOW §3.1 → §3.2): find/create customer, open a job.
        $job = CreateJobFromIntake::run($validated['customer_name'], $validated['job_title']);

        $this->reset('customer_name', 'job_title');
        $this->redirectRoute('jobs.show', $job, navigate: true);
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Jobs') }}</h2>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        {{-- Intake form --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="font-semibold text-gray-900 mb-4">New job</h3>
            <form wire:submit="createJob" class="grid gap-4 sm:grid-cols-3 sm:items-end">
                <div>
                    <x-input-label for="customer_name" :value="__('Customer')" />
                    <x-text-input wire:model="customer_name" id="customer_name" class="block mt-1 w-full" placeholder="Acme Industrial" />
                    <x-input-error :messages="$errors->get('customer_name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="job_title" :value="__('Job title')" />
                    <x-text-input wire:model="job_title" id="job_title" class="block mt-1 w-full" placeholder="Backflow test + repair" />
                    <x-input-error :messages="$errors->get('job_title')" class="mt-2" />
                </div>
                <div>
                    <x-primary-button>{{ __('Create job') }}</x-primary-button>
                </div>
            </form>
        </div>

        {{-- Jobs list --}}
        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <th class="px-6 py-3">Job</th>
                        <th class="px-6 py-3">Customer</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Work orders</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($jobs as $job)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <a href="{{ route('jobs.show', $job) }}" wire:navigate class="text-indigo-600 hover:underline font-medium">
                                    {{ $job->title ?? 'Untitled job' }}
                                </a>
                            </td>
                            <td class="px-6 py-4 text-gray-700">{{ $job->customer?->name }}</td>
                            <td class="px-6 py-4"><span class="inline-flex px-2 text-xs font-semibold rounded-full bg-green-100 text-green-800">{{ $job->status }}</span></td>
                            <td class="px-6 py-4 text-gray-700">{{ $job->work_orders_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">No jobs yet — create one above.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
