<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">Parts &amp; Inventory <span class="sow">SOW §3.7</span></h2>
            @include('ops.assets.partials.tabs')
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if (session('status'))<div class="flash-ok">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="flash-err">{{ $errors->first() }}</div>@endif

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach ($stats as $label => $value)
                    <div class="stat"><div class="stat-value {{ $label === 'Low stock' && $value ? 'text-amber-600' : '' }}">{{ $value }}</div><div class="stat-label">{{ $label }}</div></div>
                @endforeach
            </div>

            {{-- Low stock --}}
            @if ($lowStock->isNotEmpty())
                <div class="card overflow-hidden">
                    <div class="card-head"><span class="card-title">Reorder alerts <span class="card-note">· at or below threshold</span></span></div>
                    <table class="table">
                        <tbody>
                            @foreach ($lowStock as $p)
                                <tr>
                                    <td class="font-medium text-slate-900"><a href="{{ route('parts.show', $p) }}" class="hover:underline">{{ $p->name }}</a> <span class="text-xs text-slate-400">{{ $p->sku }}</span></td>
                                    <td><span class="badge-amber">{{ $p->stock_qty }} on hand · reorder at {{ $p->reorder_threshold }}</span></td>
                                    <td class="text-slate-600">{{ $p->supplier?->name ?? 'no supplier' }}</td>
                                    <td class="text-right"><a href="{{ route('parts.show', $p) }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Receive →</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Search + catalog --}}
            <div class="card overflow-hidden">
                <div class="card-head">
                    <span class="card-title">Catalog <span class="card-note">· search by name, SKU, category or crew nickname</span></span>
                    <form method="GET" action="{{ route('parts.index') }}" class="flex items-center gap-2">
                        <x-text-input name="q" value="{{ $q }}" placeholder="e.g. 3/4 ball, PVC, 'the big wrench'…" class="w-72" />
                        <x-secondary-button type="submit">Search</x-secondary-button>
                        @if ($q)<a href="{{ route('parts.index') }}" class="text-sm text-slate-500">clear</a>@endif
                    </form>
                </div>
                <table class="table">
                    <thead><tr><th>Part</th><th>Category</th><th>Crew names</th><th>Supplier</th><th class="text-right">Price</th><th class="text-right">On hand</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($parts as $p)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        @if ($p->photo_path)<img src="{{ Storage::disk('public')->url($p->photo_path) }}" alt="" class="h-9 w-9 rounded-md object-cover ring-1 ring-slate-200">@else<span class="h-9 w-9 rounded-md bg-slate-100 ring-1 ring-slate-200 inline-flex items-center justify-center text-slate-400 text-xs">—</span>@endif
                                        <div>
                                            <div class="font-medium text-slate-900">{{ $p->name }} @if ($p->is_one_off)<span class="badge-gray ml-1">one-off</span>@endif</div>
                                            <div class="text-xs text-slate-400 font-mono">{{ $p->sku ?? '—' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-slate-600">{{ $p->category ?? '—' }}</td>
                                <td class="text-xs text-slate-600">{{ implode(', ', $p->nicknames ?? []) ?: '—' }}</td>
                                <td class="text-slate-600">{{ $p->supplier?->name ?? '—' }}</td>
                                <td class="text-right font-medium text-slate-900">${{ number_format((float) $p->price, 2) }} <span class="text-xs text-slate-400">/ {{ $p->unit }}</span></td>
                                <td class="text-right">@if ($p->isLowStock())<span class="badge-amber">{{ $p->stock_qty }}</span>@else<span class="text-slate-700">{{ $p->stock_qty }}</span>@endif</td>
                                <td class="text-right"><a href="{{ route('parts.show', $p) }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Open →</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-8 text-center text-slate-500">{{ $q ? 'Nothing matches "'.$q.'".' : 'No parts yet.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($canEdit)
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {{-- Add part / one-off --}}
                    <form method="POST" action="{{ route('parts.store') }}" enctype="multipart/form-data" class="lg:col-span-2 card p-6 space-y-4">
                        @csrf
                        <div class="card-title">Add a part <span class="card-note">· or a custom one-off item from a receipt</span></div>
                        <div class="grid sm:grid-cols-3 gap-3">
                            <div class="sm:col-span-2"><x-input-label for="pname" value="Name" /><x-text-input id="pname" name="name" class="mt-1 w-full" required value="{{ old('name') }}" /></div>
                            <div><x-input-label for="psku" value="SKU" /><x-text-input id="psku" name="sku" class="mt-1 w-full" value="{{ old('sku') }}" /></div>
                            <div><x-input-label for="pcat" value="Category" /><x-text-input id="pcat" name="category" class="mt-1 w-full" placeholder="Fittings" value="{{ old('category') }}" /></div>
                            <div class="sm:col-span-2"><x-input-label for="pnick" value="Crew names / abbreviations (comma-separated)" /><x-text-input id="pnick" name="nicknames" class="mt-1 w-full" placeholder="3/4 ball, BV34" value="{{ old('nicknames') }}" /></div>
                            <div><x-input-label for="pprice" value="Price" /><x-text-input id="pprice" name="price" type="number" step="0.01" min="0" class="mt-1 w-full" required value="{{ old('price') }}" /></div>
                            <div><x-input-label for="pcost" value="Cost (receipt)" /><x-text-input id="pcost" name="cost" type="number" step="0.01" min="0" class="mt-1 w-full" value="{{ old('cost') }}" /></div>
                            <div><x-input-label for="punit" value="Unit" /><x-text-input id="punit" name="unit" class="mt-1 w-full" placeholder="each" value="{{ old('unit') }}" /></div>
                            <div><x-input-label for="pqty" value="On hand" /><x-text-input id="pqty" name="stock_qty" type="number" min="0" class="mt-1 w-full" value="{{ old('stock_qty', 0) }}" /></div>
                            <div><x-input-label for="pre" value="Reorder at" /><x-text-input id="pre" name="reorder_threshold" type="number" min="0" class="mt-1 w-full" value="{{ old('reorder_threshold') }}" /></div>
                            <div><x-input-label for="psup" value="Supplier" />
                                <select id="psup" name="supplier_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="">—</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
                            <div><x-input-label for="pphoto" value="Photo" /><input id="pphoto" name="photo" type="file" accept="image/*" class="mt-1 block w-full text-xs text-slate-500 file:mr-2 file:rounded-md file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs file:font-medium" /></div>
                            <div class="sm:col-span-3 flex flex-wrap items-end gap-3 rounded-lg bg-slate-50 p-3">
                                <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="is_one_off" value="1" class="rounded border-slate-300 text-brand-700 focus:ring-brand-500" @checked(old('is_one_off'))> Custom one-off item from a receipt</label>
                                <div><x-input-label for="pjob" value="For job" />
                                    <select id="pjob" name="job_id" class="mt-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="">—</option>@foreach ($jobs as $j)<option value="{{ $j->id }}">{{ $j->number }} — {{ $j->title }}</option>@endforeach</select></div>
                                <div class="grow"><x-input-label for="pnotes" value="Notes" /><x-text-input id="pnotes" name="notes" class="mt-1 w-full" placeholder="Receipt #, where bought…" /></div>
                            </div>
                        </div>
                        <x-primary-button>Add to catalog</x-primary-button>
                    </form>

                    {{-- Import --}}
                    <div class="space-y-6">
                        <form method="POST" action="{{ route('parts.import') }}" enctype="multipart/form-data" class="card p-6 space-y-3">
                            @csrf
                            <div class="card-title">Supplier price list import</div>
                            <p class="text-xs text-slate-500">CSV with columns <code>sku, name, price</code> (optional <code>category, unit</code>). Existing SKUs get the new price; new SKUs are created. Changes show in each item's history.</p>
                            <div><x-input-label for="isup" value="Supplier" />
                                <select id="isup" name="supplier_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="">— new supplier —</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
                            <div><x-input-label for="isupname" value="…or new supplier name" /><x-text-input id="isupname" name="supplier_name" class="mt-1 w-full" placeholder="Gateway Supplies" /></div>
                            <div><x-input-label for="ifile" value="CSV file" /><input id="ifile" name="file" type="file" accept=".csv,text/csv" required class="mt-1 block w-full text-xs text-slate-500 file:mr-2 file:rounded-md file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs file:font-medium" /></div>
                            <x-primary-button>Import prices</x-primary-button>
                        </form>

                        <div class="card p-6">
                            <div class="card-title mb-2">Terminology glossary <span class="card-note">· what crews say → catalog item</span></div>
                            <dl class="text-sm divide-y divide-slate-100 max-h-72 overflow-y-auto">
                                @forelse ($glossary as $g)
                                    <div class="flex justify-between py-1.5"><dt class="text-slate-700">“{{ $g['nickname'] }}”</dt><dd><a href="{{ route('parts.show', $g['part']) }}" class="font-medium text-brand-700 hover:text-brand-900">{{ $g['part']->name }}</a></dd></div>
                                @empty
                                    <div class="py-2 text-slate-400">Add crew names to parts to build the glossary.</div>
                                @endforelse
                            </dl>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
