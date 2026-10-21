<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">Message Templates <span class="sow">SOW §3.4</span></h2>
            @include('ops.messages.partials.tabs')
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))<div class="flash-ok">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="flash-err">{{ $errors->first() }}</div>@endif

            <div class="card p-6 text-sm text-slate-600 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <span class="font-semibold text-slate-900">Placeholders:</span>
                    @foreach ($placeholders as $k => $desc)<code class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs" title="{{ $desc }}">{{ '{'.$k.'}' }}</code>@endforeach
                </div>
                <form method="POST" action="{{ route('messages.autonotify') }}">@csrf<button class="{{ $autoNotify ? 'badge-green' : 'badge-gray' }}">automatic updates {{ $autoNotify ? 'on' : 'off' }} · toggle</button></form>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @foreach ($templates as $key => $t)
                    <form method="POST" action="{{ route('messages.templates.update', $key) }}" class="card p-6 space-y-3 {{ $t['is_active'] ? '' : 'opacity-70' }}">
                        @csrf
                        <div class="flex items-center justify-between">
                            <div><span class="card-title">{{ $t['name'] }}</span> <span class="card-note">· {{ $key }}</span></div>
                            <div class="flex items-center gap-2 text-xs">
                                @if ($t['overridden'])<span class="badge-blue">customized</span>@else<span class="badge-gray">default</span>@endif
                                <label class="inline-flex items-center gap-1 text-slate-600"><input type="checkbox" name="is_active" value="1" @checked($t['is_active']) class="rounded border-slate-300 text-brand-700 focus:ring-brand-500"> active</label>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <select name="channel" class="rounded-lg border-slate-300 text-xs py-1 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                @foreach (['sms' => 'SMS first', 'email' => 'Email only', 'both' => 'Best available'] as $c => $l)<option value="{{ $c }}" @selected($t['channel'] === $c)>{{ $l }}</option>@endforeach
                            </select>
                            <x-text-input name="subject" value="{{ $t['subject'] }}" placeholder="Email subject (optional)" class="text-xs grow" />
                        </div>
                        <textarea name="body" rows="3" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" required>{{ $t['body'] }}</textarea>
                        <div class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600"><span class="font-semibold text-slate-500">Preview:</span> {{ \App\Platform\Messaging\TemplateRenderer::render($t['body'], $preview) }}</div>
                        <div class="flex justify-end"><x-secondary-button type="submit">Save template</x-secondary-button></div>
                    </form>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
