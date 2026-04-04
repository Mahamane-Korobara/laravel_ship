<div class="space-y-6">
    <a href="{{ route('deployments.show', $deployment) }}" wire:navigate class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white transition">
        <x-icon name="lucide-arrow-left" class="h-4 w-4" />
        Retour au déploiement
    </a>

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#182645] text-cyan-200">
                <x-icon name="lucide-terminal" class="h-5 w-5" />
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-white">{{ $deployment->project->name }} — {{ $deployment->release_name }}</h1>
                <p class="mt-1 text-xs text-slate-400 inline-flex items-center gap-2">
                    <x-icon name="lucide-git-branch" class="h-3 w-3" />
                    {{ $deployment->git_branch }}
                </p>
            </div>
        </div>
    </div>

    <x-ui.terminal :title="$deployment->project->name . ' — bash'" minHeight="240px" maxHeight="70vh">
        {{ $deployment->log ?: 'Aucun log disponible.' }}
    </x-ui.terminal>
</div>
