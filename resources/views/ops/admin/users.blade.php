<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">Administration · Users <span class="sow">SOW §3.13</span></h2>
            @include('ops.admin.partials.tabs')
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if (session('status'))
                <div class="flash-ok">{{ session('status') }}</div>
            @endif
            @if (session('temporary_password'))
                <div class="flash-warn">
                    <div class="font-semibold">One-time temporary password for {{ session('temporary_for') }}</div>
                    <code class="block mt-1 text-base tracking-wider">{{ session('temporary_password') }}</code>
                    <div class="mt-1 text-amber-800">Share it securely. It is shown once and not stored. Ask them to set a passkey from their Profile on first sign-in.</div>
                </div>
            @endif
            @error('user')
                <div class="flash-err">{{ $message }}</div>
            @enderror

            {{-- Seats --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="stat">
                        <div class="stat-value">{{ $seats['active'] }}<span class="text-base font-normal text-slate-400"> / {{ $seats['included'] }}</span></div>
                    <div class="stat-label">Active users / included seats</div>
                </div>
                <div class="stat">
                        <div class="stat-value">{{ $seats['available'] }}</div>
                    <div class="stat-label">Included seats remaining</div>
                </div>
                <div class="stat">
                    <div class="stat-value {{ $seats['blocks'] ? 'text-amber-600' : 'text-slate-900' }}">{{ $seats['blocks'] }}</div>
                    <div class="stat-label">Additional blocks of {{ $seats['block_size'] }} in use</div>
                </div>
                <div class="stat">
                        <div class="stat-value">{{ $users->where('is_active', false)->count() }}</div>
                    <div class="stat-label">Deactivated (kept for history)</div>
                </div>
            </div>

            {{-- Create --}}
            <div class="card p-6">
                <div class="card-title mb-3">Add a user</div>
                <form method="POST" action="{{ route('admin.users.store') }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" name="name" value="{{ old('name') }}" class="mt-1 text-sm" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="email" value="Email" />
                        <x-text-input id="email" name="email" type="email" value="{{ old('email') }}" class="mt-1 text-sm" required />
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="role" value="Role" />
                        <select id="role" name="role" class="mt-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($roles as $role)
                                <option value="{{ $role->value }}" @selected(old('role', 'field') === $role->value)>{{ $role->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('role')" class="mt-1" />
                    </div>
                    <x-primary-button>Create user</x-primary-button>
                    <div class="text-xs text-slate-400 basis-full">
                        Field users see only their own assigned work. Office users see everything in the company. Administrators also manage users, terminology and security.
                    </div>
                </form>
            </div>

            {{-- Users --}}
            <div class="card overflow-hidden">
                <div class="card-head card-title">Users</div>
                <table class="table">
                    <thead>
                        <tr>
                            <th class="px-6 py-3">Name</th>
                            <th class="px-6 py-3">Email</th>
                            <th class="px-6 py-3">Role</th>
                            <th class="px-6 py-3">Passkeys</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr class="{{ $user->is_active ? '' : 'text-slate-400' }}">
                                <td class="px-6 py-3 font-medium {{ $user->is_active ? 'text-slate-900' : '' }}">
                                    {{ $user->name }} @if ($user->is(auth()->user()))<span class="text-xs text-slate-400">(you)</span>@endif
                                </td>
                                <td class="px-6 py-3">{{ $user->email }}</td>
                                <td class="px-6 py-3">
                                    <form method="POST" action="{{ route('admin.users.role', $user) }}" class="inline">
                                        @csrf
                                        <select name="role" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-xs py-1 shadow-sm focus:border-brand-500 focus:ring-brand-500" @disabled(! $user->is_active)>
                                            @foreach ($roles as $role)
                                                <option value="{{ $role->value }}" @selected($user->role === $role)>{{ $role->label() }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                                <td class="px-6 py-3">
                                    @if ($user->passkeys_count)
                                        <span class="text-green-700">{{ $user->passkeys_count }} registered</span>
                                    @else
                                        <span class="text-slate-400">none</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3">
                                    @if ($user->is_active)
                                        <span class="badge-green">active</span>
                                    @else
                                        <span class="badge-gray">deactivated</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-right">
                                    @if ($user->is_active)
                                        @unless ($user->is(auth()->user()))
                                            <form method="POST" action="{{ route('admin.users.deactivate', $user) }}" class="inline"
                                                  onsubmit="return confirm('Deactivate {{ $user->name }}? They will be signed out and cannot sign in again until reactivated.')">
                                                @csrf
                                                <x-danger-button class="!py-1 !px-2 !text-xs">Deactivate</x-danger-button>
                                            </form>
                                        @endunless
                                    @else
                                        <form method="POST" action="{{ route('admin.users.reactivate', $user) }}" class="inline">
                                            @csrf
                                            <x-secondary-button class="!py-1 !px-2 !text-xs">Reactivate</x-secondary-button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Recent admin activity --}}
            <div class="card overflow-hidden">
                <div class="card-head card-title">Recent user administration <span class="card-note">· audit trail, MSA Ex. B.5(d)</span></div>
                <div class="divide-y divide-slate-100 text-sm">
                    @forelse ($recent as $log)
                        <div class="px-6 py-2 flex justify-between">
                            <span class="text-slate-700">{{ $log->action }} · user #{{ $log->auditable_id }}</span>
                            <span class="text-slate-400">{{ $log->created_at?->format('M j, g:ia') }}</span>
                        </div>
                    @empty
                        <div class="px-6 py-4 text-slate-500">No administrative changes yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
