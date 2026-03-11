@props([
    'title',
    'value',
    'icon' => null,
    'iconClass' => 'text-slate-400',
])

<x-ui.card {{ $attributes->merge(['class' => 'rounded-xl border border-gray-800 bg-gray-900 p-4 sm:p-5 hover:border-gray-700 transition']) }}>
    <div class="flex items-center justify-between mb-3">
        <span class="text-[10px] sm:text-xs font-semibold uppercase tracking-wider text-gray-400">{{ $title }}</span>
        @if ($icon)
            <div class="rounded-lg bg-gray-800/50 p-1.5 sm:p-2">
                <x-icon :name="$icon" class="w-4 h-4 {{ $iconClass }}" />
            </div>
        @endif
    </div>
    <div>
        <p class="text-2xl sm:text-3xl font-bold text-white leading-tight">{{ $value }}</p>
        {{ $slot }}
    </div>
</x-ui.card>
