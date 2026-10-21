<x-app-layout>
    <x-slot name="header">
        <h2 class="page-title">{{ term('proposal', true) }} &amp; Estimating <span class="sow">SOW §3.5</span></h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if (session('status'))
                <div class="flash-ok">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="flash-err">{{ session('error') }}</div>
            @endif

            {{-- New proposal --}}
            <div class="card p-6">
                <div class="card-title mb-3">New proposal</div>
                <form method="POST" action="{{ route('proposals.store') }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <label class="block text-xs text-slate-500">Customer</label>
                        <select name="customer_id" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" required>
                            @forelse ($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @empty
                                <option value="">— no customers —</option>
                            @endforelse
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500">Title</label>
                        <input name="title" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Backflow retrofit" required />
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500">Estimate $</label>
                        <input name="amount" type="number" step="0.01" class="w-28 rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" placeholder="0.00" />
                    </div>
                    <x-primary-button>Create</x-primary-button>
                </form>
            </div>

            {{-- Pipeline --}}
            <div class="card overflow-hidden">
                <div class="card-head card-title">
                    Pipeline <span class="card-note">· draft → sent → follow-up → won / lost</span>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th class="px-6 py-3">Proposal</th>
                            <th class="px-6 py-3">Customer</th>
                            <th class="px-6 py-3">Total</th>
                            <th class="px-6 py-3">Stage</th>
                            <th class="px-6 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($proposals as $p)
                            <tr>
                                <td class="px-6 py-3 font-medium">{{ $p->title }}</td>
                                <td class="px-6 py-3 text-slate-500">{{ $p->customer?->name }}</td>
                                <td class="px-6 py-3">${{ number_format((float) $p->total, 2) }}</td>
                                <td class="px-6 py-3">
                                    <span @class([
                                        'text-xs rounded-full px-2 py-1',
                                        'bg-gray-100 text-slate-700' => in_array($p->stage, ['draft', 'sent', 'follow_up']),
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
                                                    <button class="text-xs px-2 py-1 rounded border border-slate-200 hover:bg-slate-50">{{ $label }}</button>
                                                </form>
                                            @endforeach
                                            <form method="POST" action="{{ route('proposals.convert', $p) }}">
                                                @csrf
                                                <button class="text-xs px-2 py-1 rounded bg-indigo-600 text-white hover:bg-indigo-700">Convert to Job (Won)</button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-4 text-slate-500">No proposals yet — create one above.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
