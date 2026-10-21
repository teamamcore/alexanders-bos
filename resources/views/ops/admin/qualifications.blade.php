<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">Administration · Qualifications <span class="sow">SOW §3.11</span></h2>
            @include('ops.admin.partials.tabs')
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="flash-ok">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('admin.qualifications.update') }}" class="card overflow-hidden">
                @csrf
                <div class="card-head">
                    <div>
                        <div class="card-title">Qualification values by work type</div>
                        <div class="text-sm text-slate-500">Levels: {{ collect($levels)->map(fn ($l, $i) => "$i = $l")->implode(' · ') }}. Values feed the dispatch board (SOW §3.3).</div>
                    </div>
                    <x-primary-button>Save matrix</x-primary-button>
                </div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                @foreach ($workTypes as $key => $label)<th class="text-center">{{ $label }}</th>@endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($employees as $e)
                                <tr>
                                    <td>
                                        <div class="font-medium text-slate-900"><a href="{{ route('team.show', $e) }}" class="hover:underline">{{ $e->name }}</a></div>
                                        <div class="text-xs text-slate-500">{{ $e->title ?? '—' }}</div>
                                    </td>
                                    @foreach ($workTypes as $key => $label)
                                        @php $lvl = $e->levelFor($key); @endphp
                                        <td class="text-center">
                                            <select name="q[{{ $e->id }}][{{ $key }}]" class="rounded-lg border-slate-300 text-xs py-1 shadow-sm focus:border-brand-500 focus:ring-brand-500 {{ $lvl ? 'bg-brand-50 text-brand-900 font-medium' : 'text-slate-400' }}">
                                                @foreach ($levels as $i => $l)<option value="{{ $i }}" @selected($lvl === $i)>{{ $l }}</option>@endforeach
                                            </select>
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($workTypes) + 1 }}" class="px-6 py-8 text-center text-slate-500">No employees yet — add them under <a class="font-medium text-brand-700" href="{{ route('team.index') }}">Team</a>.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-3 border-t border-slate-100 text-xs text-slate-400">Work types and the number of levels are set during discovery (config/batsons.php). Every change is written to the audit trail.</div>
            </form>
        </div>
    </div>
</x-app-layout>
