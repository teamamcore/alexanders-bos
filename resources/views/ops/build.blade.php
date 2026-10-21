<x-app-layout>
    <x-slot name="header">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h2 class="page-title">Build checklist</h2>
                <div class="text-sm text-slate-500">SOW No. 01 · what is done, what is in progress, and where to see it · as of {{ $asOf->toFormattedDateString() }}</div>
            </div>
            <a href="{{ $notionUrl }}" target="_blank" rel="noopener" class="text-sm font-medium text-brand-700 hover:text-brand-900">Full plan in Notion ↗</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            {{-- Progress --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="stat md:col-span-2">
                    <div class="flex items-end justify-between">
                        <div><div class="stat-value">{{ $moduleDone }}<span class="text-base font-normal text-slate-400"> / {{ $moduleTotal }}</span></div><div class="stat-label">Build items prototyped</div></div>
                        <div class="text-sm font-semibold text-slate-700">{{ $moduleTotal ? (int) round($moduleDone / $moduleTotal * 100) : 0 }}%</div>
                    </div>
                    <div class="mt-3 h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full bg-brand-700" style="width: {{ $moduleTotal ? $moduleDone / $moduleTotal * 100 : 0 }}%"></div></div>
                </div>
                <div class="stat"><div class="stat-value text-amber-600">{{ $counts['in_progress'] }}</div><div class="stat-label">In progress</div></div>
                <div class="stat"><div class="stat-value text-slate-400">{{ $counts['not_started'] }}</div><div class="stat-label">Not started</div></div>
            </div>

            @if ($next)
                <div class="flash-warn flex items-center justify-between">
                    <span><span class="font-semibold">Up next:</span> {{ $next['name'] }}@if ($next['start']) · target {{ $next['start']->format('M j') }}@if ($next['end'])–{{ $next['end']->format('M j') }}@endif @endif</span>
                    <span class="text-xs">{{ $next['notes'] }}</span>
                </div>
            @endif

            {{-- Phases --}}
            @foreach ($phases as $phase => $window)
                @php $rows = $byPhase->get($phase, collect()); @endphp
                <div class="card overflow-hidden">
                    <div class="card-head">
                        <span class="card-title">{{ $phase }} <span class="card-note">· {{ $window }}</span></span>
                        <span class="text-xs text-slate-500">{{ $rows->where('status', 'done')->count() }} / {{ $rows->count() }} done</span>
                    </div>
                    <table class="table">
                        <tbody>
                            @foreach ($rows as $item)
                                <tr>
                                    <td class="w-10">
                                        @if ($item['status'] === 'done')
                                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500 text-white"><svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4L8 11.6l6.8-6.3a1 1 0 011.4 0z" clip-rule="evenodd"/></svg></span>
                                        @elseif ($item['status'] === 'in_progress')
                                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full border-2 border-amber-400 bg-amber-50"><span class="h-2 w-2 rounded-full bg-amber-500"></span></span>
                                        @else
                                            <span class="inline-flex h-6 w-6 rounded-full border-2 border-slate-200"></span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="font-medium {{ $item['status'] === 'done' ? 'text-slate-900' : ($item['status'] === 'in_progress' ? 'text-amber-800' : 'text-slate-600') }}">{{ $item['name'] }}</div>
                                        <div class="text-xs text-slate-500">{{ $item['notes'] }}</div>
                                    </td>
                                    <td class="text-xs text-slate-400 whitespace-nowrap">{{ $item['ref'] }}</td>
                                    <td class="text-sm whitespace-nowrap {{ $item['overdue'] ? 'text-red-600 font-medium' : 'text-slate-600' }}">
                                        @if ($item['start']){{ $item['start']->format('M j') }}@if ($item['end']) – {{ $item['end']->format('M j') }}@endif @endif
                                        @if ($item['overdue']) <span class="badge-red ml-1">behind</span> @endif
                                    </td>
                                    <td>
                                        <span class="{{ $item['status'] === 'done' ? 'badge-green' : ($item['status'] === 'in_progress' ? 'badge-amber' : 'badge-gray') }}">{{ str_replace('_', ' ', $item['status']) }}</span>
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        @if ($item['link'])
                                            <a href="{{ route($item['link']['route'], $item['link']['params'] ?? []) }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:text-brand-900">{{ $item['link']['label'] }} →</a>
                                        @elseif ($item['status'] === 'done')
                                            <span class="text-xs text-slate-400">no screen</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach

            <p class="text-xs text-slate-400">Statuses are maintained in <code>config/build.php</code> alongside the Notion plan. "Done" means prototyped and reviewable in this app, not accepted for production.</p>
        </div>
    </div>
</x-app-layout>
