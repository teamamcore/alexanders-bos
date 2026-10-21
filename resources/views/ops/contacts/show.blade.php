<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <a href="{{ route('contacts.index') }}" class="text-xs font-medium text-slate-500 hover:text-slate-700">← Contacts</a>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="page-title">{{ $customer->name }}</h2>
                    @if ($customer->segment)<span class="badge-blue">{{ str_replace('_', ' ', $customer->segment) }}</span>@endif
                </div>
                <div class="text-sm text-slate-500">Corp. POC: <span class="text-slate-800">{{ $customer->corpPoc?->name ?? '—' }}</span>{{ $customer->corpPoc?->phone ? ' · '.$customer->corpPoc->phone : '' }} · Contact owner: <span class="text-slate-800">{{ $customer->owner?->name ?? '—' }}</span> · Terms: {{ \App\Platform\Reporting\Reports::termsLabel($customer->payment_terms ?? 'net_30') }}</div>
            </div>
            <div class="text-right"><div class="stat-label">Paid · YTD</div><div class="text-2xl font-bold text-slate-900">${{ number_format($ytd, 0) }}</div><div class="text-xs text-slate-500">${{ number_format($openAr, 0) }} open A/R</div></div>
        </div>
    </x-slot>

    @php $money = fn ($v) => '$'.number_format((float) $v, 0); @endphp
    <div class="py-4 max-w-7xl mx-auto sm:px-6 lg:px-8">
        @if (session('status'))<div class="flash-ok mb-3">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="flash-err mb-3">{{ $errors->first() }}</div>@endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
            {{-- Locations (left) --}}
            <div class="lg:col-span-5 space-y-4" id="locations">
                <div class="card overflow-hidden">
                    <div class="card-head"><span class="card-title">Locations</span><span class="card-note">each with its local contact</span></div>
                    <ul class="divide-y divide-slate-100">
                        @forelse ($customer->serviceLocations as $loc)
                            <li class="px-5 py-3 flex items-center justify-between gap-3 {{ ($filters['location'] ?? null) === $loc->id ? 'bg-brand-50/50' : '' }}">
                                <div class="min-w-0"><a href="{{ route('contacts.show', [$customer, 'location' => $loc->id]) }}#comms" class="font-medium text-slate-900 hover:text-brand-700">{{ $loc->address }}</a><div class="text-xs text-slate-500">{{ $loc->label }}{{ $loc->label && $loc->type ? ' · ' : '' }}{{ $loc->type }}@if ($loc->access_notes) · {{ \Illuminate\Support\Str::limit($loc->access_notes, 50) }}@endif</div></div>
                                <div class="text-right shrink-0">
                                    @if ($canEdit)
                                        <form method="POST" action="{{ route('contacts.locations.contact', $loc) }}">@csrf<select name="contact_id" onchange="this.form.submit()" class="rounded-md border-slate-300 text-xs py-1"><option value="">— local contact —</option>@foreach ($customer->contacts as $ct)<option value="{{ $ct->id }}" @selected($loc->contact_id === $ct->id)>{{ $ct->name }}</option>@endforeach</select></form>
                                    @else
                                        <div class="text-sm text-slate-800">{{ $loc->contact?->name ?? '—' }}</div>
                                    @endif
                                    <div class="text-xs text-slate-500">{{ $loc->contact?->phone }}</div>
                                </div>
                            </li>
                        @empty
                            <li class="px-5 py-6 text-center text-slate-500">No locations yet.</li>
                        @endforelse
                    </ul>
                    @if ($canEdit)
                        <form method="POST" action="{{ route('contacts.locations.store', $customer) }}" class="px-5 py-3 border-t border-slate-100 bg-slate-50/50 grid grid-cols-2 gap-2">
                            @csrf
                            <x-text-input name="address" placeholder="Address" class="col-span-2 text-sm" required />
                            <x-text-input name="label" placeholder="Label (Building C)" class="text-sm" />
                            <select name="contact_id" class="rounded-lg border-slate-300 text-sm"><option value="">Local contact…</option>@foreach ($customer->contacts as $ct)<option value="{{ $ct->id }}">{{ $ct->name }}</option>@endforeach</select>
                            <x-text-input name="access_notes" placeholder="Access / badging / site training" class="col-span-2 text-sm" />
                            <div class="col-span-2"><x-secondary-button type="submit">Add location</x-secondary-button></div>
                        </form>
                    @endif
                </div>

                <div class="card overflow-hidden" id="contacts">
                    <div class="card-head"><span class="card-title">People</span></div>
                    <ul class="divide-y divide-slate-100 text-sm">
                        @foreach ($customer->contacts as $ct)<li class="px-5 py-2 flex justify-between"><span><span class="font-medium text-slate-900">{{ $ct->name }}</span> <span class="text-xs text-slate-500">{{ $ct->role }}</span>@if ($customer->corp_poc_contact_id === $ct->id)<span class="badge-blue ml-1">corp POC</span>@endif</span><span class="text-xs text-slate-600">{{ $ct->phone }}{{ $ct->phone && $ct->email ? ' · ' : '' }}{{ $ct->email }}</span></li>@endforeach
                    </ul>
                    @if ($canEdit)
                        <form method="POST" action="{{ route('contacts.people.store', $customer) }}" class="px-5 py-3 border-t border-slate-100 bg-slate-50/50 grid grid-cols-2 gap-2">@csrf<x-text-input name="name" placeholder="Name" class="text-sm" required /><x-text-input name="role" placeholder="Role (site manager)" class="text-sm" /><x-text-input name="phone" placeholder="Mobile" class="text-sm" /><x-text-input name="email" type="email" placeholder="Email" class="text-sm" /><div class="col-span-2"><x-secondary-button type="submit">Add person</x-secondary-button></div></form>
                    @endif
                </div>
            </div>

            {{-- Comms + AR (centre) --}}
            <div class="lg:col-span-5 space-y-4" id="comms">
                <div class="card overflow-hidden">
                    <div class="card-head"><span class="card-title">Comms</span><span class="card-note">@if ($filters['location'] || $filters['job'])filtered · <a href="{{ route('contacts.show', $customer) }}#comms" class="text-brand-700">all</a>@else all threads · click a location to filter @endif</span></div>
                    <ul class="divide-y divide-slate-100 max-h-[26rem] overflow-y-auto">
                        @forelse ($threads as $m)
                            <li class="px-5 py-2.5 {{ $m->direction === 'inbound' ? 'bg-brand-50/40' : '' }}">
                                <div class="flex items-center justify-between text-xs text-slate-500"><span>{{ $m->workOrder?->number ?? $m->job?->number ?? '' }} · {{ $m->direction === 'inbound' ? 'customer' : ($m->sender?->name ?? 'auto') }} · {{ $m->channel }}</span><span>{{ $m->created_at?->format('M j, g:ia') }}</span></div>
                                <div class="text-sm text-slate-800">{{ $m->body }}</div>
                            </li>
                        @empty
                            <li class="px-5 py-6 text-center text-slate-500">No conversations yet.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="card p-5" id="billing">
                    <div class="flex items-center justify-between"><span class="card-title">AR / Report</span><span class="card-note">paid vs. open by month</span></div>
                    @php $W = 420; $H = 120; $n = count($series); $slot = $W / max(1, $n); $bw = max(6, ($slot - 8) / 2); @endphp
                    <svg viewBox="0 0 {{ $W }} {{ $H + 18 }}" class="w-full h-40 mt-2" role="img" aria-label="Paid and open invoices by month">
                        @foreach ([0.5, 1] as $g)<line x1="0" x2="{{ $W }}" y1="{{ $H - $H * $g }}" y2="{{ $H - $H * $g }}" stroke="var(--chart-grid)" />@endforeach
                        @foreach ($series as $i => $s)
                            @php $x = $i * $slot + ($slot - (2 * $bw + 2)) / 2; $h1 = $H * $s['paid'] / $maxSeries; $h2 = $H * $s['open'] / $maxSeries; @endphp
                            <rect x="{{ $x }}" y="{{ $H - $h1 }}" width="{{ $bw }}" height="{{ $h1 }}" rx="2" fill="var(--chart-1)"><title>{{ $s['label'] }} paid {{ $money($s['paid']) }}</title></rect>
                            <rect x="{{ $x + $bw + 2 }}" y="{{ $H - $h2 }}" width="{{ $bw }}" height="{{ $h2 }}" rx="2" fill="var(--chart-2)"><title>{{ $s['label'] }} open {{ $money($s['open']) }}</title></rect>
                            <text x="{{ $i * $slot + $slot / 2 }}" y="{{ $H + 13 }}" text-anchor="middle" font-size="9" fill="#64748b">{{ $s['label'] }}</text>
                        @endforeach
                    </svg>
                    <div class="flex gap-4 text-xs text-slate-600"><span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm" style="background:var(--chart-1)"></span>Paid</span><span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm" style="background:var(--chart-2)"></span>Open</span><a href="{{ route('reports.ar') }}" class="ml-auto text-brand-700">Full A/R →</a></div>
                </div>
            </div>

            {{-- Right rail --}}
            <div class="lg:col-span-2 space-y-3">
                @foreach ([['alerts', 'Alerts', '#alerts'], ['reminders', 'Notes & events', '#events'], ['contacts', 'Contacts', '#contacts'], ['portals', 'Network', '#network'], ['invoices', 'Billing', '#billing'], ['messages', 'Messaging', '#comms']] as [$icon, $label, $href])
                    <a href="{{ $href }}" class="card flex items-center gap-2 px-3 py-2 text-xs font-medium text-slate-700 hover:border-brand-300"><x-icon :name="$icon" class="h-5 w-5 text-slate-500" />{{ $label }}</a>
                @endforeach
                <div class="card p-3 text-xs" id="alerts">
                    <div class="font-semibold text-slate-800 mb-1">Upcoming service</div>
                    @forelse ($reminders as $r)<div class="py-1 text-slate-700">{{ $r->typeLabel() }} · due {{ $r->due_at->format('M j') }}</div>@empty<div class="text-slate-400">No reminders.</div>@endforelse
                </div>
                <div class="card p-3 text-xs" id="events">
                    <div class="font-semibold text-slate-800 mb-1">Recent jobs</div>
                    @forelse ($customer->jobs as $j)<a href="{{ route('jobs.show', $j) }}" class="block py-1 text-brand-700 hover:underline">{{ $j->number ?? '#'.$j->id }} · {{ \Illuminate\Support\Str::limit($j->title, 28) }}</a>@empty<div class="text-slate-400">None.</div>@endforelse
                </div>
                @if ($canEdit)
                    <form method="POST" action="{{ route('contacts.update', $customer) }}" class="card p-3 space-y-2 text-xs" id="network">
                        @csrf
                        <div class="font-semibold text-slate-800">Corp. info</div>
                        <select name="segment" class="w-full rounded-lg border-slate-300 text-xs"><option value="">Segment…</option>@foreach ($segments as $s)<option value="{{ $s }}" @selected($customer->segment === $s)>{{ str_replace('_', ' ', $s) }}</option>@endforeach</select>
                        <select name="owner_user_id" class="w-full rounded-lg border-slate-300 text-xs"><option value="">Contact owner…</option>@foreach ($owners as $o)<option value="{{ $o->id }}" @selected($customer->owner_user_id === $o->id)>{{ $o->name }}</option>@endforeach</select>
                        <select name="corp_poc_contact_id" class="w-full rounded-lg border-slate-300 text-xs"><option value="">Corp. POC…</option>@foreach ($customer->contacts as $ct)<option value="{{ $ct->id }}" @selected($customer->corp_poc_contact_id === $ct->id)>{{ $ct->name }}</option>@endforeach</select>
                        <select name="payment_terms" class="w-full rounded-lg border-slate-300 text-xs">@foreach (config('batsons.payment_terms') as $k => $d)<option value="{{ $k }}" @selected(($customer->payment_terms ?? 'net_30') === $k)>{{ \App\Platform\Reporting\Reports::termsLabel($k) }}</option>@endforeach</select>
                        <x-text-input name="phone" value="{{ $customer->phone }}" placeholder="Main phone" class="w-full text-xs" />
                        <x-text-input name="email" value="{{ $customer->email }}" placeholder="Main email" class="w-full text-xs" />
                        <x-secondary-button type="submit" class="!py-1 !px-2 !text-xs">Save</x-secondary-button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
