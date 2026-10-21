{{-- Field / phone: bottom tab bar instead of a hamburger (design notes §1). --}}
@php
    $tabs = array_values(array_filter([
        ['route' => 'work-orders.index', 'is' => ['work-orders.*', 'jobs.*'], 'label' => 'Work', 'icon' => 'work_orders'],
        ['route' => 'schedule.index', 'is' => ['schedule.*'], 'label' => 'Schedule', 'icon' => 'schedule'],
        ['route' => 'time.index', 'is' => ['time.*'], 'label' => 'Clock', 'icon' => 'time'],
        ['route' => 'inbox.index', 'is' => ['inbox.*', 'messages.*'], 'label' => 'Inbox', 'icon' => 'inbox'],
        ['route' => 'notifications.index', 'is' => ['notifications.*'], 'label' => 'Alerts', 'icon' => 'notifications'],
    ]));
@endphp
<nav class="md:hidden fixed bottom-0 inset-x-0 z-40 border-t border-slate-200 bg-white/95 backdrop-blur">
    <ul class="grid grid-cols-5">
        @foreach ($tabs as $t)
            @php $active = request()->routeIs(...$t['is']); @endphp
            <li>
                <a href="{{ route($t['route']) }}" class="flex flex-col items-center gap-0.5 py-2 text-[10px] font-medium {{ $active ? 'text-brand-800' : 'text-slate-500' }}">
                    <x-icon :name="$t['icon']" class="h-6 w-6" />
                    {{ $t['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
