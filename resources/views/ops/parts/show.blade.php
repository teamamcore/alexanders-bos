<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <a href="{{ route('parts.index') }}" class="text-xs font-medium text-slate-500 hover:text-slate-700">← Parts</a>
                <h2 class="page-title">{{ $part->name }} @if ($part->is_one_off)<span class="badge-gray align-middle">one-off</span>@endif</h2>
                <div class="text-sm text-slate-500 font-mono">{{ $part->sku ?? 'no SKU' }}@if ($part->category) · <span class="font-sans">{{ $part->category }}</span>@endif @if ($part->supplier) · <span class="font-sans">{{ $part->supplier->name }}</span>@endif</div>
            </div>
            @include('ops.assets.partials.tabs')
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
            @if (session('status'))<div class="lg:col-span-3 flash-ok">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="lg:col-span-3 flash-err">{{ $errors->first() }}</div>@endif

            <div class="lg:col-span-2 space-y-6">
                {{-- Summary --}}
                <div class="card p-6 grid sm:grid-cols-4 gap-4 items-start">
                    <div class="sm:col-span-1">
                        @if ($part->photo_path)<img src="{{ Storage::disk('public')->url($part->photo_path) }}" alt="{{ $part->name }}" class="w-full aspect-square object-cover rounded-lg ring-1 ring-slate-200">@else<div class="w-full aspect-square rounded-lg bg-slate-100 ring-1 ring-slate-200 flex items-center justify-center text-slate-400 text-sm">no photo</div>@endif
                    </div>
                    <div class="sm:col-span-3 grid grid-cols-3 gap-4">
                        <div><div class="stat-label">On hand</div><div class="text-2xl font-bold {{ $part->isLowStock() ? 'text-amber-600' : 'text-slate-900' }}">{{ $part->stock_qty }} <span class="text-sm font-normal text-slate-400">{{ $part->unit }}</span></div>@if ($part->reorder_threshold !== null)<div class="text-xs text-slate-400">reorder at {{ $part->reorder_threshold }}</div>@endif</div>
                        <div><div class="stat-label">Price</div><div class="text-2xl font-bold text-slate-900">${{ number_format((float) $part->price, 2) }}</div>@if ($part->cost)<div class="text-xs text-slate-400">cost ${{ number_format((float) $part->cost, 2) }}</div>@endif</div>
                        <div><div class="stat-label">Crew names</div><div class="text-sm text-slate-700">{{ implode(', ', $part->nicknames ?? []) ?: '—' }}</div></div>
                        @if ($canEdit)
                            <form method="POST" action="{{ route('parts.price', $part) }}" class="col-span-3 flex items-end gap-2 rounded-lg bg-slate-50 p-3">
                                @csrf
                                <div><x-input-label for="price" value="Manual price update" /><x-text-input id="price" name="price" type="number" step="0.01" min="0" value="{{ $part->price }}" class="mt-1 w-36" /></div>
                                <x-secondary-button type="submit">Save price</x-secondary-button>
                                <span class="text-xs text-slate-400">Authorized users can change price any time; it is logged in history.</span>
                            </form>
                        @endif
                        @if ($part->job)<div class="col-span-3 text-xs text-slate-500">Bought for <a class="font-medium text-brand-700" href="{{ route('jobs.show', $part->job) }}">{{ $part->job->number }} — {{ $part->job->title }}</a>@if ($part->notes) · {{ $part->notes }}@endif</div>@endif
                    </div>
                </div>

                {{-- Stock adjustment --}}
                <form method="POST" action="{{ route('parts.adjust', $part) }}" class="card p-6">
                    @csrf
                    <div class="card-title mb-3">Adjust count <span class="card-note">· used on a job, not used, billable not used, breakage, correction</span></div>
                    <div class="flex flex-wrap items-end gap-3">
                        <div><x-input-label for="qty" value="Quantity (±)" /><x-text-input id="qty" name="quantity" type="number" class="mt-1 w-28" placeholder="-2" required /></div>
                        <div><x-input-label for="reason" value="Reason" />
                            <select id="reason" name="reason" class="mt-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">@foreach ($reasons as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></div>
                        <div><x-input-label for="wo" value="Work order" />
                            <select id="wo" name="work_order_id" class="mt-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="">—</option>@foreach ($workOrders as $w)<option value="{{ $w->id }}">{{ $w->number }} ({{ $w->job?->title }})</option>@endforeach</select></div>
                        <div class="grow"><x-input-label for="anotes" value="Notes" /><x-text-input id="anotes" name="notes" class="mt-1 w-full" /></div>
                        <x-primary-button>Adjust</x-primary-button>
                    </div>
                </form>

                {{-- Delivery check --}}
                <form method="POST" action="{{ route('parts.delivery', $part) }}" enctype="multipart/form-data" class="card p-6">
                    @csrf
                    <div class="card-title mb-3">Check on delivery <span class="card-note">· ordered vs. received; shortage &amp; damage recorded, photo attached, linked to the job</span></div>
                    <div class="flex flex-wrap items-end gap-3">
                        <div><x-input-label for="ref" value="PO / ref" /><x-text-input id="ref" name="reference" class="mt-1 w-28" /></div>
                        <div><x-input-label for="ord" value="Ordered" /><x-text-input id="ord" name="qty_ordered" type="number" min="0" class="mt-1 w-24" required /></div>
                        <div><x-input-label for="rec" value="Received" /><x-text-input id="rec" name="qty_received" type="number" min="0" class="mt-1 w-24" required /></div>
                        <div><x-input-label for="dmg" value="Damaged" /><x-text-input id="dmg" name="qty_damaged" type="number" min="0" value="0" class="mt-1 w-24" /></div>
                        <div><x-input-label for="dsup" value="Supplier" />
                            <select id="dsup" name="supplier_id" class="mt-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="">{{ $part->supplier?->name ?? '—' }}</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
                        <div><x-input-label for="dwo" value="Work order" />
                            <select id="dwo" name="work_order_id" class="mt-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="">—</option>@foreach ($workOrders as $w)<option value="{{ $w->id }}">{{ $w->number }}</option>@endforeach</select></div>
                        <div><x-input-label for="dphoto" value="Photo" /><input id="dphoto" name="photo" type="file" accept="image/*" class="mt-1 block text-xs text-slate-500 file:mr-2 file:rounded-md file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs" /></div>
                        <div class="grow"><x-input-label for="dnotes" value="Notes" /><x-text-input id="dnotes" name="notes" class="mt-1 w-full" placeholder="Short 2 — backordered" /></div>
                        <x-primary-button>Record delivery</x-primary-button>
                    </div>
                </form>
            </div>

            {{-- History --}}
            <div class="space-y-6">
                <div class="card overflow-hidden">
                    <div class="card-head"><span class="card-title">Stock movements</span></div>
                    <ul class="divide-y divide-slate-100 text-sm max-h-80 overflow-y-auto">
                        @forelse ($part->stockAdjustments as $a)
                            <li class="px-6 py-2.5 flex items-start justify-between gap-3">
                                <div><span class="font-semibold {{ $a->quantity > 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ $a->quantity > 0 ? '+' : '' }}{{ $a->quantity }}</span> <span class="text-slate-700">{{ $reasons[$a->reason] ?? $a->reason }}</span>@if ($a->notes)<div class="text-xs text-slate-400">{{ $a->notes }}</div>@endif</div>
                                <span class="text-xs text-slate-400 whitespace-nowrap">{{ $a->created_at?->format('M j') }}</span>
                            </li>
                        @empty
                            <li class="px-6 py-4 text-slate-400">No movements yet.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="card overflow-hidden">
                    <div class="card-head"><span class="card-title">Deliveries</span></div>
                    <ul class="divide-y divide-slate-100 text-sm">
                        @forelse ($part->deliveryChecks as $d)
                            <li class="px-6 py-2.5">
                                <div class="flex justify-between"><span class="font-medium text-slate-900">{{ $d->reference ?? 'Delivery #'.$d->id }}</span><span class="text-xs text-slate-400">{{ $d->created_at?->format('M j') }}</span></div>
                                <div class="text-xs text-slate-600">{{ $d->qty_received }} / {{ $d->qty_ordered }} received
                                    @if ($d->shortage())<span class="badge-amber ml-1">short {{ $d->shortage() }}</span>@endif
                                    @if ($d->qty_damaged)<span class="badge-red ml-1">{{ $d->qty_damaged }} damaged</span>@endif
                                    @if ($d->workOrder) · {{ $d->workOrder->number }}@endif
                                    @if ($d->photo_path) · <a class="text-brand-700" href="{{ Storage::disk('public')->url($d->photo_path) }}" target="_blank">photo</a>@endif
                                </div>
                            </li>
                        @empty
                            <li class="px-6 py-4 text-slate-400">No deliveries checked yet.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="card overflow-hidden">
                    <div class="card-head"><span class="card-title">Item history</span><span class="card-note">audit trail</span></div>
                    <ul class="divide-y divide-slate-100 text-xs max-h-72 overflow-y-auto">
                        @forelse ($part->history as $h)
                            <li class="px-6 py-2 flex justify-between gap-3">
                                <span class="text-slate-700">{{ $h->action }}@if ($h->action === 'updated' && isset($h->changes['after']['price'])) · price {{ $h->changes['before']['price'] ?? '?' }} → {{ $h->changes['after']['price'] }}@elseif ($h->action === 'updated') · {{ implode(', ', array_keys($h->changes['after'] ?? [])) }}@endif</span>
                                <span class="text-slate-400 whitespace-nowrap">{{ $h->created_at?->format('M j, g:ia') }}</span>
                            </li>
                        @empty
                            <li class="px-6 py-4 text-slate-400">No history.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
