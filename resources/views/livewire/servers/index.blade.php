<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Serveurs</h1>
            <p class="text-sm text-slate-400">Gérez vos VPS et leur configuration</p>
        </div>
        <x-ui.button href="{{ route('servers.create') }}" wire:navigate variant="danger" size="sm">
            <x-icon name="lucide-plus" class="h-4 w-4" />
            Ajouter un VPS
        </x-ui.button>
    </div>

    @if ($servers->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-2xl border border-slate-800 bg-slate-900/50 p-10 text-center">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-800/60 text-slate-300">
                <x-icon name="lucide-server" class="h-6 w-6" />
            </div>
            <h2 class="mt-4 text-base font-semibold text-white">Aucun serveur configuré</h2>
            <p class="mt-2 max-w-md text-sm text-slate-400">Ajoutez votre premier VPS pour commencer à déployer vos projets Laravel.</p>
            <x-ui.button href="{{ route('servers.create') }}" wire:navigate variant="primary" size="sm" class="mt-5">
                Ajouter un serveur
            </x-ui.button>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($servers as $server)
                @php
                    $statusClass = match ($server->status) {
                        'active' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
                        'inactive' => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
                        'error' => 'bg-rose-500/15 text-rose-300 border-rose-500/30',
                        default => 'bg-slate-700/40 text-slate-300 border-slate-600/30',
                    };
                @endphp
                <a href="{{ route('servers.show', $server) }}" wire:navigate class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5 transition hover:bg-slate-900/80">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-600/15 text-indigo-300">
                            <x-icon name="lucide-server" class="h-5 w-5" />
                        </div>
                        <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-medium {{ $statusClass }}">
                            {{ ucfirst($server->status) }}
                        </span>
                    </div>

                    <div class="mt-4">
                        <h3 class="text-base font-semibold text-white">{{ $server->name }}</h3>
                        <p class="mt-1 text-xs text-slate-400">{{ $server->masked_ip }}</p>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 text-xs text-slate-400">
                        <div class="flex items-center gap-2">
                            <x-icon name="lucide-globe" class="h-3.5 w-3.5" />
                            {{ $server->masked_ip }}
                        </div>
                        <div class="flex items-center gap-2">
                            <x-icon name="lucide-server" class="h-3.5 w-3.5" />
                            {{ $server->ssh_user }}:{{ $server->ssh_port }}
                        </div>
                        <div class="flex items-center gap-2">
                            <x-icon name="lucide-file-text" class="h-3.5 w-3.5" />
                            PHP {{ $server->php_version }}
                        </div>
                        <div class="flex items-center gap-2">
                            <x-icon name="lucide-folder-git-2" class="h-3.5 w-3.5" />
                            {{ $server->projects_count }} projet{{ $server->projects_count > 1 ? 's' : '' }}
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
