<div class="space-y-5">
    <div><h1 class="text-2xl font-bold">Repo Radar</h1><p class="text-sm text-[#8ea2c5]">Importez vos repositories en un clic.</p></div>
    <div class="ship-panel p-4">
        <div class="flex gap-2"><input wire:model.live.debounce.300ms="search" placeholder="Search repos..." class="w-full rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"/><button wire:click="loadRepos" class="ship-btn">Refresh</button></div>
    </div>
    @if ($error)<div class="ship-panel border-rose-500/40 p-4 text-sm text-rose-300">{{ $error }}</div>@endif
    @if ($loading)
        <div class="ship-panel p-8 text-center text-[#8ea2c5]">Loading repositories...</div>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($filteredRepos as $repo)
                <div class="ship-panel p-4">
                    <div class="flex items-start justify-between gap-2"><h2 class="text-sm font-semibold">{{ $repo['full_name'] }}</h2>@if(!empty($repo['private']))<span class="ship-badge">PRIVATE</span>@endif</div>
                    <p class="mt-2 line-clamp-2 text-xs text-[#8ea2c5]">{{ $repo['description'] ?: 'No description' }}</p>
                    <div class="mt-4 flex items-center justify-between">
                        @if (in_array($repo['full_name'], $importedRepos, true))
                            <span class="ship-badge">Imported</span>
                        @else
                            <button wire:click="importRepo('{{ $repo['full_name'] }}')" class="ship-btn ship-btn-primary">Import</button>
                        @endif
                        <span class="text-[11px] text-[#8ea2c5]">{{ $repo['default_branch'] ?? 'main' }}</span>
                    </div>
                </div>
            @empty
                <div class="ship-panel col-span-full p-8 text-center text-[#8ea2c5]">No repository found.</div>
            @endforelse
        </div>
    @endif
</div>
