@php
    $statusClass = match ($server->status) {
        'active' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
        'inactive' => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
        'error' => 'bg-rose-500/15 text-rose-300 border-rose-500/30',
        default => 'bg-slate-700/40 text-slate-300 border-slate-600/30',
    };
    $statusLabel = match ($server->status) {
        'active' => 'Actif',
        'inactive' => 'Inactif',
        'error' => 'Erreur',
        default => 'Inconnu',
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
                        {{ $statusLabel }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-slate-400">{{ $server->masked_ip }}</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <x-ui.button type="button" wire:click="testConnection" variant="primary" wire:loading.attr="disabled" wire:target="testConnection">
                <span wire:loading.remove wire:target="testConnection">Tester Docker</span>
                <span wire:loading wire:target="testConnection" class="inline-flex items-center gap-2">
                    <x-ui.spinner size="sm" />
                    Test en cours...
                </span>
            </x-ui.button>
            <x-ui.button type="button" wire:click="delete" wire:confirm="Supprimer ce serveur ?" variant="danger" wire:loading.attr="disabled" wire:target="delete" class="bg-transparent border border-rose-500/50 text-rose-300 hover:bg-rose-500/10">
                <span wire:loading.remove wire:target="delete" class="inline-flex items-center gap-2">
                    <x-icon name="lucide-trash-2" class="h-4 w-4" />
                    Supprimer
                </span>
                <span wire:loading wire:target="delete" class="inline-flex items-center gap-2">
                    <x-ui.spinner size="sm" />
                    Suppression...
                </span>
            </x-ui.button>
        </div>
    </div>

    <div class="{{ $testResult ? '' : 'hidden' }}" wire:loading.class.remove="hidden" wire:target="testConnection">
        <x-ui.terminal title="Test SSH" minHeight="200px" maxHeight="360px" stream="testResult" variant="{{ $testSuccess ? 'success' : ($testResult ? 'error' : 'info') }}">
            {{ $testResult ?: '→ Test en cours…' }}
        </x-ui.terminal>
    </div>

    <div x-data="{ agentModalOpen: false }" @keydown.escape.window="agentModalOpen = false">
    <x-ui.card class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="text-sm font-semibold text-white">Agent LaravelShip</div>
                <div class="mt-1 text-xs text-slate-400">
                    @if ($server->agent_enabled)
                        Actif · {{ $server->agent_url ?? 'URL inconnue' }}
                    @else
                        Non installé
                    @endif
                </div>
                @if ($server->agent_last_seen_at)
                    <div class="mt-1 text-xs text-slate-500">Dernier signal: {{ $server->agent_last_seen_at->format('d/m/Y H:i') }}</div>
                @endif
            </div>
            <div class="flex items-center gap-2">
                @if (!$server->agent_enabled)
                    <x-ui.button type="button" variant="primary" @click="agentModalOpen = true" wire:loading.attr="disabled" wire:target="installAgent">
                        <span wire:loading.remove wire:target="installAgent">Installer l’agent</span>
                        <span wire:loading wire:target="installAgent" class="inline-flex items-center gap-2">
                            <x-ui.spinner size="sm" />
                            Installation...
                        </span>
                    </x-ui.button>
                @else
                    <x-ui.button type="button" wire:click="removeAgent" variant="danger" wire:loading.attr="disabled" wire:target="removeAgent" class="bg-transparent border border-rose-500/50 text-rose-300 hover:bg-rose-500/10">
                        <span wire:loading.remove wire:target="removeAgent">Supprimer l’agent</span>
                        <span wire:loading wire:target="removeAgent" class="inline-flex items-center gap-2">
                            <x-ui.spinner size="sm" />
                            Suppression...
                        </span>
                    </x-ui.button>
                @endif
            </div>
        </div>
        <div class="{{ $agentResult ? 'mt-3' : 'hidden' }}" wire:loading.class.remove="hidden" wire:target="installAgent,removeAgent">
            <x-ui.terminal title="Agent LaravelShip" minHeight="160px" maxHeight="280px" stream="agentResult" variant="{{ str_contains($agentResult ?? '', 'Erreur') ? 'error' : ($agentResult ? 'success' : 'info') }}">
                {{ $agentResult ?: ($removingAgent ? '→ Suppression en cours…' : '→ Installation en cours…') }}
            </x-ui.terminal>
        </div>
    </x-ui.card>
        <div x-show="agentModalOpen" x-cloak x-transition.opacity class="fixed inset-0 z-50">
            <x-ui.modal title="Installer l’agent LaravelShip">
                <p>
                    L’agent ajoute un exécuteur local sécurisé pour les déploiements et les commandes à distance.
                    Il tourne dans un conteneur Docker et expose une API protégée par token.
                </p>
                <div class="rounded-xl border border-[#1f2a44] bg-[#0b1426] p-3 text-xs text-slate-300">
                    <div class="font-semibold text-slate-100">Commandes exécutées :</div>
                    <div class="mt-2 space-y-1 font-mono">
                        <div>mkdir -p /opt/laravelship-agent</div>
                        <div>upload Dockerfile, router.php, agent.php, docker-compose.yml</div>
                        <div>docker compose up -d --build</div>
                    </div>
                    <div class="mt-2 text-[11px] text-slate-400">
                        Ports: 8081 (agent) · Volumes: /var/run/docker.sock, /var/www
                    </div>
                </div>
                <x-slot name="actions">
                    <x-ui.button type="button" variant="secondary" @click="agentModalOpen = false">
                        Annuler
                    </x-ui.button>
                    <x-ui.button type="button" variant="primary" wire:click="installAgent" wire:loading.attr="disabled" wire:target="installAgent" @click="agentModalOpen = false">
                        <span wire:loading.remove wire:target="installAgent">Continuer</span>
                        <span wire:loading wire:target="installAgent" class="inline-flex items-center gap-2">
                            <x-ui.spinner size="sm" />
                            Installation...
                        </span>
                    </x-ui.button>
                </x-slot>
            </x-ui.modal>
        </div>
    </div>

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
                {{ $server->ram_mb ? round($server->ram_mb / 1024, 1).' Go' : '—' }}
            </div>
        </x-ui.card>
        <x-ui.card class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>DISQUE</span>
                <span class="rounded-md bg-slate-500/10 p-2 text-slate-300">
                    <x-icon name="lucide-hard-drive" class="h-4 w-4" />
                </span>
            </div>
            <div class="mt-4 text-2xl font-semibold text-white">{{ $server->disk_gb ? $server->disk_gb.' Go' : '—' }}</div>
        </x-ui.card>
        <x-ui.card class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>Runtime</span>
                <span class="rounded-md bg-emerald-500/10 p-2 text-emerald-400">
                    <x-icon name="lucide-server" class="h-4 w-4" />
                </span>
            </div>
            <div class="mt-4 text-2xl font-semibold text-white">Docker</div>
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
                <span>Runtime</span>
                <span class="text-slate-100">Docker</span>
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
            Conteneurs actifs sur le VPS ({{ count($remoteContainers) }})
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 text-sm text-slate-400">
            @if (count($remoteContainers) === 0)
                Aucun conteneur détecté.
            @else
                <ul class="grid gap-2 sm:grid-cols-2">
                    @foreach ($remoteContainers as $remoteContainer)
                        <li class="flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900/50 px-3 py-2 text-slate-200">
                            <x-icon name="lucide-server" class="h-4 w-4 text-slate-400" />
                            {{ $remoteContainer }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
</div>
