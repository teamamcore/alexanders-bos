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
    <body class="font-sans text-slate-900 antialiased h-full">
        <div class="min-h-full grid lg:grid-cols-2">
            {{-- Brand panel --}}
            <div class="hidden lg:flex flex-col justify-between bg-brand-950 text-white p-12 relative overflow-hidden">
                <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-brand-800/40 blur-3xl"></div>
                <div class="absolute -bottom-32 -left-16 w-96 h-96 rounded-full bg-accent-500/20 blur-3xl"></div>
                <a href="/" class="relative flex items-center gap-3">
                    <x-application-logo class="w-10 h-10 text-white" />
                    <span class="text-lg font-semibold tracking-tight">Batsons <span class="text-accent-300">BOS</span></span>
                </a>
                <div class="relative max-w-md">
                    <h1 class="text-4xl font-bold tracking-tight leading-tight">One place to run the whole shop.</h1>
                    <p class="mt-4 text-slate-300 leading-relaxed">Intake, jobs, dispatch, time, invoicing and the field crew's day — in a single system built around how Batsons Plumbing actually works.</p>
                    <dl class="mt-8 grid grid-cols-3 gap-6 text-sm">
                        <div><dt class="text-slate-400">Roles</dt><dd class="font-semibold">Field · Office · Admin</dd></div>
                        <div><dt class="text-slate-400">Sign-in</dt><dd class="font-semibold">Passkeys</dd></div>
                        <div><dt class="text-slate-400">Ledger</dt><dd class="font-semibold">QuickBooks</dd></div>
                    </dl>
                </div>
                <div class="relative text-xs text-slate-400">Prototype · AmCore Systems</div>
            </div>

            {{-- Form panel --}}
            <div class="flex flex-col justify-center items-center px-6 py-12 bg-slate-50">
                <a href="/" class="lg:hidden mb-8 flex items-center gap-3 text-brand-900">
                    <x-application-logo class="w-10 h-10" />
                    <span class="text-lg font-semibold tracking-tight">Batsons <span class="text-accent-500">BOS</span></span>
                </a>
                <div class="w-full sm:max-w-md px-8 py-8 bg-white border border-slate-200 shadow-card rounded-2xl">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
