@props([
    'variant' => 'default',
])

@php
    $variants = [
        'default' => 'bg-slate-500/10 text-slate-300 border-slate-500/20',
        'success' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
        'warning' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
        'danger' => 'bg-red-500/10 text-red-400 border-red-500/20',
        'info' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
        'brand' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
    ];
    $variantClass = $variants[$variant] ?? $variants['default'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium '.$variantClass]) }}>
    {{ $slot }}
</span>
