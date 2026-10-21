<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Batsons BOS') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased h-full">
        <div class="min-h-full flex flex-col">
            <livewire:layout.navigation />

            <!-- Page Heading -->
            @if (isset($header))
                <div class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-2">
                    {{ $header }}
                </div>
            @endif

            <!-- Page Content -->
            <main class="flex-1">
                {{ $slot }}
            </main>

            <footer class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 text-xs text-slate-400 flex items-center justify-between">
                <span>{{ \App\Models\Tenant::current()?->name ?? config('app.name') }} · Business Operating System</span>
                <span>Prototype · built by AmCore Systems</span>
            </footer>
        </div>
    </body>
</html>
