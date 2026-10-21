<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-brand-900 border border-transparent rounded-lg text-sm font-semibold text-white shadow-sm hover:bg-brand-800 focus:bg-brand-800 active:bg-brand-950 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
