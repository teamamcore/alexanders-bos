<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">Accounts Receivable <span class="sow">SOW §3.9</span></h2>
            @include('ops.reports.partials.tabs')
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="stat"><div class="stat-value">${{ number_format($ar['total'], 0) }}</div><div class="stat-label">Open A/R</div></div>
                <div class="stat"><div class="stat-value {{ $ar['overdue'] > 0 ? 'text-amber-600' : '' }}">${{ number_format($ar['overdue'], 0) }}</div><div class="stat-label">Past due</div></div>
                <div class="stat"><div class="stat-value {{ $ar['flagged'] ? 'text-red-600' : '' }}">{{ $ar['flagged'] }}</div><div class="stat-label">Collection flags (&gt;{{ config('batsons.collection_flag_days') }} d)</div></div>
                <div class="stat"><div class="stat-value">{{ $ar['open']->count() }}</div><div class="stat-label">Open invoices</div></div>
            </div>

            <div class="card overflow-hidden">
                <div class="card-head">
                    <span class="card-title">Open invoices <span class="card-note">· by status and payment terms, oldest first</span></span>
                    <form method="GET" class="flex items-center gap-2 text-sm">
                        <select name="bucket" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-xs py-1"><option value="">All ages</option>@foreach ($ar['buckets'] as $k => $b)<option value="{{ $k }}" @selected($bucket === $k)>{{ $b['label'] }} ({{ $b['count'] }})</option>@endforeach</select>
                        <select name="terms" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-xs py-1"><option value="">All terms</option>@foreach ($ar['by_terms'] as $k => $t)<option value="{{ $k }}" @selected($terms === $k)>{{ $t['label'] }} ({{ $t['count'] }})</option>@endforeach</select>
                        @if ($bucket || $terms)<a href="{{ route('reports.ar') }}" class="text-xs text-slate-500">clear</a>@endif
                    </form>
                </div>
                <table class="table">
                    <thead><tr><th>Invoice</th><th>Customer</th><th>Terms</th><th>Issued</th><th>Due</th><th class="text-right">Days past</th><th>Flag</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                        @forelse ($rows as $r)
                            @php $inv = $r['invoice']; @endphp
                            <tr class="{{ $r['flag'] === 'collection' ? 'bg-red-50/40' : '' }}">
                                <td><div class="font-medium text-slate-900">{{ $inv->number }}</div><div class="text-xs text-slate-400">{{ $inv->status }}@if ($inv->is_progress_payment) · progress @endif</div></td>
                                <td class="text-slate-700">{{ $inv->customer?->name ?? $inv->workOrder?->job?->customer?->name ?? '—' }}@if ($inv->workOrder)<div class="text-xs text-slate-400"><a class="hover:text-brand-700" href="{{ route('reports.job', $inv->workOrder->job_id) }}">{{ $inv->workOrder->number }}</a></div>@endif</td>
                                <td class="text-slate-600">{{ \App\Platform\Reporting\Reports::termsLabel($inv->terms ?: 'net_30') }}</td>
                                <td class="text-slate-600">{{ $inv->issued_at?->format('M j') ?? '—' }}</td>
                                <td class="text-slate-600">{{ $inv->due_at?->format('M j') ?? '—' }}</td>
                                <td class="text-right {{ $r['days'] > 0 ? 'font-semibold text-slate-900' : 'text-slate-400' }}">{{ $r['days'] ?: '—' }}</td>
                                <td>@if ($r['flag'] === 'collection')<span class="badge-red">⚑ collections</span>@elseif ($r['flag'] === 'overdue')<span class="badge-amber">overdue</span>@else<span class="badge-gray">current</span>@endif</td>
                                <td class="text-right font-semibold text-slate-900">${{ number_format((float) $inv->total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-6 py-8 text-center text-slate-500">Nothing outstanding.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())<tfoot class="bg-slate-50/80 font-semibold text-sm"><tr><td class="px-6 py-2" colspan="7">Total ({{ $rows->count() }})</td><td class="px-6 py-2 text-right">${{ number_format($rows->sum(fn ($r) => (float) $r['invoice']->total), 2) }}</td></tr></tfoot>@endif
                </table>
            </div>
            <p class="text-xs text-slate-400">Payment status only — no card or bank data is stored (MSA Ex. B.2). Mark invoices paid from the Invoicing page or let the QuickBooks sync do it.</p>
        </div>
    </div>
</x-app-layout>
