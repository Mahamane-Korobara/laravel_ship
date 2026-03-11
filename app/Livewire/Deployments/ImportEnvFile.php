<?php

namespace App\Livewire\Deployments;

use App\Models\Deployment;
use Livewire\Component;
use Livewire\WithFileUploads;

class ImportEnvFile extends Component
{
    use WithFileUploads;

    public Deployment $deployment;
    public $envFile;
    public bool $showUpload = false;

    public function mount(Deployment $deployment): void
    {
        $this->deployment = $deployment;
    }

    public function uploadEnvFile(): void
    {
        $this->validate([
            'envFile' => 'required|file|mimes:env,txt|max:1024', // 1MB max
        ]);

        // Uploader le fichier temporaire
        $path = $this->envFile->storeAs(
            path: 'deployment-env-files',
            name: "deployment_{$this->deployment->id}_{now()->timestamp}.env",
            options: 'local'
        );

        // Mettre à jour le déploiement
        $this->deployment->update(['env_file_path' => $path]);

        // Reset l'upload
        $this->envFile = null;
        $this->showUpload = false;

        $this->dispatch('notify', message: "Fichier .env uploadé avec succès ✓", type: 'success');
        $this->dispatch('env-file-uploaded');
    }

    public function render()
    {
        return view('livewire.deployments.import-env-file');
    }
}
