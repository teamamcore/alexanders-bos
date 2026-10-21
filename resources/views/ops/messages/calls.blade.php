<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">Call Transcripts <span class="sow">SOW §3.4</span></h2>
            @include('ops.messages.partials.tabs')
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
            @if (session('status'))<div class="lg:col-span-3 flash-ok">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="lg:col-span-3 flash-err">{{ $errors->first() }}</div>@endif

            <div class="lg:col-span-2 card overflow-hidden">
                <div class="card-head"><span class="card-title">Calls</span><span class="card-note">office + limited field use · usage billed per Exhibit A</span></div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($calls as $c)
                        <li class="px-6 py-3">
                            <div class="flex items-center justify-between gap-2">
                                <div class="text-sm font-medium text-slate-900">{{ $c->customer?->name ?? $c->phone ?? 'Unknown caller' }} <span class="badge-gray ml-1">{{ $c->direction }}</span>@if ($c->status === 'pending')<span class="badge-amber ml-1">awaiting transcription</span>@endif</div>
                                <div class="text-xs text-slate-400">{{ $c->called_at?->format('M j, g:ia') }}@if ($c->duration_seconds) · {{ (int) round($c->duration_seconds / 60) }} min @endif</div>
                            </div>
                            @if ($c->summary)<div class="text-sm text-slate-700 mt-1">{{ $c->summary }}</div>@endif
                            @if ($c->transcript)<details class="mt-1"><summary class="text-xs font-medium text-brand-700 cursor-pointer">Full transcript</summary><div class="mt-1 text-sm text-slate-600 whitespace-pre-line">{{ $c->transcript }}</div></details>@endif
                            <div class="text-[11px] text-slate-400 mt-1">{{ $c->recorder?->name }}@if ($c->job) · {{ $c->job->number }} {{ $c->job->title }}@endif @if ($c->audio_path) · <a class="text-brand-700" href="{{ Storage::disk('public')->url($c->audio_path) }}">audio</a>@endif · consent noted</div>
                        </li>
                    @empty
                        <li class="px-6 py-10 text-center text-slate-500">No calls logged yet.</li>
                    @endforelse
                </ul>
            </div>

            <form method="POST" action="{{ route('messages.calls.store') }}" enctype="multipart/form-data" class="card p-6 space-y-3">
                @csrf
                <div class="card-title">Log a call</div>
                <div class="grid grid-cols-2 gap-2">
                    <div class="col-span-2"><x-input-label for="cc" value="Customer" /><select id="cc" name="customer_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="">—</option>@foreach ($customers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                    <div class="col-span-2"><x-input-label for="cj" value="Job" /><select id="cj" name="job_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="">—</option>@foreach ($jobs as $j)<option value="{{ $j->id }}">{{ $j->number }} — {{ $j->title }}</option>@endforeach</select></div>
                    <div><x-input-label for="cd" value="Direction" /><select id="cd" name="direction" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"><option value="inbound">Inbound</option><option value="outbound">Outbound</option></select></div>
                    <div><x-input-label for="cp" value="Phone" /><x-text-input id="cp" name="phone" class="mt-1 w-full" /></div>
                    <div><x-input-label for="cm" value="Minutes" /><x-text-input id="cm" name="duration_minutes" type="number" step="0.5" min="0" class="mt-1 w-full" /></div>
                    <div><x-input-label for="cw" value="When" /><x-text-input id="cw" name="called_at" type="datetime-local" class="mt-1 w-full" /></div>
                </div>
                <div><x-input-label for="ct" value="Transcript" /><textarea id="ct" name="transcript" rows="6" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Paste or dictate the transcript…"></textarea></div>
                <div><x-input-label for="ca" value="…or upload audio" /><input id="ca" name="audio" type="file" accept="audio/*" class="mt-1 block w-full text-xs text-slate-500 file:mr-2 file:rounded-md file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs" /><p class="text-[11px] text-slate-400 mt-1">Audio is transcribed by the AI provider (SOW §3.16) and marked pending until then.</p></div>
                <label class="flex items-start gap-2 text-xs text-slate-600"><input type="checkbox" name="consent_noted" value="1" required class="mt-0.5 rounded border-slate-300 text-brand-700 focus:ring-brand-500"> The caller was given the legally required recording/transcription notice and consented (Client responsibility, SOW §3.4).</label>
                <x-primary-button>Save call</x-primary-button>
            </form>
        </div>
    </div>
</x-app-layout>
