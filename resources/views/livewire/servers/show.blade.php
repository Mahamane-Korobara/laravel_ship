@php
    $statusClass = match ($server->status) {
        'active' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
        'inactive' => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
        'error' => 'bg-rose-500/15 text-rose-300 border-rose-500/30',
        default => 'bg-slate-700/40 text-slate-300 border-slate-600/30',
    };
@endphp

<div class="space-y-6">
    <a href="{{ route('servers.index') }}" wire:navigate class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white transition">
        <x-icon name="lucide-arrow-left" class="h-4 w-4" />
        Retour aux serveurs
    </a>

    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-start gap-4">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-600/15 text-indigo-300">
                <x-icon name="lucide-server" class="h-6 w-6" />
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-white">{{ $server->name }}</h1>
                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $statusClass }}">
                        {{ ucfirst($server->status) }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-slate-400">{{ $server->masked_ip }}</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <x-ui.button type="button" wire:click="testConnection" variant="primary">
                Test SSH
            </x-ui.button>
            <x-ui.button type="button" wire:click="delete" wire:confirm="Supprimer ce serveur ?" variant="danger" class="bg-transparent border border-rose-500/50 text-rose-300 hover:bg-rose-500/10">
                <x-icon name="lucide-trash-2" class="h-4 w-4" />
                Supprimer
            </x-ui.button>
        </div>
    </div>

    @if ($testResult)
        <pre class="rounded-xl border border-slate-800 bg-black/50 p-3 text-xs {{ $testSuccess ? 'text-emerald-300' : 'text-rose-300' }}">{{ $testResult }}</pre>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.card class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>VCPU</span>
                <span class="rounded-md bg-rose-500/10 p-2 text-rose-400">
                    <x-icon name="lucide-cpu" class="h-4 w-4" />
                </span>
            </div>
            <div class="mt-4 text-2xl font-semibold text-white">{{ $server->vcpu ?? '—' }}</div>
        </x-ui.card>
        <x-ui.card class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>RAM</span>
                <span class="rounded-md bg-purple-500/10 p-2 text-purple-400">
                    <x-icon name="lucide-memory" class="h-4 w-4" />
                </span>
            </div>
            <div class="mt-4 text-2xl font-semibold text-white">
                {{ $server->ram_mb ? round($server->ram_mb / 1024, 1).' GB' : '—' }}
            </div>
        </x-ui.card>
        <x-ui.card class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>DISQUE</span>
                <span class="rounded-md bg-slate-500/10 p-2 text-slate-300">
                    <x-icon name="lucide-hard-drive" class="h-4 w-4" />
                </span>
            </div>
            <div class="mt-4 text-2xl font-semibold text-white">{{ $server->disk_gb ? $server->disk_gb.' GB' : '—' }}</div>
        </x-ui.card>
        <x-ui.card class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>PHP</span>
                <span class="rounded-md bg-emerald-500/10 p-2 text-emerald-400">
                    <x-icon name="lucide-globe" class="h-4 w-4" />
                </span>
            </div>
            <div class="mt-4 text-2xl font-semibold text-white">{{ $server->php_version }}</div>
        </x-ui.card>
    </div>

    <x-ui.card class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <div class="flex items-center gap-2 text-sm font-semibold text-white">
            <x-icon name="lucide-shield" class="h-4 w-4 text-slate-400" />
            Détails de connexion
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 text-sm text-slate-400">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <span>Adresse IP</span>
                <span class="text-slate-100">{{ $server->masked_ip }}</span>
            </div>
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <span>Utilisateur SSH</span>
                <span class="text-slate-100">{{ $server->ssh_user }}</span>
            </div>
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <span>Port SSH</span>
                <span class="text-slate-100">{{ $server->ssh_port }}</span>
            </div>
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <span>Version PHP</span>
                <span class="text-slate-100">PHP {{ $server->php_version }}</span>
            </div>
        </div>
    </x-ui.card>

    <section class="space-y-3">
        <div class="flex items-center gap-2 text-sm font-semibold text-white">
            <x-icon name="lucide-folder-git-2" class="h-4 w-4 text-slate-400" />
            Projets hébergés dans LaravelShip ({{ $projects->count() }})
        </div>
        <div class="space-y-3">
            @forelse ($projects as $project)
                @php
                    $projectBadge = match ($project->status) {
                        'deployed' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
                        'deploying' => 'bg-blue-500/15 text-blue-300 border-blue-500/30',
                        'failed' => 'bg-rose-500/15 text-rose-300 border-rose-500/30',
                        default => 'bg-slate-700/40 text-slate-300 border-slate-600/30',
                    };
                @endphp
                <a href="{{ route('projects.show', $project) }}" wire:navigate class="flex flex-col gap-3 rounded-2xl border border-slate-800 bg-slate-900/60 p-4 transition hover:bg-slate-900/80 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-white">{{ $project->name }}</p>
                        <p class="text-xs text-slate-400">{{ $project->github_repo }}</p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-medium {{ $projectBadge }}">
                        {{ $project->status_label }}
                    </span>
                </a>
            @empty
                <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 text-sm text-slate-500">
                    Aucun projet hébergé sur ce serveur.
                </div>
            @endforelse
        </div>
    </section>

    <section class="space-y-3">
        <div class="flex items-center gap-2 text-sm font-semibold text-white">
            <x-icon name="lucide-server" class="h-4 w-4 text-slate-400" />
            Projets détectés sur le VPS ({{ count($remoteProjects) }})
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 text-sm text-slate-400">
            @if (count($remoteProjects) === 0)
                Aucun projet détecté dans `/var/www/projects`.
            @else
                <ul class="grid gap-2 sm:grid-cols-2">
                    @foreach ($remoteProjects as $remoteProject)
                        <li class="flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900/50 px-3 py-2 text-slate-200">
                            <x-icon name="lucide-folder-git-2" class="h-4 w-4 text-slate-400" />
                            {{ $remoteProject }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
</div>
