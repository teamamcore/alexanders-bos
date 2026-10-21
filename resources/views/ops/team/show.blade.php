<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <a href="{{ route('team.index') }}" class="text-xs font-medium text-slate-500 hover:text-slate-700">← Team</a>
                <h2 class="page-title">{{ $employee->name }} <span class="sow">SOW §3.11</span></h2>
                <div class="text-sm text-slate-500">{{ $employee->title ?? 'No title' }}@if ($employee->hired_at) · since {{ $employee->hired_at->format('M j, Y') }}@endif @if ($employee->user) · {{ $employee->user->email }} @endif</div>
            </div>
            @php $state = $employee->certificationState(); @endphp
            <span class="{{ $state === 'expired' ? 'badge-red' : ($state === 'expiring' ? 'badge-amber' : 'badge-green') }}">
                {{ $state === 'expired' ? 'Licence / cert expired' : ($state === 'expiring' ? 'Cert expiring soon' : 'Certifications current') }}
            </span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-3 gap-6">

            @if (session('status'))
                <div class="lg:col-span-3 flash-ok">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="lg:col-span-3 flash-err">{{ $errors->first() }}</div>
            @endif

            {{-- Certifications --}}
            <div class="lg:col-span-2 space-y-6">
                <div class="card overflow-hidden">
                    <div class="card-head"><span class="card-title">Certifications &amp; licences</span><span class="card-note">alerts {{ config('batsons.certification_alert_days') }} days before expiry</span></div>
                    <table class="table">
                        <thead><tr><th>Name</th><th>Type</th><th>Number</th><th>Issued</th><th>Expires</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($employee->certifications as $c)
                                <tr>
                                    <td class="font-medium text-slate-900">{{ $c->name }}</td>
                                    <td class="text-slate-600">{{ $c->type }}</td>
                                    <td class="text-slate-600 font-mono text-xs">{{ $c->number ?? '—' }}</td>
                                    <td class="text-slate-600">{{ $c->issued_at?->toFormattedDateString() ?? '—' }}</td>
                                    <td class="text-slate-600">{{ $c->expires_at?->toFormattedDateString() ?? 'no expiry' }}</td>
                                    <td>
                                        @switch($c->status())
                                            @case('expired') <span class="badge-red">expired</span> @break
                                            @case('expiring') <span class="badge-amber">{{ $c->daysUntilExpiry() }} days</span> @break
                                            @case('valid') <span class="badge-green">current</span> @break
                                            @default <span class="badge-gray">—</span>
                                        @endswitch
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-6 py-6 text-center text-slate-500">No certifications on file.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    @if ($canEdit)
                        <form method="POST" action="{{ route('team.certifications.store', $employee) }}" class="px-6 py-4 border-t border-slate-100 flex flex-wrap items-end gap-3 bg-slate-50/50">
                            @csrf
                            <div><x-input-label for="cname" value="Name" /><x-text-input id="cname" name="name" class="mt-1" placeholder="Backflow tester" required /></div>
                            <div><x-input-label for="ctype" value="Type" />
                                <select id="ctype" name="type" class="mt-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    <option value="certification">Certification</option><option value="license">Licence</option>
                                </select></div>
                            <div><x-input-label for="cnum" value="Number" /><x-text-input id="cnum" name="number" class="mt-1 w-32" /></div>
                            <div><x-input-label for="cissued" value="Issued" /><x-text-input id="cissued" name="issued_at" type="date" class="mt-1" /></div>
                            <div><x-input-label for="cexp" value="Expires" /><x-text-input id="cexp" name="expires_at" type="date" class="mt-1" /></div>
                            <x-primary-button>Add</x-primary-button>
                        </form>
                    @endif
                </div>

                {{-- Milestones --}}
                <div class="card overflow-hidden">
                    <div class="card-head"><span class="card-title">Milestones</span></div>
                    <ol class="divide-y divide-slate-100 text-sm">
                        @forelse ($employee->milestones as $m)
                            <li class="px-6 py-3 flex items-start gap-4">
                                <span class="w-28 shrink-0 text-slate-500">{{ $m->occurred_at->toFormattedDateString() }}</span>
                                <span><span class="font-medium text-slate-900">{{ $m->title }}</span>@if ($m->notes) <span class="text-slate-500"> — {{ $m->notes }}</span>@endif</span>
                            </li>
                        @empty
                            <li class="px-6 py-6 text-center text-slate-500">No milestones yet.</li>
                        @endforelse
                    </ol>
                    @if ($canEdit)
                        <form method="POST" action="{{ route('team.milestones.store', $employee) }}" class="px-6 py-4 border-t border-slate-100 flex flex-wrap items-end gap-3 bg-slate-50/50">
                            @csrf
                            <div><x-input-label for="mtitle" value="Milestone" /><x-text-input id="mtitle" name="title" class="mt-1" placeholder="Promoted to lead" required /></div>
                            <div><x-input-label for="mdate" value="Date" /><x-text-input id="mdate" name="occurred_at" type="date" class="mt-1" required /></div>
                            <div class="grow"><x-input-label for="mnotes" value="Notes" /><x-text-input id="mnotes" name="notes" class="mt-1 w-full" /></div>
                            <x-primary-button>Add</x-primary-button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Right column --}}
            <div class="space-y-6">
                <div class="card p-6">
                    <div class="flex items-center justify-between mb-3">
                        <span class="card-title">Qualification values</span>
                        @if ($isAdmin) <a href="{{ route('admin.qualifications') }}" class="text-xs font-medium text-brand-700 hover:text-brand-900">Assign →</a> @endif
                    </div>
                    <dl class="space-y-2 text-sm">
                        @foreach ($workTypes as $key => $label)
                            @php $lvl = $employee->levelFor($key); @endphp
                            <div class="flex items-center justify-between">
                                <dt class="text-slate-600">{{ $label }}</dt>
                                <dd class="flex items-center gap-2">
                                    <span class="flex gap-0.5">@for ($i = 1; $i < count($levels); $i++)<span class="h-2 w-4 rounded-sm {{ $i <= $lvl ? 'bg-brand-700' : 'bg-slate-200' }}"></span>@endfor</span>
                                    <span class="w-20 text-right font-medium {{ $lvl ? 'text-slate-900' : 'text-slate-400' }}">{{ $levels[$lvl] ?? $lvl }}</span>
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                    <p class="mt-3 text-xs text-slate-400">Feeds the dispatch board indicators (SOW §3.3).</p>
                </div>

                <div class="card p-6">
                    <div class="card-title mb-3">Background check</div>
                    @php $bg = $employee->background_check_status; @endphp
                    <div class="flex items-center gap-3">
                        <span class="{{ $bg === 'cleared' ? 'badge-green' : ($bg === 'flagged' || $bg === 'expired' ? 'badge-red' : 'badge-gray') }}">{{ $bg ? str_replace('_', ' ', $bg) : 'not started' }}</span>
                        <span class="text-sm text-slate-600">{{ $employee->background_check_date?->toFormattedDateString() ?? 'no date' }}</span>
                    </div>
                    @if ($isAdmin)
                        <form method="POST" action="{{ route('team.background', $employee) }}" class="mt-4 flex flex-wrap items-end gap-2">
                            @csrf
                            <div><x-input-label for="bgs" value="Status" />
                                <select id="bgs" name="status" class="mt-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    @foreach ($bgStatuses as $s)<option value="{{ $s }}" @selected($bg === $s)>{{ str_replace('_', ' ', $s) }}</option>@endforeach
                                </select></div>
                            <div><x-input-label for="bgd" value="Date" /><x-text-input id="bgd" name="date" type="date" value="{{ $employee->background_check_date?->toDateString() }}" class="mt-1" /></div>
                            <x-secondary-button type="submit">Save</x-secondary-button>
                        </form>
                    @endif
                    <p class="mt-3 text-xs text-slate-400">Status and date only. Supporting documents are held offline by administrators and are never uploaded here (MSA Ex. B.2, B.5(g)).</p>
                </div>

                <div class="card p-6 text-sm">
                    <div class="card-title mb-2">Contact</div>
                    <dl class="space-y-1 text-slate-600">
                        <div class="flex justify-between"><dt>Phone</dt><dd class="text-slate-900">{{ $employee->phone ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt>Sign-in</dt><dd class="text-slate-900">{{ $employee->user?->email ?? 'not linked' }}</dd></div>
                        <div class="flex justify-between"><dt>Role</dt><dd class="text-slate-900">{{ $employee->user?->role->label() ?? '—' }}</dd></div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
