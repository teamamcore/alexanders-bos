<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">Administration · AI usage <span class="sow">SOW §3.16 · Ex. A</span></h2>
            @include('ops.admin.partials.tabs')
        </div>
    </x-slot>

    <div class="py-6 max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="stat"><div class="stat-value">{{ $total['calls'] }}</div><div class="stat-label">AI calls · {{ $month->format('M Y') }}</div></div>
            <div class="stat"><div class="stat-value">{{ number_format($total['tokens']) }}</div><div class="stat-label">Tokens</div></div>
            <div class="stat"><div class="stat-value">${{ number_format($total['cost'], 2) }}</div><div class="stat-label">Estimated usage charge</div></div>
            <div class="stat"><div class="stat-value {{ $enabled ? 'text-emerald-700' : 'text-amber-600' }}">{{ $enabled ? 'Claude' : 'Offline' }}</div><div class="stat-label">{{ $enabled ? $model : 'no API key — keyword fallback' }}</div></div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-head"><span class="card-title">By feature</span><span class="card-note">this month · metered per call for the Exhibit A pass-through</span></div>
            <table class="table"><thead><tr><th>Feature</th><th class="text-right">Calls</th><th class="text-right">Input tokens</th><th class="text-right">Output tokens</th><th class="text-right">Failed</th><th class="text-right">Cost</th></tr></thead>
                <tbody>@forelse ($byFeature as $f => $r)<tr><td class="font-medium text-slate-900">{{ str_replace('_', ' ', $f) }}</td><td class="text-right">{{ $r['calls'] }}</td><td class="text-right">{{ number_format($r['input']) }}</td><td class="text-right">{{ number_format($r['output']) }}</td><td class="text-right {{ $r['failed'] ? 'text-red-700' : '' }}">{{ $r['failed'] }}</td><td class="text-right font-semibold">${{ number_format($r['cost'], 4) }}</td></tr>@empty<tr><td colspan="6" class="px-6 py-6 text-center text-slate-500">No AI calls yet this month.</td></tr>@endforelse</tbody>
            </table>
        </div>

        <div class="card overflow-hidden">
            <div class="card-head"><span class="card-title">Recent calls</span></div>
            <ul class="divide-y divide-slate-100 text-xs">@foreach ($recent as $u)<li class="px-6 py-2 flex justify-between gap-3"><span class="text-slate-700">{{ $u->feature }} · {{ $u->provider }}{{ $u->model ? ' · '.$u->model : '' }} · {{ $u->user?->name ?? 'system' }}{{ $u->ok ? '' : ' · failed: '.$u->error }}</span><span class="text-slate-400 whitespace-nowrap">{{ $u->duration_ms }} ms · {{ $u->created_at?->format('M j, g:ia') }}</span></li>@endforeach</ul>
        </div>

        <div class="card p-5 text-sm text-slate-600 space-y-1">
            <div class="font-semibold text-slate-900">Setup</div>
            <div>Set <code>ANTHROPIC_API_KEY</code> (and optionally <code>AI_MODEL</code>, default <code>{{ $model }}</code>) in <code>.env</code> to switch from the offline assistant to Claude. Search planning and answers, tag suggestions, in-app help and call summaries all route through one metered client.</div>
            <div>Call audio transcription driver: <code>{{ $transcription }}</code> — Claude summarizes transcripts but does not transcribe audio; a speech-to-text provider plugs in behind <code>AI_TRANSCRIPTION_DRIVER</code>. Until then transcripts are typed or pasted on the Calls page.</div>
        </div>
    </div>
</x-app-layout>
