<x-app-layout>
    <x-slot name="header">
        <h2 class="page-title">Operations Dashboard</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if (session('status'))
                <div class="flash-ok">{{ session('status') }}</div>
            @endif

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach ($counts as $label => $value)
                    <div class="stat">
                        <div class="stat-value">{{ $value }}</div>
                        <div class="stat-label">{{ $label }}</div>
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="card">
                    <div class="card-head">
                        <span class="card-title">Recent {{ term('job', true) }}</span>
                        <a href="{{ route('jobs.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">All {{ strtolower(term('job', true)) }} →</a>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @forelse ($jobs as $job)
                            <a href="{{ route('jobs.show', $job) }}" class="block px-6 py-3 hover:bg-slate-50">
                                <div class="font-medium text-slate-900">{{ $job->number }} — {{ $job->title }}</div>
                                <div class="text-sm text-slate-500">{{ $job->customer?->name }} · {{ $job->workOrders->count() }} {{ abbr('work_order') }}</div>
                            </a>
                        @empty
                            <div class="px-6 py-4 text-slate-500">No jobs yet.</div>
                        @endforelse
                    </div>
                </div>

                <div class="card">
                    <div class="card-head">
                        <span class="card-title">Recent {{ term('invoice', true) }}</span>
                        <a href="{{ route('invoices.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">All {{ strtolower(term('invoice', true)) }} →</a>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @forelse ($invoices as $inv)
                            <div class="flex items-center justify-between px-6 py-3">
                                <div>
                                    <div class="font-medium text-slate-900">{{ $inv->number }}</div>
                                    <div class="text-sm text-slate-500">${{ number_format((float) $inv->total, 2) }}</div>
                                </div>
                                <span class="text-xs rounded-full px-2 py-1 bg-gray-100 text-slate-700">{{ $inv->status }}</span>
                            </div>
                        @empty
                            <div class="px-6 py-4 text-slate-500">No {{ strtolower(term('invoice', true)) }} yet — create one on the <a href="{{ route('invoices.index') }}" class="font-medium text-brand-700 hover:text-brand-900">{{ term('invoice', true) }}</a> page.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
