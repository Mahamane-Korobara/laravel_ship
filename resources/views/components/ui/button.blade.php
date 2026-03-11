@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-md font-semibold transition focus:outline-none focus:ring-2 focus:ring-slate-400/40 disabled:opacity-60 disabled:cursor-not-allowed';
    $variants = [
        'primary' => 'bg-emerald-600 text-white hover:bg-emerald-500',
        'secondary' => 'bg-slate-800 text-slate-100 hover:bg-slate-700',
        'ghost' => 'text-slate-300 hover:text-white hover:bg-slate-800/60',
        'danger' => 'bg-red-600 text-white hover:bg-red-500',
    ];
    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-5 py-2.5 text-sm',
    ];
    $variantClass = $variants[$variant] ?? $variants['primary'];
    $sizeClass = $sizes[$size] ?? $sizes['md'];
    $classes = $base.' '.$variantClass.' '.$sizeClass;
    $href = $attributes->get('href');
@endphp

@if ($href)
    <a {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
