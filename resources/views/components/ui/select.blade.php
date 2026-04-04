@props([
    'options' => [],
    'placeholder' => 'Sélectionner',
    'icon' => null,
    'size' => 'md',
    'value' => null,
    'name' => null,
    'disabled' => false,
])

@php
    $normalized = [];

    if (is_array($options)) {
        $isAssoc = array_keys($options) !== range(0, count($options) - 1);
        if ($isAssoc) {
            foreach ($options as $val => $label) {
                $normalized[] = ['value' => (string) $val, 'label' => (string) $label];
            }
        } else {
            foreach ($options as $opt) {
                if (is_array($opt) && isset($opt['value'], $opt['label'])) {
                    $normalized[] = ['value' => (string) $opt['value'], 'label' => (string) $opt['label']];
                }
            }
        }
    }

    $sizeClasses = match ($size) {
        'sm' => 'h-8 text-xs',
        'lg' => 'h-10 text-sm',
        default => 'h-9 text-sm',
    };

    $paddingLeft = $icon ? 'pl-9' : 'pl-3';
    $wireModel = $attributes->get('wire:model')
        ?? $attributes->get('wire:model.live')
        ?? $attributes->get('wire:model.defer')
        ?? $attributes->get('wire:model.lazy');

    $entangleModifier = '';
    if ($attributes->has('wire:model.live')) {
        $entangleModifier = '.live';
    } elseif ($attributes->has('wire:model.lazy')) {
        $entangleModifier = '.lazy';
    } elseif ($attributes->has('wire:model.defer')) {
        $entangleModifier = '.defer';
    }

    $selectedInit = $wireModel ? "\$wire.entangle('{$wireModel}'){$entangleModifier}" : '@js($value)';
@endphp

<div
    x-data="{
        open: false,
        selected: {!! $selectedInit !!},
        options: @js($normalized),
        label() {
            const found = this.options.find(o => o.value == this.selected);
            return found ? found.label : @js($placeholder);
        }
    }"
    class="relative"
    {{ $attributes->except(['wire:model','wire:model.live','wire:model.defer','wire:model.lazy']) }}
>
    <button
        type="button"
        @click="open = !open"
        @keydown.escape.prevent="open = false"
        @click.outside="open = false"
        :disabled="@js($disabled)"
        class="flex w-full items-center justify-between rounded-md border border-slate-700/70 bg-slate-950/90 text-slate-100 shadow-sm transition hover:border-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:ring-offset-2 focus:ring-offset-slate-950 disabled:cursor-not-allowed disabled:opacity-60 {{ $sizeClasses }} {{ $paddingLeft }} pr-9"
        :class="open ? 'border-indigo-500/60 ring-2 ring-indigo-500/30' : ''"
    >
        <span class="truncate" x-text="label()"></span>
        <svg class="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
    </button>

    @if ($icon)
        <x-icon name="{{ $icon }}" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
    @endif

    @if ($name)
        <input type="hidden" name="{{ $name }}" x-model="selected">
    @endif

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-120"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-1"
        class="absolute z-30 mt-2 w-full overflow-hidden rounded-md border border-slate-800 bg-slate-950 shadow-xl"
        style="display:none"
    >
        <div class="max-h-56 overflow-y-auto ship-scroll py-1">
            @foreach ($normalized as $opt)
                <button
                    type="button"
                    @click="selected = @js($opt['value']); open = false"
                    class="flex w-full items-center justify-between px-3 py-2 text-left text-sm text-slate-200 hover:bg-slate-900/70"
                    :class="selected == @js($opt['value']) ? 'bg-slate-900/80 text-white' : ''"
                >
                    <span class="truncate">{{ $opt['label'] }}</span>
                    <svg x-show="selected == @js($opt['value'])" viewBox="0 0 24 24" class="h-4 w-4 text-indigo-400" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
                </button>
            @endforeach
        </div>
    </div>
</div>
