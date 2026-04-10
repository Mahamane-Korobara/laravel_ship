@props([
    'title' => 'terminal',
    'minHeight' => '320px',
    'maxHeight' => '520px',
    'stream' => null,
    'textClass' => null,
    'variant' => 'info',
    'compact' => false,
])

@php
$variantClasses = [
    'info' => 'text-slate-200',
    'success' => 'text-emerald-300',
    'error' => 'text-rose-300',
];
$resolvedText = $textClass ?: ($variantClasses[$variant] ?? $variantClasses['info']);
$bodyClasses = $compact
    ? 'p-3 sm:p-4 text-[11px] sm:text-xs leading-4'
    : 'p-4 text-xs leading-5';
@endphp

<section {{ $attributes->merge(['class' => 'rounded-lg border border-[#1f2a44] bg-[#0b1020]/80 overflow-hidden']) }}>
    <div class="flex items-center justify-between border-b border-[#1f2a44] px-4 py-3">
        <div class="flex items-center gap-3">
            <span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span>
            <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
            <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
            <span class="ml-3 text-xs text-[#8ea2c5]">{{ $title }}</span>
        </div>
    </div>

    <div
        x-data="{
            scrollToBottom() { this.$refs.body.scrollTop = this.$refs.body.scrollHeight; },
            init() {
                const body = this.$refs.body;
                this.scrollToBottom();
                const observer = new MutationObserver(() => this.scrollToBottom());
                observer.observe(body, { childList: true, subtree: true, characterData: true });
            }
        }"
        x-init="init()">
        <div
            x-ref="body"
            @if($stream) wire:stream="{{ $stream }}" @endif
            class="ship-scroll overflow-y-auto overflow-x-hidden font-mono {{ $resolvedText }} {{ $bodyClasses }} whitespace-pre-wrap break-words"
            :style="'min-height: ' + ($minHeight || '320px') + '; max-height: ' + ($maxHeight || '520px') + ';'">
            {{ $slot }}
        </div>
    </div>
</section>
