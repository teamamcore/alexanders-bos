<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">Administration · Reminder types <span class="sow">SOW §3.10</span></h2>
            @include('ops.admin.partials.tabs')
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))<div class="flash-ok">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="flash-err">{{ $errors->first() }}</div>@endif

            <form method="POST" action="{{ route('admin.reminder-types.update') }}" class="card overflow-hidden">
                @csrf
                <div class="card-head">
                    <div><div class="card-title">Recurring reminder types</div><div class="text-sm text-slate-500">Interval, the contact window (start contacting N days before due; have it scheduled by M days before), whether the customer is messaged automatically, and the work type that starts the schedule when a job completes.</div></div>
                    <x-primary-button>Save</x-primary-button>
                </div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Key</th><th>Label</th><th>Customer wording</th><th>Every (mo)</th><th>Alert (days before)</th><th>Contact by (days before)</th><th>Auto-message</th><th>Triggered by work type</th></tr></thead>
                        <tbody>
                            @foreach ($types as $key => $t)
                                <tr>
                                    <td class="font-mono text-xs text-slate-500">{{ $key }}<input type="hidden" name="types[{{ $key }}][key]" value="{{ $key }}"></td>
                                    <td><x-text-input name="types[{{ $key }}][label]" value="{{ $t['label'] }}" class="w-44" required /></td>
                                    <td><x-text-input name="types[{{ $key }}][customer_label]" value="{{ $t['customer_label'] }}" class="w-44" /></td>
                                    <td><x-text-input name="types[{{ $key }}][interval_months]" type="number" min="1" value="{{ $t['interval_months'] }}" class="w-20" /></td>
                                    <td><x-text-input name="types[{{ $key }}][lead_days_max]" type="number" min="0" value="{{ $t['lead_days_max'] }}" class="w-20" /></td>
                                    <td><x-text-input name="types[{{ $key }}][lead_days_min]" type="number" min="0" value="{{ $t['lead_days_min'] }}" class="w-20" /></td>
                                    <td class="text-center"><input type="checkbox" name="types[{{ $key }}][auto_message]" value="1" @checked($t['auto_message']) class="rounded border-slate-300 text-brand-700 focus:ring-brand-500"></td>
                                    <td><select name="types[{{ $key }}][work_type]" class="rounded-lg border-slate-300 text-xs py-1 shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="">—</option>@foreach ($workTypes as $wk => $wl)<option value="{{ $wk }}" @selected($t['work_type'] === $wk)>{{ $wl }}</option>@endforeach</select></td>
                                </tr>
                            @endforeach
                            {{-- new type --}}
                            <tr class="bg-slate-50/60">
                                <td><x-text-input name="types[new][key]" placeholder="new_key" class="w-28 font-mono text-xs" /></td>
                                <td><x-text-input name="types[new][label]" placeholder="New reminder type" class="w-44" /></td>
                                <td><x-text-input name="types[new][customer_label]" placeholder="what the customer sees" class="w-44" /></td>
                                <td><x-text-input name="types[new][interval_months]" type="number" min="1" value="12" class="w-20" /></td>
                                <td><x-text-input name="types[new][lead_days_max]" type="number" min="0" value="30" class="w-20" /></td>
                                <td><x-text-input name="types[new][lead_days_min]" type="number" min="0" value="7" class="w-20" /></td>
                                <td class="text-center"><input type="checkbox" name="types[new][auto_message]" value="1" class="rounded border-slate-300 text-brand-700 focus:ring-brand-500"></td>
                                <td><select name="types[new][work_type]" class="rounded-lg border-slate-300 text-xs py-1 shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="">—</option>@foreach ($workTypes as $wk => $wl)<option value="{{ $wk }}">{{ $wl }}</option>@endforeach</select></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-3 border-t border-slate-100 text-xs text-slate-400">The automatic customer message uses the <a class="text-brand-700" href="{{ route('messages.templates') }}">service_reminder</a> template. Existing schedules keep their own interval/window; new ones take these values.</div>
            </form>
        </div>
    </div>
</x-app-layout>
