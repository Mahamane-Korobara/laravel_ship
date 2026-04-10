@props([
    'rows' => 6,
])

<textarea rows="{{ $rows }}" {{ $attributes->merge(['class' => 'ship-scroll w-full rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/20']) }}></textarea>
