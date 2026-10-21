{{-- Collapsible left sidebar (design notes §1). Icons come from the client-selected set (§4). --}}
<aside class="hidden md:flex flex-col shrink-0 border-r border-slate-200 bg-white transition-[width] duration-200"
       :class="sidebar ? 'w-56' : 'w-16'">
    <nav class="flex-1 overflow-y-auto py-3">
        @foreach (bos_nav() as $group)
            @php $items = array_filter($group['items'], fn ($i) => $i['show']); @endphp
            @if ($items !== [])
                <div class="px-3 pt-3 pb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400" x-show="sidebar" x-cloak>{{ $group['label'] }}</div>
                <ul class="space-y-0.5 px-2">
                    @foreach ($items as $item)
                        @php $active = request()->routeIs(...(array) $item['is']); @endphp
                        <li>
                            <a href="{{ route($item['route']) }}" title="{{ $item['label'] }}"
                               class="group flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm font-medium transition
                                      {{ $active ? 'bg-brand-900 text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' }}"
                               :class="sidebar ? '' : 'justify-center'">
                                <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0 {{ $active ? 'text-white' : 'text-slate-500 group-hover:text-brand-700' }}" />
                                <span x-show="sidebar" x-cloak class="truncate">{{ $item['label'] }}</span>
                                @if (! empty($item['badge']))
                                    <span x-show="sidebar" x-cloak class="ml-auto rounded-full bg-accent-500 px-1.5 text-[10px] font-bold text-white">{{ $item['badge'] }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endforeach
    </nav>
    <div class="border-t border-slate-200 p-2">
        <button type="button" @click="sidebar = !sidebar" class="flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-xs font-medium text-slate-500 hover:bg-slate-100" :class="sidebar ? '' : 'justify-center'" title="Collapse sidebar">
            <svg class="h-4 w-4 transition-transform" :class="sidebar ? '' : 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6"/></svg>
            <span x-show="sidebar" x-cloak>Collapse</span>
        </button>
    </div>
</aside>
