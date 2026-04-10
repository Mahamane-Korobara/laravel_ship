@props([
    'title' => 'Confirmation',
])

<div class="fixed inset-0 z-50 flex items-center justify-center px-4">
    <div class="absolute inset-0 bg-black/60"></div>
    <div class="relative w-full max-w-2xl rounded-2xl border border-[#1f2a44] bg-[#0b1020] p-6 shadow-xl">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-white">{{ $title }}</h3>
                @isset($description)
                    <p class="mt-1 text-sm text-slate-400">{{ $description }}</p>
                @endisset
            </div>
        </div>

        <div class="mt-4 space-y-3 text-sm text-slate-300">
            {{ $slot }}
        </div>

        @isset($actions)
            <div class="mt-6 flex flex-wrap justify-end gap-2">
                {{ $actions }}
            </div>
        @endisset
    </div>
</div>
