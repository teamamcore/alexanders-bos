<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">Fleet <span class="sow">SOW §3.7</span></h2>
            @include('ops.assets.partials.tabs')
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            @if (session('status'))<div class="flash-ok">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="flash-err">{{ $errors->first() }}</div>@endif

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach ($stats as $label => $value)
                    <div class="stat"><div class="stat-value {{ $label !== 'Vehicles' && $value ? 'text-amber-600' : '' }}">{{ $value }}</div><div class="stat-label">{{ $label }}</div></div>
                @endforeach
            </div>

            <div class="card overflow-hidden">
                <div class="card-head"><span class="card-title">Vehicles</span><span class="card-note">service by miles or date · renewals · monthly mileage check</span></div>
                <table class="table">
                    <thead><tr><th>Vehicle</th><th>Assigned</th><th class="text-right">Mileage</th><th>Alerts</th><th class="text-right">Costs</th><th class="text-right">TCO</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($rows as $r)
                            @php $v = $r['v']; @endphp
                            <tr>
                                <td><div class="font-medium text-slate-900">{{ $v->label() }} @if ($v->status === 'down')<span class="badge-red ml-1">down</span>@endif</div><div class="text-xs text-slate-400">{{ $v->year }} {{ $v->make }} {{ $v->model }} · {{ $v->plate ?? 'no plate' }}</div></td>
                                <td class="text-slate-700">{{ $v->driver?->name ?? $v->crew?->name ?? '—' }}</td>
                                <td class="text-right text-slate-700">{{ number_format($v->mileage) }} @if ($r['stale'])<span class="badge-amber ml-1">update</span>@endif</td>
                                <td class="text-xs space-x-1">
                                    @foreach ($r['service_due'] as $d)<span class="badge-amber">{{ $serviceItems[$d['name']] ?? $d['name'] }}</span>@endforeach
                                    @foreach ($r['renewals'] as $d)<span class="{{ $d['overdue'] ? 'badge-red' : 'badge-amber' }}">{{ $d['name'] }} {{ $d['due']->format('M j') }}</span>@endforeach
                                    @if ($r['open_issues'])<span class="badge-red">{{ $r['open_issues'] }} issue</span>@endif
                                    @if (! $r['service_due'] && ! $r['renewals'] && ! $r['open_issues'])<span class="text-slate-400">—</span>@endif
                                </td>
                                <td class="text-right text-slate-700">${{ number_format($r['cost'], 0) }}</td>
                                <td class="text-right text-slate-700">${{ number_format($r['tco'], 0) }}</td>
                                <td class="text-right"><a href="{{ route('fleet.show', $v) }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Open →</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-8 text-center text-slate-500">No vehicles yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($canEdit)
                <form method="POST" action="{{ route('fleet.store') }}" class="card p-6 space-y-3">
                    @csrf
                    <div class="card-title">Add a vehicle</div>
                    <div class="grid sm:grid-cols-4 lg:grid-cols-6 gap-3">
                        <div><x-input-label for="vn" value="Unit name" /><x-text-input id="vn" name="name" class="mt-1 w-full" placeholder="Truck 7" /></div>
                        <div><x-input-label for="vy" value="Year" /><x-text-input id="vy" name="year" type="number" class="mt-1 w-full" /></div>
                        <div><x-input-label for="vm" value="Make" /><x-text-input id="vm" name="make" class="mt-1 w-full" /></div>
                        <div><x-input-label for="vmo" value="Model" /><x-text-input id="vmo" name="model" class="mt-1 w-full" /></div>
                        <div><x-input-label for="vv" value="VIN" /><x-text-input id="vv" name="vin" class="mt-1 w-full" /></div>
                        <div><x-input-label for="vp" value="Plate" /><x-text-input id="vp" name="plate" class="mt-1 w-full" /></div>
                        <div><x-input-label for="vd" value="Driver" /><select id="vd" name="assigned_user_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="">—</option>@foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
                        <div><x-input-label for="vmi" value="Mileage" /><x-text-input id="vmi" name="mileage" type="number" min="0" class="mt-1 w-full" /></div>
                        <div><x-input-label for="vreg" value="Registration due" /><x-text-input id="vreg" name="registration_due" type="date" class="mt-1 w-full" /></div>
                        <div><x-input-label for="vins" value="Inspection due" /><x-text-input id="vins" name="inspection_due" type="date" class="mt-1 w-full" /></div>
                        <div><x-input-label for="vinsur" value="Insurance due" /><x-text-input id="vinsur" name="insurance_due" type="date" class="mt-1 w-full" /></div>
                        <div><x-input-label for="vpc" value="Purchase cost" /><x-text-input id="vpc" name="purchase_cost" type="number" step="0.01" min="0" class="mt-1 w-full" /></div>
                        <div><x-input-label for="vpd" value="Purchased" /><x-text-input id="vpd" name="purchased_at" type="date" class="mt-1 w-full" /></div>
                        <div><x-input-label for="vl" value="Life (yrs)" /><x-text-input id="vl" name="useful_life_years" type="number" min="1" class="mt-1 w-full" /></div>
                        <div><x-input-label for="vs" value="Salvage" /><x-text-input id="vs" name="salvage_value" type="number" step="0.01" min="0" class="mt-1 w-full" /></div>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Service intervals</div>
                        <div class="grid sm:grid-cols-3 gap-3">
                            @foreach ($serviceItems as $k => $label)
                                <div class="flex items-end gap-2"><span class="text-sm text-slate-700 w-28">{{ $label }}</span><x-text-input name="intervals[{{ $k }}][every_miles]" type="number" min="1" placeholder="mi" class="w-24" /><x-text-input name="intervals[{{ $k }}][every_months]" type="number" min="1" placeholder="mo" class="w-20" /></div>
                            @endforeach
                        </div>
                    </div>
                    <x-primary-button>Add vehicle</x-primary-button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
