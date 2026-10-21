<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

@php
    $user = auth()->user();
    $unreadNotifications = $user->unreadNotifications()->count();
    $unreadMessages = \App\Models\UserMessage::where('recipient_id', $user->id)->whereNull('read_at')->count();
@endphp

{{-- Persistent top bar (design notes §1): back · logo · page name · date/time · bell · inbox · user · settings --}}
<header class="sticky top-0 z-40 bg-brand-950 text-white shadow-md">
    <div class="flex h-14 items-center gap-2 px-3 sm:px-4">
        <button type="button" onclick="history.length > 1 ? history.back() : (location.href = '{{ route('dashboard') }}')"
                class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-300 hover:bg-white/10 hover:text-white" title="Back">
            <x-icon name="back" set="standard" class="h-5 w-5" />
        </button>
        <button type="button" @click="sidebar = !sidebar" class="hidden md:inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-300 hover:bg-white/10 hover:text-white" title="Toggle sidebar">
            <x-icon name="collapse" set="standard" class="h-5 w-5" />
        </button>

        <a href="{{ route('dashboard') }}" wire:navigate class="ml-1 flex items-center gap-2">
            <x-application-logo class="h-8 w-8 text-white" />
            <span class="hidden sm:inline font-semibold tracking-tight text-[15px]">Batsons <span class="text-accent-300">BOS</span></span>
        </a>

        <div class="mx-3 hidden sm:block h-6 w-px bg-white/15"></div>
        <div class="min-w-0 truncate text-sm font-semibold text-white">{{ bos_page_title() }}</div>

        <div class="ml-auto flex items-center gap-1">
            {{-- §3.16 one search box for everything --}}
            <form method="GET" action="{{ route('search') }}" class="hidden md:flex items-center mr-1">
                <label class="relative block">
                    <span class="pointer-events-none absolute inset-y-0 left-2 flex items-center text-slate-400"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></span>
                    <input type="search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}" placeholder="Search anything…" aria-label="Search"
                           class="h-9 w-44 lg:w-64 rounded-lg border-0 bg-white/10 pl-8 pr-2 text-sm text-white placeholder:text-slate-400 focus:bg-white/15 focus:ring-2 focus:ring-accent-400">
                </label>
            </form>
            <a href="{{ route('search') }}" class="md:hidden inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-300 hover:bg-white/10 hover:text-white" title="Search"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></a>
            <a href="{{ route('help', ['from' => request()->route()?->getName()]) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-300 hover:bg-white/10 hover:text-white" title="Help">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.3-1 .9-1 1.7"/><circle cx="12" cy="17" r=".6" fill="currentColor"/></svg>
            </a>
            <div class="hidden lg:block mr-2 text-right leading-tight" x-data="{ now: new Date() }" x-init="setInterval(() => now = new Date(), 15000)">
                <div class="text-[13px] font-medium text-white" x-text="now.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })"></div>
                <div class="text-[11px] text-slate-400" x-text="now.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })"></div>
            </div>

            <a href="{{ route('notifications.index') }}" class="relative inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-300 hover:bg-white/10 hover:text-white" title="Notifications">
                <x-icon name="notifications" class="h-5 w-5" />
                @if ($unreadNotifications)<span class="notif-badge absolute -top-0.5 -right-0.5 min-w-[1.1rem] h-[1.1rem] px-1 rounded-full bg-accent-500 text-[10px] font-bold text-white flex items-center justify-center">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>@endif
            </a>
            <a href="{{ route('inbox.index') }}" class="relative inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-300 hover:bg-white/10 hover:text-white" title="Inbox">
                <x-icon name="inbox" class="h-5 w-5" />
                @if ($unreadMessages)<span class="absolute -top-0.5 -right-0.5 min-w-[1.1rem] h-[1.1rem] px-1 rounded-full bg-brand-400 text-[10px] font-bold text-white flex items-center justify-center">{{ $unreadMessages }}</span>@endif
            </a>

            <x-dropdown align="right" width="48">
                <x-slot name="trigger">
                    <button class="inline-flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium text-slate-200 hover:bg-white/10 hover:text-white">
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-accent-500 text-[11px] font-bold text-white">
                            {{ collect(explode(' ', $user->name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
                        </span>
                        <span class="hidden md:flex flex-col items-start leading-tight">
                            <span x-data="{{ json_encode(['name' => $user->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></span>
                            <span class="text-[11px] text-slate-400">{{ $user->role->label() }}</span>
                        </span>
                    </button>
                </x-slot>
                <x-slot name="content">
                    <div class="px-4 py-2 text-xs text-slate-400 border-b border-slate-100">{{ $user->email }}</div>
                    <x-dropdown-link :href="route('profile')" wire:navigate>{{ __('Profile & passkeys') }}</x-dropdown-link>
                    @if ($user->isAdministrator())
                        <x-dropdown-link :href="route('admin.users')">{{ __('Administration') }}</x-dropdown-link>
                    @endif
                    <button wire:click="logout" class="w-full text-start">
                        <x-dropdown-link>{{ __('Log Out') }}</x-dropdown-link>
                    </button>
                </x-slot>
            </x-dropdown>

            <a href="{{ $user->isAdministrator() ? route('admin.terminology') : route('profile') }}" class="hidden sm:inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-300 hover:bg-white/10 hover:text-white" title="Settings">
                <x-icon name="settings" set="standard" class="h-5 w-5" />
            </a>
        </div>
    </div>
</header>
