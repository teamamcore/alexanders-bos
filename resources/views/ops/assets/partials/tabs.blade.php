<div class="flex gap-1 text-sm">
    @foreach (['parts.index' => 'Parts', 'equipment.index' => 'Equipment', 'fleet.index' => 'Fleet'] as $route => $label)
        @php $active = request()->routeIs(str_replace('.index', '.*', $route)); @endphp
        <a href="{{ route($route) }}"
           class="px-3 py-1.5 rounded-md {{ $active ? 'bg-brand-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }}</a>
    @endforeach
</div>
