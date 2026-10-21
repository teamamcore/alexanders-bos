<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">Customer Communication <span class="sow">SOW §3.4</span></h2>
            @include('ops.messages.partials.tabs')
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            @if (session('status'))<div class="flash-ok">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="flash-err">{{ $errors->first() }}</div>@endif

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach ($stats as $label => $value)
                    <div class="stat"><div class="stat-value {{ $label === 'Failed' && $value ? 'text-red-600' : '' }}">{{ $value }}</div><div class="stat-label">{{ $label }}</div></div>
                @endforeach
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Monitor --}}
                <div class="lg:col-span-2 card overflow-hidden">
                    <div class="card-head">
                        <span class="card-title">All messages <span class="card-note">· field-to-customer texts, automatic updates, replies</span></span>
                        <form method="GET" class="flex items-center gap-2 text-sm">
                            <select name="customer" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-xs py-1 shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="">All customers</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected(($filters['customer'] ?? null) == $c->id)>{{ $c->name }}</option>@endforeach</select>
                            <select name="direction" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-xs py-1 shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="">In + out</option><option value="outbound" @selected(($filters['direction'] ?? null) === 'outbound')>Outbound</option><option value="inbound" @selected(($filters['direction'] ?? null) === 'inbound')>Replies</option></select>
                            <select name="channel" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-xs py-1 shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="">SMS + email</option><option value="sms" @selected(($filters['channel'] ?? null) === 'sms')>SMS</option><option value="email" @selected(($filters['channel'] ?? null) === 'email')>Email</option></select>
                        </form>
                    </div>
                    <ul class="divide-y divide-slate-100">
                        @forelse ($messages as $m)
                            <li class="px-6 py-3 flex gap-4 {{ $m->direction === 'inbound' ? 'bg-brand-50/40' : '' }}">
                                <div class="shrink-0 pt-0.5">
                                    @if ($m->direction === 'inbound')<span class="badge-blue">reply</span>
                                    @elseif ($m->status === 'failed')<span class="badge-red">failed</span>
                                    @elseif ($m->status === 'sent')<span class="badge-green">{{ $m->channel }}</span>
                                    @else<span class="badge-gray">{{ $m->status }}</span>@endif
                                </div>
                                <div class="min-w-0 grow">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="text-sm font-medium text-slate-900 truncate">{{ $m->customer?->name ?? $m->to }} <span class="text-xs font-normal text-slate-400">{{ $m->direction === 'inbound' ? 'from '.$m->from : 'to '.$m->to }}@if ($m->workOrder) · {{ $m->workOrder->number }}@endif</span></div>
                                        <div class="text-xs text-slate-400 whitespace-nowrap">{{ $m->created_at?->format('M j, g:ia') }}</div>
                                    </div>
                                    @if ($m->subject)<div class="text-xs font-medium text-slate-600">{{ $m->subject }}</div>@endif
                                    <div class="text-sm text-slate-700 whitespace-pre-line">{{ $m->body }}</div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">@if ($m->template_key)template: {{ $m->template_key }} · @endif{{ $m->sender?->name ?? ($m->direction === 'inbound' ? 'customer' : 'automatic') }}@if ($m->error) · <span class="text-red-600">{{ $m->error }}</span>@endif</div>
                                </div>
                            </li>
                        @empty
                            <li class="px-6 py-10 text-center text-slate-500">No messages yet. Schedule a work order for a customer with a phone number, or send one from a job.</li>
                        @endforelse
                    </ul>
                </div>

                {{-- Compose --}}
                <div class="space-y-6">
                    <form method="POST" action="{{ route('messages.send') }}" class="card p-6 space-y-3">
                        @csrf
                        <div class="card-title">Send a message</div>
                        <div><x-input-label for="mc" value="Customer" /><select id="mc" name="customer_id" required class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">@foreach ($customers as $c)<option value="{{ $c->id }}">{{ $c->name }}{{ $c->phone ? ' · '.$c->phone : ($c->email ? ' · '.$c->email : ' · no contact info') }}</option>@endforeach</select></div>
                        <div><x-input-label for="mw" value="Work order (optional)" /><select id="mw" name="work_order_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="">—</option>@foreach ($workOrders as $w)<option value="{{ $w->id }}">{{ $w->number }} — {{ $w->job?->customer?->name }}</option>@endforeach</select></div>
                        <div><x-input-label for="mt" value="Template" /><select id="mt" name="template_key" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="">— free text —</option>@foreach ($templates as $k => $t)<option value="{{ $k }}">{{ $t['name'] }}</option>@endforeach</select></div>
                        <div><x-input-label for="mb" value="Message / note" /><textarea id="mb" name="body" rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Free text, or the {note} for a status-update template"></textarea></div>
                        <div class="flex items-center justify-between">
                            <select name="channel" class="rounded-lg border-slate-300 text-xs py-1 shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="">best channel</option><option value="sms">SMS</option><option value="email">Email</option></select>
                            <x-primary-button>Send</x-primary-button>
                        </div>
                    </form>

                    <div class="card p-6 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="card-title">Automatic updates</span>
                            <form method="POST" action="{{ route('messages.autonotify') }}">@csrf<button class="{{ $autoNotify ? 'badge-green' : 'badge-gray' }}">{{ $autoNotify ? 'on' : 'off' }} · toggle</button></form>
                        </div>
                        <p class="mt-2 text-slate-600">When on, customers get a text (or email) when a work order is scheduled or rescheduled, and when work starts and finishes. Field users can send "on the way", "arrived" and instructions from the job.</p>
                        <p class="mt-2 text-xs text-slate-400">Texts go through AmCore's texting platform (usage billed per Exhibit A). Driver: <code>{{ config('messaging.sms_driver') }}</code>.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
