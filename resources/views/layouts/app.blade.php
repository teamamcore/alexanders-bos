<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ bos_page_title() }} · {{ config('app.name', 'Batsons BOS') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    {{-- Page chrome (design notes §1): persistent top bar, collapsible left sidebar, back button, no hamburger. --}}
    <body class="font-sans antialiased h-full" x-data="{ sidebar: (localStorage.getItem('bos.sidebar') ?? 'open') !== 'closed' }" x-init="$watch('sidebar', v => localStorage.setItem('bos.sidebar', v ? 'open' : 'closed'))">
        <div class="min-h-full flex flex-col">
            <livewire:layout.navigation />

            <div class="flex flex-1 min-h-0">
                @include('layouts.partials.sidebar')

                <main class="flex-1 min-w-0 pb-20 md:pb-0">
                    @if (isset($header))
                        <div class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 pt-6 pb-1">
                            {{ $header }}
                        </div>
                    @endif
                    {{ $slot }}
                    <footer class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 text-xs text-slate-400 flex items-center justify-between">
                        <span>{{ \App\Models\Tenant::current()?->name ?? config('app.name') }} · Business Operating System</span>
                        <span>Prototype · built by AmCore Systems</span>
                    </footer>
                </main>
            </div>

            @include('layouts.partials.mobile-bar')
        </div>
    </body>
</html>
