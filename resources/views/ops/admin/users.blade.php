<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Administration · Users <span class="text-sm text-gray-400">(SOW §3.13)</span></h2>
            @include('ops.admin.partials.tabs')
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if (session('status'))
                <div class="rounded-md bg-green-50 text-green-800 px-4 py-2 text-sm">{{ session('status') }}</div>
            @endif
            @if (session('temporary_password'))
                <div class="rounded-md bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">
                    <div class="font-semibold">One-time temporary password for {{ session('temporary_for') }}</div>
                    <code class="block mt-1 text-base tracking-wider">{{ session('temporary_password') }}</code>
                    <div class="mt-1 text-amber-800">Share it securely. It is shown once and not stored. Ask them to set a passkey from their Profile on first sign-in.</div>
                </div>
            @endif
            @error('user')
                <div class="rounded-md bg-red-50 text-red-800 px-4 py-2 text-sm">{{ $message }}</div>
            @enderror

            {{-- Seats --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-3xl font-bold text-gray-900">{{ $seats['active'] }}<span class="text-base font-normal text-gray-400"> / {{ $seats['included'] }}</span></div>
                    <div class="text-sm text-gray-500">Active users / included seats</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-3xl font-bold text-gray-900">{{ $seats['available'] }}</div>
                    <div class="text-sm text-gray-500">Included seats remaining</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-3xl font-bold {{ $seats['blocks'] ? 'text-amber-600' : 'text-gray-900' }}">{{ $seats['blocks'] }}</div>
                    <div class="text-sm text-gray-500">Additional blocks of {{ $seats['block_size'] }} in use</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-3xl font-bold text-gray-900">{{ $users->where('is_active', false)->count() }}</div>
                    <div class="text-sm text-gray-500">Deactivated (kept for history)</div>
                </div>
            </div>

            {{-- Create --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <div class="font-semibold text-gray-800 mb-3">Add a user</div>
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
                        <select id="role" name="role" class="mt-1 rounded-md border-gray-300 text-sm">
                            @foreach ($roles as $role)
                                <option value="{{ $role->value }}" @selected(old('role', 'field') === $role->value)>{{ $role->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('role')" class="mt-1" />
                    </div>
                    <x-primary-button>Create user</x-primary-button>
                    <div class="text-xs text-gray-400 basis-full">
                        Field users see only their own assigned work. Office users see everything in the company. Administrators also manage users, terminology and security.
                    </div>
                </form>
            </div>

            {{-- Users --}}
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 font-semibold text-gray-800">Users</div>
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-6 py-3">Name</th>
                            <th class="px-6 py-3">Email</th>
                            <th class="px-6 py-3">Role</th>
                            <th class="px-6 py-3">Passkeys</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($users as $user)
                            <tr class="{{ $user->is_active ? '' : 'text-gray-400' }}">
                                <td class="px-6 py-3 font-medium {{ $user->is_active ? 'text-gray-900' : '' }}">
                                    {{ $user->name }} @if ($user->is(auth()->user()))<span class="text-xs text-gray-400">(you)</span>@endif
                                </td>
                                <td class="px-6 py-3">{{ $user->email }}</td>
                                <td class="px-6 py-3">
                                    <form method="POST" action="{{ route('admin.users.role', $user) }}" class="inline">
                                        @csrf
                                        <select name="role" onchange="this.form.submit()" class="rounded-md border-gray-300 text-xs py-1" @disabled(! $user->is_active)>
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
                                        <span class="text-gray-400">none</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3">
                                    @if ($user->is_active)
                                        <span class="inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-700">active</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">deactivated</span>
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
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 font-semibold text-gray-800">Recent user administration <span class="text-xs font-normal text-gray-400">· audit trail, MSA Ex. B.5(d)</span></div>
                <div class="divide-y divide-gray-100 text-sm">
                    @forelse ($recent as $log)
                        <div class="px-6 py-2 flex justify-between">
                            <span class="text-gray-700">{{ $log->action }} · user #{{ $log->auditable_id }}</span>
                            <span class="text-gray-400">{{ $log->created_at?->format('M j, g:ia') }}</span>
                        </div>
                    @empty
                        <div class="px-6 py-4 text-gray-500">No administrative changes yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
