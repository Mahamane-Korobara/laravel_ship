@php
    $statusVariant = match ($deployment->status) {
        'success' => 'success',
        'running', 'pending' => 'info',
        'failed' => 'danger',
        'rolled_back' => 'warning',
        default => 'default',
    };
    $statusDot = match ($deployment->status) {
        'success' => 'bg-emerald-400',
        'running', 'pending' => 'bg-blue-400',
        'failed' => 'bg-rose-400',
        'rolled_back' => 'bg-amber-400',
        default => 'bg-slate-400',
    };
@endphp

<div class="space-y-6" @if (!$completed) wire:poll.5s="refreshDeploymentState" @endif>
    <div class="flex items-center justify-between">
        <a href="{{ route('projects.show', $project) }}" wire:navigate class="inline-flex items-center gap-2 text-sm text-[#8ea2c5] hover:text-white">
            <x-icon name="lucide-arrow-left" class="h-4 w-4" />
            Retour au projet
        </a>
        @if(!$completed)
            <x-ui.button type="button" wire:click="cancelDeployment" variant="danger" size="sm">
                Annuler
            </x-ui.button>
        @endif
    </div>

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#182645] text-cyan-200">
                <x-icon name="lucide-terminal" class="h-5 w-5" />
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-white">{{ $project->name }} — {{ $deployment->release_name }}</h1>
                <p class="mt-1 text-xs text-[#8ea2c5] inline-flex items-center gap-2">
                    <x-icon name="lucide-git-branch" class="h-3 w-3" />
                    {{ $deployment->git_branch }}
                    <span class="text-[#5d6a86]">•</span>
                    <x-icon name="lucide-clock" class="h-3 w-3" />
                    {{ $deployment->duration_human }}
                </p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.badge variant="{{ $statusVariant }}">
                <span class="h-1.5 w-1.5 rounded-full {{ $statusDot }}"></span>
                {{ $deployment->status_label }}
            </x-ui.badge>
            @if ($project->url)
                <x-ui.button href="{{ $project->url }}" target="_blank" variant="default" size="sm">
                    <x-icon name="lucide-globe" class="h-4 w-4" />
                    Visiter
                </x-ui.button>
            @endif
            @if (!empty($rollbackReleases))
                <div class="flex items-center gap-2">
                    <select wire:model="rollbackTarget" class="h-8 rounded-lg border border-[#2f3f61] bg-[#0b1426] px-2 text-xs text-white">
                        @foreach ($rollbackReleases as $release)
                            <option value="{{ $release['name'] }}">{{ $release['label'] }}</option>
                        @endforeach
                    </select>
                    <x-ui.button type="button" wire:click="rollbackSymlink" wire:confirm="Confirmer le retour arrière vers {{ $rollbackTarget }} ?" variant="danger" size="sm">
                        <x-icon name="lucide-rotate-ccw" class="h-4 w-4" />
                        Retour arrière
                    </x-ui.button>
                </div>
            @endif
        </div>
    </div>

    <section class="rounded-2xl border border-[#1f2a44] bg-[#0b1020]/80 overflow-hidden">
        <div class="flex items-center justify-between border-b border-[#1f2a44] px-4 py-3">
            <div class="flex items-center gap-3">
                <span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                <span class="ml-3 text-xs text-[#8ea2c5]">{{ $project->name }} — bash</span>
            </div>
        </div>
        <div x-data x-ref="terminal" x-init="$nextTick(()=>{$refs.terminal.scrollTop=$refs.terminal.scrollHeight})" x-effect="$nextTick(()=>{$refs.terminal.scrollTop=$refs.terminal.scrollHeight})" class="min-h-[320px] max-h-[520px] overflow-auto p-4 font-mono text-xs leading-6 text-[#a5b4fc]">
            @forelse ($logs as $line)<div>{{ $line }}</div>@empty<div class="text-[#8ea2c5]">En attente des logs de déploiement...</div>@endforelse
        </div>
    </section>

</div>
