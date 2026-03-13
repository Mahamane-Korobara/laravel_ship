<div class="space-y-4">
    <x-ui.card tone="light" class="p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-2 flex items-center gap-2">
            <x-icon name="lucide-file-text" class="h-5 w-5 text-slate-600" />
            Fichier .env
        </h3>
        <p class="text-sm text-gray-600 mb-4">
            Vous pouvez importer un fichier <code class="bg-gray-100 px-2 py-1 rounded">.env</code>
            au lieu de remplir les variables individuellement.
        </p>

        @if ($hasExistingFile)
        <x-ui.card tone="light" class="bg-blue-50 border border-blue-200 p-4 mb-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2">
                    <x-icon name="lucide-info" class="h-5 w-5 text-blue-600" />
                    <span class="text-sm font-medium text-blue-900">
                        Fichier .env actuellement utilisé
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <x-ui.button
                        wire:click="downloadEnvFile"
                        variant="ghost"
                        size="sm"
                        class="text-blue-600 hover:text-blue-700"
                        title="Télécharger le fichier">
                        <x-icon name="lucide-download" class="h-4 w-4" />
                        Télécharger
                    </x-ui.button>
                    <x-ui.button
                        wire:click="deleteEnvFile"
                        variant="ghost"
                        size="sm"
                        class="text-red-600 hover:text-red-700"
                        wire:confirm="Êtes-vous sûr de vouloir supprimer ce fichier ?">
                        <x-icon name="lucide-trash-2" class="h-4 w-4" />
                        Supprimer
                    </x-ui.button>
                </div>
            </div>
        </x-ui.card>
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
                <x-icon name="lucide-upload" class="mx-auto h-12 w-12 text-gray-400 mb-2" />
                <p class="text-sm font-medium text-gray-700">
                    Cliquez pour importer ou glissez votre fichier <span class="font-mono text-blue-600">.env</span>
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    Taille maximale : 1 Mo
                </p>
            </label>
        </div>

        @if ($envFile && $envFile instanceof Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
        <x-ui.card tone="light" class="mt-4 bg-gray-50 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-900">
                        {{ $envFile->getClientOriginalName() }}
                    </p>
                    <p class="text-xs text-gray-500">
                        {{ number_format($envFile->getSize() / 1024, 2) }} KB
                    </p>
                </div>
                <x-ui.button
                    wire:click="uploadEnvFile"
                    variant="primary"
                    size="md"
                    class="bg-blue-600 hover:bg-blue-700">
                    <x-icon name="lucide-upload" class="h-4 w-4" />
                    Charger le fichier
                </x-ui.button>
            </div>
        </x-ui.card>
        @endif

        @error('envFile')
        <x-ui.card tone="light" class="mt-4 bg-red-50 border border-red-200 p-4">
            <p class="text-sm font-medium text-red-800 flex items-center gap-2">
                <x-icon name="lucide-alert-triangle" class="h-4 w-4 text-red-600" />
                {{ $message }}
            </p>
        </x-ui.card>
        @enderror
    </x-ui.card>
</div>
