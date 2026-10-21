<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">Notifications <span class="sow">SOW §3.12</span></h2>
            @if ($unread)
                <form method="POST" action="{{ route('notifications.readAll') }}">@csrf<x-secondary-button type="submit">Mark all read</x-secondary-button></form>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))<div class="flash-ok">{{ session('status') }}</div>@endif
            <div class="card overflow-hidden">
                <ul class="divide-y divide-slate-100">
                    @forelse ($notifications as $n)
                        @php $d = $n->data; $kind = $d['kind'] ?? 'info'; @endphp
                        <li class="{{ $n->read_at ? '' : 'bg-brand-50/50' }}">
                            <form method="POST" action="{{ route('notifications.read', $n->id) }}">
                                @csrf
                                <button class="w-full text-left px-6 py-3 flex gap-3 hover:bg-slate-50">
                                    <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full {{ $n->read_at ? 'bg-slate-200' : 'bg-accent-500' }}"></span>
                                    <span class="min-w-0 grow">
                                        <span class="flex items-center justify-between gap-2">
                                            <span class="text-sm font-semibold text-slate-900 truncate">{{ $d['title'] ?? '' }}</span>
                                            <span class="text-xs text-slate-400 whitespace-nowrap">{{ $n->created_at?->diffForHumans() }}</span>
                                        </span>
                                        @if (! empty($d['body']))<span class="block text-sm text-slate-600">{{ $d['body'] }}</span>@endif
                                        <span class="badge-gray mt-1">{{ str_replace('_', ' ', $kind) }}</span>
                                    </span>
                                </button>
                            </form>
                        </li>
                    @empty
                        <li class="px-6 py-12 text-center text-slate-500">You're all caught up.</li>
                    @endforelse
                </ul>
            </div>
            <p class="text-xs text-slate-400">Assignments, schedule changes, customer replies, inbound email, field issues, delivery shortages, direct messages, and the morning digest (certifications, reorders, service due, fleet) all land here instead of internal email.</p>
        </div>
    </div>
</x-app-layout>
