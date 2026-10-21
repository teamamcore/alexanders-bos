<?php

use App\Actions\Communication\NotifyCustomer;
use App\Actions\Communication\SendCustomerMessage;
use App\Actions\WorkOrders\AddWorkOrder;
use App\Actions\WorkOrders\CompleteWorkOrder;
use App\Actions\WorkOrders\StartWorkOrder;
use App\Models\CustomerMessage;
use App\Models\Job;
use App\Models\WorkOrder;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Job $job;
    public string $wo_number = '';
    public string $customer_notes = '';
    public string $schedule_window = 'morning';
    public string $work_type = '';

    // SOW §3.4 — field-to-customer messaging
    public ?int $messaging_id = null;
    public string $message_text = '';
    public string $eta = '';
    public ?string $flash = null;

    public function mount(Job $job): void
    {
        // Route-model binding is tenant-scoped, so cross-tenant ids 404.
        $this->job = $job->load('customer', 'serviceLocation');
    }

    public function with(): array
    {
        return [
            'workOrders' => $this->job->workOrders()->with('assignee')->latest()->get(),
            'recipient' => SendCustomerMessage::recipient($this->job->customer->load('contacts')),
            'recentMessages' => CustomerMessage::where('job_id', $this->job->id)->with('sender')->latest()->take(8)->get(),
            'windows' => config('batsons.schedule_windows'),
            'workTypes' => config('batsons.work_types', []),
        ];
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

        $this->reset('wo_number', 'customer_notes', 'work_type');
        $this->schedule_window = 'morning';
    }

    // ---- SOW §3.4: message the customer from the job ----------------------

    public function quickMessage(int $workOrderId, string $key): void
    {
        $wo = WorkOrder::findOrFail($workOrderId);
        $vars = $key === 'on_the_way' && trim($this->eta) !== '' ? ['eta' => 'ETA '.trim($this->eta).'.'] : [];
        $m = NotifyCustomer::run($wo, $key, auth()->user(), $vars);
        $this->flash = $m ? ucfirst($m->channel)." {$m->status} to {$m->to}." : 'No phone or email on file for this customer.';
        $this->reset('eta');
    }

    public function sendMessage(int $workOrderId): void
    {
        $this->validate(['message_text' => ['required', 'string', 'max:600']]);
        $wo = WorkOrder::findOrFail($workOrderId);
        $m = SendCustomerMessage::run($this->job->customer, null, $this->message_text, auth()->user(), $wo);
        $this->flash = $m ? ucfirst($m->channel)." {$m->status} to {$m->to}." : 'No phone or email on file for this customer.';
        $this->reset('message_text', 'messaging_id');
    }

    public function startWork(int $workOrderId): void
    {
        StartWorkOrder::run(WorkOrder::findOrFail($workOrderId), auth()->user());
        $this->flash = 'Work started — customer notified.';
    }

    public function completeWork(int $workOrderId): void
    {
        CompleteWorkOrder::run(WorkOrder::findOrFail($workOrderId), auth()->user());
        $this->flash = 'Work complete — customer notified.';
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">
                {{ $job->title ?? 'Job' }} <span class="text-slate-400">· {{ $job->customer?->name }}</span>
            </h2>
            <a href="{{ route('jobs.index') }}" wire:navigate class="text-sm font-medium text-brand-700 hover:text-brand-900 hover:underline">&larr; All jobs</a>
        </div>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        {{-- Add work order --}}
        <div class="card p-6">
            <h3 class="font-semibold text-slate-900 mb-4">Add work order</h3>
            <form wire:submit="addWorkOrder" class="grid gap-4 sm:grid-cols-5 sm:items-end">
                <div>
                    <x-input-label for="wo_number" :value="__('WO number')" />
                    <x-text-input wire:model="wo_number" id="wo_number" class="block mt-1 w-full" placeholder="WO-1002" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="customer_notes" :value="__('Customer notes')" />
                    <x-text-input wire:model="customer_notes" id="customer_notes" class="block mt-1 w-full" placeholder="What the customer reported" />
                </div>
                <div>
                    <x-input-label for="work_type" :value="__('Work type')" />
                    <select wire:model="work_type" id="work_type" class="block mt-1 w-full border-slate-300 rounded-lg text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">—</option>
                        @foreach ($workTypes as $k => $label)
                            <option value="{{ $k }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="schedule_window" :value="__('Window')" />
                    <select wire:model="schedule_window" id="schedule_window" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                        @foreach ($windows as $w)
                            <option value="{{ $w }}">{{ str_replace('_', ' ', $w) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-5">
                    <x-primary-button>{{ __('Add work order') }}</x-primary-button>
                </div>
            </form>
        </div>

        @if ($flash)<div class="flash-ok">{{ $flash }}</div>@endif

        {{-- Work orders --}}
        <div class="card overflow-hidden">
            <div class="card-head"><span class="card-title">Work orders</span>
                <span class="card-note">customer reachable by @if ($recipient){{ $recipient['channel'] }} · {{ $recipient['to'] }}@else<span class="text-amber-600">nothing on file — add a phone/email at intake</span>@endif</span></div>
            <table class="table">
                <thead>
                    <tr>
                        <th>WO #</th>
                        <th>Status</th>
                        <th>Window</th>
                        <th>Assignee</th>
                        <th>Notes</th>
                        <th class="text-right">Customer</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($workOrders as $wo)
                        <tr>
                            <td class="font-medium text-slate-900"><a href="{{ route('work-orders.show', $wo) }}" class="text-brand-800 hover:underline">{{ $wo->number ?? 'WO #'.$wo->id }}</a>@if ($wo->on_hold)<span class="pill-hold !ml-1 !py-0.5">hold</span>@endif</td>
                            <td><span class="badge-blue">{{ $wo->status->label() }}</span></td>
                            <td class="text-slate-700">{{ str_replace('_', ' ', $wo->schedule_window) }}@if ($wo->scheduled_date)<div class="text-xs text-slate-400">{{ $wo->scheduled_date->format('D, M j') }}</div>@endif</td>
                            <td class="text-slate-700">{{ $wo->assignee?->name ?? 'Unassigned' }}</td>
                            <td class="text-slate-500">{{ \Illuminate\Support\Str::limit($wo->customer_notes, 50) }}</td>
                            <td class="text-right">
                                <div class="flex flex-wrap justify-end gap-1">
                                    @if ($wo->status instanceof \App\States\WorkOrder\Scheduled)
                                        <button wire:click="quickMessage({{ $wo->id }}, 'on_the_way')" class="rounded-md bg-brand-50 px-2 py-1 text-xs font-medium text-brand-800 hover:bg-brand-100">On the way</button>
                                        <button wire:click="quickMessage({{ $wo->id }}, 'arrived')" class="rounded-md bg-brand-50 px-2 py-1 text-xs font-medium text-brand-800 hover:bg-brand-100">Arrived</button>
                                        <button wire:click="startWork({{ $wo->id }})" class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-800 hover:bg-emerald-100">Start work</button>
                                    @elseif ($wo->status instanceof \App\States\WorkOrder\InProgress)
                                        <button wire:click="completeWork({{ $wo->id }})" class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-800 hover:bg-emerald-100">Complete</button>
                                    @endif
                                    <button wire:click="$set('messaging_id', {{ $messaging_id === $wo->id ? 'null' : $wo->id }})" class="rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200">Message…</button>
                                </div>
                                @if ($messaging_id === $wo->id)
                                    <form wire:submit="sendMessage({{ $wo->id }})" class="mt-2 flex flex-col items-end gap-1">
                                        <textarea wire:model="message_text" rows="2" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Instructions for the customer…"></textarea>
                                        <x-input-error :messages="$errors->get('message_text')" />
                                        <div class="flex items-center gap-2">
                                            <x-text-input wire:model="eta" placeholder="ETA for 'on the way' (e.g. 20 min)" class="text-xs w-56" />
                                            <x-primary-button class="!py-1 !px-2 !text-xs">Send</x-primary-button>
                                        </div>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-slate-500">No work orders yet — add one above.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Recent customer messages (SOW §3.4) --}}
        @if ($recentMessages->isNotEmpty())
            <div class="card overflow-hidden">
                <div class="card-head"><span class="card-title">Customer messages</span><span class="card-note">latest on this job · office sees all under Messages</span></div>
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($recentMessages as $m)
                        <li class="px-6 py-2.5 flex justify-between gap-3 {{ $m->direction === 'inbound' ? 'bg-brand-50/40' : '' }}">
                            <span class="text-slate-700">@if ($m->direction === 'inbound')<span class="badge-blue mr-1">reply</span>@else<span class="{{ $m->status === 'sent' ? 'badge-green' : 'badge-gray' }} mr-1">{{ $m->channel }}</span>@endif{{ $m->body }}</span>
                            <span class="text-xs text-slate-400 whitespace-nowrap">{{ $m->sender?->name ?? ($m->direction === 'inbound' ? 'customer' : 'auto') }} · {{ $m->created_at?->format('M j, g:ia') }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
