<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">Inbox <span class="sow">SOW §3.12</span></h2>
            @unless ($me->isField())
                <a href="{{ route('inbox.email') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Inbound email @if ($unroutedEmails)<span class="badge-amber ml-1">{{ $unroutedEmails }} to route</span>@endif →</a>
            @endunless
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
            @if ($errors->any())<div class="lg:col-span-3 flash-err">{{ $errors->first() }}</div>@endif

            {{-- Threads --}}
            <div class="card overflow-hidden">
                <div class="card-head"><span class="card-title">Conversations</span></div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($threads as $t)
                        <li>
                            <a href="{{ route('inbox.thread', $t['user']) }}" class="block px-6 py-3 hover:bg-slate-50 {{ $with && $with->is($t['user']) ? 'bg-brand-50/60' : '' }}">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-semibold text-slate-900">{{ $t['user']->name }} @if ($t['unread'])<span class="badge-blue ml-1">{{ $t['unread'] }}</span>@endif</span>
                                    <span class="text-xs text-slate-400">{{ $t['last']->created_at?->diffForHumans(null, true) }}</span>
                                </div>
                                <div class="text-xs text-slate-500 truncate">{{ $t['last']->sender_id === $me->id ? 'You: ' : '' }}{{ $t['last']->body }}</div>
                            </a>
                        </li>
                    @empty
                        <li class="px-6 py-8 text-center text-slate-500 text-sm">No conversations yet.</li>
                    @endforelse
                </ul>
                <div class="px-6 py-3 border-t border-slate-100 text-xs text-slate-500">
                    Start one:
                    @foreach ($people as $p)<a href="{{ route('inbox.thread', $p) }}" class="ml-1 font-medium text-brand-700 hover:underline">{{ $p->name }}</a>@endforeach
                </div>
            </div>

            {{-- Thread --}}
            <div class="lg:col-span-2 card flex flex-col min-h-[28rem]">
                @if ($with)
                    <div class="card-head"><span class="card-title">{{ $with->name }} <span class="card-note">· {{ $with->role->label() }}</span></span></div>
                    <div class="grow px-6 py-4 space-y-3 overflow-y-auto max-h-[32rem]">
                        @forelse ($thread as $m)
                            <div class="flex {{ $m->sender_id === $me->id ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-[75%] rounded-2xl px-4 py-2 text-sm {{ $m->sender_id === $me->id ? 'bg-brand-900 text-white rounded-br-sm' : 'bg-slate-100 text-slate-800 rounded-bl-sm' }}">
                                    <div class="whitespace-pre-line">{{ $m->body }}</div>
                                    <div class="mt-1 text-[10px] {{ $m->sender_id === $me->id ? 'text-brand-200' : 'text-slate-400' }}">{{ $m->created_at?->format('M j, g:ia') }}@if ($m->job) · {{ $m->job->number }}@endif @if ($m->sender_id === $me->id && $m->read_at) · read @endif</div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-sm text-slate-400 py-10">Say hello.</div>
                        @endforelse
                    </div>
                    <form method="POST" action="{{ route('inbox.send') }}" class="px-6 py-4 border-t border-slate-100 flex items-end gap-2">
                        @csrf
                        <input type="hidden" name="recipient_id" value="{{ $with->id }}">
                        <textarea name="body" rows="2" required class="grow rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Message {{ $with->name }}…"></textarea>
                        <x-primary-button>Send</x-primary-button>
                    </form>
                @else
                    <div class="grow flex flex-col items-center justify-center text-slate-400 text-sm px-6 py-10 text-center">
                        <div class="font-medium text-slate-600">Pick a conversation or start one.</div>
                        <div class="mt-1">In-app messages replace internal email. The other person gets a notification.</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
