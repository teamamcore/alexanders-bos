<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <a href="{{ route('inbox.index') }}" class="text-xs font-medium text-slate-500 hover:text-slate-700">← Inbox</a>
                <h2 class="page-title">Inbound email <span class="sow">SOW §3.12</span></h2>
                <div class="text-sm text-slate-500">Mail sent to <span class="font-mono text-slate-700">{{ $inboundAddress }}</span> is routed by job number or sender; anything unmatched waits here.</div>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))<div class="flash-ok">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="flash-err">{{ $errors->first() }}</div>@endif

            <div class="card overflow-hidden">
                <ul class="divide-y divide-slate-100">
                    @forelse ($emails as $e)
                        <li class="px-6 py-4 {{ $e->status === 'new' ? 'bg-amber-50/40' : '' }}">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-slate-900 truncate">{{ $e->subject ?: '(no subject)' }}</div>
                                    <div class="text-xs text-slate-500">{{ $e->from_name ? $e->from_name.' <'.$e->from_email.'>' : $e->from_email }} · {{ $e->received_at?->format('M j, g:ia') }}</div>
                                    <div class="mt-1 text-sm text-slate-700 whitespace-pre-line line-clamp-4">{{ $e->body }}</div>
                                </div>
                                <div class="shrink-0 text-right">
                                    @if ($e->status === 'routed')
                                        <span class="badge-green">routed · {{ str_replace('_', ' ', $e->routed_by_rule) }}</span>
                                        <div class="text-xs text-slate-500 mt-1">{{ $e->customer?->name }}@if ($e->job) · <a class="text-brand-700" href="{{ route('jobs.show', $e->job) }}">{{ $e->job->number }}</a>@endif</div>
                                    @elseif ($e->status === 'archived')
                                        <span class="badge-gray">archived</span>
                                    @else
                                        <span class="badge-amber">needs routing</span>
                                    @endif
                                </div>
                            </div>
                            @if ($e->status === 'new')
                                <form method="POST" action="{{ route('inbox.email.route', $e) }}" class="mt-3 flex flex-wrap items-end gap-2">
                                    @csrf
                                    <div><x-input-label for="c{{ $e->id }}" value="Customer" /><select id="c{{ $e->id }}" name="customer_id" class="mt-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="">—</option>@foreach ($customers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                                    <div><x-input-label for="j{{ $e->id }}" value="Job" /><select id="j{{ $e->id }}" name="job_id" class="mt-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="">—</option>@foreach ($jobs as $j)<option value="{{ $j->id }}">{{ $j->number }} — {{ $j->customer?->name }}</option>@endforeach</select></div>
                                    <x-primary-button>Route</x-primary-button>
                                    <button name="archive" value="1" class="text-sm text-slate-500 hover:text-slate-700 px-2">Archive</button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="px-6 py-12 text-center text-slate-500">Nothing has arrived yet.</li>
                    @endforelse
                </ul>
            </div>
            <p class="text-xs text-slate-400">Your mail provider forwards the inbound address to <code>POST /webhooks/email</code> with the shared token. Routed mail also appears on the customer's job under Messages.</p>
        </div>
    </div>
</x-app-layout>
