<x-app-layout>
    <x-slot name="header">
        <h2 class="page-title">Time &amp; Pay <span class="sow">SOW §3.8</span></h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if (session('status'))
                <div class="flash-ok">{{ session('status') }}</div>
            @endif

            {{-- Clock in / out --}}
            <div class="card p-6">
                <div class="card-title mb-3">Clock</div>
                @if ($openEntry)
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-slate-600">
                            Clocked in since <span class="font-medium">{{ $openEntry->started_at?->format('M j, g:ia') }}</span>
                            ({{ $openEntry->category }})
                        </div>
                        <form method="POST" action="{{ route('time.clockout', $openEntry) }}">
                            @csrf
                            <x-primary-button>Clock out</x-primary-button>
                        </form>
                    </div>
                @else
                    <form method="POST" action="{{ route('time.clockin') }}" class="flex flex-wrap items-center gap-3">
                        @csrf
                        <select name="category" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="on_site">On-site</option>
                            <option value="travel">Travel</option>
                            <option value="shop">Shop (non-billable)</option>
                        </select>
                        <select name="work_order_id" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="">— no work order —</option>
                            @foreach ($workOrders as $wo)
                                <option value="{{ $wo->id }}">{{ $wo->number }} ({{ $wo->job?->number }})</option>
                            @endforeach
                        </select>
                        <x-primary-button>Clock in</x-primary-button>
                    </form>
                @endif
            </div>

            {{-- Entries with computed pay --}}
            <div class="card overflow-hidden">
                <div class="card-head card-title">
                    Time entries <span class="card-note">· pay rules: 1.5× Mon–Thu after-hours, 2× Fri noon–Sun</span>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th class="px-6 py-3">User</th>
                            <th class="px-6 py-3">Category</th>
                            <th class="px-6 py-3">Started</th>
                            <th class="px-6 py-3">Ended</th>
                            <th class="px-6 py-3">Hours</th>
                            <th class="px-6 py-3">Rate</th>
                            <th class="px-6 py-3">Pay-hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $row)
                            @php($e = $row['entry'])
                            <tr>
                                <td class="px-6 py-3">{{ $e->user?->name }}</td>
                                <td class="px-6 py-3">{{ $e->category }}{{ $e->billable ? '' : ' (non-billable)' }}</td>
                                <td class="px-6 py-3">{{ $e->started_at?->format('D M j, g:ia') }}</td>
                                <td class="px-6 py-3">{{ $e->ended_at?->format('g:ia') ?? '— open —' }}</td>
                                <td class="px-6 py-3">{{ $row['pay']['hours'] ?? '—' }}</td>
                                <td class="px-6 py-3">
                                    @if ($row['pay'])
                                        <span class="text-xs rounded-full px-2 py-1 bg-indigo-50 text-indigo-700">{{ $row['pay']['multiplier'] }}× {{ $row['pay']['classification'] }}</span>
                                    @else — @endif
                                </td>
                                <td class="px-6 py-3 font-medium">{{ $row['pay']['pay_hours'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-4 text-slate-500">No time entries — clock in above.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
