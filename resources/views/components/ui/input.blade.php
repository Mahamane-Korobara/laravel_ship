@props([
    'type' => 'text',
])

<input type="{{ $type }}" {{ $attributes->merge(['class' => 'w-full rounded-md border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20']) }}>
