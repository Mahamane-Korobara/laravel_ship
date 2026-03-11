<div class="space-y-3">
    @if (!$envFilePath)
    <div class="relative">
        <input
            wire:model="envFile"
            type="file"
            accept=".env,.txt"
            id="deployEnvFileInput"
            class="hidden">
        <label for="deployEnvFileInput" class="flex items-center justify-center gap-2 rounded-lg border border-dashed border-zinc-700 bg-zinc-950 px-4 py-2 cursor-pointer hover:border-zinc-600 text-xs text-zinc-300 transition">
            <x-icon name="lucide-arrow-left" class="w-4 h-4" />
            Ou importer un fichier .env
        </label>
    </div>

    @if ($envFile)
    <div class="flex items-center justify-between rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs">
        <span class="text-zinc-300 truncate">📄 {{ $envFile->getClientOriginalName() }}</span>
        <button
            type="button"
            wire:click="uploadEnvFile"
            class="rounded-lg bg-blue-600 hover:bg-blue-700 px-3 py-1 text-xs text-white font-medium transition">
            Charger
        </button>
    </div>
    @endif

    @error('envFile')
    <p class="text-xs text-rose-300">⚠️ {{ $message }}</p>
    @enderror
    @else
    <div class="flex items-center justify-between rounded-lg border border-green-700/60 bg-green-950/30 px-3 py-2">
        <span class="text-xs text-green-300">✓ Fichier .env prêt à être utilisé</span>
        <button
            type="button"
            wire:click="clearEnvFile"
            class="rounded-lg border border-zinc-700 px-2 py-1 text-xs text-zinc-400 hover:text-zinc-300 hover:bg-zinc-800 transition">
            Modifier
        </button>
    </div>
    @endif
</div>
