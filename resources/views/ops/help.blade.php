<x-app-layout>
    <x-slot name="header">
        <h2 class="page-title">Help <span class="sow">SOW §3.16</span></h2>
    </x-slot>

    <div class="py-4 max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <form method="GET" action="{{ route('help') }}" class="card p-4 flex items-center gap-2">
            <input type="hidden" name="from" value="{{ $from }}">
            <x-text-input name="q" value="{{ $q }}" autofocus placeholder="Ask how something works in Batsons BOS…" class="grow text-sm" />
            <x-primary-button>Ask</x-primary-button>
        </form>

        @if ($result)
            <div class="card p-5">
                <div class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 mb-2">{{ $provider === 'claude' ? 'Answer' : 'From the guide' }}@if ($from) · asked from {{ \App\Platform\AI\HelpCorpus::pageContext($from) }}@endif</div>
                <div class="prose-sm text-sm text-slate-800 whitespace-pre-line">{!! nl2br(e(preg_replace('/\*\*(.+?)\*\*/', '$1', $result['answer']) ?? $result['answer'])) !!}</div>
                @if ($result['sources'])<div class="mt-3 text-xs text-slate-500">Sources: {{ implode(' · ', $result['sources']) }}</div>@endif
            </div>
        @endif

        <div class="card p-5">
            <div class="font-semibold text-slate-900 mb-2">Try asking</div>
            <div class="flex flex-wrap gap-2">@foreach ($starters as $s)<a href="{{ route('help', ['q' => $s, 'from' => $from]) }}" class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700 hover:bg-brand-50 hover:text-brand-800">{{ $s }}</a>@endforeach</div>
            <p class="mt-3 text-xs text-slate-400">Answers are grounded in the built-in guide (overview, spec, security overview, parameters, design notes). Assistant: <span class="font-mono">{{ $provider }}</span>.</p>
        </div>
    </div>
</x-app-layout>
