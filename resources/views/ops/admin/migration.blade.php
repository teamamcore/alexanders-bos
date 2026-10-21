<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">Administration · Data import <span class="sow">SOW §4</span></h2>
            @include('ops.admin.partials.tabs')
        </div>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if (session('status'))<div class="flash-ok">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="flash-err">{{ $errors->first() }}</div>@endif

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach ($kinds as $k => $label)
                <div class="stat"><div class="stat-value">{{ number_format($counts[$k] ?? 0) }}</div><div class="stat-label">{{ $label }} imported</div></div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <form method="POST" action="{{ route('admin.migration.upload') }}" enctype="multipart/form-data" class="card p-6 space-y-3 lg:col-span-2">
                @csrf
                <div class="flex items-center justify-between"><div class="card-title">Import an export file</div><span class="card-note">CSV from QuickBooks Online / Desktop or a spreadsheet · dry run first</span></div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <x-input-label for="kind" value="What is in the file?" />
                        <select id="kind" name="kind" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($kinds as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="file" value="File (.csv)" />
                        <input id="file" name="file" type="file" accept=".csv,.txt,.tsv,text/csv" required class="mt-1 block w-full text-sm text-slate-600 file:mr-2 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm" />
                    </div>
                </div>
                <label class="flex items-start gap-2 text-sm text-slate-700"><input type="checkbox" name="commit" value="1" class="mt-0.5 rounded border-slate-300 text-brand-700 focus:ring-brand-500"> Write to the system now <span class="text-slate-400">(leave unchecked to dry-run: full report, nothing saved)</span></label>
                <div class="flex items-center gap-3">
                    <x-primary-button>Run import</x-primary-button>
                    <span class="text-xs text-slate-500">Recommended order: customers → items → invoices → work orders. Re-running a file updates records instead of duplicating them.</span>
                </div>
                <details class="text-xs text-slate-600">
                    <summary class="cursor-pointer font-medium text-brand-800">How to export from QuickBooks · sample files</summary>
                    <div class="mt-2 space-y-1">
                        <div><strong>QuickBooks Online:</strong> Reports → <em>Customer Contact List</em>, Sales → Products and Services → Export, Reports → <em>Invoice List</em> or <em>A/R Aging Detail</em> (set the date range to the last {{ $years }} years). Export to Excel, then save as CSV.</div>
                        <div><strong>QuickBooks Desktop:</strong> Customer Center → Excel → Export Customer List; Lists → Item List → Excel; Reports → Customers & Receivables → <em>Open Invoices</em> / <em>Transaction List by Customer</em>. Save as CSV.</div>
                        <div><strong>Work order records</strong> come from Batsons' spreadsheet log (date, customer, site, description, tech, hours, invoice #). QuickBooks has no work orders.</div>
                        <div>Samples: @foreach ($samples as $s)<a class="text-brand-700 underline mr-2" href="{{ route('admin.migration.sample', $s) }}">{{ $kinds[$s] }}</a>@endforeach</div>
                    </div>
                </details>
            </form>

            <div class="space-y-4">
                <div class="card p-5 space-y-2">
                    <div class="flex items-center justify-between"><div class="card-title">QuickBooks Online</div><span class="{{ $qbo['configured'] ? 'badge-green' : 'badge-amber' }}">{{ $qbo['configured'] ? 'Connected' : 'Not connected' }}</span></div>
                    @if ($qbo['configured'])
                        <div class="text-sm text-slate-700">Company (realm) {{ $qbo['realm'] }} · {{ $qbo['environment'] }}. Live pull of customers, items and invoices is wired through this connection.</div>
                    @else
                        <div class="text-sm text-slate-600">Direct pull needs the Client's QuickBooks admin access (SOW §9). Set <code>{{ implode('</code>, <code>', $qbo['missing']) }}</code> in <code>.env</code>. Until then, import exported files — same result, same report.</div>
                    @endif
                </div>
                <div class="card p-5 space-y-1">
                    <div class="card-title">Import window</div>
                    <div class="text-sm text-slate-700">{{ $years }} years — records dated before <strong>{{ $cutoff->format('M j, Y') }}</strong> are skipped and listed in the report.</div>
                    <div class="text-xs text-slate-400">SOW §4 includes up to eight years; additional years are priced per Exhibit A. Change with <code>QB_IMPORT_YEARS</code>.</div>
                </div>
                <div class="card p-5 space-y-1">
                    <div class="card-title">Not included</div>
                    <div class="text-xs text-slate-500">Paper or handwritten records, payments/credits/estimates (they stay in QuickBooks, the ledger), and records older than the window (§8 add-on on request).</div>
                </div>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-head"><span class="card-title">Import runs</span><span class="card-note">every run keeps its summary of what could not be imported</span></div>
            <table class="table">
                <thead><tr><th>When</th><th>Kind</th><th>File</th><th>Mode</th><th class="text-right">Rows</th><th class="text-right">New</th><th class="text-right">Updated</th><th class="text-right">Skipped</th><th class="text-right">Warnings</th><th></th></tr></thead>
                <tbody>
                @forelse ($batches as $b)
                    <tr class="{{ session('batch') === $b->id ? 'bg-brand-50/60' : '' }}">
                        <td class="whitespace-nowrap text-slate-500">{{ $b->created_at?->format('M j, g:ia') }}<div class="text-[11px] text-slate-400">{{ $b->runner?->name }}</div></td>
                        <td class="font-medium text-slate-900">{{ $b->kindLabel() }}</td>
                        <td class="text-slate-600 max-w-[14rem] truncate" title="{{ $b->file_name }}">{{ $b->file_name }}</td>
                        <td>@if ($b->status === 'failed')<span class="badge-red">failed</span>@elseif ($b->isDryRun())<span class="badge-gray">dry run</span>@else<span class="badge-green">committed</span>@endif</td>
                        <td class="text-right">{{ $b->total }}</td><td class="text-right">{{ $b->imported }}</td><td class="text-right">{{ $b->updated }}</td>
                        <td class="text-right {{ $b->skipped ? 'text-red-700 font-semibold' : '' }}">{{ $b->skipped }}</td>
                        <td class="text-right {{ $b->warnings ? 'text-amber-700' : '' }}">{{ $b->warnings }}</td>
                        <td class="text-right whitespace-nowrap">
                            @if ($b->issues)<a href="{{ route('admin.migration.issues', $b) }}" class="text-xs font-semibold text-brand-800">Issues CSV</a>@endif
                            @if ($b->isDryRun() && $b->status === 'done' && $b->file_path)<form method="POST" action="{{ route('admin.migration.commit', $b) }}" class="inline ml-2">@csrf<button class="rounded-md bg-brand-900 px-2 py-1 text-xs font-semibold text-white hover:bg-brand-800">Commit</button></form>@endif
                        </td>
                    </tr>
                    @if ($b->status === 'failed')
                        <tr><td colspan="10" class="px-6 py-2 text-xs text-red-700 bg-red-50">{{ $b->error }}</td></tr>
                    @elseif ($b->issues)
                        <tr><td colspan="10" class="px-6 py-2 bg-slate-50">
                            <details @if (session('batch') === $b->id) open @endif>
                                <summary class="cursor-pointer text-xs font-medium text-slate-600">{{ count($b->issues) }} issue{{ count($b->issues) === 1 ? '' : 's' }}{{ ($b->skipped + $b->warnings) > count($b->issues) ? ' shown of '.($b->skipped + $b->warnings) : '' }} · columns matched: {{ implode(', ', array_map(fn ($h, $f) => "$h → $f", array_keys($b->columns ?? []), array_values($b->columns ?? []))) ?: '—' }}</summary>
                                <ul class="mt-1 space-y-0.5 text-xs">
                                    @foreach (array_slice($b->issues, 0, 60) as $i)
                                        <li><span class="{{ $i['level'] === 'skipped' ? 'badge-red' : 'badge-amber' }} mr-1">{{ $i['level'] }}</span><span class="text-slate-400">row {{ $i['row'] }}</span> · <span class="text-slate-700">{{ $i['reason'] }}</span> <span class="text-slate-400">— {{ $i['record'] }}</span></li>
                                    @endforeach
                                </ul>
                            </details>
                        </td></tr>
                    @endif
                @empty
                    <tr><td colspan="10" class="px-6 py-8 text-center text-slate-500">No imports yet. Start with a dry run of the customer list.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
