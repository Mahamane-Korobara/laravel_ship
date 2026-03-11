<div class="space-y-6" @if (!$completed) wire:poll.2s="refreshDeploymentState" @endif>
    <div class="flex items-center justify-between">
        <a href="{{ route('projects.show', $project) }}" wire:navigate class="inline-flex items-center gap-2 text-sm text-[#8ea2c5] hover:text-white">
            <x-icon name="lucide-arrow-left" class="h-4 w-4" />
            Retour au projet
        </a>
        <div class="flex gap-2">
            @if(!$completed)
                <button wire:click="cancelDeployment" class="ship-btn border-rose-500/50 text-rose-300">Annuler</button>
            @endif
        </div>
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
        <div class="flex items-center gap-2">
            <x-ui.badge class="bg-blue-500/10 text-blue-300 border-blue-500/20">
                <span class="h-1.5 w-1.5 rounded-full bg-blue-300"></span>
                {{ $deployment->status_label }}
            </x-ui.badge>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.card class="bg-[#0f172a] border-[#1e2a44] p-4">
            <p class="text-xs text-[#8ea2c5]">Status</p>
            <p class="mt-1 font-semibold text-white">{{ strtoupper($status) }}</p>
        </x-ui.card>
        <x-ui.card class="bg-[#0f172a] border-[#1e2a44] p-4">
            <p class="text-xs text-[#8ea2c5]">Branch</p>
            <p class="mt-1 font-semibold text-white">{{ $deployment->git_branch }}</p>
        </x-ui.card>
        <x-ui.card class="bg-[#0f172a] border-[#1e2a44] p-4">
            <p class="text-xs text-[#8ea2c5]">Durée</p>
            <p class="mt-1 font-semibold text-white">{{ $deployment->duration_human }}</p>
        </x-ui.card>
        <x-ui.card class="bg-[#0f172a] border-[#1e2a44] p-4">
            <p class="text-xs text-[#8ea2c5]">Déclenché par</p>
            <p class="mt-1 font-semibold text-white">{{ $deployment->triggered_by }}</p>
        </x-ui.card>
    </div>

    <section class="rounded-2xl border border-[#1f2a44] bg-[#0b1020]/80 overflow-hidden">
        <div class="flex items-center justify-between border-b border-[#1f2a44] px-4 py-3">
            <div class="flex items-center gap-3">
                <span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                <span class="ml-3 text-xs text-[#8ea2c5]">{{ $project->name }} — bash</span>
            </div>
            <x-icon name="lucide-copy" class="h-4 w-4 text-[#8ea2c5]" />
        </div>
        <div x-data x-ref="terminal" x-init="$nextTick(()=>{$refs.terminal.scrollTop=$refs.terminal.scrollHeight})" x-effect="$nextTick(()=>{$refs.terminal.scrollTop=$refs.terminal.scrollHeight})" class="min-h-[320px] max-h-[520px] overflow-auto p-4 font-mono text-xs leading-6 text-[#a5b4fc]">
            @forelse ($logs as $line)<div>{{ $line }}</div>@empty<div class="text-[#8ea2c5]">Waiting deployment logs...</div>@endforelse
        </div>
    </section>

    @php $releases = $project->deployments()->where('status','success')->latest()->take(6)->pluck('release_name'); @endphp
    @if ($releases->isNotEmpty())
        <section class="ship-panel p-4">
            <h2 class="font-semibold">Rollback</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach($releases as $release)
                    <button wire:click="rollback('{{ $release }}')" class="ship-btn">{{ $release }}</button>
                @endforeach
            </div>
        </section>
    @endif
</div>
