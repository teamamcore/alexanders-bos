<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <a href="{{ route('fleet.index') }}" class="text-xs font-medium text-slate-500 hover:text-slate-700">← Fleet</a>
                <h2 class="page-title">{{ $v->label() }} @if ($v->status === 'down')<span class="badge-red align-middle">down</span>@endif</h2>
                <div class="text-sm text-slate-500">{{ $v->year }} {{ $v->make }} {{ $v->model }} · VIN {{ $v->vin ?? '—' }} · plate {{ $v->plate ?? '—' }} · {{ $v->driver?->name ?? $v->crew?->name ?? 'unassigned' }}</div>
            </div>
            @include('ops.assets.partials.tabs')
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
            @if (session('status'))<div class="lg:col-span-3 flash-ok">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="lg:col-span-3 flash-err">{{ $errors->first() }}</div>@endif
            @if ($serviceDue || $renewals || $stale)
                <div class="lg:col-span-3 flash-warn space-x-2">
                    @foreach ($serviceDue as $d)<span class="badge-amber">{{ $serviceItems[$d['name']] ?? $d['name'] }} due · {{ $d['reason'] }}</span>@endforeach
                    @foreach ($renewals as $d)<span class="{{ $d['overdue'] ? 'badge-red' : 'badge-amber' }}">{{ $d['name'] }} {{ $d['overdue'] ? 'overdue' : 'due' }} {{ $d['due']->format('M j') }}</span>@endforeach
                    @if ($stale)<span class="badge-amber">Mileage not updated in {{ config('batsons.fleet_mileage_stale_days') }}+ days — enter it to queue service work</span>@endif
                </div>
            @endif

            <div class="lg:col-span-2 space-y-6">
                {{-- TCO --}}
                <div class="card p-6">
                    <div class="card-title mb-3">Cost of ownership</div>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                        <div><div class="stat-label">Mileage</div><div class="text-2xl font-bold text-slate-900">{{ number_format($v->mileage) }}</div></div>
                        <div><div class="stat-label">Maintenance</div><div class="text-2xl font-bold text-slate-900">${{ number_format($costByType['maintenance'] ?? 0, 0) }}</div></div>
                        <div><div class="stat-label">Repairs</div><div class="text-2xl font-bold text-slate-900">${{ number_format($costByType['repair'] ?? 0, 0) }}</div></div>
                        <div><div class="stat-label">Fuel</div><div class="text-2xl font-bold text-slate-900">${{ number_format($costByType['fuel'] ?? 0, 0) }}</div></div>
                        <div><div class="stat-label">TCO to date</div><div class="text-2xl font-bold text-slate-900">${{ number_format($tco, 0) }}</div><div class="text-xs text-slate-400">depr. ${{ number_format($depreciation['to_date'], 0) }} · book ${{ number_format($depreciation['book_value'], 0) }}</div></div>
                    </div>
                </div>

                {{-- Mileage --}}
                <form method="POST" action="{{ route('fleet.mileage', $v) }}" class="card p-6">
                    @csrf
                    <div class="card-title mb-3">Report mileage <span class="card-note">· from the field, at least monthly</span></div>
                    <div class="flex flex-wrap items-end gap-3">
                        <div><x-input-label for="odo" value="Odometer" /><x-text-input id="odo" name="odometer" type="number" min="{{ $v->mileage }}" value="{{ $v->mileage }}" class="mt-1 w-36" required /></div>
                        <div><x-input-label for="odod" value="Date" /><x-text-input id="odod" name="recorded_at" type="date" value="{{ today()->toDateString() }}" class="mt-1" /></div>
                        <x-primary-button>Record</x-primary-button>
                        <span class="text-xs text-slate-400">Last: {{ $v->readings->first()?->recorded_at?->toFormattedDateString() ?? 'never' }}</span>
                    </div>
                </form>

                {{-- Costs --}}
                <div class="card overflow-hidden">
                    <div class="card-head"><span class="card-title">Maintenance, repair &amp; fuel</span></div>
                    <table class="table"><thead><tr><th>Date</th><th>Type</th><th>Service item</th><th class="text-right">Odometer</th><th>Notes</th><th class="text-right">Cost</th></tr></thead>
                        <tbody>@forelse ($v->maintenance as $m)<tr><td class="text-slate-600">{{ $m->performed_at?->format('M j, Y') }}</td><td><span class="badge-gray">{{ $m->type }}</span></td><td class="text-slate-600">{{ $m->service_item ? ($serviceItems[$m->service_item] ?? $m->service_item) : '—' }}</td><td class="text-right text-slate-600">{{ $m->odometer ? number_format($m->odometer) : '—' }}</td><td class="text-xs text-slate-600">{{ $m->notes }}</td><td class="text-right font-medium">${{ number_format((float) $m->cost, 2) }}</td></tr>@empty<tr><td colspan="6" class="px-6 py-6 text-center text-slate-400">Nothing logged yet.</td></tr>@endforelse</tbody>
                    </table>
                    <form method="POST" action="{{ route('fleet.cost', $v) }}" class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex flex-wrap items-end gap-3">
                        @csrf
                        <div><x-input-label for="ct" value="Type" /><select id="ct" name="type" class="mt-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">@foreach ($costTypes as $t)<option value="{{ $t }}">{{ ucfirst($t) }}</option>@endforeach</select></div>
                        <div><x-input-label for="csi" value="Service item" /><select id="csi" name="service_item" class="mt-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="">—</option>@foreach ($serviceItems as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                        <div><x-input-label for="cc" value="Cost" /><x-text-input id="cc" name="cost" type="number" step="0.01" min="0" class="mt-1 w-28" required /></div>
                        <div><x-input-label for="cd" value="Date" /><x-text-input id="cd" name="performed_at" type="date" value="{{ today()->toDateString() }}" class="mt-1" /></div>
                        <div><x-input-label for="co" value="Odometer" /><x-text-input id="co" name="odometer" type="number" min="0" class="mt-1 w-32" /></div>
                        <div class="grow"><x-input-label for="cn" value="Notes" /><x-text-input id="cn" name="notes" class="mt-1 w-full" /></div>
                        <x-secondary-button type="submit">Log</x-secondary-button>
                    </form>
                </div>
            </div>

            <div class="space-y-6">
                {{-- Renewals --}}
                <div class="card p-6">
                    <div class="card-title mb-3">Renewals</div>
                    <dl class="text-sm space-y-1">
                        @foreach (['Registration' => $v->registration_due, 'Inspection' => $v->inspection_due, 'Insurance' => $v->insurance_due] as $n => $d)
                            <div class="flex justify-between"><dt class="text-slate-600">{{ $n }}</dt><dd class="{{ $d && $d->isPast() ? 'text-red-700 font-semibold' : ($d && $d->lte(today()->addDays(30)) ? 'text-amber-700 font-semibold' : 'text-slate-900') }}">{{ $d?->toFormattedDateString() ?? '—' }}</dd></div>
                        @endforeach
                    </dl>
                </div>

                {{-- Service intervals --}}
                <div class="card p-6">
                    <div class="card-title mb-3">Service intervals</div>
                    <ul class="text-sm space-y-1">
                        @forelse ($v->service_intervals ?? [] as $i)
                            <li class="flex justify-between"><span class="text-slate-600">{{ $serviceItems[$i['name']] ?? $i['name'] }}</span><span class="text-slate-900">@if (!empty($i['every_miles']))every {{ number_format($i['every_miles']) }} mi @endif @if (!empty($i['every_months']))every {{ $i['every_months'] }} mo @endif</span></li>
                        @empty
                            <li class="text-slate-400">No intervals set.</li>
                        @endforelse
                    </ul>
                </div>

                {{-- Issues --}}
                <div class="card overflow-hidden">
                    <div class="card-head"><span class="card-title">Issues &amp; damage</span></div>
                    <ul class="divide-y divide-slate-100 text-sm">
                        @forelse ($v->issues as $i)
                            <li class="px-6 py-2.5">
                                <div class="flex justify-between gap-2"><span class="{{ $i->severity === 'major' ? 'badge-red' : 'badge-amber' }}">{{ $i->severity }}</span>
                                    @if ($i->status === 'open')<form method="POST" action="{{ route('fleet.issues.resolve', $i) }}">@csrf<button class="text-xs font-medium text-brand-700">resolve</button></form>@else<span class="text-xs text-slate-400">resolved</span>@endif</div>
                                <div class="text-slate-700 mt-1">{{ $i->description }}</div>
                                <div class="text-xs text-slate-400">{{ $i->reporter?->name }} · {{ $i->created_at?->diffForHumans() }}@if ($i->photo_path) · <a class="text-brand-700" href="{{ Storage::disk('public')->url($i->photo_path) }}" target="_blank">photo</a>@endif</div>
                            </li>
                        @empty
                            <li class="px-6 py-4 text-slate-400">No issues reported.</li>
                        @endforelse
                    </ul>
                    <form method="POST" action="{{ route('fleet.issue', $v) }}" enctype="multipart/form-data" class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 space-y-2">
                        @csrf
                        <x-text-input name="description" class="w-full" placeholder="Report an issue or damage…" required />
                        <div class="flex items-center gap-2">
                            <select name="severity" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="minor">minor</option><option value="major">major</option></select>
                            <input name="photo" type="file" accept="image/*" class="text-xs text-slate-500 file:mr-2 file:rounded-md file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs" />
                            <x-secondary-button type="submit">Report</x-secondary-button>
                        </div>
                    </form>
                </div>

                {{-- Assignment --}}
                @if ($canEdit)
                    <form method="POST" action="{{ route('fleet.update', $v) }}" class="card p-6 space-y-2">
                        @csrf
                        <div class="card-title mb-1">Assignment &amp; renewals</div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="col-span-2"><x-input-label for="an" value="Unit name" /><x-text-input id="an" name="name" value="{{ $v->name }}" class="mt-1 w-full" /></div>
                            <div><x-input-label for="ad" value="Driver" /><select id="ad" name="assigned_user_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="">—</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected($v->assigned_user_id === $u->id)>{{ $u->name }}</option>@endforeach</select></div>
                            <div><x-input-label for="ac" value="Crew" /><select id="ac" name="assigned_crew_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="">—</option>@foreach ($crews as $c)<option value="{{ $c->id }}" @selected($v->assigned_crew_id === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
                            <div><x-input-label for="ar" value="Registration" /><x-text-input id="ar" name="registration_due" type="date" value="{{ $v->registration_due?->toDateString() }}" class="mt-1 w-full" /></div>
                            <div><x-input-label for="ai" value="Inspection" /><x-text-input id="ai" name="inspection_due" type="date" value="{{ $v->inspection_due?->toDateString() }}" class="mt-1 w-full" /></div>
                            <div><x-input-label for="ains" value="Insurance" /><x-text-input id="ains" name="insurance_due" type="date" value="{{ $v->insurance_due?->toDateString() }}" class="mt-1 w-full" /></div>
                            <div><x-input-label for="apc" value="Purchase cost" /><x-text-input id="apc" name="purchase_cost" type="number" step="0.01" value="{{ $v->purchase_cost }}" class="mt-1 w-full" /></div>
                            <div><x-input-label for="apd" value="Purchased" /><x-text-input id="apd" name="purchased_at" type="date" value="{{ $v->purchased_at?->toDateString() }}" class="mt-1 w-full" /></div>
                            <div><x-input-label for="al" value="Life (yrs)" /><x-text-input id="al" name="useful_life_years" type="number" min="1" value="{{ $v->useful_life_years }}" class="mt-1 w-full" /></div>
                        </div>
                        <x-secondary-button type="submit">Save</x-secondary-button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
