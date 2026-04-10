@props([
    'size' => 'md',
])

@php
$sizes = [
    'sm' => 'h-3.5 w-3.5',
    'md' => 'h-4 w-4',
    'lg' => 'h-5 w-5',
];
$sizeClass = $sizes[$size] ?? $sizes['md'];
@endphp

<svg {{ $attributes->merge(['class' => "animate-spin {$sizeClass} text-white/80"]) }} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
    <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
    <path class="opacity-90" fill="currentColor" d="M22 12a10 10 0 0 1-10 10v-3.2a6.8 6.8 0 0 0 6.8-6.8H22z"></path>
</svg>
