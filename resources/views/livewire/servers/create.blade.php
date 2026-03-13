<div class="mx-auto max-w-3xl space-y-5">
    <a href="{{ route('servers.index') }}" wire:navigate class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white transition">
        <x-icon name="lucide-arrow-left" class="h-4 w-4" />
        Retour aux serveurs
    </a>
    <div>
        <h1 class="text-2xl font-bold text-white">Ajouter un serveur</h1>
        <p class="text-sm text-slate-400">Configurez un nouveau VPS pour vos déploiements</p>
    </div>
    <form wire:submit="save" class="ship-panel space-y-4 p-5">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="text-xs text-[#8ea2c5]">Nom</label>
                <input wire:model.defer="name" placeholder="VPS Prod Hostinger" class="mt-1 w-full rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"/>
                @error('name')<p class="text-xs text-rose-300 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-xs text-[#8ea2c5]">IP</label>
                <input wire:model.defer="ip_address" placeholder="185.23.45.67" class="mt-1 w-full rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"/>
                @error('ip_address')<p class="text-xs text-rose-300 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-xs text-[#8ea2c5]">Utilisateur SSH</label>
                <input wire:model.defer="ssh_user" placeholder="deployer" class="mt-1 w-full rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"/>
            </div>
            <div>
                <label class="text-xs text-[#8ea2c5]">Port SSH</label>
                <input wire:model.defer="ssh_port" type="number" placeholder="22" class="mt-1 w-full rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"/>
            </div>
            <div class="md:col-span-2">
                <label class="text-xs text-[#8ea2c5]">Clé privée SSH</label>
                <textarea wire:model.defer="ssh_private_key" rows="6" placeholder="-----BEGIN OPENSSH PRIVATE KEY-----" class="mt-1 w-full rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"></textarea>
                @error('ssh_private_key')<p class="text-xs text-rose-300 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-xs text-[#8ea2c5]">Version PHP</label>
                <select wire:model.defer="php_version" class="mt-1 w-full rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2">
                    <option value="7.4">7.4</option>
                    <option value="8.0">8.0</option>
                    <option value="8.1">8.1</option>
                    <option value="8.2">8.2</option>
                    <option value="8.3">8.3</option>
                    <option value="8.4">8.4</option>
                </select>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-ui.button type="button" wire:click="testConnection" variant="primary">
                Tester SSH
            </x-ui.button>
            <x-ui.button variant="danger">
                Ajouter le serveur
            </x-ui.button>
        </div>
        @if ($testResult)<pre class="rounded-xl border border-[#2f3f61] bg-black p-3 text-xs text-emerald-300">{{ $testResult }}</pre>@endif
    </form>
</div>
