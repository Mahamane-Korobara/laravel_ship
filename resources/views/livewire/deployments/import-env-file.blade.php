<div class="space-y-4">
    @if (!$showUpload)
    <button
        wire:click="$toggle('showUpload')"
        class="inline-flex items-center px-4 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 text-sm font-medium rounded-lg border border-blue-200 transition">
        <x-icon name="lucide-plus" class="w-4 h-4 mr-2" />
        Importer un fichier .env
    </button>
    @else
    <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                <x-icon name="lucide-file-text" class="w-5 h-5 text-gray-600" />
                Importer un fichier .env
            </h4>
            <button
                wire:click="$toggle('showUpload')"
                class="text-gray-400 hover:text-gray-600">
                <x-icon name="lucide-x" class="w-6 h-6" />
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
                <x-icon name="lucide-file-text" class="mx-auto h-12 w-12 text-gray-400 mb-2" />
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
                    <x-icon name="lucide-upload" class="w-4 h-4 mr-2" />
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
            <x-icon name="lucide-check-circle" class="w-5 h-5 text-green-600" />
            <span class="text-sm font-medium text-green-900">
                ✓ Fichier .env importé et prêt pour le déploiement
            </span>
        </div>
    </div>
    @endif
</div>
