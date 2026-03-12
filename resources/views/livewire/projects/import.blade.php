@php
    $repoCount = is_array($filteredRepos) ? count($filteredRepos) : 0;
@endphp

<div class="space-y-6">
    <a href="{{ route('projects.index') }}" wire:navigate class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white transition">
        <x-icon name="lucide-arrow-left" class="h-4 w-4" />
        Retour aux projets
    </a>

    <div>
        <h1 class="text-2xl font-bold text-white">Importer un projet</h1>
        <p class="text-sm text-slate-400">Sélectionnez un dépôt GitHub à déployer</p>
    </div>

    <x-ui.card class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-800/60 text-slate-200">
                    <x-icon name="lucide-github" class="h-5 w-5" />
                </div>
                <div>
                    <p class="text-sm font-semibold text-white">GitHub connecté</p>
                    <p class="text-xs text-slate-400">{{ $repoCount }} dépôts disponibles</p>
                </div>
            </div>
            <button wire:click="refreshRepos" class="inline-flex items-center gap-2 text-sm font-medium text-slate-300 hover:text-white">
                <x-icon name="lucide-refresh-cw" class="h-4 w-4" />
                Actualiser
            </button>
        </div>
    </x-ui.card>

    <div class="relative">
        <x-icon name="lucide-search" class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
        <input
            wire:model.live.debounce.300ms="search"
            placeholder="Rechercher un dépôt..."
            class="w-full rounded-xl border border-slate-800 bg-slate-900/60 pl-9 pr-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500/50"/>
    </div>

    @if ($error)
        <x-ui.card class="rounded-2xl border border-rose-500/40 bg-rose-500/10 p-4 text-sm text-rose-300">
            {{ $error }}
        </x-ui.card>
    @endif

    @if ($loading)
        <x-ui.card class="rounded-2xl border border-slate-800 bg-slate-900/60 p-8 text-center text-sm text-slate-400">
            Chargement des dépôts...
        </x-ui.card>
    @else
        <div class="space-y-3">
            @forelse ($filteredRepos as $repo)
                @php
                    $isImported = in_array($repo['full_name'], $importedRepos, true);
                    $stars = $repo['stargazers_count'] ?? 0;
                    $branch = $repo['default_branch'] ?? 'main';
                    $language = $repo['language'] ?? '—';
                @endphp
                <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-4">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600/15 text-indigo-300">
                                <x-icon name="lucide-folder-git-2" class="h-5 w-5" />
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-semibold text-white">{{ $repo['name'] }}</p>
                                    @if (!empty($repo['private']))
                                        <x-ui.badge variant="default">Privé</x-ui.badge>
                                    @endif
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-3 text-xs text-slate-400">
                                    <span class="inline-flex items-center gap-1">
                                        <x-icon name="lucide-git-branch" class="h-3.5 w-3.5" />
                                        {{ $branch }}
                                    </span>
                                    <span class="inline-flex items-center gap-1">
                                        <x-icon name="lucide-star" class="h-3.5 w-3.5" />
                                        {{ $stars }}
                                    </span>
                                    <span class="inline-flex items-center gap-1">
                                        <x-icon name="lucide-code" class="h-3.5 w-3.5" />
                                        {{ $language }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            @if ($isImported)
                                <x-ui.badge variant="success">Importé</x-ui.badge>
                            @else
                                <x-ui.button type="button" wire:click="importRepo('{{ $repo['full_name'] }}')" variant="secondary" size="sm" class="border border-slate-700/60 bg-slate-900/60 text-slate-200 hover:bg-slate-800">
                                    <x-icon name="lucide-arrow-right" class="h-4 w-4" />
                                    Importer
                                </x-ui.button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <x-ui.card class="rounded-2xl border border-slate-800 bg-slate-900/60 p-8 text-center text-sm text-slate-500">
                    Aucun dépôt trouvé.
                </x-ui.card>
            @endforelse
        </div>
    @endif
</div>
