<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'LaravelShip') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        body { background:#020617; color:#e2e8f0; }
        .ship-scroll::-webkit-scrollbar{width:8px;height:8px}
        .ship-scroll::-webkit-scrollbar-thumb{background:#1f2937;border-radius:999px}
        .ship-scroll::-webkit-scrollbar-track{background:transparent}
    </style>
</head>
<body class="h-full antialiased">
<div
    x-data="{ collapsed: localStorage.getItem('ship_collapsed') === '1', mobileOpen: false }"
    x-init="$watch('collapsed', v => localStorage.setItem('ship_collapsed', v ? '1' : '0'))"
    class="min-h-screen bg-slate-950 text-slate-200"
>
    <div class="lg:hidden fixed top-0 left-0 right-0 h-14 bg-slate-950 border-b border-slate-800 z-50 flex items-center justify-between px-4">
        <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2">
            <span class="w-7 h-7 rounded-md bg-red-600 flex items-center justify-center text-white">
                <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 6v6l4 2"/><circle cx="12" cy="12" r="9"/></svg>
            </span>
            <span class="font-bold text-white">Laravel<span class="text-red-500">Ship</span></span>
        </a>
        <button @click="mobileOpen = !mobileOpen" class="text-slate-400 hover:text-white p-1">
            <svg x-show="!mobileOpen" viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
            <svg x-show="mobileOpen" viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
    </div>

    <template x-teleport="body">
        <div x-show="mobileOpen" style="display:none" class="lg:hidden fixed inset-0 z-50">
            <div class="absolute inset-0 bg-black/60" @click="mobileOpen=false"></div>
            <aside
                class="absolute top-0 left-0 bottom-0 w-[280px] bg-slate-950 border-r border-slate-800"
                x-show="mobileOpen"
                x-transition:enter="transition transform ease-out duration-300"
                x-transition:enter-start="-translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition transform ease-in duration-200"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="-translate-x-full"
            >
                <div class="flex flex-col h-full">
                    <div class="flex items-center gap-3 px-4 h-16 border-b border-slate-800">
                        <div class="w-8 h-8 rounded-md bg-red-600 flex items-center justify-center text-white">
                            <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 6v6l4 2"/><circle cx="12" cy="12" r="9"/></svg>
                        </div>
                        <span class="font-bold text-white text-lg tracking-tight">Laravel<span class="text-red-500">Ship</span></span>
                    </div>

                    <nav class="flex-1 px-3 py-4 space-y-0.5">
                        <a href="{{ route('dashboard') }}" wire:navigate @click="mobileOpen=false" class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium transition-colors {{ request()->routeIs('dashboard') ? 'bg-red-600/10 text-red-400' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800' }}">
                            <svg viewBox="0 0 24 24" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
                            <span>Dashboard</span>
                        </a>
                        <a href="{{ route('projects.import') }}" wire:navigate @click="mobileOpen=false" class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium transition-colors text-slate-400 hover:text-slate-200 hover:bg-slate-800">
                            <svg viewBox="0 0 24 24" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 11 3-3a2 2 0 0 1 3 0l1 1"/><path d="m12 13 3-3a2 2 0 0 1 3 0l1 1"/><path d="M4 20h4"/><path d="M6 18v4"/><path d="M14 20h6"/></svg>
                            <span>Projets</span>
                        </a>
                        <a href="{{ route('servers.index') }}" wire:navigate @click="mobileOpen=false" class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium transition-colors {{ request()->routeIs('servers.*') ? 'bg-red-600/10 text-red-400' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800' }}">
                            <svg viewBox="0 0 24 24" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="6" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><path d="M7 7h.01M7 17h.01"/></svg>
                            <span>Serveurs</span>
                        </a>
                        <a href="{{ route('dashboard') }}" wire:navigate @click="mobileOpen=false" class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium transition-colors text-slate-400 hover:text-slate-200 hover:bg-slate-800">
                            <svg viewBox="0 0 24 24" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3h12l3 5-3 5H6L3 8l3-5Z"/><path d="m8 14-2 7"/><path d="m16 14 2 7"/></svg>
                            <span>Déploiements</span>
                        </a>
                    </nav>

                    <div class="px-3 pb-4">
                        <a href="{{ route('projects.import') }}" wire:navigate @click="mobileOpen=false" class="flex items-center gap-3 px-3 py-2.5 rounded-md bg-red-600 hover:bg-red-500 text-white text-sm font-medium transition-colors">
                            <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5v14"/></svg>
                            <span>Nouveau projet</span>
                        </a>
                    </div>
                </div>
            </aside>
        </div>
    </template>

    <aside class="hidden lg:block fixed top-0 left-0 bottom-0 bg-slate-950 border-r border-slate-800 z-40 transition-all duration-300" :class="collapsed ? 'w-[72px]' : 'w-[240px]'">
        <div class="flex flex-col h-full">
            <div class="flex items-center px-4 h-16 border-b border-slate-800" :class="collapsed ? 'justify-center' : 'gap-3'">
                <div class="w-8 h-8 rounded-md bg-red-600 flex items-center justify-center text-white flex-shrink-0">
                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 6v6l4 2"/><circle cx="12" cy="12" r="9"/></svg>
                </div>
                <span x-show="!collapsed" x-transition.opacity class="font-bold text-white text-lg tracking-tight">Laravel<span class="text-red-500">Ship</span></span>
            </div>

            <nav class="flex-1 px-3 py-4 space-y-0.5">
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium transition-colors {{ request()->routeIs('dashboard') ? 'bg-red-600/10 text-red-400' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800' }}" :class="collapsed ? 'justify-center' : ''">
                    <svg viewBox="0 0 24 24" class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
                    <span x-show="!collapsed">Dashboard</span>
                </a>
                <a href="{{ route('projects.import') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium transition-colors text-slate-400 hover:text-slate-200 hover:bg-slate-800" :class="collapsed ? 'justify-center' : ''">
                    <svg viewBox="0 0 24 24" class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 11 3-3a2 2 0 0 1 3 0l1 1"/><path d="m12 13 3-3a2 2 0 0 1 3 0l1 1"/><path d="M4 20h4"/><path d="M6 18v4"/><path d="M14 20h6"/></svg>
                    <span x-show="!collapsed">Projets</span>
                </a>
                <a href="{{ route('servers.index') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium transition-colors {{ request()->routeIs('servers.*') ? 'bg-red-600/10 text-red-400' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800' }}" :class="collapsed ? 'justify-center' : ''">
                    <svg viewBox="0 0 24 24" class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="6" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><path d="M7 7h.01M7 17h.01"/></svg>
                    <span x-show="!collapsed">Serveurs</span>
                </a>
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium transition-colors text-slate-400 hover:text-slate-200 hover:bg-slate-800" :class="collapsed ? 'justify-center' : ''">
                    <svg viewBox="0 0 24 24" class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3h12l3 5-3 5H6L3 8l3-5Z"/><path d="m8 14-2 7"/><path d="m16 14 2 7"/></svg>
                    <span x-show="!collapsed">Déploiements</span>
                </a>
            </nav>

            <div class="px-3 pb-4 space-y-2">
                <a href="{{ route('projects.import') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-md bg-red-600 hover:bg-red-500 text-white text-sm font-medium transition-colors" :class="collapsed ? 'justify-center' : ''">
                    <svg viewBox="0 0 24 24" class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5v14"/></svg>
                    <span x-show="!collapsed">Nouveau projet</span>
                </a>

                <button @click="collapsed = !collapsed" class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-md text-slate-600 hover:text-slate-400 hover:bg-slate-800 transition-colors text-xs">
                    <svg x-show="collapsed" viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                    <svg x-show="!collapsed" viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
                    <span x-show="!collapsed">Réduire</span>
                </button>
            </div>
        </div>
    </aside>

    <main class="hidden lg:block min-h-screen transition-all duration-300" :class="collapsed ? 'ml-[72px]' : 'ml-[240px]'">
        <div class="p-6 lg:p-8 max-w-[1400px] mx-auto">
            @if (session('success'))
                <div class="mb-4 rounded-md border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded-md border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">{{ session('error') }}</div>
            @endif
            {{ $slot }}
        </div>
    </main>

    <div class="lg:hidden pt-14">
        <div class="p-4">
            @if (session('success'))
                <div class="mb-4 rounded-md border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded-md border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">{{ session('error') }}</div>
            @endif
            {{ $slot }}
        </div>
    </div>
</div>

@livewireScripts
</body>
</html>
