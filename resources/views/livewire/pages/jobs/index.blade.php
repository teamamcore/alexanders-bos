<?php

use App\Actions\Intake\CreateJobFromIntake;
use App\Models\Job;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $customer_name = '';
    public string $customer_phone = '';
    public string $customer_email = '';
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
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'job_title' => ['required', 'string', 'max:255'],
        ]);

        // Intake (SOW §3.1 → §3.2): find/create customer, open a job.
        $job = CreateJobFromIntake::run($validated['customer_name'], $validated['job_title'], $validated['customer_phone'] ?: null, $validated['customer_email'] ?: null);

        $this->reset('customer_name', 'customer_phone', 'customer_email', 'job_title');
        $this->redirectRoute('jobs.show', $job, navigate: true);
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="page-title">{{ __('Jobs') }}</h2>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        {{-- Intake form --}}
        <div class="card p-6">
            <h3 class="font-semibold text-slate-900 mb-4">New job</h3>
            <form wire:submit="createJob" class="grid gap-4 sm:grid-cols-5 sm:items-end">
                <div>
                    <x-input-label for="customer_name" :value="__('Customer')" />
                    <x-text-input wire:model="customer_name" id="customer_name" class="block mt-1 w-full" placeholder="Acme Industrial" />
                    <x-input-error :messages="$errors->get('customer_name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="customer_phone" :value="__('Mobile (for texts)')" />
                    <x-text-input wire:model="customer_phone" id="customer_phone" class="block mt-1 w-full" placeholder="555-201-3344" />
                    <x-input-error :messages="$errors->get('customer_phone')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="customer_email" :value="__('Email')" />
                    <x-text-input wire:model="customer_email" id="customer_email" type="email" class="block mt-1 w-full" placeholder="office@acme.com" />
                    <x-input-error :messages="$errors->get('customer_email')" class="mt-2" />
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
        <div class="card overflow-hidden">
            <table class="table">
                <thead>
                    <tr class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-3">Job</th>
                        <th class="px-6 py-3">Customer</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Work orders</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jobs as $job)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4">
                                <a href="{{ route('jobs.show', $job) }}" wire:navigate class="text-indigo-600 hover:underline font-medium">
                                    {{ $job->title ?? 'Untitled job' }}
                                </a>
                            </td>
                            <td class="px-6 py-4 text-slate-700">{{ $job->customer?->name }}</td>
                            <td class="px-6 py-4"><span class="badge-green">{{ $job->status }}</span></td>
                            <td class="px-6 py-4 text-slate-700">{{ $job->work_orders_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">No jobs yet — create one above.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
