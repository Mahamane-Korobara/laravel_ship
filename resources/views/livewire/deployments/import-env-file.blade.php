<div class="space-y-4">
    @if (!$showUpload)
    <button
        wire:click="$toggle('showUpload')"
        class="inline-flex items-center px-4 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 text-sm font-medium rounded-lg border border-blue-200 transition">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Importer un fichier .env
    </button>
    @else
    <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-lg font-semibold text-gray-900">📄 Importer un fichier .env</h4>
            <button
                wire:click="$toggle('showUpload')"
                class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div
            class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-gray-400 transition"
            @dragover.prevent="$el.classList.add('border-blue-400', 'bg-blue-50')"
            @dragleave.prevent="$el.classList.remove('border-blue-400', 'bg-blue-50')"
            @drop.prevent="$el.classList.remove('border-blue-400', 'bg-blue-50')">
            <input
                wire:model="envFile"
                type="file"
                accept=".env,.txt"
                id="deploymentEnvFileInput"
                class="hidden">

            <label for="deploymentEnvFileInput" class="cursor-pointer">
                <svg class="mx-auto h-12 w-12 text-gray-400 mb-2" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                    <path d="M28 8H12a4 4 0 00-4 4v20a4 4 0 004 4h24a4 4 0 004-4V20m-8-12l8 8m0 0V8m0 8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <p class="text-sm font-medium text-gray-700">
                    Cliquez pour importer ou glissez votre fichier <span class="font-mono text-blue-600">.env</span>
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    Taille maximum: 1 MB
                </p>
            </label>
        </div>

        @if ($envFile && $envFile instanceof Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
        <div class="mt-4 bg-gray-50 rounded-lg p-4">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-900">
                        📦 {{ $envFile->getClientOriginalName() }}
                    </p>
                    <p class="text-xs text-gray-500">
                        {{ number_format($envFile->getSize() / 1024, 2) }} KB
                    </p>
                </div>
                <button
                    wire:click="uploadEnvFile"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3v-6" />
                    </svg>
                    Charger le fichier
                </button>
            </div>
        </div>
        @endif

        @error('envFile')
        <div class="mt-4 bg-red-50 border border-red-200 rounded-lg p-4">
            <p class="text-sm font-medium text-red-800">⚠️ {{ $message }}</p>
        </div>
        @enderror
    </div>
    @endif

    @if ($deployment->env_file_path)
    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
        <div class="flex items-center space-x-2">
            <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            <span class="text-sm font-medium text-green-900">
                ✓ Fichier .env importé et prêt pour le déploiement
            </span>
        </div>
    </div>
    @endif
</div>