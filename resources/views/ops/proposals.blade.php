<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ term('proposal', true) }} &amp; Estimating <span class="text-sm text-gray-400">(SOW §3.5)</span></h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if (session('status'))
                <div class="rounded-md bg-green-50 text-green-800 px-4 py-2 text-sm">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="rounded-md bg-red-50 text-red-800 px-4 py-2 text-sm">{{ session('error') }}</div>
            @endif

            {{-- New proposal --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <div class="font-semibold text-gray-800 mb-3">New proposal</div>
                <form method="POST" action="{{ route('proposals.store') }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-500">Customer</label>
                        <select name="customer_id" class="rounded-md border-gray-300 text-sm" required>
                            @forelse ($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @empty
                                <option value="">— no customers —</option>
                            @endforelse
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Title</label>
                        <input name="title" class="rounded-md border-gray-300 text-sm" placeholder="Backflow retrofit" required />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Estimate $</label>
                        <input name="amount" type="number" step="0.01" class="w-28 rounded-md border-gray-300 text-sm" placeholder="0.00" />
                    </div>
                    <x-primary-button>Create</x-primary-button>
                </form>
            </div>

            {{-- Pipeline --}}
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 font-semibold text-gray-800">
                    Pipeline <span class="text-xs font-normal text-gray-400">· draft → sent → follow-up → won / lost</span>
                </div>
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-6 py-3">Proposal</th>
                            <th class="px-6 py-3">Customer</th>
                            <th class="px-6 py-3">Total</th>
                            <th class="px-6 py-3">Stage</th>
                            <th class="px-6 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($proposals as $p)
                            <tr>
                                <td class="px-6 py-3 font-medium">{{ $p->title }}</td>
                                <td class="px-6 py-3 text-gray-500">{{ $p->customer?->name }}</td>
                                <td class="px-6 py-3">${{ number_format((float) $p->total, 2) }}</td>
                                <td class="px-6 py-3">
                                    <span @class([
                                        'text-xs rounded-full px-2 py-1',
                                        'bg-gray-100 text-gray-700' => in_array($p->stage, ['draft', 'sent', 'follow_up']),
                                        'bg-green-50 text-green-700' => $p->stage === 'won',
                                        'bg-red-50 text-red-700' => $p->stage === 'lost',
                                    ])>{{ str_replace('_', ' ', $p->stage) }}</span>
                                </td>
                                <td class="px-6 py-3">
                                    @if ($p->convertedJob)
                                        <a href="{{ route('jobs.show', $p->convertedJob) }}" class="text-indigo-600 hover:text-indigo-800 text-xs">→ Job {{ $p->convertedJob->number ?? '#'.$p->convertedJob->id }}</a>
                                    @elseif (! in_array($p->stage, ['won', 'lost']))
                                        <div class="flex items-center gap-1">
                                            @foreach (['sent' => 'Sent', 'follow_up' => 'Follow-up', 'lost' => 'Lost'] as $stage => $label)
                                                <form method="POST" action="{{ route('proposals.stage', $p) }}">
                                                    @csrf
                                                    <input type="hidden" name="stage" value="{{ $stage }}" />
                                                    <button class="text-xs px-2 py-1 rounded border border-gray-200 hover:bg-gray-50">{{ $label }}</button>
                                                </form>
                                            @endforeach
                                            <form method="POST" action="{{ route('proposals.convert', $p) }}">
                                                @csrf
                                                <button class="text-xs px-2 py-1 rounded bg-indigo-600 text-white hover:bg-indigo-700">Convert to Job (Won)</button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-4 text-gray-500">No proposals yet — create one above.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
