<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Invoicing <span class="text-sm text-gray-400">(SOW §3.6)</span></h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if (session('status'))
                <div class="rounded-md bg-green-50 text-green-800 px-4 py-2 text-sm">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="rounded-md bg-red-50 text-red-800 px-4 py-2 text-sm">{{ session('error') }}</div>
            @endif

            {{-- Generate from a work order --}}
            <div class="bg-white shadow-sm rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100 font-semibold text-gray-800">Generate invoice from a work order</div>
                <div class="divide-y divide-gray-100">
                    @foreach ($workOrders as $wo)
                        <div class="px-6 py-3 flex flex-wrap items-center gap-3 justify-between">
                            <div class="text-sm">
                                <span class="font-medium">{{ $wo->number }}</span>
                                <span class="text-gray-500">· {{ $wo->job?->number }} · {{ $wo->job?->customer?->name }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('invoices.generate', $wo) }}" class="flex items-center gap-2">
                                    @csrf
                                    <input type="number" step="0.01" name="total" placeholder="0.00" class="w-28 rounded-md border-gray-300 text-sm" />
                                    <x-primary-button>Generate</x-primary-button>
                                </form>
                                <form method="POST" action="{{ route('invoices.progress', $wo) }}" class="flex items-center gap-2">
                                    @csrf
                                    <input type="number" step="0.01" name="amount" placeholder="progress $" class="w-28 rounded-md border-gray-300 text-sm" />
                                    <x-secondary-button>+ Progress</x-secondary-button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Invoices --}}
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 font-semibold text-gray-800">Invoices</div>
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
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
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($invoices as $inv)
                            <tr>
                                <td class="px-6 py-3 font-medium">{{ $inv->number }}</td>
                                <td class="px-6 py-3 text-gray-500">{{ $inv->workOrder?->number }}</td>
                                <td class="px-6 py-3">${{ number_format((float) $inv->total, 2) }}</td>
                                <td class="px-6 py-3 text-gray-500">{{ $inv->is_progress_payment ? 'progress' : 'invoice' }}</td>
                                <td class="px-6 py-3">
                                    <span @class([
                                        'text-xs rounded-full px-2 py-1',
                                        'bg-gray-100 text-gray-700' => $inv->status === 'draft',
                                        'bg-blue-50 text-blue-700' => $inv->status === 'approved',
                                        'bg-green-50 text-green-700' => $inv->status === 'paid',
                                        'bg-red-50 text-red-700' => $inv->status === 'void',
                                    ])>{{ $inv->status }}</span>
                                </td>
                                <td class="px-6 py-3 text-gray-400">{{ $inv->qb_synced_at ? '✓ synced' : '—' }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-1">
                                        @foreach (['approve' => 'Approve', 'sync' => 'QB Sync', 'paid' => 'Paid', 'void' => 'Void'] as $action => $label)
                                            <form method="POST" action="{{ route('invoices.action', ['invoice' => $inv, 'action' => $action]) }}">
                                                @csrf
                                                <button class="text-xs px-2 py-1 rounded border border-gray-200 hover:bg-gray-50">{{ $label }}</button>
                                            </form>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-4 text-gray-500">No invoices yet — generate one above.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
