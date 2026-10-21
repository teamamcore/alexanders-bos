<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Administration · Terminology <span class="text-sm text-gray-400">(SOW §3.13)</span></h2>
            @include('ops.admin.partials.tabs')
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if (session('status'))
                <div class="rounded-md bg-green-50 text-green-800 px-4 py-2 text-sm">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('admin.terminology.update') }}" class="bg-white shadow-sm rounded-lg overflow-hidden">
                @csrf
                <div class="px-6 py-4 border-b border-gray-100">
                    <div class="font-semibold text-gray-800">What {{ $tenant->name }} calls things</div>
                    <div class="text-sm text-gray-500">Leave a field blank to use the platform default. Changes apply across menus, pages and documents immediately.</div>
                </div>
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-6 py-3">Concept</th>
                            <th class="px-6 py-3">Default</th>
                            <th class="px-6 py-3">Your term</th>
                            <th class="px-6 py-3">Default abbr.</th>
                            <th class="px-6 py-3">Your abbr.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($defaults as $key => $default)
                            <tr>
                                <td class="px-6 py-3 font-mono text-xs text-gray-500">{{ $key }}</td>
                                <td class="px-6 py-3 text-gray-700">{{ $default }}</td>
                                <td class="px-6 py-3">
                                    <x-text-input name="terminology[{{ $key }}]" value="{{ old("terminology.$key", $terminology[$key] ?? '') }}" placeholder="{{ $default }}" class="text-sm w-48" maxlength="40" />
                                </td>
                                <td class="px-6 py-3 text-gray-700">{{ $defaultAbbr[$key] ?? '—' }}</td>
                                <td class="px-6 py-3">
                                    <x-text-input name="abbreviations[{{ $key }}]" value="{{ old("abbreviations.$key", $abbreviations[$key] ?? '') }}" placeholder="{{ $defaultAbbr[$key] ?? '' }}" class="text-sm w-24" maxlength="10" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
                    <div class="text-xs text-gray-400">Stored in the client layer (tenant settings) — travels with your export.</div>
                    <x-primary-button>Save terminology</x-primary-button>
                </div>
            </form>

            <div class="bg-white shadow-sm rounded-lg p-6 text-sm text-gray-600">
                <div class="font-semibold text-gray-800 mb-2">Preview</div>
                <p>Menu: <span class="font-medium text-gray-900">{{ term('job', true) }}</span> · <span class="font-medium text-gray-900">{{ term('schedule') }}</span> · <span class="font-medium text-gray-900">{{ term('proposal', true) }}</span> · <span class="font-medium text-gray-900">{{ term('invoice', true) }}</span></p>
                <p class="mt-1">Numbering: <span class="font-mono text-gray-900">{{ abbr('job') }}-1001</span>, <span class="font-mono text-gray-900">{{ abbr('work_order') }}-1001-1</span>, <span class="font-mono text-gray-900">{{ abbr('invoice') }}-0001</span></p>
            </div>
        </div>
    </div>
</x-app-layout>
