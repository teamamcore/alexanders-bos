<div class="flex gap-1 text-sm">
    @foreach (['messages.index' => 'Messages', 'messages.templates' => 'Templates', 'messages.calls' => 'Calls'] as $route => $label)
        @if (Route::has($route) && ($route !== 'messages.index' || ! auth()->user()->isField()))
            <a href="{{ route($route) }}"
               class="px-3 py-1.5 rounded-md {{ request()->routeIs($route) ? 'bg-brand-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }}</a>
        @endif
    @endforeach
</div>
