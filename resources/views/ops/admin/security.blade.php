<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Administration · Security Overview <span class="text-sm text-gray-400">(SOW §3.13)</span></h2>
            @include('ops.admin.partials.tabs')
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-3 gap-6">

            <div class="lg:col-span-2 bg-white shadow-sm rounded-lg p-8">
                <style>
                    .security-doc h1 { font-size: 1.5rem; font-weight: 700; color: #111827; margin-bottom: .5rem; }
                    .security-doc h2 { font-size: 1.125rem; font-weight: 600; color: #111827; margin-top: 1.75rem; margin-bottom: .5rem; }
                    .security-doc h3 { font-size: 1rem; font-weight: 600; color: #1f2937; margin-top: 1rem; margin-bottom: .25rem; }
                    .security-doc p  { color: #374151; font-size: .9rem; line-height: 1.6; margin-bottom: .6rem; }
                    .security-doc ul { list-style: disc; padding-left: 1.4rem; color: #374151; font-size: .9rem; line-height: 1.6; margin-bottom: .6rem; }
                    .security-doc li { margin-bottom: .15rem; }
                    .security-doc code { background: #f3f4f6; padding: 0 .3rem; border-radius: .25rem; font-size: .8rem; }
                    .security-doc table { font-size: .85rem; border-collapse: collapse; margin: .5rem 0 1rem; }
                    .security-doc th, .security-doc td { border: 1px solid #e5e7eb; padding: .3rem .6rem; text-align: left; }
                    .security-doc th { background: #f9fafb; }
                </style>
                <div class="security-doc">{!! $overview !!}</div>
            </div>

            <div class="space-y-6">
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div class="font-semibold text-gray-800 mb-3">Live posture · {{ $tenant->name }}</div>
                    <dl class="space-y-2 text-sm">
                        @foreach ($posture as $label => $value)
                            <div class="flex justify-between"><dt class="text-gray-500">{{ $label }}</dt><dd class="font-semibold text-gray-900">{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                </div>

                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div class="font-semibold text-gray-800 mb-3">Service targets <span class="text-xs font-normal text-gray-400">· MSA §8 / Ex. B</span></div>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">Availability</dt><dd class="font-semibold text-gray-900">{{ $targets['availability_pct'] ?? '—' }}%</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Backups</dt><dd class="font-semibold text-gray-900">every {{ $targets['backup_interval_hours'] ?? '—' }}h · {{ $targets['backup_retention_days'] ?? '—' }}d kept</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Recovery point</dt><dd class="font-semibold text-gray-900">{{ $targets['rpo_hours'] ?? '—' }}h</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Restore target</dt><dd class="font-semibold text-gray-900">{{ $targets['restore_target_hours'] ?? '—' }}h</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Breach notice</dt><dd class="font-semibold text-gray-900">{{ $targets['breach_notice_hours'] ?? '—' }}h</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Critical patches</dt><dd class="font-semibold text-gray-900">{{ $targets['critical_patch_business_days'] ?? '—' }} business days</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Export formats</dt><dd class="font-semibold text-gray-900">{{ implode(', ', array_map('strtoupper', $targets['data_export_formats'] ?? [])) }}</dd></div>
                    </dl>
                </div>

                <div class="bg-white shadow-sm rounded-lg p-6 text-sm text-gray-600">
                    <div class="font-semibold text-gray-800 mb-2">Your controls</div>
                    <ul class="list-disc pl-5 space-y-1">
                        <li><a class="text-indigo-600" href="{{ route('admin.users') }}">Deactivate departed users</a> the same day (MSA §5.1).</li>
                        <li>Ask every user to add a passkey from <a class="text-indigo-600" href="{{ route('profile') }}">Profile</a>.</li>
                        <li>Never type card numbers, SSNs or health details into notes (MSA Ex. B.2).</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
