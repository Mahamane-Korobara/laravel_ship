@php
    $latest = $deployments->first();
    $repoUrl = 'https://github.com/' . $project->github_repo;
    $statusVariant = match ($project->status) {
        'deployed' => 'success',
        'deploying' => 'info',
        'failed' => 'danger',
        'idle' => 'default',
        default => 'default',
    };
    $statusDot = match ($project->status) {
        'deployed' => 'bg-emerald-400',
        'deploying' => 'bg-blue-400',
        'failed' => 'bg-red-400',
        'idle' => 'bg-slate-400',
        default => 'bg-slate-400',
    };
@endphp
<div class="space-y-6">
    <a href="{{ route('dashboard') }}" wire:navigate class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white transition">
        <x-icon name="lucide-arrow-left" class="h-4 w-4" />
        Retour aux projets
    </a>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-start gap-4">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl border border-slate-800 bg-slate-900/70 shadow-inner">
                <x-icon name="lucide-rocket" class="h-6 w-6 text-blue-300" />
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-white md:text-3xl">{{ $project->name }}</h1>
                    <x-ui.badge variant="{{ $statusVariant }}">
                        <span class="h-2 w-2 rounded-full {{ $statusDot }}"></span>
                        {{ $project->status_label }}
                    </x-ui.badge>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-slate-400">
                    <span class="inline-flex items-center gap-1.5">
                        <x-icon name="lucide-git-branch" class="h-4 w-4" />
                        {{ $project->github_branch }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <x-icon name="lucide-globe" class="h-4 w-4" />
                        {{ $project->domain ?: 'sans domaine' }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <x-icon name="lucide-server" class="h-4 w-4" />
                        {{ $project->server?->name ?? 'Serveur inconnu' }}
                    </span>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($project->url)
                <x-ui.button href="{{ $project->url }}" target="_blank" variant="secondary" size="sm" class="border border-slate-700/60 bg-slate-900/60 text-slate-200 hover:bg-slate-800">
                    <x-icon name="lucide-globe" class="h-4 w-4" />
                    Visiter
                </x-ui.button>
            @endif
            <x-ui.button href="{{ route('projects.deploy', $project) }}" wire:navigate variant="secondary" size="sm" class="bg-indigo-600 text-white hover:bg-indigo-500">
                <x-icon name="lucide-rocket" class="h-4 w-4" />
                Déployer
            </x-ui.button>
        </div>
    </div>

    <nav class="flex flex-wrap gap-2 text-sm">
        <a href="#overview" class="rounded-lg border border-slate-800 bg-slate-900/70 px-3 py-1.5 text-white">Vue d'ensemble</a>
        <a href="#deployments" class="rounded-lg border border-slate-800 px-3 py-1.5 text-slate-400 hover:text-white hover:bg-slate-900/60">Déploiements</a>
        <a href="#env" class="rounded-lg border border-slate-800 px-3 py-1.5 text-slate-400 hover:text-white hover:bg-slate-900/60">Variables .env</a>
        <a href="{{ route('projects.settings', $project) }}" wire:navigate class="rounded-lg border border-slate-800 px-3 py-1.5 text-slate-400 hover:text-white hover:bg-slate-900/60">Paramètres</a>
    </nav>

    <div id="overview" class="grid gap-6 lg:grid-cols-[1.1fr_1fr]">
        <x-ui.card class="rounded-2xl border border-slate-800/80 bg-slate-900/60 p-0 shadow-lg">
            <div class="border-b border-slate-800/70 px-5 py-4">
                <h2 class="text-lg font-semibold text-white">Configuration</h2>
            </div>
            <div class="divide-y divide-slate-800/60 px-5 py-2 text-sm text-slate-400">
                <div class="flex items-center justify-between gap-4 py-3">
                    <span>Repository</span>
                    <span class="font-mono text-slate-100">{{ $project->github_repo }}</span>
                </div>
                <div class="flex items-center justify-between gap-4 py-3">
                    <span>Branche</span>
                    <span class="font-semibold text-slate-100">{{ $project->github_branch }}</span>
                </div>
                <div class="flex items-center justify-between gap-4 py-3">
                    <span>Framework</span>
                    <span class="font-semibold text-slate-100">Laravel 11</span>
                </div>
                <div class="flex items-center justify-between gap-4 py-3">
                    <span>PHP</span>
                    <span class="font-semibold text-slate-100">{{ $project->php_version }}</span>
                </div>
                <div class="flex items-center justify-between gap-4 py-3">
                    <span>Domaine</span>
                    <span class="font-mono text-slate-100">{{ $project->domain ?: 'sans domaine' }}</span>
                </div>
                <div class="flex items-center justify-between gap-4 py-3">
                    <span>Serveur</span>
                    <span class="font-semibold text-slate-100">{{ $project->server?->name ?? 'Serveur inconnu' }}</span>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card class="rounded-2xl border border-slate-800/80 bg-slate-900/60 p-0 shadow-lg">
            <div class="border-b border-slate-800/70 px-5 py-4">
                <h2 class="text-lg font-semibold text-white">Options de déploiement</h2>
            </div>
            <div class="divide-y divide-slate-800/60 px-5 py-2 text-sm text-slate-400">
                <div class="flex items-center justify-between gap-4 py-3">
                    <span>Migrations</span>
                    <x-icon name="lucide-check-circle" class="h-4 w-4 {{ $project->run_migrations ? 'text-emerald-400' : 'text-slate-600' }}" />
                </div>
                <div class="flex items-center justify-between gap-4 py-3">
                    <span>Seeders</span>
                    <x-icon name="lucide-check-circle" class="h-4 w-4 {{ $project->run_seeders ? 'text-emerald-400' : 'text-slate-600' }}" />
                </div>
                <div class="flex items-center justify-between gap-4 py-3">
                    <span>npm build</span>
                    <x-icon name="lucide-check-circle" class="h-4 w-4 {{ $project->run_npm_build ? 'text-emerald-400' : 'text-slate-600' }}" />
                </div>
                <div class="flex items-center justify-between gap-4 py-3">
                    <span>Queue Worker</span>
                    <x-icon name="lucide-check-circle" class="h-4 w-4 {{ $project->has_queue_worker ? 'text-emerald-400' : 'text-slate-600' }}" />
                </div>
            </div>
        </x-ui.card>
    </div>

    <section id="deployments" class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-white">Déploiements récents</h2>
            <span class="text-xs text-slate-400">Derniers 10</span>
        </div>
        <div class="space-y-3">
            @forelse ($deployments as $deployment)
                @php
                    $dot = match ($deployment->status) {
                        'success' => 'bg-emerald-400',
                        'running', 'pending' => 'bg-cyan-400',
                        'failed' => 'bg-rose-400',
                        default => 'bg-zinc-500',
                    };
                @endphp
                <x-ui.list-card href="{{ route('deployments.show', $deployment) }}" wire:navigate class="flex flex-col gap-3 rounded-xl border border-slate-800 bg-slate-900/60 p-4 hover:bg-slate-900">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="rounded-lg bg-orange-500/10 p-2">
                                <x-icon name="lucide-rocket" class="h-4 w-4 text-orange-400" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-white">{{ $deployment->release_name }}</p>
                                <p class="text-xs text-slate-400">{{ $deployment->git_branch }} · {{ $deployment->git_commit ? substr($deployment->git_commit, 0, 8) : 'no-commit' }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 text-xs text-slate-400">
                            <span>{{ $deployment->duration_human }}</span>
                            <x-ui.badge variant="default" class="border-slate-700/60">
                                <span class="h-2 w-2 rounded-full {{ $dot }}"></span>
                                {{ $deployment->status_label }}
                            </x-ui.badge>
                        </div>
                    </div>
                    <div class="text-xs text-slate-500">{{ $deployment->created_at?->diffForHumans() }}</div>
                </x-ui.list-card>
            @empty
                <x-ui.card class="rounded-xl border border-slate-800 bg-slate-900/60 p-6 text-center text-sm text-slate-400">
                    Aucun déploiement disponible.
                </x-ui.card>
            @endforelse
        </div>
    </section>

    <section id="env" class="rounded-xl border border-slate-800 bg-slate-900/50 p-5 text-sm text-slate-400">
        <p>Les variables d'environnement se gèrent dans l'onglet Paramètres.</p>
    </section>
</div>
