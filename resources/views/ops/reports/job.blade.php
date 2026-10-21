<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <a href="{{ route('reports.work-orders') }}" class="text-xs font-medium text-slate-500 hover:text-slate-700">← Job costing</a>
                <h2 class="page-title">{{ $job->number ?? 'Job #'.$job->id }} — {{ $job->title }}</h2>
                <div class="text-sm text-slate-500">{{ $job->customer?->name }}@if ($job->serviceLocation) · {{ $job->serviceLocation->address }}@endif · <a href="{{ route('jobs.show', $job) }}" class="text-brand-700">open job →</a></div>
            </div>
            @include('ops.reports.partials.tabs')
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="stat"><div class="stat-value">${{ number_format($total['revenue'], 0) }}</div><div class="stat-label">Invoiced</div></div>
                <div class="stat"><div class="stat-value">${{ number_format($total['labor'], 0) }}</div><div class="stat-label">Labor incl. {{ (int) round($burden * 100) }}% burden · {{ $total['labor_hours'] }} h</div></div>
                <div class="stat"><div class="stat-value">${{ number_format($total['materials'], 0) }}</div><div class="stat-label">Materials</div></div>
                <div class="stat"><div class="stat-value">${{ number_format($total['equipment'], 0) }}</div><div class="stat-label">Equipment</div></div>
                <div class="stat"><div class="stat-value {{ $total['profit'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">${{ number_format($total['profit'], 0) }}</div><div class="stat-label">Profit · {{ $total['margin'] === null ? '—' : $total['margin'].'%' }}</div></div>
            </div>

            @foreach ($rows as $r)
                @php $wo = $r['wo']; $c = $r['cost']; @endphp
                <div class="card overflow-hidden">
                    <div class="card-head">
                        <span class="card-title">{{ $wo->number ?? 'WO #'.$wo->id }} <span class="card-note">· {{ $wo->status->label() }} · {{ $wo->assignee?->name ?? 'unassigned' }}@if ($wo->work_type) · {{ config('batsons.work_types')[$wo->work_type] ?? $wo->work_type }}@endif</span></span>
                        <span class="text-sm">Revenue <span class="font-semibold">${{ number_format($c['revenue'], 2) }}</span> · Cost <span class="font-semibold">${{ number_format($c['cost'], 2) }}</span> · <span class="font-semibold {{ $c['profit'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">${{ number_format($c['profit'], 2) }}</span></span>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-3 divide-y lg:divide-y-0 lg:divide-x divide-slate-100 text-sm">
                        <div class="p-5">
                            <div class="stat-label mb-2">Labor · ${{ number_format($c['labor'], 2) }}</div>
                            <ul class="space-y-1">
                                @forelse ($wo->timeEntries->whereNotNull('ended_at') as $t)
                                    @php $pay = \App\Actions\Time\CalculatePay::run($t->started_at); @endphp
                                    <li class="flex justify-between gap-2"><span class="text-slate-700">{{ $t->user?->name }} <span class="text-xs text-slate-400">{{ $t->started_at->format('M j') }} · {{ $t->category }}@if ($pay['multiplier'] > 1) · ×{{ $pay['multiplier'] }}@endif</span></span><span class="text-slate-900">{{ number_format((float) $t->started_at->diffInHours($t->ended_at), 2) }} h</span></li>
                                @empty
                                    <li class="text-slate-400">No time logged.</li>
                                @endforelse
                            </ul>
                        </div>
                        <div class="p-5">
                            <div class="stat-label mb-2">Materials · ${{ number_format($c['materials'], 2) }}</div>
                            <ul class="space-y-1">
                                @forelse ($r['parts'] as $a)
                                    <li class="flex justify-between gap-2"><span class="text-slate-700">{{ $a->part?->name }} <span class="text-xs text-slate-400">× {{ abs($a->quantity) }} @ ${{ number_format((float) ($a->part?->cost ?? $a->part?->price ?? 0), 2) }}</span></span><span class="text-slate-900">${{ number_format(abs($a->quantity) * (float) ($a->part?->cost ?? $a->part?->price ?? 0), 2) }}</span></li>
                                @empty
                                    <li class="text-slate-400">No parts used.</li>
                                @endforelse
                            </ul>
                        </div>
                        <div class="p-5">
                            <div class="stat-label mb-2">Equipment · ${{ number_format($c['equipment'], 2) }}</div>
                            <ul class="space-y-1">
                                @forelse ($wo->equipmentUsages as $u)
                                    <li class="flex justify-between gap-2"><span class="text-slate-700">{{ $u->equipment?->name }} <span class="text-xs text-slate-400">{{ $u->quantity }} {{ $u->unit }} @ ${{ number_format((float) $u->rate, 2) }}</span></span><span class="text-slate-900">${{ number_format((float) $u->amount, 2) }}</span></li>
                                @empty
                                    <li class="text-slate-400">No equipment billed.</li>
                                @endforelse
                            </ul>
                            <div class="stat-label mt-4 mb-2">Invoices</div>
                            <ul class="space-y-1">
                                @forelse ($wo->invoices as $inv)
                                    <li class="flex justify-between gap-2"><span class="text-slate-700">{{ $inv->number }} <span class="badge-gray ml-1">{{ $inv->status }}</span></span><span class="text-slate-900">${{ number_format((float) $inv->total, 2) }}</span></li>
                                @empty
                                    <li class="text-slate-400">Not invoiced.</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
