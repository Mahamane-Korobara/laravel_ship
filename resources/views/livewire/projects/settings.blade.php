<div class="mx-auto max-w-3xl space-y-5">
    <div><h1 class="text-2xl font-bold">Project Matrix Settings</h1><p class="text-sm text-[#8ea2c5]">Affinez le comportement global de {{ $project->name }}.</p></div>
    <form wire:submit="save" class="ship-panel space-y-4 p-5">
        <div><label class="text-xs text-[#8ea2c5]">Name</label><input wire:model.defer="name" class="mt-1 w-full rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"/></div>
        <div class="grid gap-4 md:grid-cols-2"><div><label class="text-xs text-[#8ea2c5]">Branch</label><input wire:model.defer="github_branch" class="mt-1 w-full rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"/></div><div><label class="text-xs text-[#8ea2c5]">PHP</label><select wire:model.defer="php_version" class="mt-1 w-full rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"><option value="7.4">7.4</option><option value="8.0">8.0</option><option value="8.1">8.1</option><option value="8.2">8.2</option><option value="8.3">8.3</option><option value="8.4">8.4</option></select></div></div>
        <div><label class="text-xs text-[#8ea2c5]">Domain</label><input wire:model.defer="domain" class="mt-1 w-full rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"/></div>
        <div class="grid gap-2 sm:grid-cols-2 text-sm">
            <label class="ship-panel flex items-center gap-2 px-3 py-2"><input type="checkbox" wire:model="run_migrations" class="rounded border-[#2f3f61] bg-[#0b1426]">Migrations</label>
            <label class="ship-panel flex items-center gap-2 px-3 py-2"><input type="checkbox" wire:model="run_seeders" class="rounded border-[#2f3f61] bg-[#0b1426]">Seeders</label>
            <label class="ship-panel flex items-center gap-2 px-3 py-2"><input type="checkbox" wire:model="run_npm_build" class="rounded border-[#2f3f61] bg-[#0b1426]">Build assets</label>
            <label class="ship-panel flex items-center gap-2 px-3 py-2"><input type="checkbox" wire:model="has_queue_worker" class="rounded border-[#2f3f61] bg-[#0b1426]">Queue worker</label>
        </div>
        <div class="flex gap-2"><button class="ship-btn ship-btn-primary">Save</button><button type="button" wire:click="deleteProject" wire:confirm="Supprimer ce projet ?" class="ship-btn border-rose-500/50 text-rose-300">Delete Project</button></div>
    </form>
</div>
