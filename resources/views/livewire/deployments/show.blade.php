<div class="space-y-6" @if (!$completed) wire:poll.2s="refreshDeploymentState" @endif>
    <div class="flex items-center justify-between">
        <div><h1 class="text-3xl font-bold">Run Chamber</h1><p class="text-sm text-[#8ea2c5]">{{ $project->name }} · release {{ $deployment->release_name }}</p></div>
        <div class="flex gap-2">@if(!$completed)<button wire:click="cancelDeployment" class="ship-btn border-rose-500/50 text-rose-300">Cancel</button>@endif<a href="{{ route('projects.show', $project) }}" wire:navigate class="ship-btn">Back</a></div>
    </div>

    <div class="grid gap-4 md:grid-cols-4">
        <div class="ship-panel p-4"><p class="text-xs text-[#8ea2c5]">Status</p><p class="mt-1 font-semibold">{{ strtoupper($status) }}</p></div>
        <div class="ship-panel p-4"><p class="text-xs text-[#8ea2c5]">Branch</p><p class="mt-1 font-semibold">{{ $deployment->git_branch }}</p></div>
        <div class="ship-panel p-4"><p class="text-xs text-[#8ea2c5]">Duration</p><p class="mt-1 font-semibold">{{ $deployment->duration_human }}</p></div>
        <div class="ship-panel p-4"><p class="text-xs text-[#8ea2c5]">Trigger</p><p class="mt-1 font-semibold">{{ $deployment->triggered_by }}</p></div>
    </div>

    <section class="ship-panel overflow-hidden bg-black/80">
        <div class="flex items-center justify-between border-b border-[#24324d] px-4 py-3"><h2 class="font-semibold">Neon Console</h2><span class="text-xs text-[#8ea2c5]">stream</span></div>
        <div x-data x-ref="terminal" x-init="$nextTick(()=>{$refs.terminal.scrollTop=$refs.terminal.scrollHeight})" x-effect="$nextTick(()=>{$refs.terminal.scrollTop=$refs.terminal.scrollHeight})" class="max-h-[460px] overflow-auto p-4 font-mono text-xs leading-6 text-[#7fffd4]">
            @forelse ($logs as $line)<div>{{ $line }}</div>@empty<div class="text-[#8ea2c5]">Waiting deployment logs...</div>@endforelse
        </div>
    </section>

    @php $releases = $project->deployments()->where('status','success')->latest()->take(6)->pluck('release_name'); @endphp
    @if ($releases->isNotEmpty())
        <section class="ship-panel p-4"><h2 class="font-semibold">Rollback Matrix</h2><div class="mt-3 flex flex-wrap gap-2">@foreach($releases as $release)<button wire:click="rollback('{{ $release }}')" class="ship-btn">{{ $release }}</button>@endforeach</div></section>
    @endif
</div>
