@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-lg border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm focus:border-brand-500 focus:ring-brand-500 disabled:bg-slate-50 disabled:text-slate-400']) }}>
