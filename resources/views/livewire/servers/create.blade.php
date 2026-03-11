<div class="mx-auto max-w-3xl space-y-5">
    <div><h1 class="text-2xl font-bold">Provision Server</h1><p class="text-sm text-[#8ea2c5]">Ajouter un noeud au cluster.</p></div>
    <form wire:submit="save" class="ship-panel space-y-4 p-5">
        <div class="grid gap-4 md:grid-cols-2">
            <div><label class="text-xs text-[#8ea2c5]">Name</label><input wire:model.defer="name" class="mt-1 w-full rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"/>@error('name')<p class="text-xs text-rose-300 mt-1">{{ $message }}</p>@enderror</div>
            <div><label class="text-xs text-[#8ea2c5]">IP</label><input wire:model.defer="ip_address" class="mt-1 w-full rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"/>@error('ip_address')<p class="text-xs text-rose-300 mt-1">{{ $message }}</p>@enderror</div>
            <div><label class="text-xs text-[#8ea2c5]">SSH User</label><input wire:model.defer="ssh_user" class="mt-1 w-full rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"/></div>
            <div><label class="text-xs text-[#8ea2c5]">SSH Port</label><input wire:model.defer="ssh_port" type="number" class="mt-1 w-full rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"/></div>
            <div class="md:col-span-2"><label class="text-xs text-[#8ea2c5]">SSH Private Key</label><textarea wire:model.defer="ssh_private_key" rows="6" class="mt-1 w-full rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"></textarea>@error('ssh_private_key')<p class="text-xs text-rose-300 mt-1">{{ $message }}</p>@enderror</div>
            <div><label class="text-xs text-[#8ea2c5]">PHP Version</label><select wire:model.defer="php_version" class="mt-1 w-full rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"><option value="7.4">7.4</option><option value="8.0">8.0</option><option value="8.1">8.1</option><option value="8.2">8.2</option><option value="8.3">8.3</option><option value="8.4">8.4</option></select></div>
        </div>
        <div class="flex gap-2"><button type="button" wire:click="testConnection" class="ship-btn">Test SSH</button><button class="ship-btn ship-btn-primary">Save Server</button></div>
        @if ($testResult)<pre class="rounded-xl border border-[#2f3f61] bg-black p-3 text-xs text-emerald-300">{{ $testResult }}</pre>@endif
    </form>
</div>
