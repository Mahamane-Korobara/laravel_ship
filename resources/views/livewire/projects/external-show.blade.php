<div class="space-y-6">
    <a href="{{ route('projects.index') }}" wire:navigate class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white transition">
        <x-icon name="lucide-arrow-left" class="h-4 w-4" />
        Retour aux projets
    </a>

    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-start gap-4">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-600/15 text-indigo-300">
                <x-icon name="lucide-folder-git-2" class="h-6 w-6" />
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-white">{{ $project }}</h1>
                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium bg-slate-700/40 text-slate-300 border-slate-600/30">
                        Externe
                    </span>
                </div>
                <div class="mt-1.5 flex flex-wrap items-center gap-3 text-sm text-slate-400">
                    <span class="inline-flex items-center gap-1.5">
                        <x-icon name="lucide-server" class="h-3.5 w-3.5" />
                        {{ $server->name }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <x-icon name="lucide-globe" class="h-3.5 w-3.5" />
                        {{ $server->masked_ip }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.card class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
            <div class="text-xs text-slate-400">Taille</div>
            <div class="mt-3 text-xl font-semibold text-white">{{ $info['size'] ?? '—' }}</div>
        </x-ui.card>
        <x-ui.card class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
            <div class="text-xs text-slate-400">Dernière modif</div>
            <div class="mt-3 text-sm font-semibold text-white">{{ $info['lastMod'] ?? '—' }}</div>
        </x-ui.card>
        <x-ui.card class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
            <div class="text-xs text-slate-400">Branche</div>
            <div class="mt-3 text-xl font-semibold text-white">{{ $info['branch'] ?? '—' }}</div>
        </x-ui.card>
        <x-ui.card class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
            <div class="text-xs text-slate-400">Commit</div>
            <div class="mt-3 text-xl font-semibold text-white">{{ $info['commit'] ?? '—' }}</div>
        </x-ui.card>
    </div>

    <x-ui.card class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <div class="text-xs text-slate-400">Chemin</div>
        <div class="mt-2 font-mono text-sm text-slate-100">{{ $info['basePath'] ?? $path }}</div>
    </x-ui.card>

    <x-ui.card class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <div class="text-xs text-slate-400">Remote Git</div>
        <div class="mt-2 font-mono text-sm text-slate-100">{{ $info['remote'] ?? '—' }}</div>
    </x-ui.card>
</div>
