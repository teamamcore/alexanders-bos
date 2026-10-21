@props(['active', 'navigate' => false])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-accent-400 text-sm font-semibold leading-5 text-white focus:outline-none transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-slate-300 hover:text-white hover:border-slate-500 focus:outline-none focus:text-white focus:border-slate-500 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if($navigate) wire:navigate @endif>
    {{ $slot }}
</a>
