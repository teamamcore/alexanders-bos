<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <a href="{{ route('reports.index') }}" class="text-xs font-medium text-slate-500 hover:text-slate-700">← Reports</a>
                <h2 class="page-title">Job costing <span class="sow">SOW §3.9</span></h2>
                <div class="text-sm text-slate-500">
                    @if ($typeLabel){{ $typeLabel }} · @endif
                    @if ($customer){{ $customer->name }} · @endif
                    @if ($filters['from'] || $filters['to']){{ $filters['from'] ?? '…' }} → {{ $filters['to'] ?? 'today' }}@else all time @endif
                    @if ($typeLabel || $customer || $filters['from']) · <a href="{{ route('reports.work-orders') }}" class="text-brand-700">clear filters</a>@endif
                </div>
            </div>
            @include('ops.reports.partials.tabs')
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="stat"><div class="stat-value">${{ number_format($total['revenue'], 0) }}</div><div class="stat-label">Revenue</div></div>
                <div class="stat"><div class="stat-value">${{ number_format($total['labor'], 0) }}</div><div class="stat-label">Labor · {{ number_format($total['labor_hours'], 0) }} h</div></div>
                <div class="stat"><div class="stat-value">${{ number_format($total['materials'], 0) }}</div><div class="stat-label">Materials</div></div>
                <div class="stat"><div class="stat-value">${{ number_format($total['equipment'], 0) }}</div><div class="stat-label">Equipment</div></div>
                <div class="stat"><div class="stat-value {{ $total['profit'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">${{ number_format($total['profit'], 0) }}</div><div class="stat-label">Profit · {{ $total['margin'] === null ? '—' : $total['margin'].'%' }}</div></div>
            </div>

            <div class="card overflow-hidden">
                <div class="card-head"><span class="card-title">Work orders <span class="card-note">· {{ $rows->count() }} · click a job for the cost sheet</span></span></div>
                <table class="table">
                    <thead><tr><th>WO</th><th>Customer / job</th><th>Type</th><th>Tech</th><th class="text-right">Labor</th><th class="text-right">Parts</th><th class="text-right">Equip.</th><th class="text-right">Revenue</th><th class="text-right">Profit</th></tr></thead>
                    <tbody>
                        @forelse ($rows as $r)
                            @php $wo = $r['wo']; $c = $r['cost']; @endphp
                            <tr>
                                <td><div class="font-medium text-slate-900">{{ $wo->number ?? 'WO #'.$wo->id }}</div><div class="text-xs text-slate-400">{{ $wo->status->label() }}@if ($wo->scheduled_date) · {{ $wo->scheduled_date->format('M j') }}@endif</div></td>
                                <td><a href="{{ route('reports.job', $wo->job_id) }}" class="text-slate-900 hover:text-brand-700">{{ $wo->job?->customer?->name }}</a><div class="text-xs text-slate-500">{{ $wo->job?->title }}</div></td>
                                <td class="text-slate-600 text-xs">{{ $wo->work_type ? (config('batsons.work_types')[$wo->work_type] ?? $wo->work_type) : '—' }}</td>
                                <td class="text-slate-600">{{ $wo->assignee?->name ?? '—' }}</td>
                                <td class="text-right">${{ number_format($c['labor'], 0) }}<div class="text-[10px] text-slate-400">{{ $c['labor_hours'] }} h</div></td>
                                <td class="text-right">${{ number_format($c['materials'], 0) }}</td>
                                <td class="text-right">${{ number_format($c['equipment'], 0) }}</td>
                                <td class="text-right font-medium">${{ number_format($c['revenue'], 0) }}</td>
                                <td class="text-right font-semibold {{ $c['profit'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ $c['profit'] < 0 ? '-' : '' }}${{ number_format(abs($c['profit']), 0) }}<div class="text-[10px] font-normal text-slate-400">{{ $c['margin'] === null ? '—' : $c['margin'].'%' }}</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-6 py-8 text-center text-slate-500">No work orders match.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
