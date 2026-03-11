<div class="space-y-5">
    <div class="flex items-center justify-between">
        <div><h1 class="text-2xl font-bold">Fleet Servers</h1><p class="text-sm text-[#8ea2c5]">Infrastructure active.</p></div>
        <a href="{{ route('servers.create') }}" wire:navigate class="ship-btn ship-btn-primary">+ Add Server</a>
    </div>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($servers as $server)
            <a href="{{ route('servers.show', $server) }}" wire:navigate class="ship-panel p-4 hover:bg-[#152340]">
                <div class="flex items-start justify-between"><h2 class="font-semibold">{{ $server->name }}</h2><span class="ship-badge">{{ $server->status }}</span></div>
                <p class="mt-2 text-xs text-[#8ea2c5]">{{ $server->masked_ip }} · {{ $server->ssh_user }}:{{ $server->ssh_port }}</p>
                <p class="text-xs text-[#8ea2c5]">PHP {{ $server->php_version }} · {{ $server->projects_count }} projects</p>
            </a>
        @empty
            <div class="ship-panel col-span-full p-10 text-center text-[#8ea2c5]">No servers yet.</div>
        @endforelse
    </div>
</div>
