<x-app-layout>
    <x-slot name="header">
        <h2 class="page-title">Team <span class="sow">SOW §3.11</span></h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if (session('status'))
                <div class="flash-ok">{{ session('status') }}</div>
            @endif

            {{-- Stats --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach ($stats as $label => $value)
                    <div class="stat">
                        <div class="stat-value {{ $label === 'Expired' && $value ? 'text-red-600' : (str_starts_with($label, 'Expiring') && $value ? 'text-amber-600' : '') }}">{{ $value }}</div>
                        <div class="stat-label">{{ $label }}</div>
                    </div>
                @endforeach
            </div>

            {{-- Expiration alerts --}}
            @if ($expired->isNotEmpty() || $expiring->isNotEmpty())
                <div class="card overflow-hidden">
                    <div class="card-head">
                        <span class="card-title">Certification &amp; licence alerts <span class="card-note">· next {{ $alertDays }} days</span></span>
                    </div>
                    <table class="table">
                        <tbody>
                            @foreach ($expired as $c)
                                <tr>
                                    <td class="font-medium text-slate-900"><a href="{{ route('team.show', $c->employee) }}" class="hover:underline">{{ $c->employee?->name }}</a></td>
                                    <td class="text-slate-700">{{ $c->name }} <span class="text-slate-400 text-xs">({{ $c->type }})</span></td>
                                    <td><span class="badge-red">expired {{ $c->expires_at?->diffForHumans() }}</span></td>
                                </tr>
                            @endforeach
                            @foreach ($expiring as $c)
                                <tr>
                                    <td class="font-medium text-slate-900"><a href="{{ route('team.show', $c->employee) }}" class="hover:underline">{{ $c->employee?->name }}</a></td>
                                    <td class="text-slate-700">{{ $c->name }} <span class="text-slate-400 text-xs">({{ $c->type }})</span></td>
                                    <td><span class="badge-amber">expires {{ $c->expires_at?->toFormattedDateString() }} · {{ $c->daysUntilExpiry() }}d</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Add --}}
            @if ($canEdit)
                <div class="card p-6">
                    <div class="card-title mb-3">Add a team member</div>
                    <form method="POST" action="{{ route('team.store') }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        <div>
                            <x-input-label for="name" value="Name" />
                            <x-text-input id="name" name="name" value="{{ old('name') }}" class="mt-1" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="title" value="Title" />
                            <x-text-input id="title" name="title" value="{{ old('title') }}" class="mt-1" placeholder="Journeyman plumber" />
                        </div>
                        <div>
                            <x-input-label for="hired_at" value="Hired" />
                            <x-text-input id="hired_at" name="hired_at" type="date" value="{{ old('hired_at') }}" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="user_id" value="Sign-in account" />
                            <select id="user_id" name="user_id" class="mt-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="">— none yet —</option>
                                @foreach ($unlinkedUsers as $u)
                                    <option value="{{ $u->id }}" @selected(old('user_id') == $u->id)>{{ $u->name }} ({{ $u->role->label() }})</option>
                                @endforeach
                            </select>
                        </div>
                        <x-primary-button>Create profile</x-primary-button>
                    </form>
                </div>
            @endif

            {{-- Roster --}}
            <div class="card overflow-hidden">
                <div class="card-head"><span class="card-title">Roster</span><span class="card-note">qualification values are set by administrators under Admin → Qualifications</span></div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Hired</th>
                            <th>Certifications</th>
                            <th>Background check</th>
                            <th>Qualifications</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $e)
                            <tr>
                                <td>
                                    <div class="font-medium text-slate-900">{{ $e->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $e->title ?? '—' }}@if ($e->user) · {{ $e->user->role->label() }} account @else · <span class="text-amber-600">no sign-in</span> @endif</div>
                                </td>
                                <td class="text-slate-700">{{ $e->hired_at?->format('M Y') ?? '—' }}</td>
                                <td>
                                    @php $s = $e->certificationSummary(); @endphp
                                    @if ($s['expired']) <span class="badge-red">{{ $s['expired'] }} expired</span> @endif
                                    @if ($s['expiring']) <span class="badge-amber">{{ $s['expiring'] }} expiring</span> @endif
                                    @if ($s['valid']) <span class="badge-green">{{ $s['valid'] }} current</span> @endif
                                    @if (! array_sum($s)) <span class="text-slate-400">none</span> @endif
                                </td>
                                <td>
                                    @php $bg = $e->background_check_status; @endphp
                                    <span class="{{ $bg === 'cleared' ? 'badge-green' : ($bg === 'flagged' || $bg === 'expired' ? 'badge-red' : 'badge-gray') }}">{{ $bg ? str_replace('_', ' ', $bg) : 'not started' }}</span>
                                    @if ($e->background_check_date) <div class="text-xs text-slate-400 mt-0.5">{{ $e->background_check_date->toFormattedDateString() }}</div> @endif
                                </td>
                                <td class="text-xs text-slate-600">
                                    @php $top = $e->qualifications->where('value', '>', 0)->sortByDesc('value')->take(3); @endphp
                                    @forelse ($top as $q)
                                        <span class="badge-blue mr-1 mb-1">{{ \App\Support\SchedulingIndicators::workTypeLabel($q->work_type) }} · {{ config('batsons.qualification_levels')[$q->value] ?? $q->value }}</span>
                                    @empty
                                        <span class="text-slate-400">none set</span>
                                    @endforelse
                                </td>
                                <td class="text-right"><a href="{{ route('team.show', $e) }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Profile →</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-8 text-center text-slate-500">No team members yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
