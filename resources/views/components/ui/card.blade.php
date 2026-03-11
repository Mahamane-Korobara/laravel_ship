@props([
    'tone' => 'dark',
])

@php
    $tones = [
        'dark' => 'bg-slate-900 border border-slate-800 text-slate-100',
        'light' => 'bg-white border border-slate-200 text-slate-900',
    ];
    $toneClass = $tones[$tone] ?? $tones['dark'];
    $href = $attributes->get('href');
@endphp

@if ($href)
    <a {{ $attributes->merge(['class' => 'rounded-md p-4 '.$toneClass]) }}>
        {{ $slot }}
    </a>
@else
    <div {{ $attributes->merge(['class' => 'rounded-md p-4 '.$toneClass]) }}>
        {{ $slot }}
    </div>
@endif
