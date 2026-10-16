<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Time &amp; Pay <span class="text-sm text-gray-400">(SOW §3.8)</span></h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if (session('status'))
                <div class="rounded-md bg-green-50 text-green-800 px-4 py-2 text-sm">{{ session('status') }}</div>
            @endif

            {{-- Clock in / out --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <div class="font-semibold text-gray-800 mb-3">Clock</div>
                @if ($openEntry)
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-600">
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
                        <select name="category" class="rounded-md border-gray-300 text-sm">
                            <option value="on_site">On-site</option>
                            <option value="travel">Travel</option>
                            <option value="shop">Shop (non-billable)</option>
                        </select>
                        <select name="work_order_id" class="rounded-md border-gray-300 text-sm">
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
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 font-semibold text-gray-800">
                    Time entries <span class="text-xs font-normal text-gray-400">· pay rules: 1.5× Mon–Thu after-hours, 2× Fri noon–Sun</span>
                </div>
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
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
                    <tbody class="divide-y divide-gray-100">
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
                            <tr><td colspan="7" class="px-6 py-4 text-gray-500">No time entries — clock in above.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
