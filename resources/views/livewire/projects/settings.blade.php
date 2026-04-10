<div class="space-y-6">

    {{-- Back --}}
    <a href="{{ route('dashboard') }}" wire:navigate class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white transition">
        <x-icon name="lucide-arrow-left" class="h-4 w-4" />
        Retour aux projets
    </a>

    {{-- Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="flex items-start gap-3 sm:gap-4">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-600/80 shadow-lg sm:h-12 sm:w-12">
                <x-icon name="lucide-rocket" class="h-6 w-6 text-white" />
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-xl font-bold text-white sm:text-2xl md:text-3xl">{{ $project->name }}</h1>
                    @php
                    $statusBg = match ($project->status) {
                    'deployed' => 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30',
                    'deploying' => 'bg-blue-500/20 text-blue-400 border border-blue-500/30',
                    'failed' => 'bg-red-500/20 text-red-400 border border-red-500/30',
                    default => 'bg-slate-700/40 text-slate-400 border border-slate-600/30',
                    };
                    $statusDot = match ($project->status) {
                    'deployed' => 'bg-emerald-400',
                    'deploying' => 'bg-blue-400',
                    'failed' => 'bg-red-400',
                    default => 'bg-slate-500',
                    };
                    @endphp
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusBg }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $statusDot }}"></span>
                        {{ $project->status_label }}
                    </span>
                </div>
                <div class="mt-1.5 flex flex-wrap items-center gap-3 text-xs text-slate-400 sm:text-sm">
                    <span class="inline-flex items-center gap-1.5">
                        <x-icon name="lucide-git-branch" class="h-3.5 w-3.5" />
                        {{ $project->github_branch }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <x-icon name="lucide-globe" class="h-3.5 w-3.5" />
                        {{ $project->domain ?: 'sans domaine' }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <x-icon name="lucide-server" class="h-3.5 w-3.5" />
                        {{ $project->server?->name ?? 'Serveur inconnu' }}
                    </span>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($project->url)
            <a href="{{ $project->url }}" target="_blank"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-700 bg-transparent px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 transition sm:px-4">
                <x-icon name="lucide-external-link" class="h-4 w-4" />
                Visiter
            </a>
            @endif
            <a href="{{ route('projects.deploy', $project) }}" wire:navigate
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500 transition shadow-lg shadow-indigo-900/40 sm:px-4">
                <x-icon name="lucide-rocket" class="h-4 w-4" />
                Déployer
            </a>
        </div>
    </div>

    {{-- Tabs --}}
    <nav class="-mx-2 flex flex-nowrap gap-1 overflow-x-auto border-b border-slate-800 px-2 text-sm sm:text-base">
        <a href="{{ route('projects.show', $project) }}" wire:navigate
            class="whitespace-nowrap px-3 py-2 text-sm font-medium rounded-t-lg transition text-slate-400 hover:text-white hover:bg-slate-800/40 sm:px-4">
            Vue d'ensemble
        </a>
        <a href="{{ route('projects.show', ['project' => $project, 'tab' => 'deployments']) }}" wire:navigate
            class="whitespace-nowrap px-3 py-2 text-sm font-medium rounded-t-lg transition text-slate-400 hover:text-white hover:bg-slate-800/40 sm:px-4">
            Déploiements
        </a>
        <a href="{{ route('projects.show', ['project' => $project, 'tab' => 'env']) }}" wire:navigate
            class="whitespace-nowrap px-3 py-2 text-sm font-medium rounded-t-lg transition text-slate-400 hover:text-white hover:bg-slate-800/40 sm:px-4">
            Variables .env
        </a>
        <a href="{{ route('projects.settings', $project) }}" wire:navigate
            class="whitespace-nowrap px-3 py-2 text-sm font-medium rounded-t-lg transition border border-b-0 border-slate-700 bg-slate-800/60 text-white sm:px-4">
            Paramètres
        </a>
    </nav>

    {{-- Settings form --}}
    <div class="max-w-3xl rounded-xl border border-slate-800 bg-[#131525] p-4 sm:p-6 space-y-6">
        <h2 class="text-base font-semibold text-white">Paramètres du projet</h2>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-xs text-slate-400 mb-1.5">Nom du projet</label>
                <input wire:model.defer="name" type="text" value="{{ $name }}"
                    class="w-full rounded-lg border border-slate-700 bg-slate-900/80 px-3 py-2.5 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500/50" />
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1.5">Branche</label>
                <input wire:model.defer="github_branch" type="text" value="{{ $github_branch }}"
                    class="w-full rounded-lg border border-slate-700 bg-slate-900/80 px-3 py-2.5 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500/50" />
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1.5">Domaine</label>
                <input wire:model.defer="domain" type="text" value="{{ $domain }}"
                    class="w-full rounded-lg border border-slate-700 bg-slate-900/80 px-3 py-2.5 text-sm text-white font-mono focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500/50" />
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1.5">Serveur cible</label>
                <x-ui.select
                    wire:model.defer="server_id"
                    :options="$servers->pluck('name', 'id')->toArray()"
                />
            </div>
        </div>

        {{-- Toggle options --}}
        <div class="grid grid-cols-1 gap-3 xs:grid-cols-2 sm:grid-cols-4">
            @foreach ([
            ['run_migrations', 'Migrations'],
            ['run_seeders', 'Données d\'initialisation'],
            ['run_npm_build', 'Compilation NPM'],
            ['has_queue_worker', 'Worker de file'],
            ] as [$field, $label])
            <button
                wire:click="$toggle('{{ $field }}')"
                class="rounded-lg border px-4 py-2.5 text-sm font-medium transition
                    {{ $this->$field
                        ? 'border-indigo-500/60 bg-indigo-600/20 text-indigo-300'
                        : 'border-slate-700 bg-slate-900/50 text-slate-400 hover:border-slate-600 hover:text-slate-300' }}">
                {{ $label }}
            </button>
            @endforeach
        </div>

        <div>
            <button wire:click="save" wire:loading.attr="disabled" wire:target="save"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 transition shadow-lg shadow-indigo-900/40 disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                    <x-icon name="lucide-settings" class="h-4 w-4" />
                    Sauvegarder
                </span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                    <x-ui.spinner size="sm" />
                    Sauvegarde...
                </span>
            </button>
        </div>
    </div>

</div>
