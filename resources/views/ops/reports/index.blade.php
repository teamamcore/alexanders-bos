<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">Reports &amp; Job Costing <span class="sow">SOW §3.9</span></h2>
            @include('ops.reports.partials.tabs')
        </div>
    </x-slot>

    @php
        $money = fn ($v, $d = 0) => '$'.number_format((float) $v, $d);
        // Chart geometry (inline SVG, two series max — brand + accent, validated palette).
        $W = 720; $H = 220; $padL = 8; $padB = 26; $padT = 10;
        $n = max(1, count($trends)); $slot = ($W - $padL) / $n; $bw = max(6, min(22, ($slot - 10) / 2));
        $scale = fn ($v) => $maxMoney > 0 ? ($H - $padB - $padT) * ((float) $v / $maxMoney) : 0;
    @endphp

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            {{-- KPIs (drill-down on click) --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach ($kpis as $k)
                    <a href="{{ $k['url'] }}" class="stat hover:border-brand-300 transition">
                        <div class="stat-value {{ ($k['tone'] ?? null) === 'amber' ? 'text-amber-600' : (($k['tone'] ?? null) === 'green' ? 'text-emerald-700' : '') }}">{{ $k['money'] ? $money($k['value']) : ($k['value'] === null ? '—' : $k['value'].'%') }}</div>
                        <div class="stat-label">{{ $k['label'] }}</div>
                        <div class="mt-1 text-xs text-slate-500">{{ $k['sub'] }}</div>
                    </a>
                @endforeach
            </div>

            {{-- Trends --}}
            <div class="card p-6">
                <div class="flex items-center justify-between mb-2">
                    <div><span class="card-title">Trends</span> <span class="card-note">· invoiced vs. job cost by month, last {{ $months }} months</span></div>
                    <div class="flex items-center gap-4 text-xs text-slate-600">
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background: var(--chart-1)"></span>Invoiced</span>
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background: var(--chart-2)"></span>Job cost</span>
                        <span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-4" style="background: var(--chart-3)"></span>Collected</span>
                        <form method="GET" class="ml-2"><select name="months" onchange="this.form.submit()" class="rounded-md border-slate-300 text-xs py-1">@foreach ([6, 12, 24] as $m)<option value="{{ $m }}" @selected($months === $m)>{{ $m }} mo</option>@endforeach</select></form>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <svg viewBox="0 0 {{ $W }} {{ $H }}" class="w-full h-56" role="img" aria-label="Monthly invoiced revenue, job cost, and collections">
                        {{-- gridlines --}}
                        @foreach ([0.25, 0.5, 0.75, 1] as $g)
                            @php $y = $H - $padB - ($H - $padB - $padT) * $g; @endphp
                            <line x1="{{ $padL }}" x2="{{ $W }}" y1="{{ $y }}" y2="{{ $y }}" stroke="var(--chart-grid)" stroke-width="1" />
                            <text x="{{ $W - 2 }}" y="{{ $y - 3 }}" text-anchor="end" font-size="9" fill="#94a3b8">{{ $money($maxMoney * $g) }}</text>
                        @endforeach
                        <line x1="{{ $padL }}" x2="{{ $W }}" y1="{{ $H - $padB }}" y2="{{ $H - $padB }}" stroke="#cbd5e1" stroke-width="1" />
                        {{-- bars --}}
                        @foreach ($trends as $i => $t)
                            @php $x0 = $padL + $i * $slot + ($slot - (2 * $bw + 2)) / 2; $h1 = $scale($t['invoiced']); $h2 = $scale($t['cost']); $base = $H - $padB; @endphp
                            <a href="{{ route('reports.work-orders', ['month' => $t['month']]) }}">
                                <rect x="{{ $x0 }}" y="{{ $base - $h1 }}" width="{{ $bw }}" height="{{ $h1 }}" rx="3" fill="var(--chart-1)"><title>{{ $t['label'] }} · invoiced {{ $money($t['invoiced']) }}</title></rect>
                                <rect x="{{ $x0 + $bw + 2 }}" y="{{ $base - $h2 }}" width="{{ $bw }}" height="{{ $h2 }}" rx="3" fill="var(--chart-2)"><title>{{ $t['label'] }} · job cost {{ $money($t['cost']) }}</title></rect>
                                <rect x="{{ $padL + $i * $slot }}" y="{{ $padT }}" width="{{ $slot }}" height="{{ $H - $padT }}" fill="transparent" />
                            </a>
                            <text x="{{ $padL + $i * $slot + $slot / 2 }}" y="{{ $H - 8 }}" text-anchor="middle" font-size="10" fill="#64748b">{{ $t['label'] }}</text>
                        @endforeach
                        {{-- collected line --}}
                        @php $pts = collect($trends)->map(fn ($t, $i) => ($padL + $i * $slot + $slot / 2).','.($H - $padB - $scale($t['collected'])))->implode(' '); @endphp
                        <polyline points="{{ $pts }}" fill="none" stroke="var(--chart-3)" stroke-width="2" stroke-linejoin="round" />
                        @foreach ($trends as $i => $t)
                            <circle cx="{{ $padL + $i * $slot + $slot / 2 }}" cy="{{ $H - $padB - $scale($t['collected']) }}" r="4" fill="var(--chart-3)" stroke="#fff" stroke-width="2"><title>{{ $t['label'] }} · collected {{ $money($t['collected']) }}</title></circle>
                        @endforeach
                    </svg>
                </div>
                <details class="mt-2"><summary class="text-xs font-medium text-brand-700 cursor-pointer">Table view</summary>
                    <table class="table mt-2"><thead><tr><th>Month</th><th class="text-right">Invoiced</th><th class="text-right">Job cost</th><th class="text-right">Collected</th><th class="text-right">WOs completed</th><th></th></tr></thead>
                        <tbody>@foreach ($trends as $t)<tr><td>{{ $t['label'] }} {{ substr($t['month'], 0, 4) }}</td><td class="text-right">{{ $money($t['invoiced']) }}</td><td class="text-right">{{ $money($t['cost']) }}</td><td class="text-right">{{ $money($t['collected']) }}</td><td class="text-right">{{ $t['completed'] }}</td><td class="text-right"><a class="text-xs font-medium text-brand-700" href="{{ route('reports.work-orders', ['month' => $t['month']]) }}">drill down →</a></td></tr>@endforeach</tbody></table>
                </details>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- A/R aging --}}
                <div class="card overflow-hidden">
                    <div class="card-head"><span class="card-title">Accounts receivable <span class="card-note">· by aging bucket</span></span><a href="{{ route('reports.ar') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Full A/R →</a></div>
                    @php $maxB = max(1.0, (float) max(array_column($ar['buckets'], 'total'))); @endphp
                    <ul class="divide-y divide-slate-100">
                        @foreach ($ar['buckets'] as $key => $b)
                            <li>
                                <a href="{{ route('reports.ar', ['bucket' => $key]) }}" class="block px-6 py-2.5 hover:bg-slate-50">
                                    <div class="flex items-center justify-between text-sm"><span class="text-slate-700">{{ $b['label'] }} <span class="text-xs text-slate-400">· {{ $b['count'] }}</span></span><span class="font-semibold {{ in_array($key, ['61_90', '90_plus']) && $b['total'] > 0 ? 'text-red-700' : 'text-slate-900' }}">{{ $money($b['total']) }}</span></div>
                                    <div class="mt-1 h-1.5 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full" style="width: {{ $b['total'] / $maxB * 100 }}%; background: {{ in_array($key, ['61_90', '90_plus']) ? 'var(--status-serious)' : ($key === '31_60' ? 'var(--status-warning)' : 'var(--chart-1)') }}"></div></div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <div class="px-6 py-3 border-t border-slate-100 flex flex-wrap gap-4 text-xs text-slate-600">
                        @foreach ($ar['by_terms'] as $k => $t)<a href="{{ route('reports.ar', ['terms' => $k]) }}" class="hover:text-brand-700">{{ $t['label'] }}: <span class="font-semibold text-slate-900">{{ $money($t['total']) }}</span>@if ($t['overdue'] > 0) <span class="text-amber-700">({{ $money($t['overdue']) }} overdue)</span>@endif</a>@endforeach
                    </div>
                </div>

                {{-- Profitability by type --}}
                <div class="card overflow-hidden">
                    <div class="card-head"><span class="card-title">Profitability by job type <span class="card-note">· all time</span></span><a href="{{ route('reports.work-orders') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">All work orders →</a></div>
                    <table class="table">
                        <thead><tr><th>Type</th><th class="text-right">WOs</th><th class="text-right">Revenue</th><th class="text-right">Cost</th><th class="text-right">Profit</th><th class="text-right">Margin</th></tr></thead>
                        <tbody>
                            @forelse ($byType as $row)
                                <tr>
                                    <td><a href="{{ route('reports.work-orders', ['type' => $row['type']]) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $row['label'] }}</a></td>
                                    <td class="text-right text-slate-600">{{ $row['count'] }}</td>
                                    <td class="text-right">{{ $money($row['revenue']) }}</td>
                                    <td class="text-right">{{ $money($row['cost']) }}</td>
                                    <td class="text-right font-semibold {{ $row['profit'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ $row['profit'] < 0 ? '-' : '' }}{{ $money(abs($row['profit'])) }}</td>
                                    <td class="text-right text-slate-700">{{ $row['margin'] === null ? '—' : $row['margin'].'%' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-6 py-6 text-center text-slate-500">No costed work yet.</td></tr>
                            @endforelse
                        </tbody>
                        @if ($byType->isNotEmpty())<tfoot class="bg-slate-50/80 text-sm font-semibold"><tr><td class="px-6 py-2">Total</td><td class="px-6 py-2 text-right">{{ $byType->sum('count') }}</td><td class="px-6 py-2 text-right">{{ $money($all['revenue']) }}</td><td class="px-6 py-2 text-right">{{ $money($all['cost']) }}</td><td class="px-6 py-2 text-right {{ $all['profit'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ $money($all['profit']) }}</td><td class="px-6 py-2 text-right">{{ $all['margin'] === null ? '—' : $all['margin'].'%' }}</td></tr></tfoot>@endif
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Equipment --}}
                <div class="card overflow-hidden">
                    <div class="card-head"><span class="card-title">Equipment: revenue vs. cost <span class="card-note">· per item</span></span><a href="{{ route('equipment.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Register →</a></div>
                    <table class="table"><thead><tr><th>Item</th><th class="text-right">Hours</th><th class="text-right">Revenue</th><th class="text-right">Maint.</th><th class="text-right">Depr.</th><th class="text-right">Net</th></tr></thead>
                        <tbody>@foreach ($equipment->take(10) as $r)<tr><td><a href="{{ route('equipment.show', $r['item']) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $r['item']->name }}</a></td><td class="text-right text-slate-600">{{ number_format($r['hours'], 0) }}</td><td class="text-right">{{ $money($r['revenue']) }}</td><td class="text-right">{{ $money($r['maintenance']) }}</td><td class="text-right">{{ $money($r['depreciation']) }}</td><td class="text-right font-semibold {{ $r['net'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ $r['net'] < 0 ? '-' : '' }}{{ $money(abs($r['net'])) }}</td></tr>@endforeach</tbody>
                    </table>
                </div>
                {{-- Fleet --}}
                <div class="card overflow-hidden">
                    <div class="card-head"><span class="card-title">Fleet: cost of ownership</span><a href="{{ route('fleet.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Fleet →</a></div>
                    <table class="table"><thead><tr><th>Vehicle</th><th class="text-right">Mileage</th><th class="text-right">Maint.</th><th class="text-right">Repair</th><th class="text-right">Fuel</th><th class="text-right">TCO</th></tr></thead>
                        <tbody>@forelse ($fleet as $r)<tr><td><a href="{{ route('fleet.show', $r['vehicle']) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $r['vehicle']->label() }}</a></td><td class="text-right text-slate-600">{{ number_format($r['mileage']) }}</td><td class="text-right">{{ $money($r['by_type']['maintenance'] ?? 0) }}</td><td class="text-right">{{ $money($r['by_type']['repair'] ?? 0) }}</td><td class="text-right">{{ $money($r['by_type']['fuel'] ?? 0) }}</td><td class="text-right font-semibold">{{ $money($r['tco']) }}</td></tr>@empty<tr><td colspan="6" class="px-6 py-6 text-center text-slate-500">No vehicles.</td></tr>@endforelse</tbody>
                    </table>
                </div>
            </div>
            <p class="text-xs text-slate-400">Labor cost = hours × pay-rule multiplier × hourly rate × (1 + {{ (int) round(config('batsons.labor_burden_pct') * 100) }}% burden). Materials at part cost from “used on job” adjustments. Equipment at the billed usage. Revenue from approved / sent / paid invoices.</p>
        </div>
    </div>
</x-app-layout>
