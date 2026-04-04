@php
    $failedCount = $recentDeployments->where('status', 'failed')->count();
@endphp
<div x-data="{ ready:false }" x-init="requestAnimationFrame(()=>setTimeout(()=>ready=true,80))" class="space-y-6 max-w-6xl mx-auto">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between transition-all duration-500" :class="ready ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-white">Tableau de bord</h1>
            <p class="mt-1 text-xs sm:text-sm text-gray-400">Vue d'ensemble de votre plateforme</p>
        </div>
        <x-ui.button href="{{ route('projects.import') }}" wire:navigate variant="danger" size="lg">
            <x-icon name="lucide-plus" class="h-4 w-4" />
            Nouveau projet
        </x-ui.button>
    </div>

    <!-- Stats Cards -->
    <div class="grid gap-3 sm:gap-4 sm:grid-cols-2 lg:grid-cols-4 transition-all duration-500" style="transition-delay:80ms" :class="ready ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'">
        <x-ui.stat-card
            title="Projets"
            :value="$stats['total_projects'] ?? 0"
            icon="lucide-folder-git-2"
            iconClass="text-red-400"
        />

        <!-- Serveurs -->
        <x-ui.stat-card
            title="Serveurs"
            :value="$stats['total_servers'] ?? 0"
            icon="lucide-server"
            iconClass="text-amber-400"
        />

        <!-- Déploiements -->
        <x-ui.stat-card
            title="Déploiements"
            :value="$stats['total_deployments'] ?? 0"
            icon="lucide-rocket"
            iconClass="text-cyan-400"
        >
            <p class="mt-2 text-[11px] text-gray-400">réussis</p>
        </x-ui.stat-card>

        <!-- Échoués -->
        <x-ui.stat-card
            title="Échoués"
            :value="$failedCount"
            icon="lucide-x-circle"
            iconClass="text-red-400"
        />
    </div>

    <!-- Recent Deployments & Projects -->
    <div class="grid gap-4 lg:gap-6 lg:grid-cols-[2fr_1fr] transition-all duration-500" style="transition-delay:160ms" :class="ready ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'">
        <!-- Déploiements récents -->
        <section class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg sm:text-xl font-bold text-white">
                    <x-icon name="lucide-rocket" class="inline h-5 w-5 text-orange-400" />
                    <span class="ml-2">Déploiements récents</span>
                </h2>
                @if (Route::has('deployments.index'))
                    <a href="{{ route('deployments.index') }}" wire:navigate class="text-sm text-gray-400 hover:text-white transition inline-flex items-center gap-1">
                        Tout voir
                        <x-icon name="lucide-arrow-right" class="h-3 w-3" />
                    </a>
                @else
                    <span class="text-sm text-gray-500">Tout voir</span>
                @endif
            </div>
            <div class="space-y-2 sm:space-y-3">
                @forelse($recentDeployments as $deployment)
                @php
                $ok = $deployment->status === 'success';
                $statusBg = $ok ? 'bg-green-500/10 border-green-500/30' : 'bg-red-500/10 border-red-500/30';
                $statusText = $ok ? 'text-green-400' : 'text-red-400';
                $statusDot = $ok ? 'bg-green-400' : 'bg-red-400';
                @endphp
                <x-ui.list-card href="{{ route('deployments.show', $deployment) }}" wire:navigate class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0 flex items-start gap-3">
                        <div class="mt-0.5 flex h-8 w-8 items-center justify-center rounded-lg bg-slate-800 text-orange-400">
                            <x-icon name="lucide-rocket" class="h-4 w-4" />
                        </div>
                        <div class="min-w-0">
                            <p class="font-medium text-white truncate text-sm sm:text-base">{{ $deployment->project->name ?? 'Projet' }}</p>
                            <p class="mt-1 text-[11px] sm:text-xs text-gray-500 truncate inline-flex items-center gap-1.5">
                                <x-icon name="lucide-git-branch" class="h-3 w-3" />
                                {{ $deployment->git_branch }}
                                <span>·</span>
                                <x-icon name="lucide-git-commit" class="h-3 w-3" />
                                {{ $deployment->git_commit ? substr($deployment->git_commit,0,7) : '—' }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 sm:gap-3">
                        <span class="text-[11px] sm:text-xs text-gray-500">{{ $deployment->duration_human }}</span>
                        <x-ui.badge class="{{ $statusBg }}">
                            <span class="inline-block h-2 w-2 rounded-full {{ $statusDot }}"></span>
                            <span class="text-xs font-medium {{ $statusText }}">{{ $deployment->status_label }}</span>
                        </x-ui.badge>
                    </div>
                </x-ui.list-card>
                @empty
                <x-ui.list-card class="text-center text-gray-400">
                    Aucun déploiement récent
                </x-ui.list-card>
                @endforelse
            </div>
        </section>

        <!-- Projets -->
        <section class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg sm:text-xl font-bold text-white">
                    <x-icon name="lucide-folder-git-2" class="inline h-5 w-5 text-rose-400" />
                    <span class="ml-2">Projets</span>
                </h2>
                @if (Route::has('projects.index'))
                    <a href="{{ route('projects.index') }}" wire:navigate class="text-sm text-gray-400 hover:text-white transition inline-flex items-center gap-1">
                        Tout voir
                        <x-icon name="lucide-arrow-right" class="h-3 w-3" />
                    </a>
                @else
                    <span class="text-sm text-gray-500">Tout voir</span>
                @endif
            </div>
            <div class="space-y-2 sm:space-y-3">
                @forelse($projects->take(6) as $project)
                @php
                $ok = $project->status === 'deployed';
                $statusBg = $ok ? 'bg-green-500/10 border-green-500/30' : 'bg-red-500/10 border-red-500/30';
                $statusText = $ok ? 'text-green-400' : 'text-red-400';
                @endphp
                <x-ui.list-card href="{{ route('projects.show', $project) }}" wire:navigate class="block">
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-medium text-white truncate text-sm sm:text-base">{{ $project->name }}</p>
                        <x-ui.badge class="{{ $statusBg }}">
                            <span class="text-xs font-medium {{ $statusText }}">{{ $project->status_label }}</span>
                        </x-ui.badge>
                    </div>
                    <p class="mt-2 text-[11px] sm:text-xs text-gray-500 truncate inline-flex items-center gap-1.5">
                        <x-icon name="lucide-git-branch" class="h-3 w-3" />
                        {{ $project->github_branch }}
                        <span>·</span>
                        <x-icon name="lucide-globe" class="h-3 w-3" />
                        {{ $project->domain ?: 'sans domaine' }}
                    </p>
                </x-ui.list-card>
                @empty
                <x-ui.list-card class="text-center text-gray-400">
                    Aucun projet
                </x-ui.list-card>
                @endforelse
            </div>
        </section>
    </div>
</div>
