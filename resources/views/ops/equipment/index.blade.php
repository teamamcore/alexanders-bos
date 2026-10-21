<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">Billable Equipment <span class="sow">SOW §3.7</span></h2>
            @include('ops.assets.partials.tabs')
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            @if (session('status'))<div class="flash-ok">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="flash-err">{{ $errors->first() }}</div>@endif

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach ($stats as $label => $value)
                    <div class="stat"><div class="stat-value {{ in_array($label, ['Service due', 'Down']) && $value ? 'text-amber-600' : '' }}">{{ $value }}</div><div class="stat-label">{{ $label }}</div></div>
                @endforeach
            </div>

            <div class="card overflow-hidden">
                <div class="card-head"><span class="card-title">Register</span><span class="card-note">revenue billed vs. maintenance + depreciation — does it earn its keep?</span></div>
                <table class="table">
                    <thead><tr><th>Item</th><th>Rate</th><th>Hours</th><th>Status</th><th>Service</th><th class="text-right">Revenue</th><th class="text-right">Cost</th><th class="text-right">Net</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($rows as $r)
                            @php $e = $r['item']; @endphp
                            <tr>
                                <td><div class="font-medium text-slate-900">{{ $e->name }}</div><div class="text-xs text-slate-400">{{ str_replace('_', ' ', $e->type ?? '') }}</div></td>
                                <td class="text-slate-700">${{ number_format((float) $e->billing_rate, 2) }} <span class="text-xs text-slate-400">/ {{ $units[$e->billing_unit] ?? $e->billing_unit }}</span></td>
                                <td class="text-slate-700">{{ number_format((float) $e->hours_used, 1) }}</td>
                                <td>
                                    @if ($e->status === 'checked_out')<span class="badge-blue">out · {{ $e->holder?->name }}</span>
                                    @elseif ($e->status === 'down')<span class="badge-red">down</span>
                                    @else<span class="badge-green">available</span>@endif
                                    @if ($r['open_issues'])<span class="badge-amber ml-1">{{ $r['open_issues'] }} issue</span>@endif
                                </td>
                                <td>@if ($r['maintenance']['due'])<span class="badge-amber">due</span>@elseif ($r['maintenance']['every_hours'] || $r['maintenance']['every_days'])<span class="text-xs text-slate-500">{{ $r['maintenance']['hours_since'] }}h{{ $r['maintenance']['every_hours'] ? '/'.$r['maintenance']['every_hours'].'h' : '' }}</span>@else<span class="text-xs text-slate-400">no schedule</span>@endif</td>
                                <td class="text-right text-slate-700">${{ number_format($r['revenue'], 0) }}</td>
                                <td class="text-right text-slate-700">${{ number_format($r['cost'] + $r['depreciation']['to_date'], 0) }}</td>
                                <td class="text-right font-semibold {{ $r['net'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ $r['net'] < 0 ? '-' : '' }}${{ number_format(abs($r['net']), 0) }}</td>
                                <td class="text-right"><a href="{{ route('equipment.show', $e) }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Open →</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($canEdit)
                <form method="POST" action="{{ route('equipment.store') }}" class="card p-6 space-y-3">
                    @csrf
                    <div class="card-title">Add equipment</div>
                    <div class="grid sm:grid-cols-4 lg:grid-cols-6 gap-3">
                        <div class="sm:col-span-2"><x-input-label for="ename" value="Name" /><x-text-input id="ename" name="name" class="mt-1 w-full" required /></div>
                        <div><x-input-label for="etype" value="Type" /><select id="etype" name="type" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="">—</option>@foreach ($types as $t)<option value="{{ $t }}">{{ str_replace('_', ' ', $t) }}</option>@endforeach</select></div>
                        <div><x-input-label for="erate" value="Rate" /><x-text-input id="erate" name="billing_rate" type="number" step="0.01" min="0" class="mt-1 w-full" required /></div>
                        <div><x-input-label for="eunit" value="Unit" /><select id="eunit" name="billing_unit" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">@foreach ($units as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                        <div><x-input-label for="ecost" value="Purchase cost" /><x-text-input id="ecost" name="purchase_cost" type="number" step="0.01" min="0" class="mt-1 w-full" /></div>
                        <div><x-input-label for="edate" value="Purchased" /><x-text-input id="edate" name="purchased_at" type="date" class="mt-1 w-full" /></div>
                        <div><x-input-label for="elife" value="Life (yrs)" /><x-text-input id="elife" name="useful_life_years" type="number" min="1" class="mt-1 w-full" /></div>
                        <div><x-input-label for="esalv" value="Salvage" /><x-text-input id="esalv" name="salvage_value" type="number" step="0.01" min="0" class="mt-1 w-full" /></div>
                        <div><x-input-label for="eh" value="Service every (h)" /><x-text-input id="eh" name="every_hours" type="number" min="1" class="mt-1 w-full" /></div>
                        <div><x-input-label for="ed" value="Service every (days)" /><x-text-input id="ed" name="every_days" type="number" min="1" class="mt-1 w-full" /></div>
                    </div>
                    <x-primary-button>Add to register</x-primary-button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
