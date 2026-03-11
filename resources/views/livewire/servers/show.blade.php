<div class="space-y-5">
    <div class="flex items-center justify-between">
        <div><h1 class="text-2xl font-bold">{{ $server->name }}</h1><p class="text-sm text-[#8ea2c5]">{{ $server->masked_ip }} · {{ $server->ssh_user }}:{{ $server->ssh_port }} · PHP {{ $server->php_version }}</p></div>
        <div class="flex gap-2"><button wire:click="testConnection" class="ship-btn">Test</button><button wire:click="delete" wire:confirm="Supprimer ce serveur ?" class="ship-btn border-rose-500/50 text-rose-300">Delete</button></div>
    </div>
    @if ($testResult)<pre class="rounded-xl border border-[#2f3f61] bg-black p-3 text-xs {{ $testSuccess ? 'text-emerald-300' : 'text-rose-300' }}">{{ $testResult }}</pre>@endif
    <section class="ship-panel overflow-hidden">
        <div class="border-b border-[#24324d] px-4 py-3 text-sm font-semibold">Hosted Projects</div>
        <div class="divide-y divide-[#24324d]">
            @forelse ($projects as $project)
                <a href="{{ route('projects.show', $project) }}" wire:navigate class="flex items-center justify-between px-4 py-3 hover:bg-[#16233d]"><div><p class="font-semibold">{{ $project->name }}</p><p class="text-xs text-[#8ea2c5]">{{ $project->github_repo }}</p></div><span class="ship-badge">{{ $project->status_label }}</span></a>
            @empty
                <p class="px-4 py-6 text-sm text-[#8ea2c5]">Aucun projet.</p>
            @endforelse
        </div>
    </section>
</div>
