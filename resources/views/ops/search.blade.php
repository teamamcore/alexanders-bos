<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="page-title">Search <span class="sow">SOW §3.1 · §3.16</span></h2>
            <form method="GET" action="{{ route('search') }}" class="flex items-center gap-2 w-full sm:w-auto">
                <x-text-input name="q" value="{{ $q }}" autofocus placeholder="Ask anything: WO-1001, Acme backflow, 'who called about the water heater', #leak…" class="w-full sm:w-[34rem] text-sm" />
                <x-primary-button>Search</x-primary-button>
            </form>
        </div>
    </x-slot>

    <div class="py-4 max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if ($q === '')
            <div class="card p-6 text-sm text-slate-600">
                <div class="font-semibold text-slate-900 mb-1">One box for everything</div>
                Customers, contacts, jobs, work orders and their notes, parts (by crew nickname), invoices, texts and calls — all tenant-scoped.
                Use a number (<code>WO-1001</code>), a name, a phrase from a note, or a tag (<code>#backflow</code>).
                <div class="mt-2 text-xs text-slate-400">Assistant: <span class="font-mono">{{ $provider }}</span>@if ($provider === 'offline') — keyword mode (set <code>ANTHROPIC_API_KEY</code> for AI planning and answers)@endif</div>
            </div>
        @else
            @if ($answer)<div class="card p-4 text-sm text-slate-800 border-l-4 border-l-brand-500"><span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 mr-2">{{ $provider === 'claude' ? 'AI answer' : 'Summary' }}</span>{{ $answer }}</div>@endif
            <div class="flex flex-wrap gap-1 text-xs text-slate-500">
                <span>Looked in:</span>@foreach ($plan['entities'] ?? [] as $e)<span class="badge-gray">{{ str_replace('_', ' ', $e) }}</span>@endforeach
                @if (! empty($plan['keywords']))<span class="ml-2">for:</span>@foreach ($plan['keywords'] as $k)<span class="badge-blue">{{ $k }}</span>@endforeach @endif
                @foreach ($plan['numbers'] ?? [] as $n)<span class="badge-blue">{{ $n }}</span>@endforeach
                @foreach ($plan['tags'] ?? [] as $t)<span class="badge-amber">#{{ $t }}</span>@endforeach
            </div>
            <div class="card overflow-hidden">
                <ul class="divide-y divide-slate-100">
                    @forelse ($hits as $h)
                        <li><a href="{{ $h['url'] }}" class="flex items-start gap-3 px-5 py-3 hover:bg-slate-50">
                            <span class="badge-gray shrink-0 mt-0.5 w-24 justify-center">{{ str_replace('_', ' ', $h['type']) }}</span>
                            <span class="min-w-0 grow"><span class="block text-sm font-medium text-slate-900">{{ $h['title'] }}</span><span class="block text-sm text-slate-600 truncate">{{ $h['snippet'] }}</span>@if ($h['tags'])<span class="mt-0.5 flex gap-1">@foreach ($h['tags'] as $t)<span class="badge-amber">#{{ $t }}</span>@endforeach</span>@endif</span>
                            <span class="shrink-0 text-xs text-slate-400">{{ $h['when']?->diffForHumans(null, true) }}</span>
                        </a></li>
                    @empty
                        <li class="px-5 py-10 text-center text-slate-500">No matches for “{{ $q }}”.</li>
                    @endforelse
                </ul>
            </div>
        @endif
    </div>
</x-app-layout>
