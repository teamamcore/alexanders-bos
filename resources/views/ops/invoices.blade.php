<x-app-layout>
    <x-slot name="header">
        <h2 class="page-title">Invoicing <span class="sow">SOW §3.6</span></h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if (session('status'))
                <div class="flash-ok">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="flash-err">{{ session('error') }}</div>
            @endif

            {{-- Generate from a work order --}}
            <div class="card">
                <div class="card-head card-title">Generate invoice from a work order</div>
                <div class="divide-y divide-slate-100">
                    @foreach ($workOrders as $wo)
                        <div class="px-6 py-3 flex flex-wrap items-center gap-3 justify-between">
                            <div class="text-sm">
                                <span class="font-medium">{{ $wo->number }}</span>
                                <span class="text-slate-500">· {{ $wo->job?->number }} · {{ $wo->job?->customer?->name }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('invoices.generate', $wo) }}" class="flex items-center gap-2">
                                    @csrf
                                    <input type="number" step="0.01" name="total" placeholder="0.00" class="w-28 rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                                    <x-primary-button>Generate</x-primary-button>
                                </form>
                                <form method="POST" action="{{ route('invoices.progress', $wo) }}" class="flex items-center gap-2">
                                    @csrf
                                    <input type="number" step="0.01" name="amount" placeholder="progress $" class="w-28 rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                                    <x-secondary-button>+ Progress</x-secondary-button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Invoices --}}
            <div class="card overflow-hidden">
                <div class="card-head card-title">{{ term('invoice', true) }}</div>
                <table class="table">
                    <thead>
                        <tr>
                            <th class="px-6 py-3">Invoice</th>
                            <th class="px-6 py-3">Work Order</th>
                            <th class="px-6 py-3">Total</th>
                            <th class="px-6 py-3">Type</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">QB</th>
                            <th class="px-6 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invoices as $inv)
                            <tr>
                                <td class="px-6 py-3 font-medium">{{ $inv->number }}</td>
                                <td class="px-6 py-3 text-slate-500">{{ $inv->workOrder?->number }}</td>
                                <td class="px-6 py-3">${{ number_format((float) $inv->total, 2) }}</td>
                                <td class="px-6 py-3 text-slate-500">{{ $inv->is_progress_payment ? 'progress' : 'invoice' }}</td>
                                <td class="px-6 py-3">
                                    <span @class([
                                        'text-xs rounded-full px-2 py-1',
                                        'bg-gray-100 text-slate-700' => $inv->status === 'draft',
                                        'bg-blue-50 text-blue-700' => $inv->status === 'approved',
                                        'bg-green-50 text-green-700' => $inv->status === 'paid',
                                        'bg-red-50 text-red-700' => $inv->status === 'void',
                                    ])>{{ $inv->status }}</span>
                                </td>
                                <td class="px-6 py-3 text-slate-400">{{ $inv->qb_synced_at ? '✓ synced' : '—' }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-1">
                                        @foreach (['approve' => 'Approve', 'sync' => 'QB Sync', 'paid' => 'Paid', 'void' => 'Void'] as $action => $label)
                                            <form method="POST" action="{{ route('invoices.action', ['invoice' => $inv, 'action' => $action]) }}">
                                                @csrf
                                                <button class="text-xs px-2 py-1 rounded border border-slate-200 hover:bg-slate-50">{{ $label }}</button>
                                            </form>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-4 text-slate-500">No invoices yet — generate one above.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
