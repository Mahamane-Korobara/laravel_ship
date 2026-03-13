@php
    $statusStyles = [
        'deployed' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
        'deploying' => 'bg-blue-500/15 text-blue-300 border-blue-500/30',
        'failed' => 'bg-rose-500/15 text-rose-300 border-rose-500/30',
        'idle' => 'bg-slate-700/40 text-slate-300 border-slate-600/30',
    ];
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Projets</h1>
            <p class="text-sm text-slate-400">{{ $items->count() }} projets détectés</p>
        </div>
        <x-ui.button href="{{ route('projects.import') }}" wire:navigate variant="danger" size="sm">
            <x-icon name="lucide-plus" class="h-4 w-4" />
            Importer
        </x-ui.button>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="relative w-full sm:max-w-md">
            <x-icon name="lucide-search" class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Rechercher un projet..."
                class="w-full rounded-xl border border-slate-800 bg-slate-900/60 pl-9 pr-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500/50" />
        </div>

        <div class="flex items-center gap-2">
            <button wire:click="setView('grid')" class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-800 text-slate-400 hover:text-white hover:bg-slate-800/60 {{ $view === 'grid' ? 'bg-slate-800/80 text-white' : '' }}">
                <x-icon name="lucide-layout-grid" class="h-4 w-4" />
            </button>
            <button wire:click="setView('list')" class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-800 text-slate-400 hover:text-white hover:bg-slate-800/60 {{ $view === 'list' ? 'bg-slate-800/80 text-white' : '' }}">
                <x-icon name="lucide-list" class="h-4 w-4" />
            </button>
        </div>
    </div>

    @if ($items->isEmpty())
        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-8 text-center text-sm text-slate-500">
            Aucun projet trouvé.
        </div>
    @else
        @if ($view === 'list')
            <div class="space-y-3">
                @foreach ($items as $item)
                    @php
                        $statusClass = $statusStyles[$item['status'] ?? 'idle'] ?? $statusStyles['idle'];
                        $isExternal = ($item['type'] ?? '') === 'external';
                    @endphp
                    <a href="{{ $item['route'] }}" wire:navigate class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900/60 p-4 transition hover:bg-slate-900/80 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-4">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-600/15 text-indigo-300">
                                <x-icon name="lucide-folder-git-2" class="h-5 w-5" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-white">{{ $item['name'] }}</p>
                                <p class="text-xs text-slate-400">
                                    {{ $item['github_repo'] ?? 'Projet détecté sur VPS' }}
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-400">
                            @if (!$isExternal)
                                <span class="inline-flex items-center gap-1.5">
                                    <x-icon name="lucide-git-branch" class="h-3.5 w-3.5" />
                                    {{ $item['branch'] }}
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <x-icon name="lucide-globe" class="h-3.5 w-3.5" />
                                    {{ $item['domain'] ?: 'sans domaine' }}
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <x-icon name="lucide-clock" class="h-3.5 w-3.5" />
                                    {{ $item['updated_at']?->format('d M Y, H:i') }}
                                </span>
                                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-medium {{ $statusClass }}">
                                    {{ $item['status_label'] }}
                                </span>
                                @if (!empty($item['webhook_pending']))
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-medium bg-amber-500/15 text-amber-300 border-amber-500/30">
                                        Nouveau commit
                                    </span>
                                @endif
                            @else
                                <span class="inline-flex items-center gap-1.5">
                                    <x-icon name="lucide-server" class="h-3.5 w-3.5" />
                                    {{ $item['server_name'] }}
                                </span>
                                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-medium bg-slate-700/40 text-slate-300 border-slate-600/30">
                                    Externe
                                </span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($items as $item)
                    @php
                        $statusClass = $statusStyles[$item['status'] ?? 'idle'] ?? $statusStyles['idle'];
                        $isExternal = ($item['type'] ?? '') === 'external';
                    @endphp
                    <a href="{{ $item['route'] }}" wire:navigate class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5 transition hover:bg-slate-900/80">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-600/15 text-indigo-300">
                                <x-icon name="lucide-folder-git-2" class="h-5 w-5" />
                            </div>
                            @if (!$isExternal)
                                <div class="flex flex-col items-end gap-2">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-medium {{ $statusClass }}">
                                        {{ $item['status_label'] }}
                                    </span>
                                    @if (!empty($item['webhook_pending']))
                                        <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-medium bg-amber-500/15 text-amber-300 border-amber-500/30">
                                            Nouveau commit
                                        </span>
                                    @endif
                                </div>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-medium bg-slate-700/40 text-slate-300 border-slate-600/30">
                                    Externe
                                </span>
                            @endif
                        </div>
                        <div class="mt-4">
                            <h3 class="text-base font-semibold text-white">{{ $item['name'] }}</h3>
                            <p class="mt-1 text-xs text-slate-400">{{ $item['github_repo'] ?? 'Projet détecté sur VPS' }}</p>
                        </div>
                        <div class="mt-4 space-y-2 text-xs text-slate-400">
                            @if (!$isExternal)
                                <div class="flex items-center gap-2">
                                    <x-icon name="lucide-git-branch" class="h-3.5 w-3.5" />
                                    {{ $item['branch'] }}
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-icon name="lucide-globe" class="h-3.5 w-3.5" />
                                    {{ $item['domain'] ?: 'sans domaine' }}
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-icon name="lucide-clock" class="h-3.5 w-3.5" />
                                    {{ $item['updated_at']?->format('d M Y, H:i') }}
                                </div>
                            @else
                                <div class="flex items-center gap-2">
                                    <x-icon name="lucide-server" class="h-3.5 w-3.5" />
                                    {{ $item['server_name'] }}
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-icon name="lucide-folder-git-2" class="h-3.5 w-3.5" />
                                    {{ $item['path'] }}
                                </div>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    @endif
</div>
