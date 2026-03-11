@php
    $projectsCount = $projects->count();
    $serversCount = $servers->count();
    $successCount = $recentDeployments->where('status', 'success')->count();
    $failedCount = $recentDeployments->where('status', 'failed')->count();
@endphp

<div x-data="{ ready: false }" x-init="requestAnimationFrame(() => setTimeout(() => ready = true, 80))">
    <div class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight sm:text-3xl">Dashboard</h1>
            <p class="mt-1 text-sm text-slate-400">Vue d'ensemble de votre infrastructure</p>
        </div>
        <a href="{{ route('projects.import') }}" wire:navigate class="inline-flex items-center gap-2 rounded bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-500">
            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5v14"/></svg>
            Nouveau projet
        </a>
    </div>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-md border border-slate-800 border-l-2 border-l-pink-500 bg-slate-900 p-4 transition-all duration-300" :class="ready ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'">
            <div class="mb-2 flex items-start justify-between">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Projets</p>
                <div class="flex h-8 w-8 items-center justify-center rounded-md bg-pink-500/10 text-pink-400">
                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 11 3-3a2 2 0 0 1 3 0l1 1"/><path d="m12 13 3-3a2 2 0 0 1 3 0l1 1"/><path d="M4 20h4"/><path d="M6 18v4"/><path d="M14 20h6"/></svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-white">{{ $projectsCount }}</p>
        </div>

        <div class="rounded-md border border-slate-800 border-l-2 border-l-amber-500 bg-slate-900 p-4 transition-all duration-300" :class="ready ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'" style="transition-delay:40ms">
            <div class="mb-2 flex items-start justify-between">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Serveurs</p>
                <div class="flex h-8 w-8 items-center justify-center rounded-md bg-amber-500/10 text-amber-400">
                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="6" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><path d="M7 7h.01M7 17h.01"/></svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-white">{{ $serversCount }}</p>
        </div>

        <div class="rounded-md border border-slate-800 border-l-2 border-l-emerald-500 bg-slate-900 p-4 transition-all duration-300" :class="ready ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'" style="transition-delay:80ms">
            <div class="mb-2 flex items-start justify-between">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Déploiements</p>
                <div class="flex h-8 w-8 items-center justify-center rounded-md bg-emerald-500/10 text-emerald-400">
                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-white">{{ $successCount }}</p>
            <p class="text-xs text-slate-500">réussis</p>
        </div>

        <div class="rounded-md border border-slate-800 border-l-2 border-l-rose-500 bg-slate-900 p-4 transition-all duration-300" :class="ready ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'" style="transition-delay:120ms">
            <div class="mb-2 flex items-start justify-between">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Échoués</p>
                <div class="flex h-8 w-8 items-center justify-center rounded-md bg-rose-500/10 text-rose-400">
                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-white">{{ $failedCount }}</p>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <section class="lg:col-span-2 space-y-2">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-xl font-semibold text-white">
                    <svg viewBox="0 0 24 24" class="h-5 w-5 text-orange-400" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 8-5-16-3 8H2"/></svg>
                    Déploiements récents
                </h2>
                <a href="{{ route('dashboard') }}#deployments" wire:navigate class="flex items-center gap-1 text-xs text-slate-400 transition hover:text-slate-200">Tout voir <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 5l7 7-7 7"/></svg></a>
            </div>

            @forelse ($recentDeployments->take(6) as $i => $deployment)
                @php
                    $project = $projects->firstWhere('id', $deployment->project_id);
                    $status = $deployment->status;
                    $statusLabel = $deployment->status_label;
                    $statusClass = match($status) {
                        'success' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                        'running' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                        'pending' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                        'failed' => 'bg-red-500/10 text-red-400 border-red-500/20',
                        'rolled_back' => 'bg-orange-500/10 text-orange-400 border-orange-500/20',
                        default => 'bg-slate-500/10 text-slate-300 border-slate-500/20',
                    };
                    $dotClass = match($status) {
                        'success' => 'bg-emerald-400',
                        'running' => 'bg-blue-400',
                        'pending' => 'bg-amber-400',
                        'failed' => 'bg-red-400',
                        'rolled_back' => 'bg-orange-400',
                        default => 'bg-slate-300',
                    };
                @endphp

                <a href="{{ route('deployments.show', $deployment) }}" wire:navigate class="flex items-center justify-between rounded-md border border-slate-800 bg-slate-900 p-3.5 transition-all duration-300 hover:border-slate-700" :class="ready ? 'opacity-100 translate-x-0' : 'opacity-0 -translate-x-3'" style="transition-delay: {{ 120 + ($i * 40) }}ms">
                    <div class="min-w-0 flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-slate-800 text-orange-400">
                            <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M4.5 16.5c-1.5 1.26-2 4-2 4s2.74-.5 4-2c.71-.84 1-2.2.9-3.4-1.2-.1-2.56.19-3.4.9Z"/><path d="m12 15-3-3a16.45 16.45 0 0 1 6.5-10.4L21 1l-.6 5.5A16.45 16.45 0 0 1 10 13l-3 3"/><path d="m9 12 3 3"/></svg>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-base font-medium text-slate-200">{{ $project->name ?? 'Projet inconnu' }}</p>
                            <p class="mt-0.5 flex items-center gap-1.5 truncate text-xs text-slate-500">
                                <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3v12"/><path d="M18 9v12"/><path d="m6 15 12-6"/><path d="m6 9 12 6"/></svg>
                                {{ $deployment->git_branch ?: 'main' }}
                                <span class="text-slate-600">•</span>
                                {{ $deployment->git_commit ? substr($deployment->git_commit, 0, 7) : '—' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        @if ($deployment->duration_seconds)
                            <span class="hidden text-xs text-slate-500 sm:block">{{ $deployment->duration_seconds }}s</span>
                        @endif
                        <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium {{ $statusClass }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $dotClass }}"></span>{{ $statusLabel }}
                        </span>
                    </div>
                </a>
            @empty
                <div class="rounded-md border border-slate-800 bg-slate-900 p-8 text-center">
                    <p class="text-sm text-slate-400">Aucun déploiement pour le moment</p>
                </div>
            @endforelse
        </section>

        <section class="space-y-2">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-xl font-semibold text-white">
                    <svg viewBox="0 0 24 24" class="h-5 w-5 text-rose-400" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 11 3-3a2 2 0 0 1 3 0l1 1"/><path d="m12 13 3-3a2 2 0 0 1 3 0l1 1"/><path d="M4 20h4"/><path d="M6 18v4"/><path d="M14 20h6"/></svg>
                    Projets
                </h2>
                <a href="{{ route('projects.import') }}" wire:navigate class="flex items-center gap-1 text-xs text-slate-400 transition hover:text-slate-200">Tout voir <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 5l7 7-7 7"/></svg></a>
            </div>

            @forelse ($projects->take(5) as $i => $project)
                @php
                    $status = $project->status;
                    $statusLabel = $project->status_label;
                    $statusClass = match($status) {
                        'deployed' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                        'deploying' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                        'failed' => 'bg-red-500/10 text-red-400 border-red-500/20',
                        default => 'bg-slate-500/10 text-slate-300 border-slate-500/20',
                    };
                @endphp
                <a href="{{ route('projects.show', $project) }}" wire:navigate class="block rounded-md border border-slate-800 bg-slate-900 p-3.5 transition-all duration-300 hover:border-slate-700" :class="ready ? 'opacity-100 translate-x-0' : 'opacity-0 translate-x-3'" style="transition-delay: {{ 120 + ($i * 40) }}ms">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="truncate text-base font-medium text-slate-200">{{ $project->name }}</p>
                        <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium {{ $statusClass }}">
                            <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $statusLabel }}
                        </span>
                    </div>
                    <div class="flex items-center gap-3 text-xs text-slate-500">
                        <span class="inline-flex items-center gap-1">
                            <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3v12"/><path d="M18 9v12"/><path d="m6 15 12-6"/><path d="m6 9 12 6"/></svg>
                            {{ $project->github_branch ?: 'main' }}
                        </span>
                        @if ($project->domain)
                            <span class="inline-flex items-center gap-1 truncate">
                                <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10Z"/></svg>
                                {{ $project->domain }}
                            </span>
                        @endif
                    </div>
                </a>
            @empty
                <div class="rounded-md border border-slate-800 bg-slate-900 p-8 text-center">
                    <p class="text-sm text-slate-400">Aucun projet importé</p>
                </div>
            @endforelse
        </section>
    </div>
</div>
