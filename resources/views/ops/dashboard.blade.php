<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Operations Dashboard</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if (session('status'))
                <div class="rounded-md bg-green-50 text-green-800 px-4 py-2 text-sm">{{ session('status') }}</div>
            @endif

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach ($counts as $label => $value)
                    <div class="bg-white shadow-sm rounded-lg p-4">
                        <div class="text-3xl font-bold text-gray-900">{{ $value }}</div>
                        <div class="text-sm text-gray-500">{{ $label }}</div>
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white shadow-sm rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                        <span class="font-semibold text-gray-800">Recent Jobs</span>
                        <a href="{{ route('jobs.index') }}" class="text-sm text-indigo-600">All jobs →</a>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @forelse ($jobs as $job)
                            <a href="{{ route('jobs.show', $job) }}" class="block px-6 py-3 hover:bg-gray-50">
                                <div class="font-medium text-gray-900">{{ $job->number }} — {{ $job->title }}</div>
                                <div class="text-sm text-gray-500">{{ $job->customer?->name }} · {{ $job->workOrders->count() }} WO</div>
                            </a>
                        @empty
                            <div class="px-6 py-4 text-gray-500">No jobs yet.</div>
                        @endforelse
                    </div>
                </div>

                <div class="bg-white shadow-sm rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                        <span class="font-semibold text-gray-800">Recent Invoices</span>
                        <a href="{{ route('invoices.index') }}" class="text-sm text-indigo-600">All invoices →</a>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @forelse ($invoices as $inv)
                            <div class="flex items-center justify-between px-6 py-3">
                                <div>
                                    <div class="font-medium text-gray-900">{{ $inv->number }}</div>
                                    <div class="text-sm text-gray-500">${{ number_format((float) $inv->total, 2) }}</div>
                                </div>
                                <span class="text-xs rounded-full px-2 py-1 bg-gray-100 text-gray-700">{{ $inv->status }}</span>
                            </div>
                        @empty
                            <div class="px-6 py-4 text-gray-500">No invoices yet — create one on the <a href="{{ route('invoices.index') }}" class="text-indigo-600">Invoices</a> page.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
