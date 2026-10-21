<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <a href="{{ route('equipment.index') }}" class="text-xs font-medium text-slate-500 hover:text-slate-700">← Equipment</a>
                <h2 class="page-title">{{ $e->name }}
                    @if ($e->status === 'checked_out')<span class="badge-blue align-middle">out · {{ $e->holder?->name }}</span>@elseif ($e->status === 'down')<span class="badge-red align-middle">down</span>@else<span class="badge-green align-middle">available</span>@endif
                </h2>
                <div class="text-sm text-slate-500">{{ str_replace('_', ' ', $e->type ?? 'equipment') }} · ${{ number_format((float) $e->billing_rate, 2) }} / {{ $units[$e->billing_unit] ?? $e->billing_unit }} · {{ number_format((float) $e->hours_used, 1) }} h used</div>
            </div>
            @include('ops.assets.partials.tabs')
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
            @if (session('status'))<div class="lg:col-span-3 flash-ok">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="lg:col-span-3 flash-err">{{ $errors->first() }}</div>@endif
            @if ($maintenance['due'])<div class="lg:col-span-3 flash-warn"><span class="font-semibold">Service due:</span> {{ implode(' · ', $maintenance['reasons']) }}</div>@endif

            <div class="lg:col-span-2 space-y-6">
                {{-- Earns its keep --}}
                <div class="card p-6">
                    <div class="card-title mb-3">Revenue vs. cost <span class="card-note">· per-item report</span></div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div><div class="stat-label">Revenue billed</div><div class="text-2xl font-bold text-slate-900">${{ number_format($revenue, 0) }}</div></div>
                        <div><div class="stat-label">Maintenance &amp; repair</div><div class="text-2xl font-bold text-slate-900">${{ number_format($cost, 0) }}</div></div>
                        <div><div class="stat-label">Depreciation to date</div><div class="text-2xl font-bold text-slate-900">${{ number_format($depreciation['to_date'], 0) }}</div><div class="text-xs text-slate-400">book ${{ number_format($depreciation['book_value'], 0) }} · {{ $depreciation['pct'] }}%</div></div>
                        <div><div class="stat-label">Net</div><div class="text-2xl font-bold {{ $net >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ $net < 0 ? '-' : '' }}${{ number_format(abs($net), 0) }}</div></div>
                    </div>
                </div>

                {{-- Bill on work order --}}
                <form method="POST" action="{{ route('equipment.usage', $e) }}" class="card p-6">
                    @csrf
                    <div class="card-title mb-3">Bill usage on a work order <span class="card-note">· linked to this item; hours count toward service</span></div>
                    <div class="flex flex-wrap items-end gap-3">
                        <div><x-input-label for="uwo" value="Work order" /><select id="uwo" name="work_order_id" required class="mt-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">@foreach ($workOrders as $w)<option value="{{ $w->id }}">{{ $w->number }} ({{ $w->job?->title }})</option>@endforeach</select></div>
                        <div><x-input-label for="uq" value="{{ ucfirst($units[$e->billing_unit] ?? $e->billing_unit) }}s" /><x-text-input id="uq" name="quantity" type="number" step="0.25" min="0.25" class="mt-1 w-28" required /></div>
                        <div><x-input-label for="ud" value="Date" /><x-text-input id="ud" name="used_on" type="date" class="mt-1" value="{{ today()->toDateString() }}" /></div>
                        <x-primary-button>Bill</x-primary-button>
                    </div>
                </form>

                {{-- Usage log --}}
                <div class="card overflow-hidden">
                    <div class="card-head"><span class="card-title">Usage billed</span></div>
                    <table class="table"><thead><tr><th>Date</th><th>Work order</th><th class="text-right">Qty</th><th class="text-right">Rate</th><th class="text-right">Amount</th></tr></thead>
                        <tbody>@forelse ($e->usages as $u)<tr><td class="text-slate-600">{{ $u->used_on?->format('M j, Y') }}</td><td class="font-medium text-slate-900">{{ $u->workOrder?->number }}</td><td class="text-right">{{ $u->quantity }} {{ $u->unit }}</td><td class="text-right">${{ number_format((float) $u->rate, 2) }}</td><td class="text-right font-medium">${{ number_format((float) $u->amount, 2) }}</td></tr>@empty<tr><td colspan="5" class="px-6 py-6 text-center text-slate-400">Not billed on any work order yet.</td></tr>@endforelse</tbody>
                    </table>
                </div>

                {{-- Maintenance --}}
                <div class="card overflow-hidden">
                    <div class="card-head"><span class="card-title">Maintenance, repair, parts &amp; rental</span><span class="card-note">@if ($maintenance['every_hours'])every {{ $maintenance['every_hours'] }} h @endif @if ($maintenance['every_days'])every {{ $maintenance['every_days'] }} d @endif · {{ $maintenance['hours_since'] }} h since service</span></div>
                    <table class="table"><thead><tr><th>Date</th><th>Type</th><th>Hours</th><th>Notes</th><th class="text-right">Cost</th></tr></thead>
                        <tbody>@forelse ($e->maintenance as $m)<tr><td class="text-slate-600">{{ $m->performed_at?->format('M j, Y') }}</td><td><span class="badge-gray">{{ $m->type }}</span></td><td class="text-slate-600">{{ $m->hours_at_service !== null ? number_format((float) $m->hours_at_service, 1) : '—' }}</td><td class="text-slate-600 text-xs">{{ $m->notes }}</td><td class="text-right font-medium">${{ number_format((float) $m->cost, 2) }}</td></tr>@empty<tr><td colspan="5" class="px-6 py-6 text-center text-slate-400">Nothing logged yet.</td></tr>@endforelse</tbody>
                    </table>
                    @if ($canEdit)
                        <form method="POST" action="{{ route('equipment.maintenance', $e) }}" class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex flex-wrap items-end gap-3">
                            @csrf
                            <div><x-input-label for="mt" value="Type" /><select id="mt" name="type" class="mt-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">@foreach ($costTypes as $t)<option value="{{ $t }}">{{ ucfirst($t) }}</option>@endforeach</select></div>
                            <div><x-input-label for="mc" value="Cost" /><x-text-input id="mc" name="cost" type="number" step="0.01" min="0" class="mt-1 w-28" required /></div>
                            <div><x-input-label for="md" value="Date" /><x-text-input id="md" name="performed_at" type="date" class="mt-1" value="{{ today()->toDateString() }}" /></div>
                            <div class="grow"><x-input-label for="mn" value="Notes" /><x-text-input id="mn" name="notes" class="mt-1 w-full" /></div>
                            <x-secondary-button type="submit">Log</x-secondary-button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="space-y-6">
                {{-- Check-out / return --}}
                <div class="card p-6">
                    <div class="card-title mb-3">Check-out &amp; return</div>
                    @if ($e->status === 'checked_out')
                        <p class="text-sm text-slate-600">Out with <span class="font-medium text-slate-900">{{ $e->holder?->name }}</span> since {{ $e->checked_out_at?->diffForHumans() }}.</p>
                        <form method="POST" action="{{ route('equipment.return', $e) }}" enctype="multipart/form-data" class="mt-3 space-y-2">
                            @csrf
                            <x-text-input name="issue" class="w-full" placeholder="Damage / issue on return (optional)" />
                            <div class="flex items-center gap-2">
                                <select name="severity" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="minor">minor</option><option value="major">major — take out of service</option></select>
                                <input name="photo" type="file" accept="image/*" class="text-xs text-slate-500 file:mr-2 file:rounded-md file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs" />
                            </div>
                            <x-primary-button>Return</x-primary-button>
                        </form>
                    @elseif ($e->status === 'down')
                        <p class="text-sm text-red-700">Out of service. Log a repair to bring it back.</p>
                    @else
                        <form method="POST" action="{{ route('equipment.checkout', $e) }}" class="flex items-end gap-2">
                            @csrf
                            <div class="grow"><x-input-label for="co" value="Check out to" /><select id="co" name="user_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">@foreach ($users as $u)<option value="{{ $u->id }}" @selected($u->is(auth()->user()))>{{ $u->name }}</option>@endforeach</select></div>
                            <x-primary-button>Check out</x-primary-button>
                        </form>
                    @endif
                </div>

                {{-- Issues --}}
                <div class="card overflow-hidden">
                    <div class="card-head"><span class="card-title">Issues &amp; damage</span></div>
                    <ul class="divide-y divide-slate-100 text-sm">
                        @forelse ($e->issues as $i)
                            <li class="px-6 py-2.5">
                                <div class="flex justify-between gap-2"><span class="{{ $i->severity === 'major' ? 'badge-red' : 'badge-amber' }}">{{ $i->severity }}</span>
                                    @if ($i->status === 'open')<form method="POST" action="{{ route('equipment.issues.resolve', $i) }}">@csrf<button class="text-xs font-medium text-brand-700">resolve</button></form>@else<span class="text-xs text-slate-400">resolved</span>@endif</div>
                                <div class="text-slate-700 mt-1">{{ $i->description }}</div>
                                <div class="text-xs text-slate-400">{{ $i->reporter?->name }} · {{ $i->created_at?->diffForHumans() }}@if ($i->photo_path) · <a class="text-brand-700" href="{{ Storage::disk('public')->url($i->photo_path) }}" target="_blank">photo</a>@endif</div>
                            </li>
                        @empty
                            <li class="px-6 py-4 text-slate-400">No issues reported.</li>
                        @endforelse
                    </ul>
                    <form method="POST" action="{{ route('equipment.issue', $e) }}" enctype="multipart/form-data" class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 space-y-2">
                        @csrf
                        <x-text-input name="description" class="w-full" placeholder="Report an issue from the field…" required />
                        <div class="flex items-center gap-2">
                            <select name="severity" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="minor">minor</option><option value="major">major</option></select>
                            <input name="photo" type="file" accept="image/*" class="text-xs text-slate-500 file:mr-2 file:rounded-md file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs" />
                            <x-secondary-button type="submit">Report</x-secondary-button>
                        </div>
                    </form>
                </div>

                {{-- Record --}}
                @if ($canEdit)
                    <form method="POST" action="{{ route('equipment.update', $e) }}" class="card p-6 space-y-2">
                        @csrf
                        <div class="card-title mb-1">Record</div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="col-span-2"><x-input-label for="rn" value="Name" /><x-text-input id="rn" name="name" value="{{ $e->name }}" class="mt-1 w-full" required /></div>
                            <div><x-input-label for="rt" value="Type" /><select id="rt" name="type" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="">—</option>@foreach ($types as $t)<option value="{{ $t }}" @selected($e->type === $t)>{{ str_replace('_', ' ', $t) }}</option>@endforeach</select></div>
                            <div><x-input-label for="ru" value="Unit" /><select id="ru" name="billing_unit" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">@foreach ($units as $k => $l)<option value="{{ $k }}" @selected($e->billing_unit === $k)>{{ $l }}</option>@endforeach</select></div>
                            <div><x-input-label for="rr" value="Rate" /><x-text-input id="rr" name="billing_rate" type="number" step="0.01" min="0" value="{{ $e->billing_rate }}" class="mt-1 w-full" required /></div>
                            <div><x-input-label for="rc" value="Purchase cost" /><x-text-input id="rc" name="purchase_cost" type="number" step="0.01" min="0" value="{{ $e->purchase_cost }}" class="mt-1 w-full" /></div>
                            <div><x-input-label for="rd" value="Purchased" /><x-text-input id="rd" name="purchased_at" type="date" value="{{ $e->purchased_at?->toDateString() }}" class="mt-1 w-full" /></div>
                            <div><x-input-label for="rl" value="Life (yrs)" /><x-text-input id="rl" name="useful_life_years" type="number" min="1" value="{{ $e->useful_life_years }}" class="mt-1 w-full" /></div>
                            <div><x-input-label for="rs" value="Salvage" /><x-text-input id="rs" name="salvage_value" type="number" step="0.01" min="0" value="{{ $e->salvage_value }}" class="mt-1 w-full" /></div>
                            <div><x-input-label for="rh" value="Service every (h)" /><x-text-input id="rh" name="every_hours" type="number" min="1" value="{{ $maintenance['every_hours'] }}" class="mt-1 w-full" /></div>
                            <div><x-input-label for="rdd" value="Service every (d)" /><x-text-input id="rdd" name="every_days" type="number" min="1" value="{{ $maintenance['every_days'] }}" class="mt-1 w-full" /></div>
                        </div>
                        <x-secondary-button type="submit">Save</x-secondary-button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
