@php
    $latest = $deployments->first();
    $repoUrl = 'https://github.com/' . $project->github_repo;
@endphp
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs uppercase tracking-[0.25em] text-[#8ea2c5]">Project Capsule</p>
            <h1 class="mt-2 text-3xl font-bold">{{ $project->name }}</h1>
            <p class="text-sm text-[#8ea2c5]">{{ $project->github_repo }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ $repoUrl }}" target="_blank" class="ship-btn">Repository</a>
            <a href="{{ route('projects.deploy', $project) }}" wire:navigate class="ship-btn ship-btn-primary">Deploy</a>
            <a href="{{ route('projects.settings', $project) }}" wire:navigate class="ship-btn">Settings</a>
            @if ($project->url)<a href="{{ $project->url }}" target="_blank" class="ship-btn">Visit</a>@endif
        </div>
    </div>

    <section class="ship-panel overflow-hidden">
        <div class="grid gap-5 p-5 lg:grid-cols-[1.2fr_1fr]">
            <div class="rounded-2xl border border-[#2f3f61] bg-gradient-to-br from-[#12223f] to-[#0a1120] p-5">
                <p class="text-xs uppercase tracking-[0.2em] text-[#8ea2c5]">Production Orbit</p>
                <p class="mt-3 text-lg font-semibold">{{ $project->domain ?: 'No domain linked' }}</p>
                <p class="mt-4 text-sm text-[#8ea2c5]">Release: <span class="font-mono text-[#dce7ff]">{{ $project->current_release ?: '—' }}</span></p>
                <p class="text-sm text-[#8ea2c5]">Branch: <span class="text-[#dce7ff]">{{ $project->github_branch }}</span></p>
                <p class="text-sm text-[#8ea2c5]">Commit: <span class="font-mono text-[#dce7ff]">{{ $latest?->git_commit ? substr($latest->git_commit, 0, 8) : '—' }}</span></p>
            </div>
            <div class="space-y-3">
                <div class="ship-panel p-4"><p class="text-xs text-[#8ea2c5]">Status</p><p class="mt-1 text-lg font-semibold">{{ $project->status_label }}</p></div>
                <div class="ship-panel p-4"><p class="text-xs text-[#8ea2c5]">PHP</p><p class="mt-1 text-lg font-semibold">{{ $project->php_version }}</p></div>
                <div class="ship-panel p-4"><p class="text-xs text-[#8ea2c5]">Last Deploy</p><p class="mt-1 text-sm">{{ $latest?->created_at?->diffForHumans() ?? 'Never' }}</p></div>
            </div>
        </div>
    </section>

    <section class="ship-panel overflow-hidden">
        <div class="flex items-center justify-between border-b border-[#24324d] px-5 py-4">
            <h2 class="text-lg font-semibold">Deployment Stream</h2>
            <span class="text-xs text-[#8ea2c5]">Latest 10</span>
        </div>
        <div class="divide-y divide-[#24324d]">
            @forelse ($deployments as $deployment)
                @php $dot = match($deployment->status){'success'=>'bg-emerald-400','running','pending'=>'bg-cyan-400','failed'=>'bg-rose-400',default=>'bg-zinc-500'}; @endphp
                <a href="{{ route('deployments.show', $deployment) }}" wire:navigate class="grid gap-3 px-5 py-4 hover:bg-[#16233d] md:grid-cols-[1fr_1fr_1fr_auto]">
                    <div><p class="font-mono text-sm font-semibold">{{ $deployment->release_name }}</p><p class="text-xs text-[#8ea2c5]">Production</p></div>
                    <div><p class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full {{ $dot }}"></span>{{ $deployment->status_label }}</p><p class="text-xs text-[#8ea2c5]">{{ $deployment->duration_human }}</p></div>
                    <div><p class="text-sm">{{ $deployment->git_branch }}</p><p class="font-mono text-xs text-[#8ea2c5]">{{ $deployment->git_commit ? substr($deployment->git_commit,0,8) : 'no-commit' }}</p></div>
                    <div class="text-sm text-[#8ea2c5]">{{ $deployment->created_at?->diffForHumans() }}</div>
                </a>
            @empty
                <p class="px-5 py-8 text-sm text-[#8ea2c5]">Aucun déploiement disponible.</p>
            @endforelse
        </div>
    </section>
</div>
