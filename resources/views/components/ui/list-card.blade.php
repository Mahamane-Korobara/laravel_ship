@props([
    'href' => null,
])

@if ($href)
    <x-ui.card {{ $attributes->merge(['class' => 'rounded-lg border border-gray-800 bg-gray-900 p-3 sm:p-4 hover:border-gray-700 transition', 'href' => $href]) }}>
        {{ $slot }}
    </x-ui.card>
@else
    <x-ui.card {{ $attributes->merge(['class' => 'rounded-lg border border-gray-800 bg-gray-900 p-3 sm:p-4 hover:border-gray-700 transition']) }}>
        {{ $slot }}
    </x-ui.card>
@endif
