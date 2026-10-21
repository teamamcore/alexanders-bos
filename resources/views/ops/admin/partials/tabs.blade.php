<div class="flex gap-1 text-sm">
    @foreach (['admin.users' => 'Users', 'admin.terminology' => 'Terminology', 'admin.security' => 'Security'] as $route => $label)
        <a href="{{ route($route) }}"
           class="px-3 py-1.5 rounded-md {{ request()->routeIs($route) ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">{{ $label }}</a>
    @endforeach
</div>
