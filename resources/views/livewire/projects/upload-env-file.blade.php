<div class="space-y-4">
    <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">📄 Fichier .env</h3>
        <p class="text-sm text-gray-600 mb-4">
            Vous pouvez importer un fichier <code class="bg-gray-100 px-2 py-1 rounded">.env</code>
            au lieu de remplir les variables individuellement.
        </p>

        @if ($hasExistingFile)
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8 16.5a1 1 0 01-1-1v-5h-.586a1 1 0 01-.707-1.707l2.414-2.414a1 1 0 11.414.414L8.414 8H10a2 2 0 012 2v5.5a1.5 1.5 0 01-3 0v-.5a1 1 0 10-2 0v.5a3.5 3.5 0 007 0V10a4 4 0 00-4-4h-1.586L5.707 3.293a1 1 0 00-1.414 1.414l2.414 2.414A1 1 0 008 7.414V12.5a1 1 0 001 1z" />
                    </svg>
                    <span class="text-sm font-medium text-blue-900">
                        Fichier .env actuellement utilisé
                    </span>
                </div>
                <div class="flex items-center space-x-2">
                    <button
                        wire:click="downloadEnvFile"
                        class="inline-flex items-center px-3 py-1 text-xs font-medium text-blue-600 hover:text-blue-700"
                        title="Télécharger le fichier">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                    </button>
                    <button
                        wire:click="deleteEnvFile"
                        class="inline-flex items-center px-3 py-1 text-xs font-medium text-red-600 hover:text-red-700"
                        wire:confirm="Êtes-vous sûr de vouloir supprimer ce fichier ?">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3m15 0h-21" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        @endif

        <div
            class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-gray-400 transition"
            @dragover.prevent="$el.classList.add('border-blue-400', 'bg-blue-50')"
            @dragleave.prevent="$el.classList.remove('border-blue-400', 'bg-blue-50')"
            @drop.prevent="$el.classList.remove('border-blue-400', 'bg-blue-50')">
            <input
                wire:model="envFile"
                type="file"
                accept=".env,.txt"
                id="envFileInput"
                class="hidden">

            <label for="envFileInput" class="cursor-pointer">
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
</div>