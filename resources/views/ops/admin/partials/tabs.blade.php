<div class="flex gap-1 text-sm">
    @foreach (['admin.users' => 'Users', 'admin.qualifications' => 'Qualifications', 'admin.reminder-types' => 'Reminders', 'admin.terminology' => 'Terminology', 'admin.security' => 'Security', 'admin.ai-usage' => 'AI usage'] as $route => $label)
        <a href="{{ route($route) }}"
           class="px-3 py-1.5 rounded-md {{ request()->routeIs($route) ? 'bg-brand-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }}</a>
    @endforeach
</div>
