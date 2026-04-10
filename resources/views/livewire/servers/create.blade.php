<div class="mx-auto max-w-3xl space-y-5">
    <a href="{{ route('servers.index') }}" wire:navigate class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white transition">
        <x-icon name="lucide-arrow-left" class="h-4 w-4" />
        Retour aux serveurs
    </a>
    <div>
        <h1 class="text-2xl font-bold text-white">Ajouter un serveur</h1>
        <p class="text-sm text-slate-400">Configurez un serveur Docker pour vos déploiements</p>
    </div>
    <form wire:submit.prevent="save" class="ship-panel space-y-4 p-5">
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
                <x-ui.textarea wire:model.defer="ssh_private_key" rows="6" placeholder="-----BEGIN OPENSSH PRIVATE KEY-----" class="mt-1" />
                @error('ssh_private_key')<p class="text-xs text-rose-300 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-ui.button type="button" wire:click="testConnection" variant="primary" wire:loading.attr="disabled" wire:target="testConnection">
                <span wire:loading.remove wire:target="testConnection">Tester Docker</span>
                <span wire:loading wire:target="testConnection" class="inline-flex items-center gap-2">
                    <x-ui.spinner size="sm" />
                    Test en cours...
                </span>
            </x-ui.button>
            <x-ui.button type="submit" variant="danger" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Ajouter le serveur</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                    <x-ui.spinner size="sm" />
                    Ajout en cours...
                </span>
            </x-ui.button>
        </div>
        <div class="{{ $testResult ? '' : 'hidden' }}" wire:loading.class.remove="hidden" wire:target="testConnection">
            <x-ui.terminal title="Test SSH" minHeight="200px" maxHeight="360px" stream="testResult" variant="{{ $testSuccess ? 'success' : ($testResult ? 'error' : 'info') }}">
                {{ $testResult ?: '→ Test en cours…' }}
            </x-ui.terminal>
        </div>
    </form>
</div>
