<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-white border border-red-200 rounded-lg text-sm font-semibold text-red-700 shadow-sm hover:bg-red-50 hover:border-red-300 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-40 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
