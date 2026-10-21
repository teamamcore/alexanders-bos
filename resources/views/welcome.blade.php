<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Batsons BOS') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased h-full bg-brand-950 text-white">
        <div class="min-h-full flex flex-col relative overflow-hidden">
            <div class="absolute -top-40 right-0 w-[40rem] h-[40rem] rounded-full bg-brand-800/40 blur-3xl"></div>
            <div class="absolute -bottom-40 -left-20 w-[32rem] h-[32rem] rounded-full bg-accent-500/20 blur-3xl"></div>

            <header class="relative max-w-6xl w-full mx-auto px-6 py-6 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <x-application-logo class="w-9 h-9 text-white" />
                    <span class="font-semibold tracking-tight">Batsons <span class="text-accent-300">BOS</span></span>
                </div>
                <nav class="flex items-center gap-3 text-sm">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="rounded-lg bg-white text-brand-950 px-4 py-2 font-semibold hover:bg-slate-100 transition">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg bg-white text-brand-950 px-4 py-2 font-semibold hover:bg-slate-100 transition">Log in</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="rounded-lg px-4 py-2 font-medium text-slate-200 hover:text-white transition">Register</a>
                        @endif
                    @endauth
                </nav>
            </header>

            <main class="relative flex-1 flex items-center">
                <div class="max-w-6xl w-full mx-auto px-6 py-16 grid lg:grid-cols-5 gap-12 items-center">
                    <div class="lg:col-span-3">
                        <p class="text-accent-300 text-sm font-semibold uppercase tracking-widest">Business Operating System</p>
                        <h1 class="mt-3 text-4xl sm:text-5xl font-bold tracking-tight leading-[1.1]">Batsons Plumbing, run from one screen.</h1>
                        <p class="mt-5 text-lg text-slate-300 leading-relaxed max-w-xl">From the first call to the paid invoice: intake, proposals, jobs and work orders, dispatch, time &amp; pay, parts and fleet — with QuickBooks as the ledger and the crew's phones as the front line.</p>
                        <div class="mt-8 flex flex-wrap gap-3">
                            @auth
                                <a href="{{ url('/dashboard') }}" class="rounded-lg bg-accent-500 px-5 py-2.5 font-semibold text-white hover:bg-accent-600 transition">Open the dashboard</a>
                            @else
                                <a href="{{ route('login') }}" class="rounded-lg bg-accent-500 px-5 py-2.5 font-semibold text-white hover:bg-accent-600 transition">Log in</a>
                            @endauth
                        </div>
                    </div>
                    <div class="lg:col-span-2 grid grid-cols-2 gap-3 text-sm">
                        @foreach ([
                            ['Intake & customers', 'Calls become jobs in one step'],
                            ['Dispatch board', 'Windows, crews, reschedule history'],
                            ['Time & pay', 'Clock in from the truck; pay rules applied'],
                            ['Invoicing', 'Per work order; synced to QuickBooks'],
                            ['Passkeys', 'Face ID / Touch ID sign-in'],
                            ['Admin', 'Users, roles, your own terminology'],
                        ] as [$t, $d])
                            <div class="rounded-xl bg-white/5 ring-1 ring-white/10 p-4">
                                <div class="font-semibold">{{ $t }}</div>
                                <div class="mt-1 text-slate-400 text-xs leading-relaxed">{{ $d }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </main>

            <footer class="relative max-w-6xl w-full mx-auto px-6 py-6 text-xs text-slate-400 flex justify-between">
                <span>Prototype · SOW No. 01</span>
                <span>AmCore Systems</span>
            </footer>
        </div>
    </body>
</html>
