<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="page-title">Contacts <span class="sow">SOW §3.1</span></h2>
            <form method="GET" class="flex items-center gap-2"><x-text-input name="q" value="{{ $q }}" placeholder="Search businesses…" class="w-64 text-sm" /><x-secondary-button type="submit">Search</x-secondary-button></form>
        </div>
    </x-slot>
    <div class="py-4 max-w-7xl mx-auto sm:px-6 lg:px-8">
        @if (session('status'))<div class="flash-ok mb-3">{{ session('status') }}</div>@endif
        <div class="card overflow-hidden">
            <table class="table">
                <thead><tr><th>Business</th><th>Segment</th><th>Corp. POC</th><th>Owner</th><th class="text-right">Locations</th><th class="text-right">Jobs</th><th class="text-right">Paid YTD</th><th></th></tr></thead>
                <tbody>
                    @forelse ($customers as $row)
                        @php $c = $row['c']; @endphp
                        <tr>
                            <td><a href="{{ route('contacts.show', $c) }}" class="font-semibold text-slate-900 hover:text-brand-700">{{ $c->name }}</a><div class="text-xs text-slate-500">{{ $c->phone }}{{ $c->phone && $c->email ? ' · ' : '' }}{{ $c->email }}</div></td>
                            <td>@if ($c->segment)<span class="badge-blue">{{ str_replace('_', ' ', $c->segment) }}</span>@else<span class="text-slate-400">—</span>@endif</td>
                            <td class="text-slate-700">{{ $c->corpPoc?->name ?? '—' }}</td>
                            <td class="text-slate-700">{{ $c->owner?->name ?? '—' }}</td>
                            <td class="text-right text-slate-700">{{ $c->service_locations_count }}</td>
                            <td class="text-right text-slate-700">{{ $c->jobs_count }}</td>
                            <td class="text-right font-semibold text-slate-900">${{ number_format($row['ytd'], 0) }}</td>
                            <td class="text-right"><a href="{{ route('contacts.show', $c) }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Open →</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-6 py-10 text-center text-slate-500">No businesses yet — intake creates them.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
