<div class="space-y-4">

    {{-- Upload .env file --}}
    <div class="rounded-xl border border-slate-800 bg-[#131525] p-5">
        <h2 class="text-sm font-semibold text-white mb-4">Fichier .env</h2>
        <livewire:projects.upload-env-file :project="$project" />
    </div>

    {{-- Add variable form --}}
    <div class="rounded-xl border border-slate-800 bg-[#131525] p-5">
        <h2 class="text-sm font-semibold text-white mb-4">Ajouter une variable</h2>
        <div class="flex items-center gap-3">
            <div class="flex-1">
                <label class="block text-xs text-slate-400 mb-1.5">Clé</label>
                <input
                    wire:model="newKey"
                    type="text"
                    placeholder="DB_PASSWORD"
                    class="w-full rounded-lg border border-slate-700 bg-slate-900/80 px-3 py-2 text-sm text-white placeholder-slate-600 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500/50" />
            </div>
            <div class="flex-1">
                <label class="block text-xs text-slate-400 mb-1.5">Valeur</label>
                <input
                    wire:model="newValue"
                    type="password"
                    placeholder="••••••••"
                    class="w-full rounded-lg border border-slate-700 bg-slate-900/80 px-3 py-2 text-sm text-white placeholder-slate-600 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500/50" />
            </div>
            <div class="pt-5">
                <button
                    wire:click="addVariable"
                    wire:loading.attr="disabled"
                    wire:target="addVariable"
                    class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 transition shadow-lg shadow-indigo-900/40 disabled:opacity-60 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="addVariable"><x-icon name="lucide-plus" class="h-4 w-4" /></span>
                    <span wire:loading wire:target="addVariable"><x-ui.spinner size="sm" /></span>
                </button>
            </div>
        </div>
    </div>

    {{-- Variable list --}}
    <div class="space-y-2">
        @forelse ($envVariables as $variable)
        <div wire:key="env-{{ $variable->id }}" class="flex items-center justify-between gap-4 rounded-xl border border-slate-800 bg-[#131525] px-5 py-3.5">
            <div class="flex items-center gap-3">
                <span class="text-sm font-mono font-semibold text-indigo-300">{{ $variable->key }}</span>
                <span class="text-sm text-slate-500 tracking-widest">
                    {{ in_array($variable->id, $revealed, true) ? $variable->value : $variable->masked_value }}
                </span>
            </div>
            <div class="flex items-center gap-2">
                <button
                    wire:click="toggleReveal({{ $variable->id }})"
                    class="text-slate-500 hover:text-slate-300 transition p-1 rounded">
                    <x-icon name="lucide-eye" class="h-4 w-4" />
                </button>
                <button
                    wire:click="deleteVariable({{ $variable->id }})"
                    wire:confirm="Êtes-vous sûr de vouloir supprimer cette variable ?"
                    wire:loading.attr="disabled"
                    wire:target="deleteVariable"
                    class="text-slate-500 hover:text-rose-400 transition p-1 rounded disabled:opacity-60 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="deleteVariable"><x-icon name="lucide-trash-2" class="h-4 w-4" /></span>
                    <span wire:loading wire:target="deleteVariable"><x-ui.spinner size="sm" /></span>
                </button>
            </div>
        </div>
        @empty
            @if ($project->env_file_path)
                <div class="rounded-xl border border-slate-800 bg-[#131525] p-6 text-center text-sm text-slate-400">
                    Variables non affichées car un fichier .env est utilisé.
                </div>
            @else
                <div class="rounded-xl border border-slate-800 bg-[#131525] p-8 text-center text-sm text-slate-500">
                    Aucune variable d'environnement définie.
                </div>
            @endif
        @endforelse
    </div>

</div>
