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
    $links = [
        ['route' => 'dashboard',        'is' => 'dashboard',   'label' => __('Dashboard'),       'navigate' => true],
        ['route' => 'proposals.index',  'is' => 'proposals.*', 'label' => term('proposal', true), 'navigate' => false],
        ['route' => 'jobs.index',       'is' => 'jobs.*',      'label' => term('job', true),      'navigate' => true],
        ['route' => 'schedule.index',   'is' => 'schedule.*',  'label' => term('schedule'),       'navigate' => true],
        ['route' => 'invoices.index',   'is' => 'invoices.*',  'label' => term('invoice', true),  'navigate' => false],
        ['route' => 'time.index',       'is' => 'time.*',      'label' => __('Time & Pay'),       'navigate' => false],
        ['route' => 'team.index',       'is' => 'team.*',      'label' => __('Team'),             'navigate' => false],
        ['route' => 'parts.index',      'is' => ['parts.*', 'equipment.*', 'fleet.*'], 'label' => __('Assets'), 'navigate' => false],
        ['route' => auth()->user()->isField() ? 'messages.calls' : 'messages.index', 'is' => 'messages.*', 'label' => __('Messages'), 'navigate' => false],
        ['route' => 'inbox.index',      'is' => 'inbox.*',     'label' => __('Inbox'),            'navigate' => false],
        ['route' => 'build.index',      'is' => 'build.*',     'label' => __('Build'),            'navigate' => false],
    ];
    if (auth()->user()->isAdministrator()) {
        $links[] = ['route' => 'admin.users', 'is' => 'admin.*', 'label' => __('Admin'), 'navigate' => false];
    }
    $unreadNotifications = auth()->user()->unreadNotifications()->count();
    $unreadMessages = \App\Models\UserMessage::where('recipient_id', auth()->id())->whereNull('read_at')->count();
@endphp

<nav x-data="{ open: false }" class="bg-brand-950 text-white shadow-md">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5">
                        <x-application-logo class="block h-8 w-8 text-white" />
                        <span class="font-semibold tracking-tight text-[15px]">Batsons <span class="text-accent-300">BOS</span></span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-5 sm:-my-px sm:ms-8 sm:flex">
                    @foreach ($links as $l)
                        <x-nav-link :href="route($l['route'])" :active="request()->routeIs($l['is'])" :navigate="$l['navigate']">
                            {{ $l['label'] }}
                        </x-nav-link>
                    @endforeach
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6 gap-1">
                {{-- SOW §3.12 — notification bell --}}
                <a href="{{ route('notifications.index') }}" class="relative inline-flex items-center justify-center h-9 w-9 rounded-lg text-slate-300 hover:text-white hover:bg-white/10 transition" title="Notifications">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
                    @if ($unreadNotifications)<span class="notif-badge absolute -top-0.5 -right-0.5 min-w-[1.1rem] h-[1.1rem] px-1 rounded-full bg-accent-500 text-[10px] font-bold text-white flex items-center justify-center">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>@endif
                </a>
                <a href="{{ route('inbox.index') }}" class="relative inline-flex items-center justify-center h-9 w-9 rounded-lg text-slate-300 hover:text-white hover:bg-white/10 transition" title="Inbox">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 011.037-.443 48.282 48.282 0 005.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/></svg>
                    @if ($unreadMessages)<span class="absolute -top-0.5 -right-0.5 min-w-[1.1rem] h-[1.1rem] px-1 rounded-full bg-brand-400 text-[10px] font-bold text-white flex items-center justify-center">{{ $unreadMessages }}</span>@endif
                </a>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-sm font-medium text-slate-200 hover:text-white hover:bg-white/10 focus:outline-none transition">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-accent-500 text-[11px] font-bold text-white">
                                {{ collect(explode(' ', auth()->user()->name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
                            </span>
                            <span class="hidden md:flex flex-col items-start leading-tight">
                                <span x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></span>
                                <span class="text-[11px] text-slate-400">{{ auth()->user()->role->label() }}</span>
                            </span>
                            <svg class="fill-current h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="px-4 py-2 text-xs text-slate-400 border-b border-slate-100">{{ auth()->user()->email }}</div>
                        <x-dropdown-link :href="route('profile')" wire:navigate>
                            {{ __('Profile & passkeys') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-slate-300 hover:text-white hover:bg-white/10 focus:outline-none transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden border-t border-white/10">
        <div class="pt-2 pb-3 space-y-1">
            @foreach ($links as $l)
                <x-responsive-nav-link :href="route($l['route'])" :active="request()->routeIs($l['is'])" :navigate="$l['navigate']">
                    {{ $l['label'] }}
                </x-responsive-nav-link>
            @endforeach
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-white/10">
            <div class="px-4">
                <div class="font-medium text-base text-white" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                <div class="font-medium text-sm text-slate-400">{{ auth()->user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile')" wire:navigate>
                    {{ __('Profile & passkeys') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <button wire:click="logout" class="w-full text-start">
                    <x-responsive-nav-link>
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </button>
            </div>
        </div>
    </div>
</nav>
