<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">Recurring Service &amp; Reminders <span class="sow">SOW §3.10</span></h2>
            @if (auth()->user()->isAdministrator())<a href="{{ route('admin.reminder-types') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Configure reminder types →</a>@endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            @if (session('status'))<div class="flash-ok">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="flash-err">{{ $errors->first() }}</div>@endif

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach ($stats as $label => $value)
                    <div class="stat"><div class="stat-value {{ str_starts_with($label, 'Late') && $value ? 'text-red-600' : (str_starts_with($label, 'Ready') && $value ? 'text-amber-600' : '') }}">{{ $value }}</div><div class="stat-label">{{ $label }}</div></div>
                @endforeach
            </div>

            {{-- To work --}}
            <div class="card overflow-hidden">
                <div class="card-head"><span class="card-title">Ready to work <span class="card-note">· contact window open — call or message, then schedule</span></span></div>
                <table class="table">
                    <thead><tr><th>Customer</th><th>Service</th><th>Due</th><th>Contact by</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($toWork as $r)
                            <tr class="{{ $r->isLate() ? 'bg-red-50/40' : '' }}">
                                <td><div class="font-medium text-slate-900">{{ $r->customer?->name }}</div><div class="text-xs text-slate-500">{{ $r->serviceLocation?->label ?? $r->serviceLocation?->address ?? '' }}</div></td>
                                <td class="text-slate-700">{{ $r->typeLabel() }}</td>
                                <td class="text-slate-700">{{ $r->due_at->format('M j, Y') }}</td>
                                <td class="{{ $r->isLate() ? 'text-red-700 font-semibold' : 'text-slate-700' }}">{{ $r->contact_by?->format('M j') }}@if ($r->isLate()) <span class="badge-red ml-1">late</span>@endif</td>
                                <td>
                                    @if ($r->status === 'contacted')<span class="badge-blue">contacted · {{ $r->contact_method }}</span><div class="text-[11px] text-slate-400">{{ $r->contacted_at?->format('M j') }}</div>
                                    @else<span class="badge-amber">to contact</span>@endif
                                </td>
                                <td class="text-right">
                                    <div class="flex flex-wrap justify-end gap-1">
                                        <form method="POST" action="{{ route('reminders.contact', $r) }}">@csrf<input type="hidden" name="method" value="sms"><button class="rounded-md bg-brand-50 px-2 py-1 text-xs font-medium text-brand-800 hover:bg-brand-100">Text</button></form>
                                        <form method="POST" action="{{ route('reminders.contact', $r) }}">@csrf<input type="hidden" name="method" value="email"><button class="rounded-md bg-brand-50 px-2 py-1 text-xs font-medium text-brand-800 hover:bg-brand-100">Email</button></form>
                                        <form method="POST" action="{{ route('reminders.contact', $r) }}">@csrf<input type="hidden" name="method" value="call"><button class="rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200">Called</button></form>
                                        <form method="POST" action="{{ route('reminders.schedule', $r) }}" class="flex items-center gap-1">@csrf
                                            <select name="window" class="rounded-md border-slate-300 text-xs py-1">@foreach ($windows as $w)<option value="{{ $w }}">{{ str_replace('_', ' ', $w) }}</option>@endforeach</select>
                                            <button class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-800 hover:bg-emerald-100">Schedule job</button>
                                        </form>
                                        <form method="POST" action="{{ route('reminders.skip', $r) }}" onsubmit="return confirm('Skip this cycle? The next one will still come around.')">@csrf<button class="px-2 py-1 text-xs text-slate-400 hover:text-slate-600">skip</button></form>
                                    </div>
                                    @if ($r->notes)<div class="mt-1 text-[11px] text-slate-400 whitespace-pre-line text-left">{{ $r->notes }}</div>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-8 text-center text-slate-500">Nothing in the window right now.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Scheduled --}}
                <div class="card overflow-hidden">
                    <div class="card-head"><span class="card-title">Scheduled <span class="card-note">· closes when the work order completes</span></span></div>
                    <table class="table">
                        <tbody>
                            @forelse ($scheduled as $r)
                                <tr>
                                    <td><div class="font-medium text-slate-900">{{ $r->customer?->name }}</div><div class="text-xs text-slate-500">{{ $r->typeLabel() }} · due {{ $r->due_at->format('M j') }}</div></td>
                                    <td class="text-right">@if ($r->job)<a href="{{ route('jobs.show', $r->job) }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">{{ $r->job->number ?? 'Job #'.$r->job->id }} →</a>@endif</td>
                                </tr>
                            @empty
                                <tr><td class="px-6 py-6 text-center text-slate-500">No reminder jobs in flight.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Upcoming --}}
                <div class="card overflow-hidden">
                    <div class="card-head"><span class="card-title">Upcoming <span class="card-note">· window not open yet</span></span></div>
                    <table class="table">
                        <tbody>
                            @forelse ($upcoming->take(12) as $r)
                                <tr>
                                    <td><div class="font-medium text-slate-900">{{ $r->customer?->name }}</div><div class="text-xs text-slate-500">{{ $r->typeLabel() }}</div></td>
                                    <td class="text-right text-sm text-slate-700">due {{ $r->due_at->format('M j, Y') }}<div class="text-[11px] text-slate-400">alert {{ $r->remind_at?->format('M j') }}@if ($r->auto_message) · auto-message @endif</div></td>
                                </tr>
                            @empty
                                <tr><td class="px-6 py-6 text-center text-slate-500">No upcoming cycles.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Recurring services --}}
            <div class="card overflow-hidden">
                <div class="card-head"><span class="card-title">Recurring services <span class="card-note">· created automatically when a backflow test / water-heater job completes, or added here</span></span></div>
                <table class="table">
                    <thead><tr><th>Customer / site</th><th>Service</th><th>Every</th><th>Last</th><th>Next due</th><th>Window</th><th>Auto-msg</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($services as $s)
                            <tr class="{{ $s->is_active ? '' : 'text-slate-400' }}">
                                <td><div class="font-medium {{ $s->is_active ? 'text-slate-900' : '' }}">{{ $s->customer?->name }}</div><div class="text-xs text-slate-500">{{ $s->serviceLocation?->label ?? $s->serviceLocation?->address ?? 'all sites' }}@if ($s->label) · {{ $s->label }}@endif</div></td>
                                <td>{{ $s->typeLabel() }}</td>
                                <td>{{ $s->interval_months }} mo</td>
                                <td>{{ $s->last_serviced_at?->format('M j, Y') ?? '—' }}</td>
                                <td class="font-medium">{{ $s->next_due_at->format('M j, Y') }}</td>
                                <td class="text-xs">{{ $s->lead_days_max }}–{{ $s->lead_days_min }} d before</td>
                                <td>@if ($s->auto_message)<span class="badge-green">on</span>@else<span class="badge-gray">off</span>@endif</td>
                                <td class="text-right"><form method="POST" action="{{ route('reminders.services.toggle', $s) }}">@csrf<button class="text-xs font-medium text-slate-500 hover:text-slate-800">{{ $s->is_active ? 'pause' : 'resume' }}</button></form></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-6 py-8 text-center text-slate-500">No recurring services yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <form method="POST" action="{{ route('reminders.services.store') }}" class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex flex-wrap items-end gap-3">
                    @csrf
                    <div><x-input-label for="rc" value="Customer" /><select id="rc" name="customer_id" required class="mt-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">@foreach ($customers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                    <div><x-input-label for="rl" value="Site" /><select id="rl" name="service_location_id" class="mt-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="">— all —</option>@foreach ($customers as $c)@foreach ($c->serviceLocations as $loc)<option value="{{ $loc->id }}">{{ $c->name }} · {{ $loc->label ?? $loc->address }}</option>@endforeach @endforeach</select></div>
                    <div><x-input-label for="rt" value="Service" /><select id="rt" name="type" class="mt-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">@foreach ($types as $k => $t)<option value="{{ $k }}">{{ $t['label'] }} (every {{ $t['interval_months'] }} mo)</option>@endforeach</select></div>
                    <div><x-input-label for="rls" value="Last serviced" /><x-text-input id="rls" name="last_serviced_at" type="date" class="mt-1" /></div>
                    <div><x-input-label for="rnd" value="…or next due" /><x-text-input id="rnd" name="next_due_at" type="date" class="mt-1" /></div>
                    <div><x-input-label for="rlb" value="Label" /><x-text-input id="rlb" name="label" class="mt-1 w-40" placeholder="RPZ #2 (mech room)" /></div>
                    <label class="inline-flex items-center gap-1.5 text-sm text-slate-700 pb-2"><input type="checkbox" name="auto_message" value="1" class="rounded border-slate-300 text-brand-700 focus:ring-brand-500"> auto-message customer</label>
                    <x-primary-button>Add to schedule</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
