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
'idle' => 'bg-slate-500',
default => 'bg-slate-500',
};
$statusBg = match ($project->status) {
'deployed' => 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30',
'deploying' => 'bg-blue-500/20 text-blue-400 border border-blue-500/30',
'failed' => 'bg-red-500/20 text-red-400 border border-red-500/30',
default => 'bg-slate-700/40 text-slate-400 border border-slate-600/30',
};
$activeTab = request()->get('tab', 'overview');
@endphp

<div class="space-y-6">

    {{-- Back link --}}
    <a href="{{ route('projects.index') }}" wire:navigate class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white transition">
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
            <x-ui.button href="{{ $project->url }}" target="_blank" variant="default" size="sm">
                <x-icon name="lucide-external-link" class="h-4 w-4" />
                Visiter
            </x-ui.button>
            @endif
            <x-ui.button href="{{ route('projects.deploy', $project) }}" wire:navigate variant="indigo" size="sm">
                <x-icon name="lucide-rocket" class="h-4 w-4" />
                Déployer
            </x-ui.button>
        </div>
    </div>

    {{-- Tabs --}}
    <nav class="-mx-2 flex flex-nowrap gap-1 overflow-x-auto border-b border-slate-800 pb-0 px-2 text-sm sm:text-base">
        <a href="{{ route('projects.show', $project) }}"
            wire:navigate
            class="whitespace-nowrap px-3 py-2 text-sm font-medium rounded-t-lg transition sm:px-4
               {{ $activeTab === 'overview'
                   ? 'border border-b-0 border-slate-700 bg-slate-800/60 text-white'
                   : 'text-slate-400 hover:text-white hover:bg-slate-800/40' }}">
            Vue d'ensemble
        </a>
        <a href="{{ route('projects.show', ['project' => $project, 'tab' => 'deployments']) }}"
            wire:navigate
            class="whitespace-nowrap px-3 py-2 text-sm font-medium rounded-t-lg transition sm:px-4
               {{ $activeTab === 'deployments'
                   ? 'border border-b-0 border-slate-700 bg-slate-800/60 text-white'
                   : 'text-slate-400 hover:text-white hover:bg-slate-800/40' }}">
            Déploiements
        </a>
        <a href="{{ route('projects.show', ['project' => $project, 'tab' => 'env']) }}"
            wire:navigate
            class="whitespace-nowrap px-3 py-2 text-sm font-medium rounded-t-lg transition sm:px-4
               {{ $activeTab === 'env'
                   ? 'border border-b-0 border-slate-700 bg-slate-800/60 text-white'
                   : 'text-slate-400 hover:text-white hover:bg-slate-800/40' }}">
            Variables .env
        </a>
        <a href="{{ route('projects.settings', $project) }}"
            wire:navigate
            class="whitespace-nowrap px-3 py-2 text-sm font-medium rounded-t-lg transition text-slate-400 hover:text-white hover:bg-slate-800/40 sm:px-4">
            Paramètres
        </a>
    </nav>

    {{-- Tab: Vue d'ensemble --}}
    @if ($activeTab === 'overview')
    <div class="grid gap-4 lg:grid-cols-2 lg:gap-6">

        {{-- Configuration --}}
        <div class="rounded-xl border border-slate-800 bg-[#131525] overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-800">
                <h2 class="text-base font-semibold text-white">Configuration</h2>
            </div>
            <div class="divide-y divide-slate-800 text-xs sm:text-sm">
                @foreach ([
                ['Repository', $project->github_repo, true],
                ['Branche', $project->github_branch, false],
                ['PHP', $project->php_version, false],
                ['Domaine', $project->domain ?: 'sans domaine', true],
                ['Serveur', $project->server?->name ?? 'Serveur inconnu', false],
                ] as [$label, $value, $mono])
                <div class="flex flex-col gap-2 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                    <span class="text-slate-500">{{ $label }}</span>
                    <span class="{{ $mono ? 'font-mono' : 'font-medium' }} text-slate-100 break-all text-right sm:text-left">{{ $value }}</span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Options de déploiement --}}
        <div class="rounded-xl border border-slate-800 bg-[#131525] overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-800">
                <h2 class="text-base font-semibold text-white">Options de déploiement</h2>
            </div>
            <div class="divide-y divide-slate-800 text-xs sm:text-sm">
                @foreach ([
                ['Migrations', $project->run_migrations],
                ['Seeders', $project->run_seeders],
                ['npm build', $project->run_npm_build],
                ['Queue Worker', $project->has_queue_worker],
                ] as [$label, $enabled])
                <div class="flex items-center justify-between gap-4 px-5 py-3.5">
                    <span class="text-slate-400">{{ $label }}</span>
                    @if ($enabled)
                    <x-icon name="lucide-check-square" class="h-5 w-5 text-emerald-400" />
                    @else
                    <div class="h-5 w-5 rounded border border-slate-600"></div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Tab: Déploiements --}}
    @if ($activeTab === 'deployments')
    <div class="space-y-3">
        @forelse ($deployments as $deployment)
        @php
        $dot = match ($deployment->status) {
        'success' => 'bg-emerald-400',
        'running', 'pending' => 'bg-cyan-400',
        'failed' => 'bg-rose-400',
        default => 'bg-zinc-500',
        };
        $badgeBg = match ($deployment->status) {
        'success' => 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30',
        'running', 'pending' => 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/30',
        'failed' => 'bg-rose-500/20 text-rose-400 border border-rose-500/30',
        default => 'bg-slate-700/40 text-slate-400 border border-slate-600/30',
        };
        @endphp
        <a href="{{ route('deployments.show', $deployment) }}" wire:navigate
            class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-[#131525] px-4 py-4 hover:bg-slate-800/50 transition sm:px-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-3 sm:gap-4">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600/20 text-indigo-400">
                    <x-icon name="lucide-activity" class="h-4 w-4" />
                </div>
                <div>
                    <p class="text-sm font-semibold text-white">{{ $deployment->release_name }}</p>
                    <p class="text-xs text-slate-400 mt-0.5">
                        {{ $deployment->git_branch }} · {{ $deployment->git_commit ? substr($deployment->git_commit, 0, 8) : 'no-commit' }}
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3 lg:justify-end">
                <span class="text-xs text-slate-500">{{ $deployment->duration_human }}</span>
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeBg }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>
                    {{ $deployment->status_label }}
                </span>
            </div>
        </a>
        @empty
        <div class="rounded-xl border border-slate-800 bg-[#131525] p-8 text-center text-sm text-slate-500">
            Aucun déploiement disponible.
        </div>
        @endforelse
    </div>
    @endif

    {{-- Tab: Variables .env --}}
    @if ($activeTab === 'env')
    <livewire:projects.project-env :project="$project" />
    @endif

</div>