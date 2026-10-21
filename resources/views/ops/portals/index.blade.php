<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="page-title">Vendor Portals <span class="sow">SOW §3.14</span></h2>
                <div class="text-sm text-slate-500">Customer issues work in its portal → imported as a job + work order → status and invoice sent back → payment recorded.</div>
            </div>
            <div class="text-right"><div class="text-2xl font-bold text-slate-900">{{ $usage['used'] }}<span class="text-base font-normal text-slate-400"> / {{ $usage['included'] }}</span></div><div class="text-xs text-slate-500">portals counted toward the included five @if ($usage['blocked'])· {{ $usage['blocked'] }} blocked (don't count)@endif</div></div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            @if (session('status'))<div class="flash-ok">{{ session('status') }}</div>@endif
            @if (session('error'))<div class="flash-warn">{{ session('error') }}</div>@endif
            @if ($errors->any())<div class="flash-err">{{ $errors->first() }}</div>@endif

            {{-- Integrations --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                @foreach ($integrations as $i)
                    @php $ad = $adapters[$i->adapter] ?? ['label' => $i->adapter, 'connection_fields' => [], 'description' => '']; @endphp
                    <div class="card p-5 space-y-3 {{ $i->status === 'blocked' ? 'opacity-80' : '' }}">
                        <div class="flex items-start justify-between gap-2">
                            <div><div class="font-semibold text-slate-900">{{ $i->name }}</div><div class="text-xs text-slate-500">{{ $ad['label'] }}</div></div>
                            <span class="{{ ['live' => 'badge-green', 'building' => 'badge-blue', 'planned' => 'badge-gray', 'blocked' => 'badge-red'][$i->status] ?? 'badge-gray' }}">{{ $i->status }}</span>
                        </div>
                        <div class="text-xs text-slate-600">{{ $ad['description'] }}</div>
                        <dl class="text-xs text-slate-600 space-y-0.5">
                            <div class="flex justify-between"><dt>Credentials</dt><dd class="{{ $i->isConfigured() ? 'text-emerald-700' : 'text-amber-700' }}">{{ $ad['connection_fields'] === [] ? 'none needed' : ($i->isConfigured() ? 'on file (encrypted)' : 'missing') }}</dd></div>
                            <div class="flex justify-between"><dt>Go-live</dt><dd>{{ $i->go_live_at?->toFormattedDateString() ?? '—' }}</dd></div>
                            <div class="flex justify-between"><dt>Last poll</dt><dd>{{ $i->last_polled_at?->diffForHumans() ?? 'never' }}</dd></div>
                            <div class="flex justify-between"><dt>Open requests</dt><dd>{{ $i->open_count }}</dd></div>
                            @if ($i->default_customer_id)<div class="flex justify-between"><dt>Bills as</dt><dd>{{ $i->defaultCustomer?->name }}</dd></div>@endif
                        </dl>
                        @if ($i->status === 'blocked')<div class="rounded-md bg-red-50 px-3 py-2 text-xs text-red-800"><span class="font-semibold">Blocked:</span> {{ $i->blocked_reason }} — does not count toward the five; Client notified in writing.</div>@endif
                        @if ($i->last_error)<div class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">{{ $i->last_error }}</div>@endif
                        <div class="flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('portals.poll', $i) }}">@csrf<x-secondary-button type="submit" class="!py-1 !px-2 !text-xs">Poll now</x-secondary-button></form>
                            @if ($isAdmin)<button type="button" onclick="document.getElementById('cfg-{{ $i->id }}').classList.toggle('hidden')" class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Configure</button>@endif
                            @if (in_array($i->adapter, ['manual', 'sandbox'], true))<button type="button" onclick="document.getElementById('imp-{{ $i->id }}').classList.toggle('hidden')" class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ $i->adapter === 'sandbox' ? 'Inject sample request' : 'Key in a request' }}</button>@endif
                        </div>
                        <div class="text-[11px] text-slate-400">Webhook: <code>POST /webhooks/portals/{{ $i->id }}</code> with <code>X-Portal-Token</code></div>

                        @if ($isAdmin)
                            <form id="cfg-{{ $i->id }}" method="POST" action="{{ route('portals.update', $i) }}" class="hidden space-y-2 border-t border-slate-100 pt-3">
                                @csrf
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="col-span-2"><x-input-label value="Name" /><x-text-input name="name" value="{{ $i->name }}" class="mt-1 w-full" /></div>
                                    <div><x-input-label value="Status" /><select name="status" class="mt-1 w-full rounded-lg border-slate-300 text-sm">@foreach ($statuses as $s)<option value="{{ $s }}" @selected($i->status === $s)>{{ $s }}</option>@endforeach</select></div>
                                    <div><x-input-label value="Go-live" /><x-text-input name="go_live_at" type="date" value="{{ $i->go_live_at?->toDateString() }}" class="mt-1 w-full" /></div>
                                    <div class="col-span-2"><x-input-label value="Blocked reason (if blocked)" /><x-text-input name="blocked_reason" value="{{ $i->blocked_reason }}" class="mt-1 w-full" placeholder="Provider does not allow vendor API access" /></div>
                                    <div class="col-span-2"><x-input-label value="Bill portal work to customer" /><select name="default_customer_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm"><option value="">— use the portal's customer name —</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected($i->default_customer_id === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
                                    @foreach ($ad['connection_fields'] as $f)
                                        <div class="{{ in_array($f, ['base_url', 'portal_url'], true) ? 'col-span-2' : '' }}"><x-input-label :value="str_replace('_', ' ', $f)" /><x-text-input name="{{ $f }}" type="{{ str_contains($f, 'secret') || str_contains($f, 'password') || str_contains($f, 'key') ? 'password' : 'text' }}" placeholder="{{ ! empty($i->credentials[$f]) ? '•••••• (on file)' : '' }}" class="mt-1 w-full" autocomplete="off" /></div>
                                    @endforeach
                                    <div class="col-span-2"><x-input-label value="Notes" /><textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border-slate-300 text-sm">{{ $i->notes }}</textarea></div>
                                </div>
                                <x-primary-button class="!py-1 !px-3 !text-xs">Save</x-primary-button>
                            </form>
                        @endif

                        @if (in_array($i->adapter, ['manual', 'sandbox'], true))
                            <form id="imp-{{ $i->id }}" method="POST" action="{{ route('portals.import', $i) }}" class="hidden space-y-2 border-t border-slate-100 pt-3">
                                @csrf
                                <div class="grid grid-cols-2 gap-2">
                                    <div><x-input-label value="Portal id" /><x-text-input name="external_id" required class="mt-1 w-full" value="{{ strtoupper(substr($i->key ?? 'pt', 0, 2)) }}-{{ random_int(10000, 99999) }}" /></div>
                                    <div><x-input-label value="Number" /><x-text-input name="external_number" class="mt-1 w-full" /></div>
                                    <div><x-input-label value="Customer" /><x-text-input name="customer_name" class="mt-1 w-full" placeholder="National Retail Co." /></div>
                                    <div><x-input-label value="Location" /><x-text-input name="location_name" class="mt-1 w-full" placeholder="Store #0417" /></div>
                                    <div class="col-span-2"><x-input-label value="Address" /><x-text-input name="address" class="mt-1 w-full" /></div>
                                    <div><x-input-label value="Trade" /><x-text-input name="trade" class="mt-1 w-full" placeholder="PLUMBING / BACKFLOW / WATER HEATER" /></div>
                                    <div><x-input-label value="Priority" /><x-text-input name="priority" class="mt-1 w-full" placeholder="P2 — 48 hr" /></div>
                                    <div><x-input-label value="NTE $" /><x-text-input name="nte" type="number" step="0.01" class="mt-1 w-full" /></div>
                                    <div><x-input-label value="Scheduled by" /><x-text-input name="scheduled_by" type="date" class="mt-1 w-full" /></div>
                                    <div class="col-span-2"><x-input-label value="Description" /><textarea name="description" rows="2" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></textarea></div>
                                </div>
                                <x-primary-button class="!py-1 !px-3 !text-xs">Import as job + work order</x-primary-button>
                            </form>
                        @endif
                    </div>
                @endforeach

                @if ($isAdmin)
                    <form method="POST" action="{{ route('portals.store') }}" class="card p-5 border-dashed space-y-2">
                        @csrf
                        <div class="font-semibold text-slate-900">Add a portal</div>
                        <p class="text-xs text-slate-500">Portals are selected by the Client and confirmed in writing before the build begins.</p>
                        <x-text-input name="name" placeholder="Portal name" class="w-full" required />
                        <select name="adapter" class="w-full rounded-lg border-slate-300 text-sm">@foreach ($adapters as $k => $a)<option value="{{ $k }}">{{ $a['label'] }}</option>@endforeach</select>
                        <x-primary-button class="!py-1 !px-3 !text-xs">Add</x-primary-button>
                    </form>
                @endif
            </div>

            {{-- Requests --}}
            <div class="card overflow-hidden">
                <div class="card-head"><span class="card-title">Portal work requests</span><span class="card-note">imported → scheduled → in progress → completed → invoiced → paid</span></div>
                <table class="table">
                    <thead><tr><th>Portal #</th><th>Customer / location</th><th>Trade · priority</th><th>NTE</th><th>Our job</th><th>Status</th><th>Invoice</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($requests as $r)
                            <tr>
                                <td><div class="font-medium text-slate-900">{{ $r->external_number ? '#'.$r->external_number : $r->external_id }}</div><div class="text-xs text-slate-400">{{ $r->integration?->name }} · {{ $r->imported_at?->format('M j') }}</div></td>
                                <td><div class="text-slate-900">{{ $r->customer?->name ?? $r->customer_name }}</div><div class="text-xs text-slate-500">{{ $r->location_name }}@if ($r->address) · {{ $r->address }}@endif</div></td>
                                <td class="text-xs text-slate-600">{{ $r->trade ?? '—' }}@if ($r->priority) · {{ $r->priority }}@endif @if ($r->scheduled_by)<div class="text-slate-400">by {{ $r->scheduled_by->format('M j') }}</div>@endif</td>
                                <td class="text-slate-700">{{ $r->nte !== null ? '$'.number_format((float) $r->nte, 0) : '—' }}@if ($r->isPastNte())<span class="badge-red ml-1">over</span>@endif</td>
                                <td>@if ($r->job)<a href="{{ route('jobs.show', $r->job) }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">{{ $r->workOrder?->number ?? $r->job->number ?? 'Job #'.$r->job->id }}</a>@endif</td>
                                <td><span class="{{ ['paid' => 'badge-green', 'invoiced' => 'badge-blue', 'completed' => 'badge-blue', 'in_progress' => 'badge-amber', 'scheduled' => 'badge-amber', 'imported' => 'badge-gray', 'rejected' => 'badge-red'][$r->status] ?? 'badge-gray' }}">{{ str_replace('_', ' ', $r->status) }}</span>@if ($r->external_status)<div class="text-[10px] text-slate-400">portal: {{ $r->external_status }}</div>@endif</td>
                                <td class="text-slate-700">@if ($r->invoice){{ $r->invoice->number }} · ${{ number_format((float) $r->invoice->total, 0) }} <span class="text-xs text-slate-400">{{ $r->invoice->status }}</span>@else —@endif</td>
                                <td class="text-right">@if ($r->status === 'invoiced')<form method="POST" action="{{ route('portals.paid', $r) }}">@csrf<button class="text-xs font-medium text-brand-700">mark paid (portal)</button></form>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-6 py-8 text-center text-slate-500">No portal requests yet — poll the sandbox or key one in.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Sync log --}}
            <div class="card overflow-hidden">
                <div class="card-head"><span class="card-title">Sync log</span><span class="card-note">every message in and out of each portal</span></div>
                <ul class="divide-y divide-slate-100 text-sm max-h-96 overflow-y-auto">
                    @forelse ($events as $e)
                        <li class="px-6 py-2 flex items-start gap-3">
                            <span class="{{ $e->status === 'ok' ? 'badge-green' : ($e->status === 'queued' ? 'badge-amber' : 'badge-red') }}">{{ $e->direction === 'inbound' ? '↓' : '↑' }} {{ $e->type }}</span>
                            <span class="min-w-0 grow text-slate-700"><span class="text-xs text-slate-400">{{ $e->integration?->name }}@if ($e->portalWorkOrder) · #{{ $e->portalWorkOrder->external_number ?? $e->portalWorkOrder->external_id }}@endif ·</span> {{ $e->message }}</span>
                            <span class="text-xs text-slate-400 whitespace-nowrap">{{ $e->created_at?->format('M j, g:ia') }}</span>
                        </li>
                    @empty
                        <li class="px-6 py-6 text-center text-slate-400">Nothing yet.</li>
                    @endforelse
                </ul>
            </div>
            <p class="text-xs text-slate-400">Each integration goes live when the provider grants access and it passes testing — possibly after Go-Live; provider delays don't move Turnover. Portal outages / API changes are Third-Party Services under the MSA; rework from a provider's API change is by change order.</p>
        </div>
    </div>
</x-app-layout>
